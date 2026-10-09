<?php
/**
 * Encrypts QuickBooks and Xero secrets.
 *
 * New values use libsodium secretbox. The key comes from REMEMBER_ENCRYPTION_KEY
 * when that constant is set, otherwise from WordPress AUTH_KEY. It is not stored
 * in the database. Older AES values stay readable with the existing option key
 * until a checked rewrite succeeds. If that check fails, the old value and the
 * old key are left as they are.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Billing secret encryption.
 */
class Remember_Billing_Crypto {

	const PREFIX = 'rb1:';

	/**
	 * @var bool
	 */
	private static $migrating = false;

	/**
	 * Encrypt a secret for storage.
	 *
	 * @param string $plain         Secret.
	 * @param string $legacy_option Option that holds the old AES key for this processor.
	 * @return string
	 */
	public static function seal( $plain, $legacy_option ) {
		$plain = (string) $plain;
		if ( self::can_seal() ) {
			try {
				$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$boxed = sodium_crypto_secretbox( $plain, $nonce, self::key_bytes() );
				return self::PREFIX . base64_encode( $nonce . $boxed );
			} catch ( Exception $e ) {
				unset( $e );
			}
		}
		return self::legacy_seal( $plain, $legacy_option );
	}

	/**
	 * Decrypt a stored secret.
	 *
	 * @param string $payload       Stored value.
	 * @param string $legacy_option Option that holds the old AES key for this processor.
	 * @return string|false
	 */
	public static function open( $payload, $legacy_option ) {
		$payload = (string) $payload;
		if ( self::is_current( $payload ) ) {
			return self::open_current( $payload );
		}
		return self::legacy_open( $payload, $legacy_option );
	}

	/**
	 * Rewrite this processor's stored secrets when the old key is still required.
	 *
	 * The database row is restored if the new value does not decrypt to the same secret.
	 *
	 * @param string $processor_type quickbooks|xero.
	 * @param array  $settings       Decrypted settings from get_settings().
	 * @return void
	 */
	public static function migrate_processor( $processor_type, array $settings ) {
		if ( self::$migrating ) {
			return;
		}
		$option = self::legacy_option_name( $processor_type );
		if ( '' === $option ) {
			return;
		}

		$fields = array( 'client_secret', 'access_token', 'refresh_token' );
		$legacy = array();
		foreach ( $fields as $field ) {
			$stored = isset( $settings[ $field . '_encrypted' ] ) ? $settings[ $field . '_encrypted' ] : '';
			if ( ! is_string( $stored ) || '' === $stored ) {
				continue;
			}
			if ( self::is_current( $stored ) ) {
				continue;
			}
			if ( ! isset( $settings[ $field ] ) || ! is_string( $settings[ $field ] ) || '' === $settings[ $field ] ) {
				return;
			}
			$legacy[ $field ] = $settings[ $field ];
		}

		if ( empty( $legacy ) ) {
			delete_option( $option );
			return;
		}
		if ( ! self::can_seal() ) {
			return;
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'remember_payment_processors';
		$previous = $wpdb->get_var( $wpdb->prepare( "SELECT settings FROM {$table} WHERE processor_type = %s", $processor_type ) );
		if ( ! is_string( $previous ) ) {
			return;
		}

		self::$migrating = true;
		if ( 'quickbooks' === $processor_type ) {
			$saved = Remember_QuickBooks_OAuth::save_settings( $settings );
		} else {
			$saved = Remember_Xero_OAuth::save_settings( $settings );
		}
		self::$migrating = false;
		if ( ! $saved ) {
			return;
		}

		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT settings FROM {$table} WHERE processor_type = %s", $processor_type ) );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			self::restore_settings( $table, $processor_type, $previous );
			return;
		}

		foreach ( $legacy as $field => $plain ) {
			$stored = isset( $decoded[ $field . '_encrypted' ] ) ? $decoded[ $field . '_encrypted' ] : '';
			$opened = is_string( $stored ) ? self::open( $stored, $option ) : false;
			if ( ! is_string( $opened ) || $opened !== $plain ) {
				self::restore_settings( $table, $processor_type, $previous );
				require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
				Remember_Logger::error(
					'Billing secret re-encrypt rolled back',
					array( 'processor' => $processor_type )
				);
				return;
			}
		}

		delete_option( $option );
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::info(
			'Billing secrets re-encrypted without the database key',
			array( 'processor' => $processor_type )
		);
	}

	/**
	 * Whether any stored billing secret still uses the old database key.
	 *
	 * @return bool
	 */
	public static function legacy_secrets_remain() {
		global $wpdb;
		$table = $wpdb->prefix . 'remember_payment_processors';
		$rows  = $wpdb->get_results( "SELECT settings FROM {$table} WHERE processor_type IN ('quickbooks','xero')" );
		if ( ! is_array( $rows ) ) {
			return true;
		}
		foreach ( $rows as $row ) {
			$settings = json_decode( (string) $row->settings, true );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			foreach ( array( 'client_secret_encrypted', 'access_token_encrypted', 'refresh_token_encrypted' ) as $key ) {
				if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) && ! self::is_current( $settings[ $key ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Option name for the pre-secretbox key.
	 *
	 * @param string $processor_type quickbooks|xero.
	 * @return string
	 */
	public static function legacy_option_name( $processor_type ) {
		if ( 'quickbooks' === $processor_type ) {
			return 'remember_qb_encryption_key';
		}
		if ( 'xero' === $processor_type ) {
			return 'remember_xero_encryption_key';
		}
		return '';
	}

	/**
	 * @param string $payload Stored value.
	 * @return bool
	 */
	private static function is_current( $payload ) {
		return 0 === strpos( (string) $payload, self::PREFIX );
	}

	/**
	 * @return bool
	 */
	private static function can_seal() {
		return function_exists( 'sodium_crypto_secretbox' ) && is_string( self::key_bytes() );
	}

	/**
	 * 32-byte key from wp-config, not from the database.
	 *
	 * @return string|null
	 */
	private static function key_bytes() {
		if ( defined( 'REMEMBER_ENCRYPTION_KEY' ) && is_string( REMEMBER_ENCRYPTION_KEY ) && '' !== REMEMBER_ENCRYPTION_KEY ) {
			return hash( 'sha256', 'remember-billing|' . REMEMBER_ENCRYPTION_KEY, true );
		}
		if ( defined( 'AUTH_KEY' ) && is_string( AUTH_KEY ) && '' !== AUTH_KEY ) {
			return hash_hmac( 'sha256', 'remember-billing', AUTH_KEY, true );
		}
		return null;
	}

	/**
	 * @param string $payload rb1 payload.
	 * @return string|false
	 */
	private static function open_current( $payload ) {
		if ( ! self::can_seal() ) {
			return false;
		}
		$raw = base64_decode( substr( $payload, strlen( self::PREFIX ) ), true );
		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( ! is_string( $raw ) || strlen( $raw ) <= $nonce_length ) {
			return false;
		}
		try {
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, $nonce_length ),
				substr( $raw, 0, $nonce_length ),
				self::key_bytes()
			);
		} catch ( Exception $e ) {
			unset( $e );
			return false;
		}
		return is_string( $plain ) ? $plain : false;
	}

	/**
	 * Previous AES-256-CBC storage. Does not create a key.
	 *
	 * @param string $payload       Stored value.
	 * @param string $legacy_option Option name.
	 * @return string|false
	 */
	private static function legacy_open( $payload, $legacy_option ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			$decoded = base64_decode( $payload );
			return is_string( $decoded ) ? $decoded : false;
		}
		$key = get_option( $legacy_option );
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}
		$data = base64_decode( $payload );
		if ( ! is_string( $data ) ) {
			return false;
		}
		$iv_length = openssl_cipher_iv_length( 'AES-256-CBC' );
		if ( strlen( $data ) <= $iv_length ) {
			return false;
		}
		$plain = openssl_decrypt( substr( $data, $iv_length ), 'AES-256-CBC', $key, 0, substr( $data, 0, $iv_length ) );
		return is_string( $plain ) ? $plain : false;
	}

	/**
	 * Previous write path, used only when secretbox is unavailable.
	 *
	 * @param string $plain         Secret.
	 * @param string $legacy_option Option name.
	 * @return string
	 */
	private static function legacy_seal( $plain, $legacy_option ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain );
		}
		$key = get_option( $legacy_option );
		if ( ! is_string( $key ) || '' === $key ) {
			$key = wp_generate_password( 32, true, true );
			update_option( $legacy_option, $key );
		}
		$iv        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
		$encrypted = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * @param string $table          Processor table.
	 * @param string $processor_type quickbooks|xero.
	 * @param string $settings       Previous JSON.
	 * @return void
	 */
	private static function restore_settings( $table, $processor_type, $settings ) {
		global $wpdb;
		$wpdb->update(
			$table,
			array(
				'settings'   => $settings,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'processor_type' => $processor_type ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}
}

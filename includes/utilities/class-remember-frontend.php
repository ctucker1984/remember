<?php
/**
 * Front-end logout link and admin-bar visibility.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Nonced logout URLs for menus/shortcodes/FSE, and hide the admin bar for
 * WordPress Subscriber-only accounts (reMember roles do not count).
 */
class Remember_Frontend {

	const LOGOUT_HASH = '#remember-logout';
	const LOGOUT_PATH = '/remember-logout';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'show_admin_bar', array( __CLASS__, 'filter_show_admin_bar' ), 99 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter_nav_menu_objects' ), 10, 1 );
		add_filter( 'render_block_data', array( __CLASS__, 'filter_render_block_data' ), 10, 1 );
		add_filter( 'render_block_core/navigation-link', array( __CLASS__, 'filter_navigation_link_block' ), 10, 2 );
		add_filter( 'block_type_metadata', array( __CLASS__, 'filter_navigation_allowed_blocks' ), 10, 1 );
		add_action( 'init', array( __CLASS__, 'register_logout_block' ) );
		add_action( 'load-nav-menus.php', array( __CLASS__, 'add_nav_menu_meta_box' ) );
		add_shortcode( 'remember_logout', array( __CLASS__, 'shortcode_logout' ) );
	}

	/**
	 * Hide the toolbar when the only WordPress role is Subscriber.
	 *
	 * @param bool $show Incoming value.
	 * @return bool
	 */
	public static function filter_show_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		$user  = wp_get_current_user();
		$roles = array_values( array_filter( (array) $user->roles ) );
		if ( 1 === count( $roles ) && 'subscriber' === $roles[0] ) {
			return false;
		}
		return $show;
	}

	/**
	 * Logout URL with nonce. Filter remember_logout_redirect for the destination.
	 *
	 * @return string
	 */
	public static function logout_url() {
		$redirect = home_url( '/' );
		/**
		 * Where to send the browser after logout.
		 *
		 * @param string $redirect Absolute URL.
		 */
		$redirect = apply_filters( 'remember_logout_redirect', $redirect );
		return wp_logout_url( $redirect );
	}

	/**
	 * Whether this URL is the reMember logout placeholder.
	 *
	 * @param string $url Menu or block URL.
	 * @return bool
	 */
	public static function is_logout_placeholder( $url ) {
		$url = html_entity_decode( (string) $url, ENT_QUOTES );
		if ( '' === $url ) {
			return false;
		}
		if ( self::LOGOUT_HASH === $url || 'remember-logout' === $url || self::LOGOUT_PATH === $url ) {
			return true;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '/remember-logout' === untrailingslashit( $path ) ) {
			return true;
		}
		return false !== strpos( $url, 'remember-logout' );
	}

	/**
	 * Whether a parsed navigation-link block is a logout placeholder.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return bool
	 */
	public static function block_is_logout( $parsed_block ) {
		$attrs = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : array();
		$url   = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
		if ( self::is_logout_placeholder( $url ) ) {
			return true;
		}
		$class = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';
		return false !== strpos( $class, 'remember-logout' );
	}

	/**
	 * Swap placeholder menu items for a nonced logout URL; hide when logged out.
	 *
	 * @param array<int, object> $items Menu items.
	 * @return array<int, object>
	 */
	public static function filter_nav_menu_objects( $items ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		foreach ( $items as $i => $item ) {
			$url     = isset( $item->url ) ? $item->url : '';
			$classes = isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : array();
			if ( ! self::is_logout_placeholder( $url ) && ! in_array( 'remember-logout', $classes, true ) ) {
				continue;
			}
			if ( ! is_user_logged_in() ) {
				unset( $items[ $i ] );
				continue;
			}
			$item->url = self::logout_url();
		}
		return $items;
	}

	/**
	 * Rewrite FSE Custom Link placeholders before the navigation-link block renders.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function filter_render_block_data( $parsed_block ) {
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation-link' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}
		if ( ! self::block_is_logout( $parsed_block ) ) {
			return $parsed_block;
		}
		if ( ! is_user_logged_in() ) {
			$parsed_block['attrs']['label'] = '';
			return $parsed_block;
		}
		$parsed_block['attrs']['url']           = self::logout_url();
		$parsed_block['attrs']['kind']          = 'custom';
		$parsed_block['attrs']['opensInNewTab'] = false;
		return $parsed_block;
	}

	/**
	 * Fallback HTML rewrite if the placeholder is still in the rendered link.
	 *
	 * @param string               $content Block HTML.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string
	 */
	public static function filter_navigation_link_block( $content, $block ) {
		if ( ! self::block_is_logout( is_array( $block ) ? $block : array() ) ) {
			return $content;
		}
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$url = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
		if ( $url === self::logout_url() ) {
			return $content;
		}
		$logout  = self::logout_url();
		$updated = str_replace( esc_url( $url ), esc_url( $logout ), $content );
		if ( $updated === $content && '' !== $url ) {
			$updated = str_replace( $url, esc_url( $logout ), $content );
		}
		return $updated;
	}

	/**
	 * Allow the Log out block inside core Navigation (Site Editor).
	 *
	 * @param array<string, mixed> $metadata Block metadata.
	 * @return array<string, mixed>
	 */
	public static function filter_navigation_allowed_blocks( $metadata ) {
		if ( empty( $metadata['name'] ) || 'core/navigation' !== $metadata['name'] ) {
			return $metadata;
		}
		if ( empty( $metadata['allowedBlocks'] ) || ! is_array( $metadata['allowedBlocks'] ) ) {
			return $metadata;
		}
		if ( ! in_array( 'remember/logout', $metadata['allowedBlocks'], true ) ) {
			$metadata['allowedBlocks'][] = 'remember/logout';
		}
		return $metadata;
	}

	/**
	 * Register the FSE Log out block.
	 *
	 * @return void
	 */
	public static function register_logout_block() {
		$script_rel = 'includes/blocks/logout/index.js';
		$script_abs = REMEMBER_PLUGIN_DIR . $script_rel;
		$ver        = is_readable( $script_abs ) ? (string) filemtime( $script_abs ) : REMEMBER_VERSION;

		wp_register_script(
			'remember-logout-editor',
			REMEMBER_PLUGIN_URL . $script_rel,
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			$ver,
			true
		);
		wp_set_script_translations( 'remember-logout-editor', 'remember' );

		register_block_type(
			REMEMBER_PLUGIN_DIR . 'includes/blocks/logout',
			array(
				'render_callback'         => array( __CLASS__, 'render_logout_block' ),
				'editor_script_handles'   => array( 'remember-logout-editor' ),
			)
		);
	}

	/**
	 * Front-end markup for remember/logout (matches a navigation item).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_logout_block( $attributes ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$label = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
		if ( '' === $label ) {
			$label = __( 'Log out', 'remember' );
		}
		return '<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content remember-logout" href="' . esc_url( self::logout_url() ) . '"><span class="wp-block-navigation-item__label">' . esc_html( $label ) . '</span></a></li>';
	}

	/**
	 * Appearance → Menus: add a Log out item with the placeholder URL.
	 *
	 * @return void
	 */
	public static function add_nav_menu_meta_box() {
		add_meta_box(
			'remember-nav-menu',
			__( 'reMember', 'remember' ),
			array( __CLASS__, 'render_nav_menu_meta_box' ),
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Metabox markup (classic menus).
	 *
	 * @return void
	 */
	public static function render_nav_menu_meta_box() {
		global $nav_menu_selected_id;
		?>
		<div id="posttype-remember" class="posttypediv">
			<div id="tabs-panel-remember" class="tabs-panel tabs-panel-active">
				<ul id="remember-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1">
							<?php esc_html_e( 'Log out', 'remember' ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom">
						<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php echo esc_attr( __( 'Log out', 'remember' ) ); ?>">
						<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="<?php echo esc_attr( self::LOGOUT_PATH ); ?>">
						<input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="remember-logout">
					</li>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
				<span class="add-to-menu">
					<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'remember' ); ?>" name="add-post-type-menu-item" id="submit-posttype-remember">
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * [remember_logout] — nonced link, empty when logged out.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_logout( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'text' => __( 'Log out', 'remember' ),
			),
			$atts,
			'remember_logout'
		);
		return '<a class="remember-logout" href="' . esc_url( self::logout_url() ) . '">' . esc_html( $atts['text'] ) . '</a>';
	}
}

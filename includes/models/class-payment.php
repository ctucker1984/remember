<?php
/**
 * Payment model class
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-base-model.php';

/**
 * Payment model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Payment extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'payments';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'payment_id';

	/**
	 * Create a new payment.
	 *
	 * @param array $data Payment data.
	 * @return int|false Payment ID or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		
		// Calculate amount_due if not set
		if ( ! isset( $data['amount_due'] ) ) {
			$data['amount_due'] = $data['total_amount'] - ( isset( $data['amount_paid'] ) ? $data['amount_paid'] : 0 );
		}
		
		return $this->insert( $data );
	}

	/**
	 * Update a payment.
	 *
	 * @param int   $payment_id Payment ID.
	 * @param array $data       Payment data.
	 * @return int|false
	 */
	public function update( $payment_id, $data ) {
		$data['updated_at'] = current_time( 'mysql' );

		$before = $this->get( $payment_id );
		
		// Recalculate amount_due if amount_paid changed (caller may set amount_due and payment_status explicitly).
		if ( isset( $data['amount_paid'] ) ) {
			if ( $before ) {
				if ( ! isset( $data['amount_due'] ) ) {
					$data['amount_due'] = $before->total_amount - $data['amount_paid'];
				}

				if ( ! isset( $data['payment_status'] ) ) {
					if ( $data['amount_due'] <= 0 ) {
						$data['payment_status'] = 'paid';
					} elseif ( $data['amount_paid'] > 0 ) {
						$data['payment_status'] = 'partial';
					}
				}
			}
		}
		
		$result = parent::update( $payment_id, $data );
		if ( false === $result ) {
			return $result;
		}

		$after = $this->get( $payment_id );
		if ( $before && $after && (string) $before->payment_status !== (string) $after->payment_status ) {
			/**
			 * Fires when a payment row's payment_status changes.
			 *
			 * @param int    $payment_id Payment ID.
			 * @param string $old_status Previous status.
			 * @param string $new_status New status.
			 * @param object $payment    Updated payment row.
			 */
			do_action( 'remember_payment_status_changed', (int) $payment_id, (string) $before->payment_status, (string) $after->payment_status, $after );
		}

		return $result;
	}

	/**
	 * Get payment by event application.
	 *
	 * @param int $event_application_id Event application ID.
	 * @return object|null
	 */
	public function get_by_application( $event_application_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_application_id = %d",
				$event_application_id
			)
		);
	}

	/**
	 * Get payments by member.
	 *
	 * @param int $member_id Member ID.
	 * @return array
	 */
	public function get_by_member( $member_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE member_id = %d ORDER BY created_at DESC",
				$member_id
			)
		);
	}

	/**
	 * Get payments by status.
	 *
	 * @param string $status Payment status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE payment_status = %s ORDER BY created_at DESC",
				$status
			)
		);
	}

	/**
	 * Record a payment (add to amount_paid).
	 *
	 * @param int   $payment_id Payment ID.
	 * @param float $amount     Amount to add.
	 * @param array $extra_data Additional payment data (method, transaction_id, etc.).
	 * @return int|false
	 */
	public function record_payment( $payment_id, $amount, $extra_data = array() ) {
		$payment = $this->get( $payment_id );
		if ( ! $payment ) {
			return false;
		}

		$new_amount_paid = $payment->amount_paid + $amount;
		$data = array_merge(
			array(
				'amount_paid'   => $new_amount_paid,
				'payment_date'  => current_time( 'mysql' ),
			),
			$extra_data
		);

		return $this->update( $payment_id, $data );
	}
}

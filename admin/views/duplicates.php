<?php
/**
 * Duplicate profile review and merge.
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-profile-duplicates.php';

if ( ! function_exists( 'remember_dup_format_datetime' ) ) {
	/**
	 * Format a MySQL datetime for admin display.
	 *
	 * @param string $mysql_dt Datetime.
	 * @return string
	 */
	function remember_dup_format_datetime( $mysql_dt ) {
		if ( ! is_string( $mysql_dt ) || '' === $mysql_dt ) {
			return __( 'Unknown', 'remember' );
		}
		$ts = strtotime( $mysql_dt );
		if ( ! $ts ) {
			return __( 'Unknown', 'remember' );
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}
}

if ( ! function_exists( 'remember_dup_default_side' ) ) {
	/**
	 * Default radio: filled value over empty, else later profile save, else survivor.
	 *
	 * @param string $val_a         Value A.
	 * @param string $val_b         Value B.
	 * @param string $updated_a     Profile A updated_at.
	 * @param string $updated_b     Profile B updated_at.
	 * @param string $survivor_side a|b.
	 * @return string a|b.
	 */
	function remember_dup_default_side( $val_a, $val_b, $updated_a, $updated_b, $survivor_side ) {
		$a_empty = '' === trim( (string) $val_a );
		$b_empty = '' === trim( (string) $val_b );
		if ( $a_empty && ! $b_empty ) {
			return 'b';
		}
		if ( $b_empty && ! $a_empty ) {
			return 'a';
		}
		$ts_a = $updated_a ? strtotime( $updated_a ) : 0;
		$ts_b = $updated_b ? strtotime( $updated_b ) : 0;
		if ( $ts_a && $ts_b && $ts_a !== $ts_b ) {
			return $ts_b > $ts_a ? 'b' : 'a';
		}
		return in_array( $survivor_side, array( 'a', 'b' ), true ) ? $survivor_side : 'a';
	}
}

if ( ! function_exists( 'remember_dup_format_value' ) ) {
	/**
	 * Human-readable field value for the review table.
	 *
	 * @param string $field Field key.
	 * @param string $raw   Raw value.
	 * @return string
	 */
	function remember_dup_format_value( $field, $raw ) {
		$share = array(
			'share_email_with_events',
			'share_phone_with_events',
			'share_location_with_events',
			'share_im_with_events',
			'share_interests_with_events',
			'share_photo_with_events',
		);
		if ( in_array( $field, $share, true ) ) {
			return ( '1' === (string) $raw || 1 === $raw ) ? __( 'Yes', 'remember' ) : __( 'No', 'remember' );
		}
		if ( '' === trim( (string) $raw ) ) {
			return '—';
		}
		return (string) $raw;
	}
}

$list_url = Remember_Profile_Duplicates::review_url();
$hit_id   = isset( $_GET['hit'] ) ? absint( $_GET['hit'] ) : 0;
$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
if ( ! in_array( $status, array( 'pending', 'dismissed', 'merged', 'closed', 'all' ), true ) ) {
	$status = 'pending';
}

if ( isset( $_POST['remember_dup_action'] ) && check_admin_referer( 'remember_dup_action', 'remember_dup_nonce' ) ) {
	$action = sanitize_key( wp_unslash( $_POST['remember_dup_action'] ) );
	$post_hit = isset( $_POST['hit_id'] ) ? absint( $_POST['hit_id'] ) : 0;

	if ( 'scan' === $action ) {
		$created = Remember_Profile_Duplicates::scan();
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of new hits */
				_n( 'Scan complete. %d new possible duplicate found.', 'Scan complete. %d new possible duplicates found.', $created, 'remember' ),
				$created
			)
		);
		echo '</p></div>';
	} elseif ( 'dismiss' === $action && $post_hit > 0 ) {
		$result = Remember_Profile_Duplicates::dismiss( $post_hit );
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Marked as not duplicates. This pair will not be flagged again.', 'remember' ) . '</p></div>';
			$hit_id = 0;
		}
	} elseif ( 'merge' === $action && $post_hit > 0 ) {
		$survivor_id = isset( $_POST['survivor_id'] ) ? absint( $_POST['survivor_id'] ) : 0;
		$choices     = array();
		if ( isset( $_POST['choice'] ) && is_array( $_POST['choice'] ) ) {
			foreach ( wp_unslash( $_POST['choice'] ) as $field => $side ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$field = sanitize_key( $field );
				$side  = sanitize_key( $side );
				if ( in_array( $side, array( 'a', 'b' ), true ) ) {
					$choices[ $field ] = $side;
				}
			}
		}
		$result = Remember_Profile_Duplicates::merge( $post_hit, $survivor_id, $choices );
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profiles merged. The discarded profile can no longer log in.', 'remember' ) . '</p></div>';
			$hit_id = 0;
		}
	}
}

$hit = $hit_id > 0 ? Remember_Profile_Duplicates::get_hit( $hit_id ) : null;
?>
<div class="wrap remember-duplicates">
	<h1><?php esc_html_e( 'Duplicate Profiles', 'remember' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Possible duplicates are found from matching or similar legal names, location (city/state count as one), display names, or IM/social handles. Affirm a match and pick field values, or mark the pair as not duplicates so they will not re-flag. The later-entered password is always kept. Assign Merge Duplicate Profiles on Roles to grant this screen; System Administrator has it by default. reMember System Administrators are emailed new hits; members are emailed only after a merge.', 'remember' ); ?>
	</p>

	<?php if ( $hit ) : ?>
		<?php
		$snap_a = Remember_Profile_Duplicates::snapshot( (int) $hit->member_a_id );
		$snap_b = Remember_Profile_Duplicates::snapshot( (int) $hit->member_b_id );
		$name_a = $snap_a['user'] ? $snap_a['user']->display_name : sprintf( __( 'Member #%d', 'remember' ), (int) $hit->member_a_id );
		$name_b = $snap_b['user'] ? $snap_b['user']->display_name : sprintf( __( 'Member #%d', 'remember' ), (int) $hit->member_b_id );
		$pass_a = Remember_Profile_Duplicates::password_updated_at( (int) $hit->member_a_id );
		$pass_b = Remember_Profile_Duplicates::password_updated_at( (int) $hit->member_b_id );
		$pass_from = ( strtotime( $pass_b ) > strtotime( $pass_a ) ) ? 'b' : 'a';
		$reasons = json_decode( isset( $hit->match_reasons ) ? $hit->match_reasons : '[]', true );
		if ( ! is_array( $reasons ) ) {
			$reasons = array();
		}
		$fields = Remember_Profile_Duplicates::review_fields();
		$is_pending = 'pending' === $hit->status;
		$default_survivor = 'a';
		$updated_a = $snap_a['profile_updated_at'];
		$updated_b = $snap_b['profile_updated_at'];
		if ( $updated_a && $updated_b && strtotime( $updated_b ) > strtotime( $updated_a ) ) {
			$default_survivor = 'b';
		}
		?>
		<p>
			<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All duplicate reviews', 'remember' ); ?></a>
		</p>

		<div class="remember-dup-meta">
			<p>
				<strong><?php esc_html_e( 'Match reasons:', 'remember' ); ?></strong>
				<?php
				$labels = array();
				foreach ( $reasons as $reason ) {
					if ( ! empty( $reason['label'] ) ) {
						$labels[] = $reason['label'];
					}
				}
				echo esc_html( implode( ', ', array_unique( $labels ) ) );
				?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Status:', 'remember' ); ?></strong>
				<?php echo esc_html( $hit->status ); ?>
			</p>
		</div>

		<?php if ( $is_pending ) : ?>
			<form method="post" action="" class="remember-dup-merge-form">
				<?php wp_nonce_field( 'remember_dup_action', 'remember_dup_nonce' ); ?>
				<input type="hidden" name="hit_id" value="<?php echo esc_attr( (string) $hit->hit_id ); ?>">
				<input type="hidden" name="remember_dup_action" value="merge">

				<h2><?php esc_html_e( 'Which profile remains?', 'remember' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The other profile is locked out after the merge. Roles, health options, extra social handles, custom fields, applications, payments, vetting, and profile notes move onto the remaining profile when they do not conflict.', 'remember' ); ?></p>
				<fieldset class="remember-dup-survivor">
					<label>
						<input type="radio" name="survivor_id" value="<?php echo esc_attr( (string) $hit->member_a_id ); ?>" <?php checked( $default_survivor, 'a' ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: display name, 2: member ID */
								__( '%1$s (ID %2$d)', 'remember' ),
								$name_a,
								(int) $hit->member_a_id
							)
						);
						?>
					</label>
					<label>
						<input type="radio" name="survivor_id" value="<?php echo esc_attr( (string) $hit->member_b_id ); ?>" <?php checked( $default_survivor, 'b' ); ?>>
						<?php
						echo esc_html(
							sprintf(
								__( '%1$s (ID %2$d)', 'remember' ),
								$name_b,
								(int) $hit->member_b_id
							)
						);
						?>
					</label>
				</fieldset>

				<table class="widefat striped remember-dup-compare">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'remember' ); ?></th>
							<th>
								<?php echo esc_html( $name_a ); ?>
								<div class="remember-dup-dates">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: datetime */
											__( 'Profile saved: %s', 'remember' ),
											remember_dup_format_datetime( $snap_a['profile_updated_at'] )
										)
									);
									?>
									<br>
									<?php
									echo esc_html(
										sprintf(
											__( 'Password last entered: %s', 'remember' ),
											remember_dup_format_datetime( $pass_a )
										)
									);
									?>
								</div>
							</th>
							<th>
								<?php echo esc_html( $name_b ); ?>
								<div class="remember-dup-dates">
									<?php
									echo esc_html(
										sprintf(
											__( 'Profile saved: %s', 'remember' ),
											remember_dup_format_datetime( $snap_b['profile_updated_at'] )
										)
									);
									?>
									<br>
									<?php
									echo esc_html(
										sprintf(
											__( 'Password last entered: %s', 'remember' ),
											remember_dup_format_datetime( $pass_b )
										)
									);
									?>
								</div>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $field => $label ) : ?>
							<?php
							$val_a = Remember_Profile_Duplicates::snapshot_display( $snap_a, $field );
							$val_b = Remember_Profile_Duplicates::snapshot_display( $snap_b, $field );
							$pick  = remember_dup_default_side( $val_a, $val_b, $updated_a, $updated_b, $default_survivor );
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<label class="remember-dup-pick">
										<input type="radio" name="choice[<?php echo esc_attr( $field ); ?>]" value="a" <?php checked( $pick, 'a' ); ?>>
										<?php if ( 'photo_url' === $field && '' !== $val_a ) : ?>
											<img src="<?php echo esc_url( $val_a ); ?>" alt="" class="remember-dup-photo">
										<?php else : ?>
											<span><?php echo esc_html( remember_dup_format_value( $field, $val_a ) ); ?></span>
										<?php endif; ?>
									</label>
								</td>
								<td>
									<label class="remember-dup-pick">
										<input type="radio" name="choice[<?php echo esc_attr( $field ); ?>]" value="b" <?php checked( $pick, 'b' ); ?>>
										<?php if ( 'photo_url' === $field && '' !== $val_b ) : ?>
											<img src="<?php echo esc_url( $val_b ); ?>" alt="" class="remember-dup-photo">
										<?php else : ?>
											<span><?php echo esc_html( remember_dup_format_value( $field, $val_b ) ); ?></span>
										<?php endif; ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Password', 'remember' ); ?></th>
							<td colspan="2">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: display name of the profile whose password is kept */
										__( 'Always kept from the later-entered password: %s. This is not selectable.', 'remember' ),
										'b' === $pass_from ? $name_b : $name_a
									)
								);
								?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Merge profiles', 'remember' ); ?></button>
				</p>
			</form>

			<form method="post" action="" class="remember-dup-dismiss-form" onsubmit="return confirm('<?php echo esc_js( __( 'Mark these as not duplicates? They will not be flagged again.', 'remember' ) ); ?>');">
				<?php wp_nonce_field( 'remember_dup_action', 'remember_dup_nonce' ); ?>
				<input type="hidden" name="hit_id" value="<?php echo esc_attr( (string) $hit->hit_id ); ?>">
				<input type="hidden" name="remember_dup_action" value="dismiss">
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Not duplicates', 'remember' ); ?></button>
				</p>
			</form>
		<?php else : ?>
			<p>
				<?php
				if ( 'merged' === $hit->status ) {
					echo esc_html(
						sprintf(
							__( 'Merged. Remaining member ID %1$d; locked member ID %2$d.', 'remember' ),
							(int) $hit->survivor_id,
							(int) $hit->locked_id
						)
					);
				} elseif ( 'closed' === $hit->status ) {
					esc_html_e( 'This review was closed because one of these profiles was merged into another account.', 'remember' );
				} else {
					esc_html_e( 'This pair was marked as not duplicates.', 'remember' );
				}
				?>
			</p>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . (int) $hit->member_a_id ) ); ?>">
				<?php echo esc_html( sprintf( __( 'Open %s', 'remember' ), $name_a ) ); ?>
			</a>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . (int) $hit->member_b_id ) ); ?>">
				<?php echo esc_html( sprintf( __( 'Open %s', 'remember' ), $name_b ) ); ?>
			</a>
		</p>
	<?php else : ?>
		<form method="post" action="" style="margin: 16px 0;">
			<?php wp_nonce_field( 'remember_dup_action', 'remember_dup_nonce' ); ?>
			<input type="hidden" name="remember_dup_action" value="scan">
			<button type="submit" class="button"><?php esc_html_e( 'Scan now', 'remember' ); ?></button>
			<span class="description"><?php esc_html_e( 'A daily scan also runs automatically. Use this if cron is not running on this site.', 'remember' ); ?></span>
		</form>

		<ul class="subsubsub">
			<?php
			$tabs = array(
				'pending'   => __( 'Pending', 'remember' ),
				'dismissed' => __( 'Not duplicates', 'remember' ),
				'merged'    => __( 'Merged', 'remember' ),
				'closed'    => __( 'Closed', 'remember' ),
				'all'       => __( 'All', 'remember' ),
			);
			$i = 0;
			foreach ( $tabs as $key => $label ) :
				++$i;
				?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-duplicates&status=' . $key ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
					<?php echo $i < count( $tabs ) ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="clear"></div>

		<?php
		$hits = Remember_Profile_Duplicates::get_hits( $status );
		if ( empty( $hits ) ) :
			?>
			<p><?php esc_html_e( 'No reviews in this list.', 'remember' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Profiles', 'remember' ); ?></th>
						<th><?php esc_html_e( 'Match', 'remember' ); ?></th>
						<th><?php esc_html_e( 'Status', 'remember' ); ?></th>
						<th><?php esc_html_e( 'Found', 'remember' ); ?></th>
						<th><?php esc_html_e( 'Action', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $hits as $row ) : ?>
						<?php
						$user_a = get_userdata( (int) $row->member_a_id );
						$user_b = get_userdata( (int) $row->member_b_id );
						$reasons = json_decode( isset( $row->match_reasons ) ? $row->match_reasons : '[]', true );
						$labels  = array();
						if ( is_array( $reasons ) ) {
							foreach ( $reasons as $reason ) {
								if ( ! empty( $reason['label'] ) ) {
									$labels[] = $reason['label'];
								}
							}
						}
						?>
						<tr>
							<td>
								<?php
								echo esc_html(
									sprintf(
										'%s (#%d) / %s (#%d)',
										$user_a ? $user_a->display_name : __( 'Unknown', 'remember' ),
										(int) $row->member_a_id,
										$user_b ? $user_b->display_name : __( 'Unknown', 'remember' ),
										(int) $row->member_b_id
									)
								);
								?>
							</td>
							<td><?php echo esc_html( implode( ', ', array_unique( $labels ) ) ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td><?php echo esc_html( remember_dup_format_datetime( $row->created_at ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( Remember_Profile_Duplicates::review_url( (int) $row->hit_id ) ); ?>">
									<?php echo 'pending' === $row->status ? esc_html__( 'Review', 'remember' ) : esc_html__( 'View', 'remember' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>

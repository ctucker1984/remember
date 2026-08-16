<?php
/**
 * Profile custom questions (admin-defined fields).
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-profile-question.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-profile-questions.php';

Remember_Logger::debug( 'Profile questions page loaded' );

$model      = new Remember_Profile_Question();
$editing    = null;
$editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
if ( $editing_id > 0 ) {
	$editing = $model->get( $editing_id );
}

// Delete
if ( isset( $_GET['delete'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'remember_pq_delete_' . absint( $_GET['delete'] ) ) ) {
	if ( ! current_user_can( 'remember_access_settings' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'remember' ) );
	}
	$del_id = absint( $_GET['delete'] );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'remember_profile_question_responses', array( 'question_id' => $del_id ), array( '%d' ) );
	if ( false !== $model->delete( $del_id ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field deleted.', 'remember' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete custom field.', 'remember' ) . '</p></div>';
	}
}

if ( isset( $_POST['remember_pq_action'] ) && check_admin_referer( 'remember_pq_action', 'remember_pq_nonce' ) ) {
	if ( ! current_user_can( 'remember_access_settings' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'remember' ) );
	}

	$action     = sanitize_text_field( wp_unslash( $_POST['remember_pq_action'] ) );
	$label      = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$field_key  = isset( $_POST['field_key'] ) ? Remember_Profile_Questions::sanitize_field_key( wp_unslash( $_POST['field_key'] ) ) : '';
	$field_type = isset( $_POST['field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['field_type'] ) ) : 'text';
	if ( ! in_array( $field_type, Remember_Profile_Questions::allowed_types(), true ) ) {
		$field_type = 'text';
	}
	if ( '' === $field_key && '' !== $label ) {
		$field_key = Remember_Profile_Questions::suggest_field_key_from_label( $label );
	}
	$req_mode = isset( $_POST['required_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['required_mode'] ) ) : 'optional';
	if ( ! in_array( $req_mode, array( 'optional', 'always', 'when' ), true ) ) {
		$req_mode = 'optional';
	}
	$is_required = ( 'always' === $req_mode ) ? 1 : 0;
	$is_active   = ! empty( $_POST['is_active'] ) ? 1 : 0;
	$sort_order  = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

	$required_when_json = null;
	if ( 'when' === $req_mode ) {
		$when_key = isset( $_POST['required_when_field'] ) ? Remember_Profile_Questions::sanitize_field_key( wp_unslash( $_POST['required_when_field'] ) ) : '';
		$when_vals_raw = isset( $_POST['required_when_values'] ) && is_array( $_POST['required_when_values'] )
			? wp_unslash( $_POST['required_when_values'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		$when_vals = array();
		foreach ( $when_vals_raw as $wv ) {
			$when_vals[] = Remember_Profile_Questions::sanitize_field_key( (string) $wv );
		}
		$required_when_json = Remember_Profile_Questions::encode_required_when( $when_key, $when_vals );
	}

	$option_pairs = array();
	if ( Remember_Profile_Questions::type_uses_options( $field_type ) && isset( $_POST['option_label'] ) && is_array( $_POST['option_label'] ) ) {
		$labels_in = wp_unslash( $_POST['option_label'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$keys_in   = isset( $_POST['option_key'] ) && is_array( $_POST['option_key'] ) ? wp_unslash( $_POST['option_key'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( $labels_in as $i => $opt_label ) {
			$opt_label = sanitize_text_field( (string) $opt_label );
			$opt_key   = isset( $keys_in[ $i ] ) ? Remember_Profile_Questions::sanitize_field_key( (string) $keys_in[ $i ] ) : '';
			if ( '' === $opt_label && '' === $opt_key ) {
				continue;
			}
			if ( '' === $opt_key ) {
				$opt_key = Remember_Profile_Questions::sanitize_field_key( $opt_label );
			}
			if ( '' === $opt_key ) {
				continue;
			}
			if ( '' === $opt_label ) {
				$opt_label = $opt_key;
			}
			$option_pairs[] = array(
				'key'   => $opt_key,
				'label' => $opt_label,
			);
		}
	}
	$options = Remember_Profile_Questions::type_uses_options( $field_type )
		? Remember_Profile_Questions::encode_options( $option_pairs )
		: null;

	$error = '';
	if ( '' === $label ) {
		$error = __( 'Please enter the question members will see.', 'remember' );
	} elseif ( '' === $field_key ) {
		$error = __( 'Please enter a short name for spreadsheets (for example ice_cream_flavor).', 'remember' );
	} elseif ( Remember_Profile_Questions::type_uses_options( $field_type ) && empty( Remember_Profile_Questions::parse_options( $options ) ) ) {
		$error = __( 'Add at least one choice with a label and a key (for example Vanilla / vanilla).', 'remember' );
	} elseif ( 'when' === $req_mode && null === $required_when_json ) {
		$error = __( 'Choose an earlier pick-one or pick-several field and at least one choice that makes this field required.', 'remember' );
	}

	$exclude_id = ( 'update' === $action && isset( $_POST['question_id'] ) ) ? absint( $_POST['question_id'] ) : 0;
	if ( '' === $error && $model->field_key_exists( $field_key, $exclude_id ) ) {
		$error = __( 'That short name is already used by another field. Pick a different one.', 'remember' );
	}
	if ( '' === $error && 'when' === $req_mode && null !== $required_when_json ) {
		$when_rule = Remember_Profile_Questions::parse_required_when( $required_when_json );
		$gate_q    = $when_rule ? $model->get_by_field_key( $when_rule['field_key'] ) : null;
		if ( ! $gate_q || (int) $gate_q->question_id === $exclude_id ) {
			$error = __( 'The “required when” field must be a different custom field.', 'remember' );
		} elseif ( ! Remember_Profile_Questions::type_uses_options( $gate_q->field_type ) ) {
			$error = __( 'The “required when” field must be a pick-one or pick-several question.', 'remember' );
		} else {
			$allowed_keys = wp_list_pluck( Remember_Profile_Questions::parse_options( $gate_q->options_json ), 'key' );
			foreach ( $when_rule['values'] as $wv ) {
				if ( ! in_array( $wv, $allowed_keys, true ) ) {
					$error = __( 'One of the “required when” choices is not valid for that field.', 'remember' );
					break;
				}
			}
		}
	}

	if ( '' !== $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	} elseif ( 'add' === $action ) {
		$new_id = $model->insert(
			array(
				'label'              => $label,
				'field_key'          => $field_key,
				'field_type'         => $field_type,
				'options_json'       => $options,
				'is_required'        => $is_required,
				'required_when_json' => $required_when_json,
				'is_active'          => $is_active,
				'sort_order'         => $sort_order,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			)
		);
		if ( $new_id ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field added.', 'remember' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to add custom field.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'update' === $action && $exclude_id > 0 ) {
		$ok = $model->update(
			$exclude_id,
			array(
				'label'              => $label,
				'field_key'          => $field_key,
				'field_type'         => $field_type,
				'options_json'       => $options,
				'is_required'        => $is_required,
				'required_when_json' => $required_when_json,
				'is_active'          => $is_active,
				'sort_order'         => $sort_order,
				'updated_at'         => current_time( 'mysql' ),
			)
		);
		if ( false !== $ok ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field updated.', 'remember' ) . '</p></div>';
			$editing = $model->get( $exclude_id );
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to update custom field.', 'remember' ) . '</p></div>';
		}
	}
}

$questions = $model->get_all_ordered();

$remember_pq_gate_meta = array();
foreach ( $questions as $gq ) {
	if ( ! Remember_Profile_Questions::type_uses_options( $gq->field_type ) ) {
		continue;
	}
	$remember_pq_gate_meta[ (string) $gq->field_key ] = array(
		'label'   => (string) $gq->label,
		'options' => Remember_Profile_Questions::parse_options( $gq->options_json ),
	);
}

/**
 * Shared form fields for add / edit.
 *
 * @param object|null $row Editing row or null for add.
 */
$remember_pq_render_form = function ( $row ) use ( $remember_pq_gate_meta ) {
	$is_edit       = is_object( $row );
	$label         = $is_edit ? $row->label : '';
	$field_key     = $is_edit ? $row->field_key : '';
	$field_type    = $is_edit ? $row->field_type : 'text';
	$choice_rows   = $is_edit ? Remember_Profile_Questions::parse_options( $row->options_json ) : array();
	$when_rule     = $is_edit ? Remember_Profile_Questions::parse_required_when( isset( $row->required_when_json ) ? $row->required_when_json : null ) : null;
	if ( $when_rule ) {
		$req_mode = 'when';
	} elseif ( $is_edit && ! empty( $row->is_required ) ) {
		$req_mode = 'always';
	} else {
		$req_mode = 'optional';
	}
	$when_field    = $when_rule ? $when_rule['field_key'] : '';
	$when_values   = $when_rule ? $when_rule['values'] : array();
	$is_active     = $is_edit ? ! empty( $row->is_active ) : true;
	$sort_order    = $is_edit ? (int) $row->sort_order : 0;
	$self_key      = $is_edit ? (string) $row->field_key : '';
	$gate_choices  = array();
	foreach ( $remember_pq_gate_meta as $gk => $meta ) {
		if ( $self_key !== '' && $gk === $self_key ) {
			continue;
		}
		$gate_choices[ $gk ] = $meta;
	}
	if ( empty( $choice_rows ) ) {
		$choice_rows = array(
			array(
				'key'   => '',
				'label' => '',
			),
			array(
				'key'   => '',
				'label' => '',
			),
		);
	}
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="label"><?php esc_html_e( 'Question', 'remember' ); ?></label>
			</th>
			<td>
				<input type="text" class="large-text" name="label" id="label" value="<?php echo esc_attr( $label ); ?>" required
					placeholder="<?php esc_attr_e( 'What sort of ice cream do you like?', 'remember' ); ?>">
				<p class="description"><?php esc_html_e( 'This is what members see on registration and their profile.', 'remember' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="field_type"><?php esc_html_e( 'Answer type', 'remember' ); ?></label>
			</th>
			<td>
				<select name="field_type" id="field_type">
					<option value="text" <?php selected( $field_type, 'text' ); ?>><?php esc_html_e( 'They type their own answer', 'remember' ); ?></option>
					<option value="select" <?php selected( $field_type, 'select' ); ?>><?php esc_html_e( 'They pick one from a list', 'remember' ); ?></option>
					<option value="multiselect" <?php selected( $field_type, 'multiselect' ); ?>><?php esc_html_e( 'They can pick several from a list', 'remember' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="remember-pq-options-row">
			<th scope="row"><?php esc_html_e( 'Choices', 'remember' ); ?></th>
			<td>
				<div class="remember-pq-choices" id="remember-pq-choices">
					<div class="remember-pq-choices-head">
						<span><?php esc_html_e( 'Label', 'remember' ); ?></span>
						<span><?php esc_html_e( 'Key', 'remember' ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Actions', 'remember' ); ?></span>
					</div>
					<div class="remember-pq-choices-list">
						<?php foreach ( $choice_rows as $choice ) : ?>
							<div class="remember-pq-choice-row">
								<input type="text" name="option_label[]" class="remember-pq-choice-label" value="<?php echo esc_attr( $choice['label'] ); ?>" placeholder="<?php esc_attr_e( 'Vanilla', 'remember' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'remember' ); ?>">
								<input type="text" name="option_key[]" class="remember-pq-choice-key" value="<?php echo esc_attr( $choice['key'] ); ?>" placeholder="vanilla" pattern="[a-z0-9_]*" autocomplete="off" aria-label="<?php esc_attr_e( 'Key', 'remember' ); ?>">
								<button type="button" class="button-link-delete remember-pq-remove-choice"><?php esc_html_e( 'Remove', 'remember' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="remember-pq-choices-actions">
						<button type="button" class="button" id="remember-pq-add-choice"><?php esc_html_e( 'Add choice', 'remember' ); ?></button>
					</p>
				</div>
				<p class="description">
					<?php esc_html_e( 'Label is what members see; Key is stored and used in spreadsheets. Leave Key blank and we fill it from the label.', 'remember' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Required?', 'remember' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:6px;">
						<input type="radio" name="required_mode" value="optional" <?php checked( $req_mode, 'optional' ); ?>>
						<?php esc_html_e( 'Optional', 'remember' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="radio" name="required_mode" value="always" <?php checked( $req_mode, 'always' ); ?>>
						<?php esc_html_e( 'Always required', 'remember' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="radio" name="required_mode" value="when" <?php checked( $req_mode, 'when' ); ?> <?php disabled( empty( $gate_choices ) ); ?>>
						<?php esc_html_e( 'Required when another field matches…', 'remember' ); ?>
					</label>
				</fieldset>
				<?php if ( empty( $gate_choices ) ) : ?>
					<p class="description"><?php esc_html_e( 'Add a pick-one or pick-several field first if you want conditional requirements.', 'remember' ); ?></p>
				<?php else : ?>
					<div class="remember-pq-when-wrap" style="<?php echo 'when' === $req_mode ? '' : 'display:none;'; ?>">
						<p>
							<label for="required_when_field"><?php esc_html_e( 'When this field is', 'remember' ); ?></label>
							<select name="required_when_field" id="required_when_field">
								<option value=""><?php esc_html_e( '— Select field —', 'remember' ); ?></option>
								<?php foreach ( $gate_choices as $gk => $meta ) : ?>
									<option value="<?php echo esc_attr( $gk ); ?>" <?php selected( $when_field, $gk ); ?>>
										<?php echo esc_html( $meta['label'] . ' (' . $gk . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>
						<div id="remember-pq-when-values">
							<?php
							$opts_for_when = ( $when_field && isset( $gate_choices[ $when_field ] ) )
								? $gate_choices[ $when_field ]['options']
								: array();
							if ( ! empty( $opts_for_when ) ) :
								?>
								<p class="description" style="margin-bottom:6px;"><?php esc_html_e( 'Any of these choices (member must answer this field):', 'remember' ); ?></p>
								<?php foreach ( $opts_for_when as $opt ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="required_when_values[]" value="<?php echo esc_attr( $opt['key'] ); ?>" <?php checked( in_array( $opt['key'], $when_values, true ) ); ?>>
										<?php echo esc_html( $opt['label'] ); ?>
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Select a field to choose which answers make this required.', 'remember' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="field_key"><?php esc_html_e( 'Short name', 'remember' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="field_key" id="field_key" value="<?php echo esc_attr( $field_key ); ?>"
					pattern="[a-z0-9_]*" placeholder="ice_cream_flavor" autocomplete="off">
				<p class="description">
					<?php esc_html_e( 'Used as the column name when you export members to a spreadsheet. Example: ice_cream_flavor. Leave blank and we will suggest one from the question.', 'remember' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Show on forms?', 'remember' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="is_active" value="1" <?php checked( $is_active ); ?>>
					<?php esc_html_e( 'Yes, show this field', 'remember' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="sort_order"><?php esc_html_e( 'Order', 'remember' ); ?></label>
			</th>
			<td>
				<input type="number" name="sort_order" id="sort_order" value="<?php echo esc_attr( (string) $sort_order ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Lower numbers appear first. Put the gating field (e.g. preferred role) above fields that depend on it.', 'remember' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
};
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Custom Profile Fields', 'remember' ); ?></h1>
	<p>
		<?php esc_html_e( 'Add extra questions to member registration and profiles — for example “What sort of ice cream do you like?” with choices like Vanilla and Chocolate.', 'remember' ); ?>
	</p>

	<?php if ( $editing ) : ?>
		<h2><?php esc_html_e( 'Edit field', 'remember' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=remember-profile-questions&edit=' . (int) $editing->question_id ) ); ?>" class="remember-pq-form">
			<?php wp_nonce_field( 'remember_pq_action', 'remember_pq_nonce' ); ?>
			<input type="hidden" name="remember_pq_action" value="update">
			<input type="hidden" name="question_id" value="<?php echo esc_attr( (string) $editing->question_id ); ?>">
			<?php $remember_pq_render_form( $editing ); ?>
			<?php submit_button( __( 'Save changes', 'remember' ) ); ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=remember-profile-questions' ) ); ?>"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
		</form>
	<?php else : ?>
		<div class="postbox" style="max-width:720px;margin-top:1em;">
			<div class="postbox-header"><h2 class="hndle" style="padding:8px 12px;margin:0;"><?php esc_html_e( 'Add a field', 'remember' ); ?></h2></div>
			<div class="inside" style="margin:0;padding:12px 16px 16px;">
				<form method="post" action="" class="remember-pq-form">
					<?php wp_nonce_field( 'remember_pq_action', 'remember_pq_nonce' ); ?>
					<input type="hidden" name="remember_pq_action" value="add">
					<?php $remember_pq_render_form( null ); ?>
					<?php submit_button( __( 'Add field', 'remember' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<hr>
	<h2><?php esc_html_e( 'Your fields', 'remember' ); ?></h2>
	<?php if ( empty( $questions ) ) : ?>
		<p><?php esc_html_e( 'No custom fields yet. Add one above.', 'remember' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="max-width:960px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Question', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Short name', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Type', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Required', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Shown', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Order', 'remember' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'remember' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $questions as $q ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $q->label ); ?></strong></td>
						<td><code><?php echo esc_html( $q->field_key ); ?></code></td>
						<td>
							<?php
							if ( 'select' === $q->field_type ) {
								esc_html_e( 'Pick one', 'remember' );
							} elseif ( 'multiselect' === $q->field_type ) {
								esc_html_e( 'Pick several', 'remember' );
							} else {
								esc_html_e( 'Type answer', 'remember' );
							}
							?>
						</td>
						<td>
							<?php
							$when = Remember_Profile_Questions::parse_required_when( isset( $q->required_when_json ) ? $q->required_when_json : null );
							if ( $when ) {
								esc_html_e( 'When…', 'remember' );
								echo ' <code>' . esc_html( $when['field_key'] ) . '</code>';
							} elseif ( ! empty( $q->is_required ) ) {
								esc_html_e( 'Always', 'remember' );
							} else {
								esc_html_e( 'No', 'remember' );
							}
							?>
						</td>
						<td><?php echo ! empty( $q->is_active ) ? esc_html__( 'Yes', 'remember' ) : esc_html__( 'No', 'remember' ); ?></td>
						<td><?php echo esc_html( (string) $q->sort_order ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-profile-questions&edit=' . (int) $q->question_id ) ); ?>"><?php esc_html_e( 'Edit', 'remember' ); ?></a>
							|
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-profile-questions&delete=' . (int) $q->question_id ), 'remember_pq_delete_' . (int) $q->question_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this field and all answers members have given?', 'remember' ) ); ?>');"><?php esc_html_e( 'Delete', 'remember' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<script>
(function () {
	var gateMeta = <?php echo wp_json_encode( $remember_pq_gate_meta ); ?>;

	function slugify(text) {
		return String(text || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.substring(0, 64);
	}
	function toggleOptions() {
		var sel = document.getElementById('field_type');
		if (!sel) return;
		var show = sel.value === 'select' || sel.value === 'multiselect';
		document.querySelectorAll('.remember-pq-options-row').forEach(function (row) {
			row.style.display = show ? '' : 'none';
		});
	}
	function toggleWhen() {
		var wrap = document.querySelector('.remember-pq-when-wrap');
		if (!wrap) return;
		var checked = document.querySelector('input[name="required_mode"]:checked');
		wrap.style.display = (checked && checked.value === 'when') ? '' : 'none';
	}
	function renderWhenValues(fieldKey, preserveChecked) {
		var box = document.getElementById('remember-pq-when-values');
		if (!box) return;
		var meta = gateMeta[fieldKey];
		if (!meta || !meta.options || !meta.options.length) {
			box.innerHTML = '<p class="description"><?php echo esc_js( __( 'Select a field to choose which answers make this required.', 'remember' ) ); ?></p>';
			return;
		}
		var html = '<p class="description" style="margin-bottom:6px;"><?php echo esc_js( __( 'Any of these choices (member must answer this field):', 'remember' ) ); ?></p>';
		meta.options.forEach(function (opt) {
			var checked = preserveChecked && preserveChecked.indexOf(opt.key) !== -1 ? ' checked' : '';
			html += '<label style="display:block;margin-bottom:4px;">';
			html += '<input type="checkbox" name="required_when_values[]" value="' + String(opt.key).replace(/"/g, '&quot;') + '"' + checked + '> ';
			html += String(opt.label || opt.key).replace(/</g, '&lt;');
			html += '</label>';
		});
		box.innerHTML = html;
	}
	function bindChoiceRow(row) {
		var labelInput = row.querySelector('.remember-pq-choice-label');
		var keyInput = row.querySelector('.remember-pq-choice-key');
		if (!labelInput || !keyInput) return;
		var keyTouched = keyInput.value !== '';
		keyInput.addEventListener('input', function () { keyTouched = keyInput.value !== ''; });
		labelInput.addEventListener('input', function () {
			if (!keyTouched) {
				keyInput.value = slugify(labelInput.value);
			}
		});
		var removeBtn = row.querySelector('.remember-pq-remove-choice');
		if (removeBtn) {
			removeBtn.addEventListener('click', function () {
				var list = row.parentNode;
				if (list && list.querySelectorAll('.remember-pq-choice-row').length > 1) {
					row.remove();
				} else {
					labelInput.value = '';
					keyInput.value = '';
					keyTouched = false;
				}
			});
		}
	}
	document.addEventListener('DOMContentLoaded', function () {
		var sel = document.getElementById('field_type');
		var label = document.getElementById('label');
		var key = document.getElementById('field_key');
		var keyTouched = <?php echo $editing ? 'true' : 'false'; ?>;
		if (sel) {
			sel.addEventListener('change', toggleOptions);
			toggleOptions();
		}
		if (key) {
			key.addEventListener('input', function () { keyTouched = true; });
		}
		if (label && key) {
			label.addEventListener('input', function () {
				if (!keyTouched) {
					key.value = slugify(label.value);
				}
			});
		}
		document.querySelectorAll('.remember-pq-choice-row').forEach(bindChoiceRow);
		var addBtn = document.getElementById('remember-pq-add-choice');
		var list = document.querySelector('#remember-pq-choices .remember-pq-choices-list');
		if (addBtn && list) {
			addBtn.addEventListener('click', function () {
				var first = list.querySelector('.remember-pq-choice-row');
				if (!first) return;
				var clone = first.cloneNode(true);
				clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
				list.appendChild(clone);
				bindChoiceRow(clone);
			});
		}
		document.querySelectorAll('input[name="required_mode"]').forEach(function (radio) {
			radio.addEventListener('change', toggleWhen);
		});
		toggleWhen();
		var whenField = document.getElementById('required_when_field');
		if (whenField) {
			whenField.addEventListener('change', function () {
				renderWhenValues(whenField.value, []);
			});
		}
	});
})();
</script>

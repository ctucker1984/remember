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
	$is_required = ! empty( $_POST['is_required'] ) ? 1 : 0;
	$is_active   = ! empty( $_POST['is_active'] ) ? 1 : 0;
	$sort_order  = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;

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
	}

	$exclude_id = ( 'update' === $action && isset( $_POST['question_id'] ) ) ? absint( $_POST['question_id'] ) : 0;
	if ( '' === $error && $model->field_key_exists( $field_key, $exclude_id ) ) {
		$error = __( 'That short name is already used by another field. Pick a different one.', 'remember' );
	}

	if ( '' !== $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	} elseif ( 'add' === $action ) {
		$new_id = $model->insert(
			array(
				'label'        => $label,
				'field_key'    => $field_key,
				'field_type'   => $field_type,
				'options_json' => $options,
				'is_required'  => $is_required,
				'is_active'    => $is_active,
				'sort_order'   => $sort_order,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
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
				'label'        => $label,
				'field_key'    => $field_key,
				'field_type'   => $field_type,
				'options_json' => $options,
				'is_required'  => $is_required,
				'is_active'    => $is_active,
				'sort_order'   => $sort_order,
				'updated_at'   => current_time( 'mysql' ),
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

/**
 * Shared form fields for add / edit.
 *
 * @param object|null $row Editing row or null for add.
 */
$remember_pq_render_form = static function ( $row ) {
	$is_edit       = is_object( $row );
	$label         = $is_edit ? $row->label : '';
	$field_key     = $is_edit ? $row->field_key : '';
	$field_type    = $is_edit ? $row->field_type : 'text';
	$choice_rows   = $is_edit ? Remember_Profile_Questions::parse_options( $row->options_json ) : array();
	$is_required   = $is_edit ? ! empty( $row->is_required ) : false;
	$is_active     = $is_edit ? ! empty( $row->is_active ) : true;
	$sort_order    = $is_edit ? (int) $row->sort_order : 0;
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
				<label>
					<input type="checkbox" name="is_required" value="1" <?php checked( $is_required ); ?>>
					<?php esc_html_e( 'Members must answer this', 'remember' ); ?>
				</label>
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
				<p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'remember' ); ?></p>
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
						<td><?php echo ! empty( $q->is_required ) ? esc_html__( 'Yes', 'remember' ) : esc_html__( 'No', 'remember' ); ?></td>
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
	});
})();
</script>

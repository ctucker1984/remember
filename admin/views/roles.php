<?php
/**
 * Roles view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-role.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-capabilities.php';

Remember_Logger::debug( 'Roles page loaded' );

$role_model = new Remember_Role();

// Handle delete (GET request)
if ( isset( $_GET['delete'] ) && isset( $_GET['remember_role_nonce'] ) && check_admin_referer( 'remember_role_action', 'remember_role_nonce' ) ) {
	$role_id = absint( $_GET['delete'] );
	// Delete capabilities first
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'remember_role_capabilities', array( 'role_id' => $role_id ), array( '%d' ) );
	// Delete role
	$result = $role_model->delete( $role_id );
	if ( $result !== false ) {
		Remember_Logger::info( 'Role deleted', array( 'role_id' => $role_id ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Role deleted successfully.', 'remember' ) . '</p></div>';
	} else {
		Remember_Logger::error( 'Failed to delete role', array( 'role_id' => $role_id ) );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete role.', 'remember' ) . '</p></div>';
	}
}

// Handle form submissions (POST requests)
if ( isset( $_POST['remember_role_action'] ) && check_admin_referer( 'remember_role_action', 'remember_role_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_role_action'] );
	
	if ( 'add' === $action ) {
		$data = array(
			'role_name'     => sanitize_text_field( $_POST['role_name'] ),
			'role_type'     => sanitize_text_field( $_POST['role_type'] ),
			'is_event_role' => ( 'event' === $_POST['role_type'] ) ? 1 : 0,
			'description'   => sanitize_textarea_field( $_POST['description'] ),
		);
		$role_id = $role_model->create( $data );
		if ( $role_id ) {
			// Set capabilities if provided
			if ( isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) ) {
				$capabilities = array_map( 'sanitize_text_field', $_POST['capabilities'] );
				$role_model->set_capabilities( $role_id, $capabilities );
			}
			Remember_Logger::info( 'Role created', array( 'role_id' => $role_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Role created successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to create role' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create role.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'update_capabilities' === $action && isset( $_POST['role_id'] ) ) {
		$role_id = absint( $_POST['role_id'] );
		$capabilities = isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) 
			? array_map( 'sanitize_text_field', $_POST['capabilities'] ) 
			: array();
		$result = $role_model->set_capabilities( $role_id, $capabilities );
		if ( $result ) {
			Remember_Logger::info( 'Role capabilities updated', array( 'role_id' => $role_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Capabilities updated successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to update capabilities', array( 'role_id' => $role_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to update capabilities.', 'remember' ) . '</p></div>';
		}
	}
}

// Get roles
$event_roles = $role_model->get_event_roles();
$system_roles = $role_model->get_system_roles();

// Get all available capabilities
$all_capabilities = Remember_Capabilities::get_all_capabilities();

// Check if editing capabilities for a role
$editing_role_id = isset( $_GET['edit_capabilities'] ) ? absint( $_GET['edit_capabilities'] ) : 0;
$editing_role = $editing_role_id > 0 ? $role_model->get( $editing_role_id ) : null;
$editing_role_capabilities = $editing_role ? $role_model->get_capabilities( $editing_role_id ) : array();
?>

<div class="wrap remember-roles">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<?php if ( ! $editing_role ) : ?>
		<button type="button" class="page-title-action" onclick="document.getElementById('remember-add-role').style.display='block'; this.style.display='none';"><?php esc_html_e( 'Add New', 'remember' ); ?></button>
	<?php else : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-roles' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Roles', 'remember' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( ! $editing_role ) : ?>
		<!-- Add Form -->
		<div id="remember-add-role" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Add New Role', 'remember' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_role_action', 'remember_role_nonce' ); ?>
				<input type="hidden" name="remember_role_action" value="add">
				
				<table class="form-table">
					<tr>
						<th><label for="role_name"><?php esc_html_e( 'Role Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="text" id="role_name" name="role_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="role_type"><?php esc_html_e( 'Role Type', 'remember' ); ?></label></th>
						<td>
							<select id="role_type" name="role_type" class="regular-text">
								<option value="event"><?php esc_html_e( 'Event Role', 'remember' ); ?></option>
								<option value="system"><?php esc_html_e( 'System Role', 'remember' ); ?></option>
							</select>
							<p class="description">
								<strong><?php esc_html_e( 'Event Roles:', 'remember' ); ?></strong> <?php esc_html_e( 'Roles that members can be assigned to for specific events (e.g., "Guard", "Inmate"). Members can have multiple event roles and can play different roles at different events.', 'remember' ); ?><br>
								<strong><?php esc_html_e( 'System Roles:', 'remember' ); ?></strong> <?php esc_html_e( 'Administrative roles for managing the system (e.g., "System Administrator", "Vetting Team Member"). System roles grant capabilities to manage the plugin.', 'remember' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="description"><?php esc_html_e( 'Description', 'remember' ); ?></label></th>
						<td><textarea id="description" name="description" class="large-text" rows="3"></textarea></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Capabilities', 'remember' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Select capabilities for this role. System roles typically have capabilities; event roles typically do not.', 'remember' ); ?></p>
							<fieldset style="border: 1px solid #ccd0d4; padding: 10px; margin-top: 10px;">
								<?php foreach ( $all_capabilities as $cap => $label ) : ?>
									<label style="display: block; margin: 5px 0;">
										<input type="checkbox" name="capabilities[]" value="<?php echo esc_attr( $cap ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Role', 'remember' ); ?>">
					<button type="button" class="button" onclick="document.getElementById('remember-add-role').style.display='none'; document.querySelector('.page-title-action').style.display='inline-block';"><?php esc_html_e( 'Cancel', 'remember' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Event Roles -->
		<h2><?php esc_html_e( 'Event Roles', 'remember' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Event roles are assigned to members for specific events. Members can have multiple event roles and can play different roles at different events. Event roles typically do not have capabilities.', 'remember' ); ?></p>
		<?php if ( ! empty( $event_roles ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Role Name', 'remember' ); ?></th>
						<th class="column-description"><?php esc_html_e( 'Description', 'remember' ); ?></th>
						<th class="column-capabilities"><?php esc_html_e( 'Capabilities', 'remember' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $event_roles as $role ) : 
						$role_capabilities = $role_model->get_capabilities( $role->role_id );
						// Get member count for this role
						global $wpdb;
						$member_count = $wpdb->get_var( $wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}remember_member_roles WHERE role_id = %d",
							$role->role_id
						) );
					?>
						<tr>
							<td class="column-name"><strong><?php echo esc_html( $role->role_name ); ?></strong></td>
							<td class="column-description"><?php echo esc_html( $role->description ); ?></td>
							<td class="column-capabilities">
								<?php if ( ! empty( $role_capabilities ) ) : 
									$cap_count = count( $role_capabilities );
									$cap_id = 'capabilities-' . $role->role_id;
								?>
									<a href="#" class="remember-toggle-capabilities" data-target="<?php echo esc_attr( $cap_id ); ?>" style="text-decoration: none;">
										<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; vertical-align: middle;"></span>
										<strong><?php echo esc_html( sprintf( _n( '%d capability', '%d capabilities', $cap_count, 'remember' ), $cap_count ) ); ?></strong>
									</a>
									<ul id="<?php echo esc_attr( $cap_id ); ?>" class="remember-capabilities-list" style="display: none; margin: 10px 0 0 20px; padding-left: 20px;">
										<?php foreach ( $role_capabilities as $cap ) : ?>
											<li><?php echo esc_html( Remember_Capabilities::get_capability_label( $cap ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'No capabilities', 'remember' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-roles&edit_capabilities=' . $role->role_id ) ); ?>"><?php esc_html_e( 'Edit Capabilities', 'remember' ); ?></a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-roles&delete=' . $role->role_id ), 'remember_role_action', 'remember_role_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this role?', 'remember' ); ?>');"><?php esc_html_e( 'Delete', 'remember' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No event roles found.', 'remember' ); ?></p>
		<?php endif; ?>

		<!-- System Roles -->
		<h2 style="margin-top: 30px;"><?php esc_html_e( 'System Roles', 'remember' ); ?></h2>
		<p class="description"><?php esc_html_e( 'System roles grant capabilities for managing the plugin. Members with system roles can perform administrative tasks. Members can have multiple system roles.', 'remember' ); ?></p>
		<?php if ( ! empty( $system_roles ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Role Name', 'remember' ); ?></th>
						<th class="column-description"><?php esc_html_e( 'Description', 'remember' ); ?></th>
						<th class="column-capabilities"><?php esc_html_e( 'Capabilities', 'remember' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $system_roles as $role ) : 
						$role_capabilities = $role_model->get_capabilities( $role->role_id );
						// Get member count for this role
						global $wpdb;
						$member_count = $wpdb->get_var( $wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}remember_member_roles WHERE role_id = %d",
							$role->role_id
						) );
					?>
						<tr>
							<td class="column-name"><strong><?php echo esc_html( $role->role_name ); ?></strong></td>
							<td class="column-description"><?php echo esc_html( $role->description ); ?></td>
							<td class="column-capabilities">
								<?php if ( ! empty( $role_capabilities ) ) : 
									$cap_count = count( $role_capabilities );
									$cap_id = 'capabilities-' . $role->role_id;
								?>
									<a href="#" class="remember-toggle-capabilities" data-target="<?php echo esc_attr( $cap_id ); ?>" style="text-decoration: none;">
										<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; vertical-align: middle;"></span>
										<strong><?php echo esc_html( sprintf( _n( '%d capability', '%d capabilities', $cap_count, 'remember' ), $cap_count ) ); ?></strong>
									</a>
									<ul id="<?php echo esc_attr( $cap_id ); ?>" class="remember-capabilities-list" style="display: none; margin: 10px 0 0 20px; padding-left: 20px;">
										<?php foreach ( $role_capabilities as $cap ) : ?>
											<li><?php echo esc_html( Remember_Capabilities::get_capability_label( $cap ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'No capabilities', 'remember' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&filter_role=' . $role->role_id ) ); ?>">
									<?php echo esc_html( sprintf( _n( '%d member', '%d members', $member_count, 'remember' ), $member_count ) ); ?>
								</a> |
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-roles&edit_capabilities=' . $role->role_id ) ); ?>"><?php esc_html_e( 'Edit Capabilities', 'remember' ); ?></a> |
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-roles&delete=' . $role->role_id ), 'remember_role_action', 'remember_role_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this role?', 'remember' ); ?>');"><?php esc_html_e( 'Delete', 'remember' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No system roles found.', 'remember' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<!-- Edit Capabilities Form -->
		<div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php echo esc_html( sprintf( __( 'Edit Capabilities: %s', 'remember' ), $editing_role->role_name ) ); ?></h2>
			<p class="description">
				<strong><?php esc_html_e( 'Role Type:', 'remember' ); ?></strong> 
				<?php echo 'event' === $editing_role->role_type ? esc_html__( 'Event Role', 'remember' ) : esc_html__( 'System Role', 'remember' ); ?>
				<br>
				<strong><?php esc_html_e( 'Description:', 'remember' ); ?></strong> 
				<?php echo esc_html( $editing_role->description ); ?>
			</p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_role_action', 'remember_role_nonce' ); ?>
				<input type="hidden" name="remember_role_action" value="update_capabilities">
				<input type="hidden" name="role_id" value="<?php echo esc_attr( $editing_role_id ); ?>">
				
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Capabilities', 'remember' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Select the capabilities this role should have. Members with this role will be able to perform these actions.', 'remember' ); ?></p>
							<fieldset style="border: 1px solid #ccd0d4; padding: 10px; margin-top: 10px;">
								<?php foreach ( $all_capabilities as $cap => $label ) : ?>
									<label style="display: block; margin: 5px 0;">
										<input type="checkbox" name="capabilities[]" value="<?php echo esc_attr( $cap ); ?>" <?php checked( in_array( $cap, $editing_role_capabilities, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update Capabilities', 'remember' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-roles' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
				</p>
			</form>
		</div>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	$('.remember-toggle-capabilities').on('click', function(e) {
		e.preventDefault();
		var $link = $(this);
		var $target = $('#' + $link.data('target'));
		var $icon = $link.find('.dashicons');
		
		if ($target.is(':visible')) {
			$target.slideUp();
			$icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
		} else {
			$target.slideDown();
			$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
		}
	});
});
</script>

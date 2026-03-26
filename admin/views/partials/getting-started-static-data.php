<?php
/**
 * Shared “static data” setup guide (locations → roles → products → events).
 *
 * Expects optional `$remember_gs_context`: 'wizard' | 'page'.
 *
 * @package    reMember
 * @subpackage reMember/admin/views/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$remember_gs_context = isset( $remember_gs_context ) && is_string( $remember_gs_context ) ? $remember_gs_context : 'page';
$is_wizard           = ( 'wizard' === $remember_gs_context );

$locations_url = admin_url( 'admin.php?page=remember-locations' );
$roles_url     = admin_url( 'admin.php?page=remember-roles' );
$products_url  = admin_url( 'admin.php?page=remember-products' );
$events_url    = admin_url( 'admin.php?page=remember-events' );
$settings_url  = admin_url( 'admin.php?page=remember-settings' );
?>
<div class="remember-static-data-guide" style="<?php echo $is_wizard ? 'margin-bottom: 24px;' : ''; ?>">
	<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 24px;">
		<?php if ( $is_wizard ) : ?>
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Set up your organization data', 'remember' ); ?></h2>
		<?php else : ?>
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Recommended order for static data', 'remember' ); ?></h2>
		<?php endif; ?>
		<p style="margin-top: 0; color: #50575e; max-width: 52rem;">
			<?php esc_html_e( 'Before members can apply to events, define the records below. Each step builds on the previous one.', 'remember' ); ?>
		</p>
		<ol style="margin: 0 0 0 1.25em; padding: 0; max-width: 52rem; line-height: 1.6;">
			<li style="margin-bottom: 14px;">
				<strong><?php esc_html_e( 'Locations', 'remember' ); ?></strong>
				<?php esc_html_e( '— Venues and addresses used when you create events.', 'remember' ); ?>
				<a href="<?php echo esc_url( $locations_url ); ?>"><?php esc_html_e( 'reMember → Locations', 'remember' ); ?></a>
			</li>
			<li style="margin-bottom: 14px;">
				<strong><?php esc_html_e( 'Roles', 'remember' ); ?></strong>
				<?php esc_html_e( '— Participation roles (staff, attendee, etc.) that can be offered on events. System roles also control who can do what in the admin.', 'remember' ); ?>
				<a href="<?php echo esc_url( $roles_url ); ?>"><?php esc_html_e( 'reMember → Roles', 'remember' ); ?></a>
			</li>
			<li style="margin-bottom: 14px;">
				<strong><?php esc_html_e( 'Products', 'remember' ); ?></strong>
				<?php esc_html_e( '— Optional add-ons (merchandise, fees) linked to QuickBooks items when billing is enabled.', 'remember' ); ?>
				<a href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'reMember → Products', 'remember' ); ?></a>
				<?php if ( current_user_can( 'remember_access_settings' ) ) : ?>
					<span class="description"><?php esc_html_e( 'Map catalog items under', 'remember' ); ?> <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'remember' ); ?></a>.</span>
				<?php endif; ?>
			</li>
			<li style="margin-bottom: 0;">
				<strong><?php esc_html_e( 'Events', 'remember' ); ?></strong>
				<?php esc_html_e( '— Create each event, attach a location and dates, then choose which roles and add-ons apply.', 'remember' ); ?>
				<a href="<?php echo esc_url( $events_url ); ?>"><?php esc_html_e( 'reMember → Events', 'remember' ); ?></a>
			</li>
		</ol>
		<?php if ( ! $is_wizard && current_user_can( 'remember_access_settings' ) ) : ?>
			<p style="margin: 18px 0 0 0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-setup' ) ); ?>" class="button"><?php esc_html_e( 'Open setup wizard (front-end pages)', 'remember' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>

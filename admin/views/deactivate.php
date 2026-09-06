<?php
/**
 * Deactivate confirmation: data stays; offer a backup before Plugins → Delete.
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$remember_plugin   = Remember_Admin::plugin_file();
$remember_can_bak  = Remember_Backup::current_user_can_backup();
$remember_continue = wp_nonce_url(
	self_admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $remember_plugin ) . '&remember_confirmed=1' ),
	'deactivate-plugin_' . $remember_plugin
);
?>
<div class="wrap remember-import-export">
	<h1><?php esc_html_e( 'Deactivate reMember', 'remember' ); ?></h1>
	<div class="remember-ie-backup">
		<div>
			<p><?php esc_html_e( 'Turning the plugin off leaves all reMember data in the database. Scheduled jobs stop. WordPress users are not changed.', 'remember' ); ?></p>
			<p><?php esc_html_e( 'Delete is not offered until the plugin is inactive. If you Delete it next, WordPress will also wipe reMember tables, settings, logs, photos we stored, setup pages, and capabilities. WordPress users stay.', 'remember' ); ?></p>
		</div>
	</div>
	<?php if ( $remember_can_bak ) : ?>
		<div class="remember-ie-backup">
			<div>
				<h2><?php esc_html_e( 'Download a backup first', 'remember' ); ?></h2>
				<p><?php esc_html_e( 'If you might Delete the plugin, or move this community, save a JSON backup while reMember is still running.', 'remember' ); ?></p>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_backup">
				<?php submit_button( __( 'Download backup', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
	<?php else : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'A full backup needs Access Settings and Import / Export Data. You can still deactivate.', 'remember' ); ?></p></div>
	<?php endif; ?>
	<p>
		<a class="button button-secondary" href="<?php echo esc_url( $remember_continue ); ?>"><?php esc_html_e( 'Deactivate', 'remember' ); ?></a>
		<a class="button" href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
	</p>
</div>

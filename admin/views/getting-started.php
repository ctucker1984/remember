<?php
/**
 * Getting Started admin page (static data + pointers to wizard and docs).
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap remember-getting-started">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Getting Started', 'remember' ); ?></h1>
	<hr class="wp-header-end">

	<?php
	$remember_gs_context = 'page';
	require plugin_dir_path( __FILE__ ) . 'partials/getting-started-static-data.php';
	?>

	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 24px; max-width: 900px; margin-top: 20px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Front-end pages and shortcodes', 'remember' ); ?></h2>
		<p style="color: #50575e;">
			<?php esc_html_e( 'Use the setup wizard to create WordPress pages and drop in shortcodes for the member dashboard, events list, applications, profile, and more.', 'remember' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-setup' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open setup wizard', 'remember' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-settings#shortcodes' ) ); ?>" class="button" style="margin-left: 8px;"><?php esc_html_e( 'Shortcode reference (Settings)', 'remember' ); ?></a>
		</p>
	</div>

</div>

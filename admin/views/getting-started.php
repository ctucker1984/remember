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

	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 24px; max-width: 900px; margin-top: 20px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Front-end Log out', 'remember' ); ?></h2>
		<p style="color: #50575e;">
			<?php esc_html_e( 'Log out must use a WordPress nonce. Do not link to wp-login.php?action=logout without one. reMember rewrites a placeholder URL to a nonced logout and hides the item when nobody is logged in.', 'remember' ); ?>
		</p>
		<ul style="color: #50575e;">
			<li><?php esc_html_e( 'Site Editor (FSE): edit the Navigation block → + → search “Log out”.', 'remember' ); ?></li>
			<li>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: placeholder path */
						__( 'Or add a Custom Link whose URL is %s (that path is only a marker; the real href includes the nonce).', 'remember' ),
						'<code>/remember-logout</code>'
					)
				);
				?>
			</li>
			<li><?php esc_html_e( 'Classic menus: Appearance → Menus → reMember → Log out → Add to Menu.', 'remember' ); ?></li>
			<li><code>[remember_logout]</code> <?php esc_html_e( 'or', 'remember' ); ?> <code>[remember_logout text="Sign out"]</code></li>
		</ul>
		<p style="color: #50575e; margin-bottom: 0;">
			<?php esc_html_e( 'The WordPress admin bar is hidden when the account’s only WordPress role is Subscriber. Administrator, Editor, and other WP roles still see it. reMember roles do not change this.', 'remember' ); ?>
		</p>
	</div>

</div>

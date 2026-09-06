<?php
/**
 * Staff report builder.
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap remember-reports">
	<h1><?php esc_html_e( 'Reports', 'remember' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Build a table from members, applications, payments, vetting, or events. Saved reports are yours only. Columns you cannot read are dropped when the report runs.', 'remember' ); ?></p>
	<div id="remember-reports-notice"></div>
	<div class="remember-reports-layout">
		<aside class="remember-reports-sidebar">
			<div class="remember-reports-card">
				<div class="remember-reports-card-head">
					<h2><?php esc_html_e( 'My reports', 'remember' ); ?></h2>
					<button type="button" class="button" id="remember-report-new"><?php esc_html_e( 'New report', 'remember' ); ?></button>
				</div>
				<ul id="remember-saved-reports" class="remember-reports-list"></ul>
			</div>
		</aside>
		<div class="remember-reports-main">
			<div class="remember-reports-card">
				<div class="remember-reports-toolbar">
					<label class="remember-reports-name">
						<span class="screen-reader-text"><?php esc_html_e( 'Report name', 'remember' ); ?></span>
						<input type="text" id="remember-report-name" class="regular-text" maxlength="255" placeholder="<?php esc_attr_e( 'Untitled report', 'remember' ); ?>">
					</label>
					<label>
						<?php esc_html_e( 'Subject', 'remember' ); ?>
						<select id="remember-report-subject"></select>
					</label>
					<fieldset class="remember-reports-mode">
						<legend class="screen-reader-text"><?php esc_html_e( 'Result type', 'remember' ); ?></legend>
						<label>
							<input type="radio" name="remember_report_mode" value="detail" checked>
							<?php esc_html_e( 'Rows', 'remember' ); ?>
						</label>
						<label>
							<input type="radio" name="remember_report_mode" value="summary">
							<?php esc_html_e( 'Summary', 'remember' ); ?>
						</label>
					</fieldset>
				</div>

				<div id="remember-report-columns-wrap" class="remember-reports-section">
					<h3><?php esc_html_e( 'Columns', 'remember' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Check fields to include. Use the selected list to set column order.', 'remember' ); ?></p>
					<div class="remember-reports-columns-grid">
						<div id="remember-report-column-groups"></div>
						<div>
							<h4><?php esc_html_e( 'Selected order', 'remember' ); ?></h4>
							<ol id="remember-report-column-order" class="remember-reports-order"></ol>
						</div>
					</div>
				</div>

				<div id="remember-report-summary-wrap" class="remember-reports-section" hidden>
					<h3><?php esc_html_e( 'Grouping', 'remember' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Group rows by one or more fields, then add counts or totals. Leave grouping empty for a single total row.', 'remember' ); ?></p>
					<div id="remember-report-groups"></div>
					<p><button type="button" class="button" id="remember-report-add-group"><?php esc_html_e( 'Add grouping', 'remember' ); ?></button></p>
					<h3><?php esc_html_e( 'Calculations', 'remember' ); ?></h3>
					<div id="remember-report-aggs"></div>
					<p><button type="button" class="button" id="remember-report-add-agg"><?php esc_html_e( 'Add calculation', 'remember' ); ?></button></p>
				</div>

				<div class="remember-reports-section">
					<h3><?php esc_html_e( 'Filters', 'remember' ); ?></h3>
					<div id="remember-report-filters"></div>
					<p><button type="button" class="button" id="remember-report-add-filter"><?php esc_html_e( 'Add filter', 'remember' ); ?></button></p>
				</div>

				<div class="remember-reports-section remember-reports-sort">
					<h3><?php esc_html_e( 'Sort', 'remember' ); ?></h3>
					<label>
						<?php esc_html_e( 'Field', 'remember' ); ?>
						<select id="remember-report-sort-field"></select>
					</label>
					<label>
						<?php esc_html_e( 'Direction', 'remember' ); ?>
						<select id="remember-report-sort-dir">
							<option value="asc"><?php esc_html_e( 'Ascending', 'remember' ); ?></option>
							<option value="desc"><?php esc_html_e( 'Descending', 'remember' ); ?></option>
						</select>
					</label>
				</div>

				<p class="remember-reports-actions">
					<button type="button" class="button button-primary" id="remember-report-run"><?php esc_html_e( 'Run', 'remember' ); ?></button>
					<button type="button" class="button" id="remember-report-save"><?php esc_html_e( 'Save', 'remember' ); ?></button>
					<button type="button" class="button" id="remember-report-save-as"><?php esc_html_e( 'Save as', 'remember' ); ?></button>
					<button type="button" class="button" id="remember-report-delete" disabled><?php esc_html_e( 'Delete', 'remember' ); ?></button>
					<button type="button" class="button" id="remember-report-export"><?php esc_html_e( 'Export CSV', 'remember' ); ?></button>
				</p>
			</div>

			<div class="remember-reports-card remember-reports-results-card">
				<div class="remember-reports-results-head">
					<h2><?php esc_html_e( 'Results', 'remember' ); ?></h2>
					<p id="remember-report-meta" class="description"></p>
				</div>
				<div class="remember-reports-table-wrap">
					<table class="widefat striped" id="remember-report-table">
						<thead></thead>
						<tbody></tbody>
					</table>
				</div>
				<p id="remember-report-empty" class="description" hidden><?php esc_html_e( 'Run a report to see rows here.', 'remember' ); ?></p>
				<div id="remember-report-pager" class="remember-reports-pager" hidden>
					<button type="button" class="button" id="remember-report-prev"><?php esc_html_e( 'Previous', 'remember' ); ?></button>
					<span id="remember-report-page-label"></span>
					<button type="button" class="button" id="remember-report-next"><?php esc_html_e( 'Next', 'remember' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	<form id="remember-report-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
		<input type="hidden" name="action" value="remember_report_export">
		<input type="hidden" name="nonce" value="">
		<input type="hidden" name="definition" value="">
	</form>
</div>

<?php
/**
 * Products view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-product.php';

$product_model = new Remember_Product();

/**
 * Sanitize catalog default price (non-negative, 2 decimals).
 *
 * @param mixed $raw Raw POST value.
 * @return float
 */
$remember_sanitize_catalog_price = function ( $raw ) {
	$s = is_string( $raw ) ? str_replace( ',', '', $raw ) : (string) $raw;
	return round( max( 0, floatval( $s ) ), 2 );
};

if ( isset( $_POST['remember_products_action'] ) && check_admin_referer( 'remember_products_action', 'remember_products_nonce' ) ) {
	$action = sanitize_text_field( wp_unslash( $_POST['remember_products_action'] ) );

	if ( 'add_product' === $action ) {
		$product_name        = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$product_type        = isset( $_POST['product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['product_type'] ) ) : 'Service';
		$default_price       = isset( $_POST['default_price'] ) ? $remember_sanitize_catalog_price( wp_unslash( $_POST['default_price'] ) ) : 0.00;

		if ( '' === $product_name ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Product name is required.', 'remember' ) . '</p></div>';
		} else {
			$existing = $product_model->get_by_name( $product_name );
			if ( $existing ) {
				if ( 0 === (int) $existing->is_active ) {
					$product_model->update(
						$existing->product_id,
						array(
							'description'    => $product_description,
							'product_type'     => $product_type,
							'default_price'    => $default_price,
							'is_active'        => 1,
							'updated_at'       => current_time( 'mysql' ),
						)
					);
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product restored from inactive status.', 'remember' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'That product already exists.', 'remember' ) . '</p></div>';
				}
			} else {
				$created = $product_model->insert(
					array(
						'product_name'   => $product_name,
						'description'    => $product_description,
						'product_type'     => $product_type,
						'default_price'    => $default_price,
						'is_active'        => 1,
						'created_at'       => current_time( 'mysql' ),
						'updated_at'       => current_time( 'mysql' ),
					)
				);
				if ( $created ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product added successfully.', 'remember' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to add product.', 'remember' ) . '</p></div>';
				}
			}
		}
	} elseif ( 'update_product' === $action ) {
		$product_id          = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product_name        = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$product_type        = isset( $_POST['product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['product_type'] ) ) : 'Service';
		$default_price       = isset( $_POST['default_price'] ) ? $remember_sanitize_catalog_price( wp_unslash( $_POST['default_price'] ) ) : 0.00;

		if ( $product_id > 0 && '' !== $product_name ) {
			$result = $product_model->update(
				$product_id,
				array(
					'product_name'   => $product_name,
					'description'    => $product_description,
					'product_type'     => $product_type,
					'default_price'    => $default_price,
					'updated_at'       => current_time( 'mysql' ),
				)
			);
			if ( false !== $result ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product updated.', 'remember' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to update product.', 'remember' ) . '</p></div>';
			}
		}
	} elseif ( 'deactivate_product' === $action ) {
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( $product_id > 0 ) {
			$product_model->deactivate( $product_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product archived (soft-deleted).', 'remember' ) . '</p></div>';
		}
	} elseif ( 'reactivate_product' === $action ) {
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( $product_id > 0 ) {
			$product_model->reactivate( $product_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product reactivated.', 'remember' ) . '</p></div>';
		}
	}
}

$all_products     = $product_model->get_all( array( 'orderby' => 'product_name', 'order' => 'ASC' ) );
$active_products  = array();
$inactive_products = array();
foreach ( $all_products as $product ) {
	if ( (int) $product->is_active === 1 ) {
		$active_products[] = $product;
	} else {
		$inactive_products[] = $product;
	}
}
?>

<div class="wrap remember-products">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'remember' ); ?></h1>
	<hr class="wp-header-end">

	<p class="description"><?php esc_html_e( 'Define static reMember add-on line items here. Set a default subtotal price; each event can still override the price when the add-on is attached. Events select from this catalog. Map each catalog item to a QuickBooks Product/Service under Settings → QuickBooks. Deleting is soft-delete only.', 'remember' ); ?></p>

	<div style="margin: 16px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Add Product', 'remember' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'remember_products_action', 'remember_products_nonce' ); ?>
			<input type="hidden" name="remember_products_action" value="add_product">
			<table class="form-table">
				<tr>
					<th><label for="remember_product_name"><?php esc_html_e( 'Name', 'remember' ); ?></label></th>
					<td><input id="remember_product_name" type="text" name="product_name" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="remember_product_description"><?php esc_html_e( 'Description', 'remember' ); ?></label></th>
					<td><input id="remember_product_description" type="text" name="description" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="remember_product_default_price"><?php esc_html_e( 'Default price', 'remember' ); ?></label></th>
					<td>
						<input id="remember_product_default_price" type="number" name="default_price" class="small-text" step="0.01" min="0" value="0.00">
						<p class="description"><?php esc_html_e( 'Default subtotal for this add-on when placed on an event (events may override).', 'remember' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="remember_product_type"><?php esc_html_e( 'Type', 'remember' ); ?></label></th>
					<td>
						<select id="remember_product_type" name="product_type">
							<option value="Service"><?php esc_html_e( 'Service', 'remember' ); ?></option>
							<option value="Inventory"><?php esc_html_e( 'Inventory', 'remember' ); ?></option>
							<option value="NonInventory"><?php esc_html_e( 'NonInventory', 'remember' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Product', 'remember' ); ?>"></p>
		</form>
	</div>

	<h2><?php esc_html_e( 'Active Products', 'remember' ); ?></h2>
	<table class="wp-list-table widefat striped" style="table-layout: fixed;">
		<thead>
			<tr>
				<th style="width: 22%;"><?php esc_html_e( 'Name', 'remember' ); ?></th>
				<th style="width: 26%;"><?php esc_html_e( 'Description', 'remember' ); ?></th>
				<th style="width: 12%;"><?php esc_html_e( 'Default price', 'remember' ); ?></th>
				<th style="width: 14%;"><?php esc_html_e( 'Type', 'remember' ); ?></th>
				<th style="width: 26%;"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $active_products ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No active products.', 'remember' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $active_products as $product ) : ?>
					<?php $update_form_id = 'remember-product-update-' . absint( $product->product_id ); ?>
					<tr>
						<td style="padding: 10px;">
							<input type="text" name="product_name" form="<?php echo esc_attr( $update_form_id ); ?>" value="<?php echo esc_attr( $product->product_name ); ?>" class="regular-text" style="width: 100%;" required>
						</td>
						<td style="padding: 10px;">
							<input type="text" name="description" form="<?php echo esc_attr( $update_form_id ); ?>" value="<?php echo esc_attr( $product->description ); ?>" class="regular-text" style="width: 100%;">
						</td>
						<td style="padding: 10px;">
							<?php
							$dp = isset( $product->default_price ) ? (float) $product->default_price : 0;
							?>
							<input type="number" name="default_price" form="<?php echo esc_attr( $update_form_id ); ?>" value="<?php echo esc_attr( number_format( $dp, 2, '.', '' ) ); ?>" class="small-text" step="0.01" min="0" style="width: 100%;">
						</td>
						<td style="padding: 10px;">
							<select name="product_type" form="<?php echo esc_attr( $update_form_id ); ?>" style="width: 100%;">
								<option value="Service" <?php selected( $product->product_type, 'Service' ); ?>><?php esc_html_e( 'Service', 'remember' ); ?></option>
								<option value="Inventory" <?php selected( $product->product_type, 'Inventory' ); ?>><?php esc_html_e( 'Inventory', 'remember' ); ?></option>
								<option value="NonInventory" <?php selected( $product->product_type, 'NonInventory' ); ?>><?php esc_html_e( 'NonInventory', 'remember' ); ?></option>
							</select>
						</td>
						<td style="padding: 10px;">
							<form id="<?php echo esc_attr( $update_form_id ); ?>" method="post" action="" style="display:inline;">
								<?php wp_nonce_field( 'remember_products_action', 'remember_products_nonce' ); ?>
								<input type="hidden" name="remember_products_action" value="update_product">
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->product_id ); ?>">
							</form>
							<div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-start;">
								<input type="submit" class="button button-secondary" form="<?php echo esc_attr( $update_form_id ); ?>" value="<?php esc_attr_e( 'Save', 'remember' ); ?>">
								<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Archive this product?', 'remember' ) ); ?>');">
									<?php wp_nonce_field( 'remember_products_action', 'remember_products_nonce' ); ?>
									<input type="hidden" name="remember_products_action" value="deactivate_product">
									<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->product_id ); ?>">
									<input type="submit" class="button button-link-delete" value="<?php esc_attr_e( 'Archive (Soft Delete)', 'remember' ); ?>">
								</form>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2 style="margin-top:24px;"><?php esc_html_e( 'Archived Products', 'remember' ); ?></h2>
	<table class="wp-list-table widefat striped" style="table-layout: fixed;">
		<thead>
			<tr>
				<th style="width: 28%;"><?php esc_html_e( 'Name', 'remember' ); ?></th>
				<th style="width: 32%;"><?php esc_html_e( 'Description', 'remember' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Default price', 'remember' ); ?></th>
				<th style="width: 25%;"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $inactive_products ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No archived products.', 'remember' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $inactive_products as $product ) : ?>
					<tr>
						<td style="padding: 10px;"><?php echo esc_html( $product->product_name ); ?></td>
						<td style="padding: 10px;"><?php echo esc_html( $product->description ); ?></td>
						<td style="padding: 10px;">$<?php echo esc_html( number_format( isset( $product->default_price ) ? (float) $product->default_price : 0, 2 ) ); ?></td>
						<td style="padding: 10px;">
							<form method="post" action="">
								<?php wp_nonce_field( 'remember_products_action', 'remember_products_nonce' ); ?>
								<input type="hidden" name="remember_products_action" value="reactivate_product">
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->product_id ); ?>">
								<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Restore', 'remember' ); ?>">
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

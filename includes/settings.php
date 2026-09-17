<?php
/**
 * Settings: WooCommerce > Tyche Companion.
 *
 * One option holding a few switches. Everything defaults to on, so activating
 * the plugin is enough; the page exists for turning a feature off.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings.
 *
 * @return array
 */
function tyche_companion_defaults() {
	return array(
		'sale_percentage'    => 1,
		'new_badge_days'     => 30,
		'sold_out_badge'     => 1,
		'hover_image'        => 1,
		'sticky_add_to_cart' => 1,
		'free_shipping_bar'  => 1,
		'lean_scripts'       => 1,
	);
}

/**
 * One setting, falling back to its default.
 *
 * @param string $key Setting name.
 * @return int
 */
function tyche_companion_setting( $key ) {
	$settings = wp_parse_args( (array) get_option( 'tyche_companion_settings', array() ), tyche_companion_defaults() );
	return isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
}

/**
 * Sanitize the whole option. Unknown keys are dropped; unchecked boxes are 0.
 *
 * @param mixed $input Submitted values.
 * @return array
 */
function tyche_companion_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$clean = array();

	foreach ( tyche_companion_defaults() as $key => $default ) {
		if ( 'new_badge_days' === $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? min( 365, absint( $input[ $key ] ) ) : 0;
		} else {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
	}

	return $clean;
}

/**
 * Register the option.
 */
function tyche_companion_register_settings() {
	register_setting(
		'tyche_companion',
		'tyche_companion_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tyche_companion_sanitize_settings',
			'default'           => tyche_companion_defaults(),
		)
	);
}
add_action( 'admin_init', 'tyche_companion_register_settings' );

/**
 * Menu entry under WooCommerce.
 */
function tyche_companion_settings_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Tyche Companion', 'tyche-companion' ),
		__( 'Tyche Companion', 'tyche-companion' ),
		'manage_woocommerce',
		'tyche-companion',
		'tyche_companion_settings_page'
	);
}
add_action( 'admin_menu', 'tyche_companion_settings_menu', 60 );

/**
 * Let shop managers save the option, not only administrators.
 *
 * @return string
 */
function tyche_companion_settings_capability() {
	return 'manage_woocommerce';
}
add_filter( 'option_page_capability_tyche_companion', 'tyche_companion_settings_capability' );

/**
 * The settings screen.
 */
function tyche_companion_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$fields = array(
		'sale_percentage'    => array( __( 'Show sale badges as a percentage', 'tyche-companion' ), __( 'Replaces "Sale" with the discount, such as "-25%". Variable products show their largest discount.', 'tyche-companion' ) ),
		'sold_out_badge'     => array( __( 'Show a "Sold out" badge', 'tyche-companion' ), __( 'On product cards for products that are out of stock.', 'tyche-companion' ) ),
		'hover_image'        => array( __( 'Show the second photo on hover', 'tyche-companion' ), __( 'Product cards swap to the first gallery image when a pointer hovers them.', 'tyche-companion' ) ),
		'sticky_add_to_cart' => array( __( 'Sticky add-to-cart bar', 'tyche-companion' ), __( 'On product pages, a bar with the price and button appears once the main button scrolls out of view.', 'tyche-companion' ) ),
		'free_shipping_bar'  => array( __( 'Free shipping progress', 'tyche-companion' ), __( 'Above the cart and in the cart drawer. The amount comes from the minimum order amount on your Free shipping method.', 'tyche-companion' ) ),
		'lean_scripts'       => array( __( 'Leaner pages', 'tyche-companion' ), __( 'Skip WooCommerce\'s jQuery scripts on pages built from blocks. My account, shortcode pages and the store notice keep them.', 'tyche-companion' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tyche Companion', 'tyche-companion' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'tyche_companion' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $field ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $field[0] ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="tyche_companion_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( 1, tyche_companion_setting( $key ) ); ?>>
								<?php echo esc_html( $field[1] ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="tyche-new-badge-days"><?php esc_html_e( '"New" badge', 'tyche-companion' ); ?></label></th>
					<td>
						<input id="tyche-new-badge-days" type="number" min="0" max="365" class="small-text" name="tyche_companion_settings[new_badge_days]" value="<?php echo esc_attr( tyche_companion_setting( 'new_badge_days' ) ); ?>">
						<?php esc_html_e( 'days after a product is published. 0 turns the badge off.', 'tyche-companion' ); ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

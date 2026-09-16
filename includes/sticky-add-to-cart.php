<?php
/**
 * Sticky add-to-cart bar on product pages.
 *
 * Printed once in the footer of a product page and revealed by a small script
 * once the page's own add-to-cart form scrolls above the viewport. Its button
 * never adds to the cart by itself: for a simple product it submits the page's
 * real form, so quantity, validation and any plugin hooked into adding keep
 * working; for anything with options it scrolls back to the form.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the bar.
 */
function tyche_companion_sticky_add_to_cart() {
	if ( ! tyche_companion_setting( 'sticky_add_to_cart' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}

	$direct = $product->is_type( 'simple' ) && ! $product->is_sold_individually();
	wp_enqueue_style( 'tyche-companion-sticky', TYCHE_COMPANION_URL . 'assets/css/sticky-add-to-cart.css', array(), TYCHE_COMPANION_VERSION );
	wp_enqueue_script(
		'tyche-companion-sticky',
		TYCHE_COMPANION_URL . 'assets/js/sticky-add-to-cart.js',
		array(),
		TYCHE_COMPANION_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	?>
	<div class="tyche-sticky-atc" role="region" aria-label="<?php esc_attr_e( 'Add to cart', 'tyche-companion' ); ?>" hidden>
		<div class="tyche-sticky-atc__inner">
			<?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'tyche-sticky-atc__image', 'alt' => '' ) ) ); ?>
			<div class="tyche-sticky-atc__text">
				<p class="tyche-sticky-atc__name"><?php echo esc_html( $product->get_name() ); ?></p>
				<p class="tyche-sticky-atc__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
			</div>
			<button type="button" class="tyche-sticky-atc__button wp-element-button" data-action="<?php echo $direct ? 'submit' : 'scroll'; ?>">
				<?php echo $direct ? esc_html( $product->single_add_to_cart_text() ) : esc_html__( 'Choose options', 'tyche-companion' ); ?>
			</button>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'tyche_companion_sticky_add_to_cart' );

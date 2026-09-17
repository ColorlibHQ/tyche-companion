<?php
/**
 * WooCommerce's jQuery scripts, only where something still uses them.
 *
 * WooCommerce enqueues `woocommerce`, `wc-add-to-cart`, BlockUI and js-cookie on
 * every front-end page, and with them WordPress loads jQuery and jQuery Migrate.
 * On a block theme the shop, product pages, cart drawer, cart and checkout are
 * WooCommerce blocks that do not use any of it, so those pages download and run
 * roughly 100KB of scripts for nothing.
 *
 * This takes the four scripts off pages built from blocks and leaves them where
 * classic WooCommerce output still needs them: My account, a cart or checkout
 * page still using its shortcode, any page with a WooCommerce shortcode, and a
 * site showing the store notice. If another plugin's script depends on one of
 * them, WordPress loads it anyway as a dependency, so nothing that asks for jQuery
 * goes without it.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current page still renders classic WooCommerce output.
 *
 * @return bool
 */
function tyche_companion_page_needs_classic_scripts() {
	if ( is_account_page() || 'yes' === get_option( 'woocommerce_demo_store' ) ) {
		return true;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( ( is_cart() && ! has_block( 'woocommerce/cart', $post ) ) || ( is_checkout() && ! has_block( 'woocommerce/checkout', $post ) ) ) {
		return true;
	}

	$shortcodes = array( 'products', 'product_page', 'product', 'add_to_cart', 'product_category', 'product_categories', 'recent_products', 'featured_products', 'sale_products', 'best_selling_products', 'top_rated_products', 'shop_messages', 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' );
	foreach ( $shortcodes as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Take the scripts off the queue.
 */
function tyche_companion_lean_scripts() {
	if ( ! tyche_companion_setting( 'lean_scripts' ) || ! wp_is_block_theme() || tyche_companion_page_needs_classic_scripts() ) {
		return;
	}

	foreach ( array( 'woocommerce', 'wc-add-to-cart', 'wc-cart-fragments', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'tyche_companion_lean_scripts', 100 );

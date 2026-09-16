<?php
/**
 * Product badges: sale percentage, "New" and "Sold out".
 *
 * These change WooCommerce's own blocks as they render instead of asking a theme
 * to place new blocks, so they appear on every product card in any block theme
 * and in the classic templates.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The largest discount on a product, as a whole percentage.
 *
 * @param WC_Product $product Product.
 * @return int 0 when the product is not discounted.
 */
function tyche_companion_discount_percent( $product ) {
	if ( ! $product || ! $product->is_on_sale() ) {
		return 0;
	}

	$best = 0;

	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( true );
		foreach ( $prices['regular_price'] as $id => $regular ) {
			$sale = isset( $prices['sale_price'][ $id ] ) ? (float) $prices['sale_price'][ $id ] : 0;
			if ( (float) $regular > 0 && $sale > 0 && $sale < (float) $regular ) {
				$best = max( $best, ( (float) $regular - $sale ) / (float) $regular );
			}
		}
	} else {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
			$best = ( $regular - $sale ) / $regular;
		}
	}

	// Floor, so a 24.6% discount never claims 25%.
	return (int) floor( $best * 100 );
}

/**
 * The product a block is rendering.
 *
 * @param WP_Block|null $instance Block instance.
 * @return WC_Product|false
 */
function tyche_companion_block_product( $instance ) {
	$id = ( $instance && ! empty( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : get_the_ID();
	return $id ? wc_get_product( $id ) : false;
}

/**
 * Sale badge block: "-25%" instead of "Sale".
 *
 * @param string   $content  Rendered block.
 * @param array    $block    Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function tyche_companion_sale_badge_percent( $content, $block, $instance ) {
	if ( ! tyche_companion_setting( 'sale_percentage' ) || '' === $content ) {
		return $content;
	}

	$percent = tyche_companion_discount_percent( tyche_companion_block_product( $instance ) );
	if ( $percent < 1 ) {
		return $content;
	}

	/* translators: %d: discount percentage. */
	$label = sprintf( __( '-%d%%', 'tyche-companion' ), $percent );
	/* translators: %d: discount percentage. */
	$spoken = sprintf( __( 'Save %d%%', 'tyche-companion' ), $percent );

	$content = preg_replace(
		'#(<span[^>]*wc-block-components-product-sale-badge__text[^>]*>).*?(</span>)#s',
		// <bdi>, so "-25%" keeps its order inside right-to-left text.
		'${1}<bdi>' . esc_html( $label ) . '</bdi>${2}',
		$content,
		1
	);

	return preg_replace(
		'#(<span[^>]*screen-reader-text[^>]*>).*?(</span>)#s',
		'${1}' . esc_html( $spoken ) . '${2}',
		$content,
		1
	);
}
add_filter( 'render_block_woocommerce/product-sale-badge', 'tyche_companion_sale_badge_percent', 10, 3 );

/**
 * Classic templates: the same percentage in `woocommerce_sale_flash`.
 *
 * @param string     $html    Badge HTML.
 * @param WP_Post    $post    Post.
 * @param WC_Product $product Product.
 * @return string
 */
function tyche_companion_sale_flash_percent( $html, $post, $product ) {
	if ( ! tyche_companion_setting( 'sale_percentage' ) ) {
		return $html;
	}

	$percent = tyche_companion_discount_percent( $product );
	if ( $percent < 1 ) {
		return $html;
	}

	/* translators: %d: discount percentage. */
	return '<span class="onsale"><bdi>' . esc_html( sprintf( __( '-%d%%', 'tyche-companion' ), $percent ) ) . '</bdi></span>';
}
add_filter( 'woocommerce_sale_flash', 'tyche_companion_sale_flash_percent', 10, 3 );

/**
 * "New" and "Sold out" badges on product card images.
 *
 * Added inside the product image block, next to where WooCommerce places its
 * sale badge, and only for cards in a product list -- not the product page.
 *
 * @param string   $content  Rendered block.
 * @param array    $block    Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function tyche_companion_card_badges( $content, $block, $instance ) {
	if ( '' === $content || empty( $block['attrs']['isDescendentOfQueryLoop'] ) ) {
		return $content;
	}

	$product = tyche_companion_block_product( $instance );
	if ( ! $product ) {
		return $content;
	}

	$badges = array();

	if ( tyche_companion_setting( 'sold_out_badge' ) && ! $product->is_in_stock() ) {
		$badges[] = '<span class="tyche-badge tyche-badge--sold-out">' . esc_html__( 'Sold out', 'tyche-companion' ) . '</span>';
	} else {
		$days    = tyche_companion_setting( 'new_badge_days' );
		$created = $product->get_date_created();
		if ( $days > 0 && $created && $created->getTimestamp() > time() - $days * DAY_IN_SECONDS ) {
			$badges[] = '<span class="tyche-badge tyche-badge--new">' . esc_html__( 'New', 'tyche-companion' ) . '</span>';
		}
	}

	if ( ! $badges ) {
		return $content;
	}

	$markup = '<span class="tyche-badges">' . implode( '', $badges ) . '</span>';

	// Before the block wrapper's closing tag, so the badges share the image's box.
	$position = strrpos( $content, '</div>' );
	return false === $position ? $content : substr_replace( $content, $markup, $position, 0 );
}
add_filter( 'render_block_woocommerce/product-image', 'tyche_companion_card_badges', 20, 3 );

/**
 * Badge styles, only where WooCommerce blocks render.
 */
function tyche_companion_badge_styles() {
	wp_enqueue_style( 'tyche-companion-cards', TYCHE_COMPANION_URL . 'assets/css/cards.css', array(), TYCHE_COMPANION_VERSION );
}
add_action( 'wp_enqueue_scripts', 'tyche_companion_badge_styles' );

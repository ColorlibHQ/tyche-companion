<?php
/**
 * Second product photo on hover.
 *
 * Product cards gain the first gallery image, stacked over the main one and
 * shown on hover. It is loaded lazily, marked decorative (the card already has
 * the product's name and main image alt), and never shown on touch screens,
 * where hover would need a first tap.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the hover image to a product card's image block.
 *
 * @param string   $content  Rendered block.
 * @param array    $block    Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function tyche_companion_hover_image( $content, $block, $instance ) {
	if ( ! tyche_companion_setting( 'hover_image' ) || '' === $content || empty( $block['attrs']['isDescendentOfQueryLoop'] ) ) {
		return $content;
	}

	$product = tyche_companion_block_product( $instance );
	if ( ! $product ) {
		return $content;
	}

	$gallery = $product->get_gallery_image_ids();
	if ( ! $gallery ) {
		return $content;
	}

	$size  = ! empty( $block['attrs']['imageSizing'] ) && 'thumbnail' === $block['attrs']['imageSizing'] ? 'woocommerce_thumbnail' : 'woocommerce_single';
	$hover = wp_get_attachment_image(
		(int) $gallery[0],
		$size,
		false,
		array(
			'class'       => 'tyche-hover-image',
			'alt'         => '',
			'loading'     => 'lazy',
			'decoding'    => 'async',
			'aria-hidden' => 'true',
		)
	);

	if ( ! $hover ) {
		return $content;
	}

	// Directly after the main image, inside the same link and frame.
	$processor = new WP_HTML_Tag_Processor( $content );
	if ( ! $processor->next_tag( 'img' ) ) {
		return $content;
	}
	$processor->add_class( 'tyche-has-hover-image' );
	$content = $processor->get_updated_html();

	return preg_replace( '#(<img[^>]*tyche-has-hover-image[^>]*>)#', '${1}' . $hover, $content, 1 );
}
add_filter( 'render_block_woocommerce/product-image', 'tyche_companion_hover_image', 10, 3 );

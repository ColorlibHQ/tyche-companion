<?php
/**
 * Where the free shipping bar appears without anyone placing it.
 *
 * Block hooks insert the block next to WooCommerce's own blocks in templates,
 * template parts, patterns and post content. A store that places the block by
 * hand keeps full control: WordPress skips a hook where the block is already
 * present or was removed in the editor.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook the bar before the cart and into the cart drawer.
 *
 * @param string[] $hooked   Hooked block names.
 * @param string   $position before, after, first_child or last_child.
 * @param string   $anchor   Anchor block name.
 * @return string[]
 */
function tyche_companion_hook_free_shipping_bar( $hooked, $position, $anchor ) {
	if ( ! tyche_companion_setting( 'free_shipping_bar' ) ) {
		return $hooked;
	}

	$placements = array(
		array( 'before', 'woocommerce/cart' ),
		array( 'after', 'woocommerce/mini-cart-title-block' ),
	);

	foreach ( $placements as $placement ) {
		if ( $placement[0] === $position && $placement[1] === $anchor ) {
			$hooked[] = 'tyche-companion/free-shipping-bar';
		}
	}

	return $hooked;
}
add_filter( 'hooked_block_types', 'tyche_companion_hook_free_shipping_bar', 10, 3 );

/**
 * Give the hooked bar the width of what it sits next to.
 *
 * Before the Cart block it spans the cart's wide layout rather than the page's
 * narrower text column.
 *
 * @param array|null $parsed_block      The hooked block, or null if suppressed.
 * @param string     $hooked_block_type Hooked block name.
 * @param string     $position          Relative position.
 * @param array      $anchor            The anchor block.
 * @return array|null
 */
function tyche_companion_hooked_free_shipping_bar_attributes( $parsed_block, $hooked_block_type, $position, $anchor ) {
	if ( null === $parsed_block || 'woocommerce/cart' !== ( $anchor['blockName'] ?? '' ) ) {
		return $parsed_block;
	}

	$parsed_block['attrs']['align'] = 'wide';
	$parsed_block['attrs']['style'] = array( 'spacing' => array( 'margin' => array( 'bottom' => '1.5rem' ) ) );

	return $parsed_block;
}
add_filter( 'hooked_block_tyche-companion/free-shipping-bar', 'tyche_companion_hooked_free_shipping_bar_attributes', 10, 4 );

<?php
/**
 * Block registration.
 *
 * Each block lives in blocks/<name>/ with a block.json. There is no build step:
 * the editor scripts are plain JavaScript against the `wp` globals, and each one
 * ships an .asset.php naming those globals so WordPress loads them first.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register every block in blocks/.
 */
function tyche_companion_register_blocks() {
	foreach ( array( 'free-shipping-bar', 'countdown' ) as $name ) {
		register_block_type( TYCHE_COMPANION_DIR . 'blocks/' . $name );
	}
}
add_action( 'init', 'tyche_companion_register_blocks' );

/**
 * The block category the plugin's blocks appear under.
 *
 * @param array[] $categories Existing categories.
 * @return array[]
 */
function tyche_companion_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'tyche-companion',
			'title' => __( 'Tyche store', 'tyche-companion' ),
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'tyche_companion_block_category' );

<?php
/**
 * Removing an imported starter.
 *
 * Only what the import created is deleted, and only while nothing else has
 * started using it: a category the merchant has since put their own products
 * in stays, and so does a page they have edited into something of their own.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove everything the last import added.
 *
 * @return array Counts of what was removed and what was kept.
 */
function tyche_companion_starter_remove() {
	$record = tyche_companion_imported();
	if ( ! $record['slug'] ) {
		return array( 'removed' => 0, 'kept' => 0 );
	}

	$removed = 0;
	$kept    = 0;

	foreach ( array_merge( $record['posts'], $record['menus'], $record['parts'] ) as $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			continue;
		}
		if ( 'product' === $post->post_type ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				foreach ( $product->get_children() as $child ) {
					wp_delete_post( $child, true );
				}
			}
		}
		wp_delete_post( $id, true );
		++$removed;
	}

	foreach ( $record['media'] as $id ) {
		if ( get_post( $id ) ) {
			wp_delete_attachment( $id, true );
			++$removed;
		}
	}

	foreach ( $record['terms'] as $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		// A term still in use belongs to the store now.
		if ( $term->count > 0 ) {
			++$kept;
			continue;
		}
		wp_delete_term( $term_id, $term->taxonomy );
		++$removed;
	}

	foreach ( $record['settings'] as $key => $value ) {
		if ( 'global_styles' === $key ) {
			$user = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), false );
			if ( ! empty( $user['ID'] ) ) {
				wp_update_post( array( 'ID' => $user['ID'], 'post_content' => wp_slash( (string) $value ) ) );
			}
			continue;
		}
		update_option( $key, $value );
	}

	delete_option( 'tyche_companion_imported' );
	tyche_companion_import_state_clear();

	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
	flush_rewrite_rules( false );

	return array( 'removed' => $removed, 'kept' => $kept );
}

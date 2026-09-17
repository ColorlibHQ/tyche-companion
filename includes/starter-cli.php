<?php
/**
 * WP-CLI commands for starters.
 *
 *   wp tyche starters
 *   wp tyche import roastery --mode=full
 *   wp tyche remove
 *
 * The preview sites are built with these, so a preview is always the result of
 * the same import a merchant runs, not a store dressed by hand.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage Tyche starter sites.
 */
class Tyche_Companion_CLI {

	/**
	 * List the starters in the library.
	 *
	 * @subcommand starters
	 */
	public function starters() {
		$catalogue = tyche_companion_starter_catalogue( true );
		if ( is_wp_error( $catalogue ) ) {
			WP_CLI::error( $catalogue->get_error_message() );
		}

		$rows = array();
		foreach ( $catalogue as $starter ) {
			$rows[] = array(
				'slug'     => $starter['slug'],
				'name'     => $starter['name'],
				'niche'    => $starter['niche'],
				'tier'     => $starter['tier'],
				'version'  => $starter['version'],
				'products' => isset( $starter['counts']['products'] ) ? $starter['counts']['products'] : '',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'name', 'niche', 'tier', 'version', 'products' ) );
	}

	/**
	 * Import a starter.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Which starter.
	 *
	 * [--mode=<mode>]
	 * : full imports the whole store; look brings only the design and the home page.
	 * ---
	 * default: full
	 * options:
	 *   - full
	 *   - look
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function import( $args, $assoc_args ) {
		$slug  = $args[0];
		$mode  = isset( $assoc_args['mode'] ) ? $assoc_args['mode'] : 'full';
		$start = tyche_companion_import_start( $slug, $mode );
		if ( is_wp_error( $start ) ) {
			WP_CLI::error( $start->get_error_message() );
		}

		$guard = 0;
		do {
			$state = tyche_companion_import_step();
			if ( is_wp_error( $state ) ) {
				WP_CLI::error( $state->get_error_message() );
			}
			WP_CLI::log( sprintf( '  %3d%%  %s', $state['progress'], $state['message'] ) );
			++$guard;
		} while ( empty( $state['complete'] ) && $guard < 500 );

		$record = tyche_companion_imported();
		WP_CLI::success(
			sprintf(
				'%s imported (%s): %d posts, %d products, %d images, %d terms.',
				$record['name'] ? $record['name'] : $slug,
				$record['mode'],
				count( $record['posts'] ),
				count( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids' ) ) ),
				count( $record['media'] ),
				count( $record['terms'] )
			)
		);
	}

	/**
	 * Remove the imported starter's content.
	 *
	 * @subcommand remove
	 */
	public function remove() {
		$record = tyche_companion_imported();
		if ( ! $record['slug'] ) {
			WP_CLI::error( 'Nothing has been imported.' );
		}

		$result = tyche_companion_starter_remove();
		WP_CLI::success( sprintf( 'Removed %d items, kept %d that are in use.', $result['removed'], $result['kept'] ) );
	}

	/**
	 * Show what has been imported.
	 *
	 * @subcommand status
	 */
	public function status() {
		$record = tyche_companion_imported();
		if ( ! $record['slug'] ) {
			WP_CLI::log( 'No starter imported.' );
			return;
		}
		WP_CLI::log(
			sprintf(
				"%s (%s, %s) imported %s\n  posts %d, media %d, terms %d, menus %d, parts %d",
				$record['name'],
				$record['slug'],
				$record['mode'],
				$record['date'],
				count( $record['posts'] ),
				count( $record['media'] ),
				count( $record['terms'] ),
				count( $record['menus'] ),
				count( $record['parts'] )
			)
		);
	}
}

WP_CLI::add_command( 'tyche', 'Tyche_Companion_CLI' );

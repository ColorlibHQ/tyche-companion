<?php
/**
 * Starter sites: the library, the packages, and what a store has imported.
 *
 * A starter is a complete store -- products, pages, photographs, a look -- kept
 * as a package of JSON files and images. The packages are not bundled with the
 * plugin: they are downloaded one at a time from Colorlib's library, so
 * installing the plugin stays small and a starter can be corrected without a
 * plugin update.
 *
 * During development the library can be a directory on disk. See
 * TYCHE_COMPANION_STARTER_DIR and the `tyche_companion_starter_library`
 * filter.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where packages are fetched from: a URL, or a directory while developing.
 *
 * @return string
 */
function tyche_companion_starter_library() {
	$library = defined( 'TYCHE_COMPANION_STARTER_DIR' ) ? TYCHE_COMPANION_STARTER_DIR : 'https://downloads.colorlib.com/tyche/starters/';

	/**
	 * Filters the starter library location.
	 *
	 * @param string $library A URL, or an absolute directory path.
	 */
	return trailingslashit( apply_filters( 'tyche_companion_starter_library', $library ) );
}

/**
 * Whether the library is a directory on this machine rather than a URL.
 *
 * @return bool
 */
function tyche_companion_starter_library_is_local() {
	return ! preg_match( '#^https?://#i', tyche_companion_starter_library() );
}

/**
 * Read one file from the library.
 *
 * @param string $path  Path inside the library, such as "catalogue.json".
 * @param bool   $fresh Skip the cache.
 * @return array|WP_Error Decoded JSON.
 */
function tyche_companion_starter_read( $path, $fresh = false ) {
	$library = tyche_companion_starter_library();

	if ( tyche_companion_starter_library_is_local() ) {
		$file = $library . $path;
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'tyche_starter_missing', sprintf( /* translators: %s: file name. */ __( 'Cannot read %s from the starter library.', 'tyche-companion' ), $path ) );
		}
		$body = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	} else {
		$key    = 'tyche_starter_' . md5( $path );
		$cached = $fresh ? false : get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get( $library . $path, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'tyche_starter_http', sprintf( /* translators: %s: file name. */ __( 'The starter library did not return %s.', 'tyche-companion' ), $path ) );
		}
		$body = wp_remote_retrieve_body( $response );
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'tyche_starter_json', sprintf( /* translators: %s: file name. */ __( '%s from the starter library is not valid JSON.', 'tyche-companion' ), $path ) );
	}

	if ( ! tyche_companion_starter_library_is_local() ) {
		set_transient( 'tyche_starter_' . md5( $path ), $data, DAY_IN_SECONDS );
	}

	return $data;
}

/**
 * The list of starters, as the Starter sites screen shows them.
 *
 * @param bool $fresh Skip the cache.
 * @return array|WP_Error
 */
function tyche_companion_starter_catalogue( $fresh = false ) {
	$catalogue = tyche_companion_starter_read( 'catalogue.json', $fresh );
	if ( is_wp_error( $catalogue ) ) {
		return $catalogue;
	}

	$starters = isset( $catalogue['starters'] ) ? $catalogue['starters'] : array();
	foreach ( $starters as $i => $starter ) {
		$starters[ $i ] = wp_parse_args(
			$starter,
			array(
				'slug'      => '',
				'name'      => '',
				'niche'     => '',
				'tier'      => 'free',
				'version'   => '1.0.0',
				'thumbnail' => '',
				'preview'   => '',
				'counts'    => array(),
			)
		);
	}

	return $starters;
}

/**
 * One starter from the catalogue.
 *
 * @param string $slug Starter slug.
 * @return array|WP_Error
 */
function tyche_companion_starter( $slug ) {
	$catalogue = tyche_companion_starter_catalogue();
	if ( is_wp_error( $catalogue ) ) {
		return $catalogue;
	}
	foreach ( $catalogue as $starter ) {
		if ( $starter['slug'] === $slug ) {
			return $starter;
		}
	}
	return new WP_Error( 'tyche_starter_unknown', __( 'That starter is not in the library.', 'tyche-companion' ) );
}

/**
 * One file from a starter's package.
 *
 * @param string $slug Starter slug.
 * @param string $file File name, such as "products.json".
 * @return array|WP_Error
 */
function tyche_companion_starter_file( $slug, $file ) {
	return tyche_companion_starter_read( $slug . '/' . $file );
}

/**
 * The URL of one of a starter's photographs.
 *
 * @param string $slug Starter slug.
 * @param string $file Image file name.
 * @return string
 */
function tyche_companion_starter_image_url( $slug, $file ) {
	$library = tyche_companion_starter_library();
	$path    = $slug . '/images/' . $file;

	if ( tyche_companion_starter_library_is_local() ) {
		return 'file://' . $library . $path;
	}

	return $library . $path;
}

/**
 * A URL a browser can load for one of a starter's images.
 *
 * With a published library that is the file itself. While developing, the
 * library is a directory on disk that no browser can reach, so the image is
 * served through the plugin instead.
 *
 * @param string $path Path inside the library, such as "tyche/thumbnail.webp".
 * @return string
 */
function tyche_companion_starter_asset_url( $path ) {
	if ( ! tyche_companion_starter_library_is_local() ) {
		return tyche_companion_starter_library() . $path;
	}

	// An <img> cannot send a REST nonce header, so it travels in the URL.
	return add_query_arg(
		array(
			'path'     => rawurlencode( $path ),
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
		),
		rest_url( 'tyche-companion/v1/starter-asset' )
	);
}

/* -------------------------------------------------------------------------
 * What this store has imported
 * ---------------------------------------------------------------------- */

/**
 * The record of the last import: what it created, and what it changed.
 *
 * @return array
 */
function tyche_companion_imported() {
	return wp_parse_args(
		(array) get_option( 'tyche_companion_imported', array() ),
		array(
			'slug'     => '',
			'name'     => '',
			'version'  => '',
			'mode'     => '',
			'date'     => '',
			'posts'      => array(),
			'terms'      => array(),
			'media'      => array(),
			'menus'      => array(),
			'parts'      => array(),
			'attributes' => array(),
			'settings'   => array(),
		)
	);
}

/**
 * Remember something the import created, so removing the starter can undo it.
 *
 * @param string    $type One of posts, terms, media, menus, parts, attributes.
 * @param int|array $ids  ID or IDs.
 */
function tyche_companion_imported_add( $type, $ids ) {
	$record = tyche_companion_imported();
	$ids    = array_map( 'intval', (array) $ids );

	$record[ $type ] = array_values( array_unique( array_merge( (array) $record[ $type ], $ids ) ) );

	update_option( 'tyche_companion_imported', $record, false );
}

/**
 * The state of an import in progress.
 *
 * Imports run a step at a time over several requests, so a slow host never
 * has to sideload twenty photographs inside one page load.
 *
 * @return array
 */
function tyche_companion_import_state() {
	return wp_parse_args(
		(array) get_option( 'tyche_companion_import_state', array() ),
		array(
			// Whether the store was selling anything before the import.
			'fresh'   => false,
			'slug'    => '',
			'mode'    => 'full',
			'step'    => '',
			'cursor'  => 0,
			'total'   => 0,
			'done'    => 0,
			'map'     => array(),
			'started' => 0,
		)
	);
}

/**
 * Save the state of an import in progress.
 *
 * @param array $state State.
 */
function tyche_companion_import_state_save( $state ) {
	update_option( 'tyche_companion_import_state', $state, false );
}

/**
 * Forget any import in progress.
 */
function tyche_companion_import_state_clear() {
	delete_option( 'tyche_companion_import_state' );
}

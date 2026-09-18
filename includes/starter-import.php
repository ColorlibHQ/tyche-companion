<?php
/**
 * Importing a starter into a store.
 *
 * The import runs a step at a time, and photographs and products run in small
 * batches, so a slow host is never asked to do the whole store inside one
 * request. Each step returns where it got to; the Starter sites screen asks for
 * the next one until the import is done.
 *
 * Everything created is recorded, so "Remove starter content" can take it all
 * out again, and nothing outside that record is ever touched: no orders, no
 * customers, no payment, tax or account settings.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The steps of an import, in order.
 *
 * "Look only" brings the design and the homepage, and leaves the store's own
 * products and pages alone.
 *
 * @param string $mode full or look.
 * @return array
 */
function tyche_companion_import_steps( $mode = 'full' ) {
	if ( 'look' === $mode ) {
		return array( 'style', 'parts', 'images', 'pages', 'pages_content', 'finish' );
	}

	return array( 'style', 'parts', 'images', 'terms', 'products', 'pages', 'pages_content', 'posts', 'menus', 'settings', 'finish' );
}

/**
 * What each step is called while it runs.
 *
 * @return array
 */
function tyche_companion_import_labels() {
	return array(
		'style'         => __( 'Applying the look', 'tyche-companion' ),
		'parts'         => __( 'Setting the header and footer', 'tyche-companion' ),
		'images'        => __( 'Adding the photographs', 'tyche-companion' ),
		'terms'         => __( 'Creating categories', 'tyche-companion' ),
		'products'      => __( 'Adding products', 'tyche-companion' ),
		'pages'         => __( 'Creating pages', 'tyche-companion' ),
		'pages_content' => __( 'Filling in the pages', 'tyche-companion' ),
		'posts'         => __( 'Adding journal posts', 'tyche-companion' ),
		'menus'         => __( 'Building the menu', 'tyche-companion' ),
		'settings'      => __( 'Setting the home page', 'tyche-companion' ),
		'finish'        => __( 'Finishing', 'tyche-companion' ),
	);
}

/**
 * Start an import. Clears any half-finished one.
 *
 * @param string $slug Starter slug.
 * @param string $mode full or look.
 * @return array|WP_Error The first state.
 */
function tyche_companion_import_start( $slug, $mode = 'full' ) {
	$starter = tyche_companion_starter( $slug );
	if ( is_wp_error( $starter ) ) {
		return $starter;
	}

	$manifest = tyche_companion_starter_file( $slug, 'manifest.json' );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}

	delete_option( 'tyche_companion_import_failures' );

	$steps = tyche_companion_import_steps( $mode );
	$state = array(
		'slug'    => $slug,
		'mode'    => 'look' === $mode ? 'look' : 'full',
		'step'    => $steps[0],
		'cursor'  => 0,
		'total'   => count( $steps ),
		'done'    => 0,
		'map'     => array(),
		'started' => time(),
	);

	// Importing a second starter without removing the first would otherwise
	// orphan everything the first one made: the record is what removal reads,
	// so the new record inherits the old one's items. The settings snapshot is
	// kept from the earliest import, so removing still restores the store's own
	// home page and title rather than the previous starter's.
	$previous = tyche_companion_imported();

	update_option(
		'tyche_companion_imported',
		array(
			'slug'     => $slug,
			'name'     => $starter['name'],
			'version'  => $starter['version'],
			'mode'     => $state['mode'],
			'date'     => gmdate( 'c' ),
			'posts'      => $previous['posts'],
			'terms'      => $previous['terms'],
			'media'      => $previous['media'],
			'menus'      => $previous['menus'],
			'parts'      => $previous['parts'],
			'attributes' => $previous['attributes'],
			'settings'   => $previous['slug'] ? $previous['settings'] : tyche_companion_import_settings_snapshot(),
		),
		false
	);

	tyche_companion_import_state_save( $state );

	return $state;
}

/**
 * The options an import may change, as they are now, so removing puts them back.
 *
 * @return array
 */
function tyche_companion_import_settings_snapshot() {
	$keys = array( 'blogname', 'blogdescription', 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure' );
	$out  = array();
	foreach ( $keys as $key ) {
		$out[ $key ] = get_option( $key );
	}
	return $out;
}

/**
 * Run the next piece of work.
 *
 * @return array|WP_Error state, with `message`, `progress` and `complete`.
 */
function tyche_companion_import_step() {
	$state = tyche_companion_import_state();
	if ( ! $state['slug'] ) {
		return new WP_Error( 'tyche_import_none', __( 'No import is running.', 'tyche-companion' ) );
	}

	// Newly added attachments belong to the starter, wherever they come from.
	add_action( 'add_attachment', 'tyche_companion_import_track_attachment' );

	$callback = 'tyche_companion_import_step_' . $state['step'];
	$result   = function_exists( $callback ) ? call_user_func( $callback, $state ) : new WP_Error( 'tyche_import_step', __( 'Unknown import step.', 'tyche-companion' ) );

	remove_action( 'add_attachment', 'tyche_companion_import_track_attachment' );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$state = $result;

	// A step that has not set a cursor is finished; move to the next one.
	if ( empty( $state['cursor'] ) ) {
		$steps    = tyche_companion_import_steps( $state['mode'] );
		$position = array_search( $state['step'], $steps, true );
		$next     = ( false !== $position && isset( $steps[ $position + 1 ] ) ) ? $steps[ $position + 1 ] : '';
		$state['done'] = ( false !== $position ) ? $position + 1 : $state['done'];
		$state['step'] = $next;
	}

	$labels               = tyche_companion_import_labels();
	$state['complete']    = '' === $state['step'];
	$state['message']     = $state['complete'] ? __( 'Done', 'tyche-companion' ) : ( isset( $labels[ $state['step'] ] ) ? $labels[ $state['step'] ] : $state['step'] );
	$state['progress']    = $state['total'] ? min( 100, (int) round( $state['done'] / $state['total'] * 100 ) ) : 0;

	if ( $state['complete'] ) {
		tyche_companion_import_state_clear();
	} else {
		tyche_companion_import_state_save( $state );
	}

	return $state;
}

/**
 * Record an attachment the import created.
 *
 * @param int $id Attachment ID.
 */
function tyche_companion_import_track_attachment( $id ) {
	update_post_meta( $id, '_tyche_starter', tyche_companion_import_state()['slug'] );
	tyche_companion_imported_add( 'media', $id );
}

/* -------------------------------------------------------------------------
 * Placeholders
 * ---------------------------------------------------------------------- */

/**
 * Fill in a starter's placeholders with this store's URLs and IDs.
 *
 * Anything the store has no match for falls back to something sensible -- the
 * shop for a missing category, the home page for a missing page -- so a
 * "look only" import onto a store with its own catalogue still has working
 * links.
 *
 * @param string $content Content with placeholders.
 * @param array  $map     What the import has created so far.
 * @return string
 */
function tyche_companion_import_resolve( $content, $map ) {
	if ( '' === $content || ! str_contains( $content, '{{' ) ) {
		return $content;
	}

	$shop = tyche_companion_import_page_url( 'shop' );

	// Quoted ID placeholders first: "{{imgid:x}}" has to come out as a number,
	// not as a string, or the block editor reads the attribute as invalid.
	$content = preg_replace_callback(
		'/"\{\{(imgid|pageid|postid|catid|termid):([^}"]+)\}\}"/',
		function ( $matches ) use ( $map ) {
			return (string) tyche_companion_import_lookup_id( $matches[1], $matches[2], $map );
		},
		$content
	);

	return preg_replace_callback(
		'/\{\{([a-z_]+)(?::([^}"]+))?\}\}/',
		function ( $matches ) use ( $map, $shop ) {
			$token = $matches[1];
			$value = isset( $matches[2] ) ? $matches[2] : '';

			switch ( $token ) {
				case 'theme':
					return untrailingslashit( get_theme_file_uri( '' ) );
				case 'home':
					return home_url( '/' );
				case 'shop':
					return $shop;
				case 'cart':
				case 'checkout':
					return tyche_companion_import_page_url( $token );
				case 'account':
					return tyche_companion_import_page_url( 'myaccount' );
				case 'img':
					return isset( $map['img'][ $value ]['url'] ) ? $map['img'][ $value ]['url'] : wc_placeholder_img_src();
				case 'imgid':
				case 'pageid':
				case 'postid':
				case 'catid':
				case 'termid':
					return (string) tyche_companion_import_lookup_id( $token, $value, $map );
				case 'page':
					if ( isset( $map['page'][ $value ]['url'] ) ) {
						return $map['page'][ $value ]['url'];
					}
					$page = tyche_companion_import_find( $value, 'page' );
					return $page ? get_permalink( $page ) : home_url( '/' );
				case 'post':
					$post = tyche_companion_import_find( $value, 'post' );
					return $post ? get_permalink( $post ) : home_url( '/' );
				case 'product':
					$product = tyche_companion_import_find( $value, 'product' );
					return $product ? get_permalink( $product ) : $shop;
				case 'cat':
				case 'tag':
				case 'term':
					$taxonomy = array( 'cat' => 'product_cat', 'tag' => 'product_tag', 'term' => 'category' )[ $token ];
					$term     = get_term_by( 'slug', $value, $taxonomy );
					$link     = $term ? get_term_link( $term ) : '';
					return ( $link && ! is_wp_error( $link ) ) ? $link : $shop;
			}

			return $matches[0];
		},
		$content
	);
}

/**
 * The ID behind an ID placeholder, or 0.
 *
 * @param string $token imgid, pageid, postid, catid or termid.
 * @param string $value File name or slug.
 * @param array  $map   What the import has created so far.
 * @return int
 */
function tyche_companion_import_lookup_id( $token, $value, $map ) {
	switch ( $token ) {
		case 'imgid':
			return isset( $map['img'][ $value ]['id'] ) ? (int) $map['img'][ $value ]['id'] : 0;
		case 'pageid':
			if ( isset( $map['page'][ $value ]['id'] ) ) {
				return (int) $map['page'][ $value ]['id'];
			}
			$page = tyche_companion_import_find( $value, 'page' );
			return $page ? $page->ID : 0;
		case 'postid':
			$post = tyche_companion_import_find( $value, 'post' );
			return $post ? $post->ID : 0;
		case 'catid':
		case 'termid':
			$term = get_term_by( 'slug', $value, 'catid' === $token ? 'product_cat' : 'category' );
			return $term ? (int) $term->term_id : 0;
	}

	return 0;
}

/**
 * Find a post of one type by slug.
 *
 * Not get_page_by_path(): that matches attachments too, so once a starter's
 * photographs are in the library, every product whose slug matches a file name
 * "already exists" as an image and is skipped.
 *
 * @param string $slug Post slug.
 * @param string $type Post type.
 * @return WP_Post|null
 */
function tyche_companion_import_find( $slug, $type ) {
	if ( ! $slug ) {
		return null;
	}
	$found = get_posts(
		array(
			'post_type'        => $type,
			'name'             => $slug,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'      => 1,
			'suppress_filters' => false,
		)
	);

	return $found ? $found[0] : null;
}

/**
 * A WooCommerce page URL, falling back to the home page.
 *
 * @param string $page shop, cart, checkout or myaccount.
 * @return string
 */
function tyche_companion_import_page_url( $page ) {
	$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( $page ) : '';
	return $url ? $url : home_url( '/' );
}

/* -------------------------------------------------------------------------
 * Steps
 * ---------------------------------------------------------------------- */

/**
 * The look: the theme's style variations named by the starter.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_style( $state ) {
	$manifest = tyche_companion_starter_file( $state['slug'], 'manifest.json' );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}

	$wanted = isset( $manifest['theme']['style'] ) ? (array) $manifest['theme']['style'] : array();
	$wanted = array_filter( $wanted );
	if ( ! $wanted || ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		return $state;
	}

	$merged = array();
	foreach ( WP_Theme_JSON_Resolver::get_style_variations() as $variation ) {
		$title = isset( $variation['title'] ) ? $variation['title'] : '';
		if ( ! in_array( $title, $wanted, true ) ) {
			continue;
		}
		foreach ( array( 'settings', 'styles' ) as $section ) {
			if ( isset( $variation[ $section ] ) ) {
				$merged[ $section ] = isset( $merged[ $section ] )
					? array_replace_recursive( $merged[ $section ], $variation[ $section ] )
					: $variation[ $section ];
			}
		}
	}

	if ( ! $merged ) {
		return $state;
	}

	$merged['version']                  = WP_Theme_JSON::LATEST_SCHEMA;
	$merged['isGlobalStylesUserThemeJSON'] = true;

	$user = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
	if ( ! empty( $user['ID'] ) ) {
		// The style is a change to the site, not something the import created,
		// so removal restores what was there rather than deleting the post.
		$record = tyche_companion_imported();
		if ( ! isset( $record['settings']['global_styles'] ) ) {
			$record['settings']['global_styles'] = get_post_field( 'post_content', $user['ID'] );
			update_option( 'tyche_companion_imported', $record, false );
		}
		wp_update_post(
			array(
				'ID'           => $user['ID'],
				'post_content' => wp_slash( wp_json_encode( $merged ) ),
			)
		);
	}

	return $state;
}

/**
 * Header and footer, when the starter uses different ones.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_parts( $state ) {
	$parts = tyche_companion_starter_file( $state['slug'], 'parts.json' );
	if ( is_wp_error( $parts ) ) {
		return $state;
	}

	$theme = get_stylesheet();
	foreach ( (array) $parts as $part ) {
		if ( empty( $part['slug'] ) ) {
			continue;
		}
		$existing = get_posts(
			array(
				'post_type'      => 'wp_template_part',
				'name'           => $part['slug'],
				'post_status'    => 'any',
				'numberposts'    => 1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme ),
				),
			)
		);

		$data = array(
			'post_type'    => 'wp_template_part',
			'post_status'  => 'publish',
			'post_name'    => $part['slug'],
			'post_title'   => isset( $part['title'] ) ? $part['title'] : $part['slug'],
			'post_content' => wp_slash( tyche_companion_import_resolve( $part['content'], $state['map'] ) ),
		);
		if ( $existing ) {
			$data['ID'] = $existing[0]->ID;
		}

		$id = wp_insert_post( $data );
		if ( $id && ! is_wp_error( $id ) ) {
			wp_set_object_terms( $id, $theme, 'wp_theme' );
			if ( ! empty( $part['area'] ) ) {
				wp_set_object_terms( $id, $part['area'], 'wp_template_part_area' );
			}
			if ( ! $existing ) {
				tyche_companion_imported_add( 'parts', $id );
			}
		}
	}

	return $state;
}

/**
 * The photographs, three at a time.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_images( $state ) {
	$images = tyche_companion_starter_file( $state['slug'], 'images.json' );
	if ( is_wp_error( $images ) ) {
		return $images;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$images = array_values( (array) $images );
	$start  = (int) $state['cursor'];
	$batch  = array_slice( $images, $start, 3 );

	foreach ( $batch as $image ) {
		$file = isset( $image['file'] ) ? $image['file'] : '';
		if ( ! $file ) {
			continue;
		}

		$id = tyche_companion_import_sideload( $state['slug'], $file );
		if ( is_wp_error( $id ) ) {
			// A missing photograph is not worth failing a whole store for, but
			// silence here once imported a store with no pictures at all.
			$failures   = (array) get_option( 'tyche_companion_import_failures', array() );
			$failures[] = $file . ': ' . $id->get_error_message();
			update_option( 'tyche_companion_import_failures', array_slice( $failures, 0, 40 ), false );
			continue;
		}

		if ( ! empty( $image['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $image['alt'] );
		}
		$state['map']['img'][ $file ] = array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
	}

	$state['cursor'] = ( $start + 3 < count( $images ) ) ? $start + 3 : 0;

	return $state;
}

/**
 * Put one of a starter's photographs in the media library.
 *
 * @param string $slug Starter slug.
 * @param string $file Image file name.
 * @return int|WP_Error Attachment ID.
 */
function tyche_companion_import_sideload( $slug, $file ) {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_tyche_starter_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $slug . '/' . $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = tyche_companion_starter_image_url( $slug, $file );

	// A multisite network lists the file types its sites may upload, and WebP is
	// not on the default list, so every photograph in a starter was refused with
	// "you are not allowed to upload this file type". This allows the types a
	// package can contain, for this sideload only.
	$allow = function ( $mimes ) {
		$mimes['webp'] = 'image/webp';
		$mimes['avif'] = 'image/avif';
		$mimes['jpg|jpeg|jpe'] = 'image/jpeg';
		$mimes['png'] = 'image/png';
		return $mimes;
	};
	add_filter( 'upload_mimes', $allow, 99 );

	if ( str_starts_with( $source, 'file://' ) ) {
		$path = substr( $source, 7 );
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'tyche_import_image', $file );
		}
		$temp = wp_tempnam( $file );
		copy( $path, $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
	} else {
		$temp = download_url( $source, 30 );
		if ( is_wp_error( $temp ) ) {
			return $temp;
		}
	}

	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $temp ), 0 );

	remove_filter( 'upload_mimes', $allow, 99 );

	if ( is_wp_error( $id ) ) {
		if ( file_exists( $temp ) ) {
			wp_delete_file( $temp );
		}
		return $id;
	}

	update_post_meta( $id, '_tyche_starter_file', $slug . '/' . $file );

	return (int) $id;
}

/**
 * Product categories, post categories and product attributes.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_terms( $state ) {
	$terms = tyche_companion_starter_file( $state['slug'], 'terms.json' );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	foreach ( array( 'product_cat' => 'product_cat', 'category' => 'category' ) as $key => $taxonomy ) {
		foreach ( ( isset( $terms[ $key ] ) ? $terms[ $key ] : array() ) as $term ) {
			if ( empty( $term['slug'] ) ) {
				continue;
			}
			$existing = get_term_by( 'slug', $term['slug'], $taxonomy );
			$args     = array(
				'slug'        => $term['slug'],
				'description' => isset( $term['description'] ) ? $term['description'] : '',
			);
			if ( ! empty( $term['parent'] ) ) {
				$parent = get_term_by( 'slug', $term['parent'], $taxonomy );
				if ( $parent ) {
					$args['parent'] = $parent->term_id;
				}
			}

			if ( $existing ) {
				wp_update_term( $existing->term_id, $taxonomy, array_merge( $args, array( 'name' => $term['name'] ) ) );
				$term_id = (int) $existing->term_id;
			} else {
				$made = wp_insert_term( $term['name'], $taxonomy, $args );
				if ( is_wp_error( $made ) ) {
					continue;
				}
				$term_id = (int) $made['term_id'];
				tyche_companion_imported_add( 'terms', $term_id );
			}

			if ( ! empty( $term['image'] ) && isset( $state['map']['img'][ $term['image'] ]['id'] ) ) {
				update_term_meta( $term_id, 'thumbnail_id', $state['map']['img'][ $term['image'] ]['id'] );
			}
		}
	}

	foreach ( ( isset( $terms['attributes'] ) ? $terms['attributes'] : array() ) as $attribute ) {
		if ( empty( $attribute['slug'] ) ) {
			continue;
		}
		$id   = wc_attribute_taxonomy_id_by_name( $attribute['slug'] );
		$new  = ! $id;
		$args = array(
			'name'         => $attribute['name'],
			'slug'         => $attribute['slug'],
			'type'         => isset( $attribute['type'] ) ? $attribute['type'] : 'select',
			'order_by'     => isset( $attribute['order_by'] ) ? $attribute['order_by'] : 'menu_order',
			'has_archives' => false,
		);
		$id = $id ? wc_update_attribute( $id, $args ) : wc_create_attribute( $args );
		if ( is_wp_error( $id ) ) {
			continue;
		}
		if ( $new ) {
			tyche_companion_imported_add( 'attributes', $id );
		}

		$taxonomy = wc_attribute_taxonomy_name( $attribute['slug'] );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
		}

		// The order the starter lists them in: sizes run XS to XL, not
		// alphabetically, which is what "custom ordering" means to WooCommerce.
		foreach ( array_values( (array) $attribute['terms'] ) as $position => $name ) {
			$found = term_exists( $name, $taxonomy );
			if ( ! $found ) {
				$found = wp_insert_term( $name, $taxonomy );
				if ( is_wp_error( $found ) ) {
					continue;
				}
				tyche_companion_imported_add( 'terms', (int) $found['term_id'] );
			}
			update_term_meta( (int) ( is_array( $found ) ? $found['term_id'] : $found ), 'order', $position );
		}
	}

	return $state;
}

/**
 * The products, five at a time, with their variations and reviews.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_products( $state ) {
	$products = tyche_companion_starter_file( $state['slug'], 'products.json' );
	if ( is_wp_error( $products ) ) {
		return $products;
	}

	$products = array_values( (array) $products );
	$start    = (int) $state['cursor'];

	foreach ( array_slice( $products, $start, 5 ) as $data ) {
		$id = tyche_companion_import_product( $data, $state['map'] );
		if ( $id ) {
			$state['map']['product'][ $data['slug'] ] = $id;
		}
	}

	$state['cursor'] = ( $start + 5 < count( $products ) ) ? $start + 5 : 0;

	return $state;
}

/**
 * One product.
 *
 * @param array $data Product data from the package.
 * @param array $map  What the import has created so far.
 * @return int Product ID.
 */
function tyche_companion_import_product( $data, $map ) {
	$slug     = isset( $data['slug'] ) ? $data['slug'] : '';
	$existing = tyche_companion_import_find( $slug, 'product' );
	$variable = isset( $data['type'] ) && 'variable' === $data['type'];

	if ( $existing ) {
		$product = wc_get_product( $existing->ID );
	} else {
		$product = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	}
	if ( ! $product ) {
		return 0;
	}

	$product->set_name( $data['name'] );
	$product->set_slug( $slug );
	$product->set_status( 'publish' );
	$product->set_short_description( tyche_companion_import_resolve( isset( $data['short_description'] ) ? $data['short_description'] : '', $map ) );
	$product->set_description( tyche_companion_import_resolve( isset( $data['description'] ) ? $data['description'] : '', $map ) );
	$product->set_featured( ! empty( $data['featured'] ) );
	$product->set_stock_status( isset( $data['stock_status'] ) ? $data['stock_status'] : 'instock' );
	$product->set_menu_order( isset( $data['menu_order'] ) ? (int) $data['menu_order'] : 0 );

	// How long ago the starter says this product was added. Without it every
	// product is new today, which makes "new arrivals" meaningless and puts a
	// New badge on the whole catalogue.
	if ( isset( $data['days_ago'] ) ) {
		$product->set_date_created( time() - (int) $data['days_ago'] * DAY_IN_SECONDS );
	}
	$product->set_reviews_allowed( ! empty( $data['reviews'] ) );

	if ( ! empty( $data['sku'] ) && ! wc_get_product_id_by_sku( $data['sku'] ) ) {
		$product->set_sku( $data['sku'] );
	}
	if ( ! $variable ) {
		$product->set_regular_price( isset( $data['regular_price'] ) ? $data['regular_price'] : '' );
		$product->set_sale_price( isset( $data['sale_price'] ) ? $data['sale_price'] : '' );
	}

	$categories = array();
	foreach ( ( isset( $data['categories'] ) ? $data['categories'] : array() ) as $category ) {
		$term = get_term_by( 'slug', $category, 'product_cat' );
		if ( $term ) {
			$categories[] = $term->term_id;
		}
	}
	if ( $categories ) {
		$product->set_category_ids( $categories );
	}

	if ( ! empty( $data['image'] ) && isset( $map['img'][ $data['image'] ]['id'] ) ) {
		$product->set_image_id( $map['img'][ $data['image'] ]['id'] );
	}
	$gallery = array();
	foreach ( ( isset( $data['gallery'] ) ? $data['gallery'] : array() ) as $file ) {
		if ( isset( $map['img'][ $file ]['id'] ) ) {
			$gallery[] = $map['img'][ $file ]['id'];
		}
	}
	$product->set_gallery_image_ids( $gallery );

	$attributes = array();
	foreach ( ( isset( $data['attributes'] ) ? $data['attributes'] : array() ) as $position => $source ) {
		$attribute = new WC_Product_Attribute();
		if ( ! empty( $source['taxonomy'] ) ) {
			$taxonomy = wc_attribute_taxonomy_name( $source['slug'] );
			$id       = wc_attribute_taxonomy_id_by_name( $source['slug'] );
			if ( ! $id || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$term_ids = array();
			foreach ( (array) $source['options'] as $name ) {
				$term = get_term_by( 'name', $name, $taxonomy );
				if ( $term ) {
					$term_ids[] = $term->term_id;
				}
			}
			$attribute->set_id( $id );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
		} else {
			$attribute->set_name( $source['slug'] );
			$attribute->set_options( (array) $source['options'] );
		}
		$attribute->set_position( $position );
		$attribute->set_visible( ! empty( $source['visible'] ) );
		$attribute->set_variation( ! empty( $source['variation'] ) );
		$attributes[] = $attribute;
	}
	$product->set_attributes( $attributes );

	$product_id = $product->save();
	if ( ! $product_id ) {
		return 0;
	}
	if ( ! $existing ) {
		tyche_companion_imported_add( 'posts', $product_id );
	}
	update_post_meta( $product_id, '_tyche_starter', true );

	if ( $variable && ! empty( $data['variations'] ) ) {
		tyche_companion_import_variations( $product_id, $data['variations'], $map );
	}

	if ( ! empty( $data['reviews'] ) && ! get_comments( array( 'post_id' => $product_id, 'type' => 'review', 'count' => true ) ) ) {
		foreach ( $data['reviews'] as $review ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'  => $product_id,
					'comment_author'   => $review['author'],
					'comment_content'  => $review['content'],
					'comment_type'     => 'review',
					'comment_approved' => 1,
				)
			);
			if ( $comment_id ) {
				update_comment_meta( $comment_id, 'rating', (int) $review['rating'] );
				update_comment_meta( $comment_id, 'verified', 1 );
			}
		}
		WC_Comments::clear_transients( $product_id );
	}

	return $product_id;
}

/**
 * The variations of a variable product.
 *
 * @param int   $product_id Parent product.
 * @param array $variations Variation data.
 * @param array $map        What the import has created so far.
 */
function tyche_companion_import_variations( $product_id, $variations, $map ) {
	$parent = wc_get_product( $product_id );
	if ( ! $parent || $parent->get_children() ) {
		return;
	}

	foreach ( $variations as $data ) {
		$variation  = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );

		$attributes = array();
		foreach ( ( isset( $data['attributes'] ) ? $data['attributes'] : array() ) as $slug => $value ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			$term     = taxonomy_exists( $taxonomy ) ? get_term_by( 'name', $value, $taxonomy ) : null;
			$attributes[ $term ? $taxonomy : $slug ] = $term ? $term->slug : $value;
		}
		$variation->set_attributes( $attributes );
		$variation->set_regular_price( isset( $data['regular_price'] ) ? $data['regular_price'] : '' );
		$variation->set_sale_price( isset( $data['sale_price'] ) ? $data['sale_price'] : '' );
		$variation->set_stock_status( isset( $data['stock_status'] ) ? $data['stock_status'] : 'instock' );
		if ( ! empty( $data['image'] ) && isset( $map['img'][ $data['image'] ]['id'] ) ) {
			$variation->set_image_id( $map['img'][ $data['image'] ]['id'] );
		}
		$variation->save();
	}

	WC_Product_Variable::sync( $product_id );
}

/**
 * The pages, created empty so that everything can link to everything.
 *
 * A page's content refers to other pages, so every page exists before any
 * content is filled in. On a store that already sells something, the starter's
 * home page arrives as a draft instead of taking over the front page.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_pages( $state ) {
	$pages = tyche_companion_starter_file( $state['slug'], 'pages.json' );
	if ( is_wp_error( $pages ) ) {
		return $pages;
	}

	$look_only = 'look' === $state['mode'];
	$front     = tyche_companion_import_front_slug( $state['slug'] );

	foreach ( (array) $pages as $page ) {
		if ( empty( $page['slug'] ) ) {
			continue;
		}
		if ( $look_only && $page['slug'] !== $front ) {
			continue;
		}

		$existing = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => $page['slug'],
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 1,
			)
		);

		$data = array(
			'post_type'      => 'page',
			'post_title'     => $page['title'],
			'post_name'      => $page['slug'],
			'post_status'    => 'publish',
			'comment_status' => 'closed',
		);

		if ( $existing ) {
			// Never overwrite a page the store already had: keep ours beside it.
			if ( ! get_post_meta( $existing[0]->ID, '_tyche_starter', true ) ) {
				$data['post_name'] = $page['slug'] . '-' . $state['slug'];
			} else {
				$data['ID'] = $existing[0]->ID;
			}
		}

		$id = wp_insert_post( $data );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}

		update_post_meta( $id, '_tyche_starter', $state['slug'] );
		if ( ! empty( $page['template'] ) ) {
			update_post_meta( $id, '_wp_page_template', $page['template'] );
		}
		if ( ! empty( $page['image'] ) && isset( $state['map']['img'][ $page['image'] ]['id'] ) ) {
			set_post_thumbnail( $id, $state['map']['img'][ $page['image'] ]['id'] );
		}
		if ( empty( $data['ID'] ) ) {
			tyche_companion_imported_add( 'posts', $id );
		}

		$state['map']['page'][ $page['slug'] ] = array( 'id' => $id, 'url' => get_permalink( $id ) );
	}

	return $state;
}

/**
 * The slug of the starter's own front page.
 *
 * @param string $slug Starter slug.
 * @return string
 */
function tyche_companion_import_front_slug( $slug ) {
	$manifest = tyche_companion_starter_file( $slug, 'manifest.json' );
	return ( ! is_wp_error( $manifest ) && ! empty( $manifest['settings']['front_page'] ) ) ? $manifest['settings']['front_page'] : '';
}

/**
 * Fill in the pages, now that every page, product and category exists.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_pages_content( $state ) {
	$pages = tyche_companion_starter_file( $state['slug'], 'pages.json' );
	if ( is_wp_error( $pages ) ) {
		return $pages;
	}

	$pages = array_values( (array) $pages );
	$start = (int) $state['cursor'];

	foreach ( array_slice( $pages, $start, 3 ) as $page ) {
		$slug = isset( $page['slug'] ) ? $page['slug'] : '';
		if ( ! $slug || ! isset( $state['map']['page'][ $slug ] ) ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'           => $state['map']['page'][ $slug ]['id'],
				'post_content' => wp_slash( tyche_companion_import_resolve( isset( $page['content'] ) ? $page['content'] : '', $state['map'] ) ),
			)
		);
	}

	$state['cursor'] = ( $start + 3 < count( $pages ) ) ? $start + 3 : 0;

	return $state;
}

/**
 * The journal posts, dated as they were when the starter was made.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_posts( $state ) {
	$posts = tyche_companion_starter_file( $state['slug'], 'posts.json' );
	if ( is_wp_error( $posts ) ) {
		return $posts;
	}

	foreach ( (array) $posts as $post ) {
		if ( empty( $post['slug'] ) ) {
			continue;
		}
		$existing = tyche_companion_import_find( $post['slug'], 'post' );
		$data     = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_title'     => $post['title'],
			'post_name'      => $post['slug'],
			'post_content'   => wp_slash( tyche_companion_import_resolve( isset( $post['content'] ) ? $post['content'] : '', $state['map'] ) ),
			'post_excerpt'   => isset( $post['excerpt'] ) ? $post['excerpt'] : '',
			'comment_status' => 'closed',
			'post_date'      => gmdate( 'Y-m-d H:i:s', time() - ( isset( $post['days_ago'] ) ? (int) $post['days_ago'] : 0 ) * DAY_IN_SECONDS ),
		);
		if ( $existing ) {
			$data['ID'] = $existing->ID;
		}

		$id = wp_insert_post( $data );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		if ( ! $existing ) {
			tyche_companion_imported_add( 'posts', $id );
		}
		update_post_meta( $id, '_tyche_starter', $state['slug'] );

		$categories = array();
		foreach ( ( isset( $post['categories'] ) ? $post['categories'] : array() ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term ) {
				$categories[] = (int) $term->term_id;
			}
		}
		if ( $categories ) {
			wp_set_post_categories( $id, $categories );
		}

		if ( ! empty( $post['image'] ) && isset( $state['map']['img'][ $post['image'] ]['id'] ) ) {
			set_post_thumbnail( $id, $state['map']['img'][ $post['image'] ]['id'] );
		}
	}

	return $state;
}

/**
 * The menu.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_menus( $state ) {
	$menus = tyche_companion_starter_file( $state['slug'], 'menus.json' );
	if ( is_wp_error( $menus ) ) {
		return $state;
	}

	foreach ( (array) $menus as $menu ) {
		if ( empty( $menu['slug'] ) ) {
			continue;
		}
		$existing = get_posts(
			array(
				'post_type'   => 'wp_navigation',
				'name'        => $menu['slug'],
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		);

		$data = array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => $menu['title'],
			'post_name'    => $menu['slug'],
			'post_content' => wp_slash( tyche_companion_import_resolve( $menu['content'], $state['map'] ) ),
		);
		if ( $existing ) {
			$data['ID'] = $existing[0]->ID;
		}

		$id = wp_insert_post( $data );
		if ( $id && ! is_wp_error( $id ) && ! $existing ) {
			tyche_companion_imported_add( 'menus', $id );
		}
	}

	return $state;
}

/**
 * The home page, the posts page, and the store's name.
 *
 * Shipping, payments, tax and accounts are left alone: they are the merchant's,
 * not the starter's.
 *
 * @param array $state Import state.
 * @return array|WP_Error
 */
function tyche_companion_import_step_settings( $state ) {
	$manifest = tyche_companion_starter_file( $state['slug'], 'manifest.json' );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}

	$settings = isset( $manifest['settings'] ) ? $manifest['settings'] : array();

	if ( ! empty( $settings['title'] ) && __( 'Just another WordPress site' ) === get_option( 'blogdescription' ) ) {
		// Only name a store that has never been named.
		update_option( 'blogname', $settings['title'] );
		update_option( 'blogdescription', isset( $settings['tagline'] ) ? $settings['tagline'] : '' );
	}

	// A store needs readable URLs: /product/merino-rollneck/, not ?p=123. Only
	// a site still on the plain default is changed, and removal puts it back.
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			flush_rewrite_rules( true );
		}
	}

	tyche_companion_import_shipping( isset( $settings['shipping'] ) ? $settings['shipping'] : array() );

	$front = ! empty( $settings['front_page'] ) && isset( $state['map']['page'][ $settings['front_page'] ] ) ? $state['map']['page'][ $settings['front_page'] ]['id'] : 0;
	$posts = ! empty( $settings['posts_page'] ) && isset( $state['map']['page'][ $settings['posts_page'] ] ) ? $state['map']['page'][ $settings['posts_page'] ]['id'] : 0;

	if ( $front ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
	}
	if ( $posts ) {
		update_option( 'page_for_posts', $posts );
	}

	return $state;
}

/**
 * The starter's delivery, but only on a store that has arranged none of its own.
 *
 * A starter's copy promises free delivery over an amount, and the free-shipping
 * bar reads that amount from the shipping method, so on a new store the two have
 * to agree. On a store that already has shipping zones, the merchant's rates win
 * and the copy is theirs to edit: nothing here overwrites what someone has set
 * up, priced and tested.
 *
 * @param array $shipping Shipping from the manifest.
 */
function tyche_companion_import_shipping( $shipping ) {
	if ( ! $shipping || ! class_exists( 'WC_Shipping_Zones' ) || WC_Shipping_Zones::get_zones() ) {
		return;
	}

	$country = ! empty( $shipping['country'] ) ? $shipping['country'] : 'US';
	$zone    = new WC_Shipping_Zone();
	$zone->set_zone_name( WC()->countries->countries[ $country ] ?? $country );
	$zone->add_location( $country, 'country' );
	$zone->save();

	if ( isset( $shipping['flat_rate'] ) ) {
		$flat = $zone->add_shipping_method( 'flat_rate' );
		update_option(
			'woocommerce_flat_rate_' . $flat . '_settings',
			array( 'title' => __( 'Standard delivery', 'tyche-companion' ), 'tax_status' => 'none', 'cost' => (string) $shipping['flat_rate'] )
		);
	}

	if ( isset( $shipping['free_over'] ) ) {
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option(
			'woocommerce_free_shipping_' . $free . '_settings',
			array(
				'title'            => __( 'Free delivery', 'tyche-companion' ),
				'requires'         => 'min_amount',
				'min_amount'       => (string) $shipping['free_over'],
				'ignore_discounts' => 'no',
			)
		);
	}
}

/**
 * Tidy up.
 *
 * @param array $state Import state.
 * @return array
 */
function tyche_companion_import_step_finish( $state ) {
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
	delete_transient( 'wc_attribute_taxonomies' );
	flush_rewrite_rules( false );

	return $state;
}

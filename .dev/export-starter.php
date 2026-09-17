<?php
/**
 * Export a live store as a starter package.
 *
 *   wp eval-file export-starter.php <slug> <out-dir> [--url=https://example.com/]
 *
 * A starter is designed by building a real store -- products, pages, menus, the
 * look -- and then exporting it. This reads that store and writes the package
 * the plugin's importer reads back: JSON for products, pages, posts, terms and
 * settings, and a list of the photographs with their alt text.
 *
 * Everything that only makes sense on the store it came from is turned into a
 * placeholder: an attachment URL becomes {{img:file.webp}}, its ID becomes
 * {{imgid:file.webp}}, a page link becomes {{page:slug}}, a category link
 * {{cat:slug}}, and the WooCommerce pages {{shop}}, {{cart}}, {{checkout}},
 * {{account}}. The importer fills them in with the IDs and URLs of the store it
 * is importing into.
 *
 * The photographs themselves are not copied: they are kept in the package
 * repository, converted once, and only their names are recorded here.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

$slug = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
$out  = isset( $args[1] ) ? rtrim( $args[1], '/' ) : '';
if ( ! $slug || ! $out ) {
	WP_CLI::error( 'Usage: wp eval-file export-starter.php <slug> <out-dir>' );
}
if ( ! is_dir( $out ) && ! mkdir( $out, 0755, true ) ) {
	WP_CLI::error( "Cannot create $out" );
}

/* -------------------------------------------------------------------------
 * Placeholders
 * ---------------------------------------------------------------------- */

/**
 * Package file name for an attachment: the original upload name, as WebP.
 *
 * @param int $id Attachment ID.
 * @return string
 */
function tyche_export_image_name( $id ) {
	$source = get_post_meta( $id, '_tyche_demo_source', true );
	$file   = $source ? $source : basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );
	return $file ? preg_replace( '/\.(jpe?g|png|webp|gif|avif)$/i', '', $file ) . '.webp' : '';
}

/**
 * Every attachment on the site, as id => package file name.
 *
 * WooCommerce's own placeholder image is skipped: every store already has one.
 *
 * @return array
 */
function tyche_export_attachments() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		$name = tyche_export_image_name( $id );
		if ( $name && ! str_starts_with( $name, 'woocommerce-placeholder' ) ) {
			$map[ $id ] = $name;
		}
	}
	return $map;
}

/**
 * Blocks that carry an attachment ID, and the attribute holding it.
 *
 * Replacing every `"id":123` in the markup would also hit term and page IDs --
 * a navigation link to a category whose term ID happens to match an attachment.
 * So IDs are swapped inside the blocks that own an image, and nowhere else.
 *
 * @return array
 */
function tyche_export_image_blocks() {
	return array(
		'core/image'      => 'id',
		'core/cover'      => 'id',
		'core/media-text' => 'mediaId',
		'core/site-logo'  => 'id',
		'core/video'      => 'id',
		'core/gallery'    => 'ids',
	);
}

/**
 * Swap attachment IDs for placeholders inside image-bearing blocks.
 *
 * @param array $blocks Parsed blocks.
 * @param array $images id => file name.
 * @return array
 */
function tyche_export_block_ids( $blocks, $images ) {
	$carriers = tyche_export_image_blocks();

	foreach ( $blocks as $i => $block ) {
		$name = isset( $block['blockName'] ) ? $block['blockName'] : '';

		// A menu link keeps the ID of the page or category it points at, and
		// the editor uses it to mark the current page. Those IDs mean nothing
		// on another store, so they travel as slugs too.
		if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true ) && ! empty( $block['attrs']['id'] ) ) {
			$attrs  = $block['attrs'];
			$kind   = isset( $attrs['kind'] ) ? $attrs['kind'] : '';
			$type   = isset( $attrs['type'] ) ? $attrs['type'] : '';
			$linked = (int) $attrs['id'];
			if ( 'taxonomy' === $kind ) {
				$term = get_term( $linked );
				if ( $term && ! is_wp_error( $term ) ) {
					$token = 'product_cat' === $type ? 'catid' : 'termid';
					$blocks[ $i ]['attrs']['id'] = '{{' . $token . ':' . $term->slug . '}}';
				}
			} else {
				$linked_post = get_post( $linked );
				if ( $linked_post ) {
					$token = 'post' === $linked_post->post_type ? 'postid' : 'pageid';
					$blocks[ $i ]['attrs']['id'] = '{{' . $token . ':' . $linked_post->post_name . '}}';
				}
			}
		}

		if ( isset( $carriers[ $name ] ) ) {
			$attribute = $carriers[ $name ];
			$value     = isset( $block['attrs'][ $attribute ] ) ? $block['attrs'][ $attribute ] : null;
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $id ) {
					if ( isset( $images[ $id ] ) ) {
						$blocks[ $i ]['attrs'][ $attribute ][ $k ] = '{{imgid:' . $images[ $id ] . '}}';
					}
				}
			} elseif ( is_numeric( $value ) && isset( $images[ (int) $value ] ) ) {
				$blocks[ $i ]['attrs'][ $attribute ] = '{{imgid:' . $images[ (int) $value ] . '}}';
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = tyche_export_block_ids( $block['innerBlocks'], $images );
		}
	}

	return $blocks;
}

/**
 * Replace this store's URLs and IDs with placeholders.
 *
 * Order matters. The theme's own asset URLs go first, because a starter can be
 * imported into a store where the theme directory has a different name. Then
 * uploads, then the WooCommerce pages (so /shop/ is not read as an ordinary
 * page), then terms and products, and only then pages -- the front page is left
 * out, because its permalink is the site root and would swallow every URL that
 * starts with it.
 *
 * @param string $content Block markup or a plain URL.
 * @param bool   $blocks  Whether the content is block markup.
 * @return string
 */
function tyche_export_placeholders( $content, $blocks = true ) {
	if ( '' === $content ) {
		return $content;
	}

	$images = tyche_export_attachments();

	if ( $blocks && str_contains( $content, '<!-- wp:' ) ) {
		$content = serialize_blocks( tyche_export_block_ids( parse_blocks( $content ), $images ) );
	}

	foreach ( array( get_theme_file_uri( '' ), get_stylesheet_directory_uri(), get_template_directory_uri() ) as $theme_uri ) {
		$content = str_replace( untrailingslashit( $theme_uri ), '{{theme}}', $content );
	}

	// Every generated size of every image, longest URL first, so
	// "image-300x400.jpg" is replaced before "image.jpg" matches part of it.
	$urls = array();
	foreach ( $images as $id => $name ) {
		$base = wp_get_attachment_url( $id );
		if ( ! $base ) {
			continue;
		}
		$urls[ $base ] = $name;
		$dir           = trailingslashit( dirname( $base ) );
		$meta          = wp_get_attachment_metadata( $id );
		foreach ( ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $size ) {
			$urls[ $dir . $size['file'] ] = $name;
		}
		$content = str_replace( 'wp-image-' . $id, 'wp-image-{{imgid:' . $name . '}}', $content );
	}
	uksort( $urls, function ( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	} );
	foreach ( $urls as $url => $name ) {
		$content = str_replace( $url, '{{img:' . $name . '}}', $content );
	}

	foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $wc_page ) {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( $wc_page ) : '';
		if ( $url ) {
			$token   = 'myaccount' === $wc_page ? 'account' : $wc_page;
			$content = str_replace( $url, '{{' . $token . '}}', $content );
		}
	}

	foreach ( array( 'product_cat' => 'cat', 'product_tag' => 'tag', 'category' => 'term' ) as $taxonomy => $token ) {
		foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$content = str_replace( $link, '{{' . $token . ':' . $term->slug . '}}', $content );
			}
		}
	}

	foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish' ) ) as $product ) {
		$content = str_replace( get_permalink( $product ), '{{product:' . $product->post_name . '}}', $content );
	}

	$front = (int) get_option( 'page_on_front' );
	foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ) as $page ) {
		if ( $page->ID !== $front ) {
			$content = str_replace( get_permalink( $page ), '{{page:' . $page->post_name . '}}', $content );
		}
	}

	return str_replace( array( home_url( '/' ), home_url() ), array( '{{home}}', '{{home}}' ), $content );
}

/* -------------------------------------------------------------------------
 * Pieces
 * ---------------------------------------------------------------------- */

/** Product categories, in menu order, with their images. */
function tyche_export_terms() {
	$out = array( 'product_cat' => array(), 'attributes' => array(), 'category' => array() );

	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
		$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$out['product_cat'][] = array_filter(
			array(
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => $term->parent ? get_term( $term->parent )->slug : '',
				'image'       => $thumb ? tyche_export_image_name( $thumb ) : '',
			),
			'strlen'
		);
	}

	foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $term ) {
		$out['category'][] = array_filter(
			array( 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description ),
			'strlen'
		);
	}

	foreach ( wc_get_attribute_taxonomies() as $attribute ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
		$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		$terms    = is_wp_error( $terms ) ? array() : $terms;

		// Sorting here rather than in the query: ordering by the `order` term
		// meta drops every term that has none, and a half-ordered attribute
		// would export as an empty one.
		if ( 'menu_order' === $attribute->attribute_orderby ) {
			usort(
				$terms,
				function ( $a, $b ) {
					$order_a = get_term_meta( $a->term_id, 'order', true );
					$order_b = get_term_meta( $b->term_id, 'order', true );
					if ( '' === $order_a || '' === $order_b ) {
						return '' === $order_a && '' === $order_b ? strcmp( $a->name, $b->name ) : ( '' === $order_a ? 1 : -1 );
					}
					return (int) $order_a <=> (int) $order_b;
				}
			);
		}

		$out['attributes'][] = array(
			'name'     => $attribute->attribute_label,
			'slug'     => $attribute->attribute_name,
			'type'     => $attribute->attribute_type,
			'order_by' => $attribute->attribute_orderby,
			'terms'    => wp_list_pluck( $terms, 'name' ),
		);
	}

	return $out;
}

/** One product, with its variations and reviews. */
function tyche_export_product( WC_Product $product ) {
	$data = array(
		'slug'              => $product->get_slug(),
		'name'              => $product->get_name(),
		'type'              => $product->get_type(),
		'sku'               => $product->get_sku(),
		'regular_price'     => $product->get_regular_price(),
		'sale_price'        => $product->get_sale_price(),
		'short_description' => tyche_export_placeholders( $product->get_short_description() ),
		'description'       => tyche_export_placeholders( $product->get_description() ),
		'categories'        => wp_list_pluck( wc_get_product_terms( $product->get_id(), 'product_cat' ), 'slug' ),
		'featured'          => $product->get_featured(),
		'stock_status'      => $product->get_stock_status(),
		'menu_order'        => $product->get_menu_order(),
		'days_ago'          => (int) round( ( time() - $product->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS ),
		'image'             => $product->get_image_id() ? tyche_export_image_name( $product->get_image_id() ) : '',
		'gallery'           => array_values( array_filter( array_map( 'tyche_export_image_name', $product->get_gallery_image_ids() ) ) ),
		'attributes'        => array(),
		'variations'        => array(),
		'reviews'           => array(),
	);

	foreach ( $product->get_attributes() as $attribute ) {
		$data['attributes'][] = array(
			'slug'      => $attribute->is_taxonomy() ? str_replace( 'pa_', '', $attribute->get_name() ) : $attribute->get_name(),
			'taxonomy'  => $attribute->is_taxonomy(),
			'options'   => $attribute->is_taxonomy() ? wp_list_pluck( get_terms( array( 'taxonomy' => $attribute->get_name(), 'include' => $attribute->get_options(), 'hide_empty' => false ) ), 'name' ) : $attribute->get_options(),
			'visible'   => $attribute->get_visible(),
			'variation' => $attribute->get_variation(),
		);
	}

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation ) {
			continue;
		}
		$attributes = array();
		foreach ( $variation->get_attributes() as $name => $value ) {
			$taxonomy = str_replace( 'attribute_', '', $name );
			$term     = get_term_by( 'slug', $value, $taxonomy );
			$attributes[ str_replace( 'pa_', '', $taxonomy ) ] = $term ? $term->name : $value;
		}
		$data['variations'][] = array_filter(
			array(
				'attributes'    => $attributes,
				'regular_price' => $variation->get_regular_price(),
				'sale_price'    => $variation->get_sale_price(),
				'stock_status'  => $variation->get_stock_status(),
				'image'         => $variation->get_image_id( 'edit' ) ? tyche_export_image_name( $variation->get_image_id( 'edit' ) ) : '',
			)
		);
	}

	foreach ( get_comments( array( 'post_id' => $product->get_id(), 'type' => 'review', 'status' => 'approve' ) ) as $review ) {
		$data['reviews'][] = array(
			'author'  => $review->comment_author,
			'rating'  => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
			'content' => $review->comment_content,
		);
	}

	return array_filter(
		$data,
		function ( $value ) {
			return '' !== $value && array() !== $value && false !== $value;
		}
	);
}

/** Pages and posts, with their content turned into placeholders. */
function tyche_export_posts( $type ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => $type, 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order ID', 'order' => 'ASC' ) ) as $post ) {
		if ( in_array( $post->post_name, array( 'cart', 'checkout', 'my-account', 'shop', 'refund_returns', 'privacy-policy' ), true ) ) {
			continue;
		}
		$thumb = get_post_thumbnail_id( $post );
		$entry = array(
			'slug'     => $post->post_name,
			'title'    => $post->post_title,
			'content'  => tyche_export_placeholders( $post->post_content ),
			'excerpt'  => $post->post_excerpt,
			'template' => (string) get_post_meta( $post->ID, '_wp_page_template', true ),
			'image'    => $thumb ? tyche_export_image_name( $thumb ) : '',
		);
		if ( 'post' === $type ) {
			$entry['categories'] = wp_list_pluck( get_the_category( $post->ID ), 'slug' );
			// Days before the import, so a fresh store's journal is never dated 2026.
			$entry['days_ago']   = (int) round( ( time() - strtotime( $post->post_date_gmt ) ) / DAY_IN_SECONDS );
		}
		// Keep content even when empty: the posts page has none, and the
		// importer still has to create it.
		$out[] = array_filter(
			$entry,
			function ( $value, $key ) {
				return 'content' === $key || ( '' !== $value && array() !== $value );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}
	return $out;
}

/** Navigation menus, as their block markup with placeholders. */
function tyche_export_menus() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'numberposts' => -1, 'post_status' => 'publish' ) ) as $menu ) {
		$out[] = array(
			'slug'    => $menu->post_name,
			'title'   => $menu->post_title,
			'content' => tyche_export_placeholders( $menu->post_content ),
		);
	}
	return $out;
}

/** Template part changes, so a starter can pick a different header or footer. */
function tyche_export_template_parts() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'wp_template_part', 'numberposts' => -1, 'post_status' => 'publish' ) ) as $part ) {
		$out[] = array(
			'slug'    => $part->post_name,
			'area'    => implode( ',', wp_get_post_terms( $part->ID, 'wp_template_part_area', array( 'fields' => 'names' ) ) ),
			'content' => tyche_export_placeholders( $part->post_content ),
		);
	}
	return $out;
}

/** The settings a starter is allowed to carry. Never payments, tax or accounts. */
function tyche_export_settings() {
	$front   = (int) get_option( 'page_on_front' );
	$posts   = (int) get_option( 'page_for_posts' );
	$zone    = null;
	$free    = '';
	$flat    = '';
	$country = '';
	foreach ( WC_Shipping_Zones::get_zones() as $candidate ) {
		$zone = new WC_Shipping_Zone( $candidate['id'] );
		foreach ( $zone->get_shipping_methods() as $method ) {
			if ( 'free_shipping' === $method->id && 'min_amount' === $method->get_option( 'requires' ) ) {
				$free = $method->get_option( 'min_amount' );
			}
			if ( 'flat_rate' === $method->id ) {
				$flat = $method->get_option( 'cost' );
			}
		}
		foreach ( $candidate['zone_locations'] as $location ) {
			if ( 'country' === $location->type && ! $country ) {
				$country = $location->code;
			}
		}
		break;
	}

	return array_filter(
		array(
			'title'       => get_option( 'blogname' ),
			'tagline'     => get_option( 'blogdescription' ),
			'front_page'  => $front ? get_post( $front )->post_name : '',
			'posts_page'  => $posts ? get_post( $posts )->post_name : '',
			'currency'    => get_option( 'woocommerce_currency' ),
			'country'     => get_option( 'woocommerce_default_country' ),
			'shipping'    => array_filter( array( 'country' => $country, 'flat_rate' => $flat, 'free_over' => $free ) ),
		)
	);
}

/** The photographs the starter uses, with their alt text. */
function tyche_export_images() {
	$out = array();
	foreach ( tyche_export_attachments() as $id => $name ) {
		$out[] = array(
			'file' => $name,
			'alt'  => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
	usort( $out, function ( $a, $b ) {
		return strcmp( $a['file'], $b['file'] );
	} );
	return $out;
}

/* -------------------------------------------------------------------------
 * Write
 * ---------------------------------------------------------------------- */

$products = array();
foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
	$product = wc_get_product( $post );
	if ( $product ) {
		$products[] = tyche_export_product( $product );
	}
}

$style      = get_posts( array( 'post_type' => 'wp_global_styles', 'numberposts' => 1, 'post_status' => 'publish' ) );
$pages      = tyche_export_posts( 'page' );
$posts      = tyche_export_posts( 'post' );
$files      = array(
	'terms.json'    => tyche_export_terms(),
	'products.json' => $products,
	'pages.json'    => $pages,
	'posts.json'    => $posts,
	'menus.json'    => tyche_export_menus(),
	'parts.json'    => tyche_export_template_parts(),
	'images.json'   => tyche_export_images(),
);

$manifest = array(
	'schema'   => 1,
	'slug'     => $slug,
	'exported' => gmdate( 'c' ),
	'theme'    => array( 'slug' => 'tyche', 'style' => '' ),
	'settings' => tyche_export_settings(),
	'counts'   => array(
		'products' => count( $products ),
		'pages'    => count( $pages ),
		'posts'    => count( $posts ),
		'images'   => count( $files['images.json'] ),
	),
);

foreach ( $files as $name => $data ) {
	file_put_contents( "$out/$name", wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
}
file_put_contents( "$out/manifest.json", wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

WP_CLI::success(
	sprintf(
		'%s: %d products, %d pages, %d posts, %d images, %d menus%s',
		$slug,
		count( $products ),
		count( $pages ),
		count( $posts ),
		count( $files['images.json'] ),
		count( $files['menus.json'] ),
		$style ? '' : ' (no saved global styles)'
	)
);

<?php
/**
 * The Starter sites screen, under Appearance.
 *
 * The screen lists what the library offers, says plainly what an import will do
 * to this particular store, and runs the import a step at a time so the browser
 * can show progress instead of hanging on one long request.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the screen.
 */
function tyche_companion_starter_menu() {
	add_theme_page(
		__( 'Starter sites', 'tyche-companion' ),
		__( 'Starter sites', 'tyche-companion' ),
		'manage_woocommerce',
		'tyche-starter-sites',
		'tyche_companion_starter_screen'
	);
}
add_action( 'admin_menu', 'tyche_companion_starter_menu' );

/**
 * Scripts and styles, only on this screen.
 *
 * @param string $hook Screen hook.
 */
function tyche_companion_starter_assets( $hook ) {
	if ( 'appearance_page_tyche-starter-sites' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'tyche-companion-starters', TYCHE_COMPANION_URL . 'assets/css/starters.css', array(), TYCHE_COMPANION_VERSION );
	wp_enqueue_script( 'tyche-companion-starters', TYCHE_COMPANION_URL . 'assets/js/starters.js', array( 'wp-api-fetch' ), TYCHE_COMPANION_VERSION, true );

	wp_add_inline_script(
		'tyche-companion-starters',
		'window.tycheStarters = ' . wp_json_encode(
			array(
				'strings' => array(
					'importing' => __( 'Importing', 'tyche-companion' ),
					'done'      => __( 'Done. Opening your new store.', 'tyche-companion' ),
					'failed'    => __( 'The import stopped:', 'tyche-companion' ),
					'removing'  => __( 'Removing', 'tyche-companion' ),
					'removed'   => __( 'The starter content has been removed.', 'tyche-companion' ),
					'confirm'   => __( 'Remove everything this starter added: its products, pages, photographs and menu?', 'tyche-companion' ),
				),
				'home'    => home_url( '/' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'tyche_companion_starter_assets' );

/**
 * What this store already has, so the screen can warn before it is changed.
 *
 * @return array
 */
function tyche_companion_starter_store_state() {
	$products = (int) wp_count_posts( 'product' )->publish;
	$pages    = get_posts(
		array(
			'post_type'   => 'page',
			'numberposts' => 20,
			'fields'      => 'ids',
			'post_status' => 'publish',
			'exclude'     => array_filter(
				array(
					(int) wc_get_page_id( 'cart' ),
					(int) wc_get_page_id( 'checkout' ),
					(int) wc_get_page_id( 'myaccount' ),
					(int) wc_get_page_id( 'shop' ),
					(int) get_option( 'wp_page_for_privacy_policy' ),
				)
			),
		)
	);

	return array(
		'products' => $products,
		'pages'    => count( $pages ),
		'busy'     => $products > 0 || count( $pages ) > 2,
	);
}

/**
 * The screen itself.
 */
function tyche_companion_starter_screen() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$catalogue = tyche_companion_starter_catalogue( isset( $_GET['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$record    = tyche_companion_imported();
	$store     = tyche_companion_starter_store_state();
	?>
	<div class="wrap tyche-starters">
		<h1><?php esc_html_e( 'Starter sites', 'tyche-companion' ); ?></h1>
		<p class="tyche-starters__lede">
			<?php esc_html_e( 'A starter is a complete store: products, pages, photographs and a look. Import the whole thing, or just the look if you already sell something.', 'tyche-companion' ); ?>
		</p>

		<?php if ( $record['slug'] ) : ?>
			<div class="notice notice-info tyche-starters__current">
				<p>
					<?php
					printf(
						/* translators: 1: starter name, 2: how it was imported. */
						esc_html__( '%1$s is imported on this store (%2$s).', 'tyche-companion' ),
						'<strong>' . esc_html( $record['name'] ) . '</strong>',
						'look' === $record['mode'] ? esc_html__( 'look only', 'tyche-companion' ) : esc_html__( 'full store', 'tyche-companion' )
					);
					?>
					<button type="button" class="button-link tyche-starters__remove"><?php esc_html_e( 'Remove starter content', 'tyche-companion' ); ?></button>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( is_wp_error( $catalogue ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $catalogue->get_error_message() ); ?></p></div>
		<?php else : ?>
			<div class="tyche-starters__grid">
				<?php foreach ( $catalogue as $starter ) : ?>
					<?php tyche_companion_starter_card( $starter, $store, $record ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="tyche-starters__progress" hidden>
			<div class="tyche-starters__progress-inner">
				<p class="tyche-starters__progress-text"></p>
				<div class="tyche-starters__bar"><span></span></div>
				<p class="tyche-starters__progress-note"><?php esc_html_e( 'Leave this page open until it finishes.', 'tyche-companion' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * One starter's card.
 *
 * @param array $starter Catalogue entry.
 * @param array $store   What this store already has.
 * @param array $record  What has been imported.
 */
function tyche_companion_starter_card( $starter, $store, $record ) {
	$is_pro     = 'pro' === $starter['tier'];
	$imported   = $record['slug'] === $starter['slug'];
	$thumbnail  = $starter['thumbnail'] ? tyche_companion_starter_asset_url( $starter['thumbnail'] ) : '';
	$counts     = wp_parse_args( $starter['counts'], array( 'products' => 0, 'pages' => 0, 'posts' => 0 ) );
	?>
	<div class="tyche-starter <?php echo $is_pro ? 'is-pro' : ''; ?>" data-slug="<?php echo esc_attr( $starter['slug'] ); ?>">
		<div class="tyche-starter__shot">
			<?php if ( $thumbnail ) : ?>
				<img src="<?php echo esc_url( $thumbnail ); ?>" alt="" loading="lazy">
			<?php else : ?>
				<span class="tyche-starter__noshot"><?php echo esc_html( $starter['name'] ); ?></span>
			<?php endif; ?>
			<?php if ( $is_pro ) : ?>
				<span class="tyche-starter__tier"><?php esc_html_e( 'Pro', 'tyche-companion' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="tyche-starter__body">
			<h2><?php echo esc_html( $starter['name'] ); ?></h2>
			<p class="tyche-starter__niche"><?php echo esc_html( $starter['niche'] ); ?></p>
			<p class="tyche-starter__counts">
				<?php
				printf(
					/* translators: 1: number of products, 2: number of pages, 3: number of posts. */
					esc_html__( '%1$d products · %2$d pages · %3$d journal posts', 'tyche-companion' ),
					(int) $counts['products'],
					(int) $counts['pages'],
					(int) $counts['posts']
				);
				?>
			</p>

			<div class="tyche-starter__actions">
				<?php if ( $is_pro ) : ?>
					<a class="button button-primary" href="https://colorlib.com/wp/themes/tyche/" target="_blank" rel="noopener"><?php esc_html_e( 'Get Tyche Pro', 'tyche-companion' ); ?></a>
				<?php else : ?>
					<button type="button" class="button button-primary tyche-starter__import" data-mode="full">
						<?php echo $store['busy'] ? esc_html__( 'Import full store', 'tyche-companion' ) : esc_html__( 'Import', 'tyche-companion' ); ?>
					</button>
					<button type="button" class="button tyche-starter__import" data-mode="look"><?php esc_html_e( 'Look only', 'tyche-companion' ); ?></button>
				<?php endif; ?>
				<?php if ( $starter['preview'] ) : ?>
					<a class="tyche-starter__preview" href="<?php echo esc_url( $starter['preview'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'tyche-companion' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( $imported ) : ?>
				<p class="tyche-starter__state"><?php esc_html_e( 'Imported on this store', 'tyche-companion' ); ?></p>
			<?php elseif ( ! $is_pro && $store['busy'] ) : ?>
				<p class="tyche-starter__warning">
					<?php esc_html_e( 'This store already sells something. A full import adds the starter beside what you have, and keeps your home page: "Look only" changes the design and nothing else.', 'tyche-companion' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * REST
 * ---------------------------------------------------------------------- */

/**
 * Routes the screen talks to.
 */
function tyche_companion_starter_routes() {
	$permission = function () {
		return current_user_can( 'manage_woocommerce' );
	};

	register_rest_route(
		'tyche-companion/v1',
		'/import',
		array(
			'methods'             => 'POST',
			'permission_callback' => $permission,
			'callback'            => 'tyche_companion_starter_rest_start',
			'args'                => array(
				'slug' => array( 'required' => true, 'type' => 'string' ),
				'mode' => array( 'type' => 'string', 'default' => 'full', 'enum' => array( 'full', 'look' ) ),
			),
		)
	);

	register_rest_route(
		'tyche-companion/v1',
		'/import/step',
		array(
			'methods'             => 'POST',
			'permission_callback' => $permission,
			'callback'            => 'tyche_companion_starter_rest_step',
		)
	);

	// Only while the library is a directory on this machine: a browser cannot
	// load a file path, so the plugin passes the image through.
	if ( tyche_companion_starter_library_is_local() ) {
		register_rest_route(
			'tyche-companion/v1',
			'/starter-asset',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => 'tyche_companion_starter_rest_asset',
				'args'                => array( 'path' => array( 'required' => true, 'type' => 'string' ) ),
			)
		);
	}

	register_rest_route(
		'tyche-companion/v1',
		'/import/remove',
		array(
			'methods'             => 'POST',
			'permission_callback' => $permission,
			'callback'            => 'tyche_companion_starter_rest_remove',
		)
	);
}
add_action( 'rest_api_init', 'tyche_companion_starter_routes' );

/**
 * Begin an import.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function tyche_companion_starter_rest_start( $request ) {
	$state = tyche_companion_import_start( $request->get_param( 'slug' ), $request->get_param( 'mode' ) );
	if ( is_wp_error( $state ) ) {
		return $state;
	}

	return rest_ensure_response(
		array(
			'progress' => 0,
			'message'  => __( 'Starting', 'tyche-companion' ),
			'complete' => false,
		)
	);
}

/**
 * Run one step.
 *
 * @return WP_REST_Response|WP_Error
 */
function tyche_companion_starter_rest_step() {
	$state = tyche_companion_import_step();
	if ( is_wp_error( $state ) ) {
		return $state;
	}

	return rest_ensure_response(
		array(
			'progress' => $state['progress'],
			'message'  => $state['message'],
			'complete' => (bool) $state['complete'],
		)
	);
}

/**
 * Remove what the last import added.
 *
 * @return WP_REST_Response
 */
function tyche_companion_starter_rest_remove() {
	$result = tyche_companion_starter_remove();

	return rest_ensure_response( $result );
}

/**
 * Serve a package image while the library is a local directory.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function tyche_companion_starter_rest_asset( $request ) {
	$path = ltrim( (string) $request->get_param( 'path' ), '/' );
	if ( str_contains( $path, '..' ) || ! preg_match( '#^[a-z0-9._/-]+\.(webp|png|jpe?g)$#i', $path ) ) {
		return new WP_Error( 'tyche_starter_asset', __( 'Not an image in the library.', 'tyche-companion' ), array( 'status' => 400 ) );
	}

	$file = tyche_companion_starter_library() . $path;
	if ( ! is_readable( $file ) ) {
		return new WP_Error( 'tyche_starter_asset', __( 'No such image.', 'tyche-companion' ), array( 'status' => 404 ) );
	}

	$type = wp_check_filetype( $file );
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Cache-Control: max-age=300' );
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}

<?php
/**
 * Plugin Name:       Tyche Companion
 * Plugin URI:        https://colorlib.com/wp/themes/tyche/
 * Description:       Store features for the Tyche theme: a free-shipping progress bar, a sticky add-to-cart bar, sale and stock badges, and a second product photo on hover. Built as blocks, so they work in any block theme.
 * Version:           0.2.0-dev.3
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Colorlib
 * Author URI:        https://colorlib.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tyche-companion
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

define( 'TYCHE_COMPANION_VERSION', '0.2.0-dev.3' );
define( 'TYCHE_COMPANION_DIR', plugin_dir_path( __FILE__ ) );
define( 'TYCHE_COMPANION_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the features once WooCommerce is loaded.
 *
 * `Requires Plugins` keeps this plugin from being activated without WooCommerce,
 * but WooCommerce can still be deactivated afterwards, so every feature checks.
 */
function tyche_companion_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require TYCHE_COMPANION_DIR . 'includes/settings.php';
	require TYCHE_COMPANION_DIR . 'includes/free-shipping.php';
	require TYCHE_COMPANION_DIR . 'includes/badges.php';
	require TYCHE_COMPANION_DIR . 'includes/hover-image.php';
	require TYCHE_COMPANION_DIR . 'includes/sticky-add-to-cart.php';
	require TYCHE_COMPANION_DIR . 'includes/blocks.php';
	require TYCHE_COMPANION_DIR . 'includes/block-hooks.php';
	require TYCHE_COMPANION_DIR . 'includes/lean-scripts.php';
	require TYCHE_COMPANION_DIR . 'includes/starters.php';
	require TYCHE_COMPANION_DIR . 'includes/starter-import.php';
	require TYCHE_COMPANION_DIR . 'includes/starter-remove.php';
	require TYCHE_COMPANION_DIR . 'includes/starter-cli.php';

	// Always loaded, not only in the admin: the screen's REST routes have to be
	// registered on the REST request too, which is not an admin request.
	require TYCHE_COMPANION_DIR . 'includes/starter-admin.php';
}
add_action( 'plugins_loaded', 'tyche_companion_boot', 20 );

/**
 * Whether the plugin's features are running. The theme asks before printing
 * the plugin's blocks into its templates.
 *
 * @return bool
 */
function tyche_companion_is_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'tyche_companion_register_blocks' );
}

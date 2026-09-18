<?php
/**
 * The globals edit.js uses, so WordPress loads them first.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
	'version'      => defined( 'TYCHE_COMPANION_VERSION' ) ? TYCHE_COMPANION_VERSION : '0.2.0',
);

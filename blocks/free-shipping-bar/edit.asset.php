<?php
/**
 * The globals edit.js uses, so WordPress loads them first.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
	'version'      => defined( 'TYCHE_COMPANION_VERSION' ) ? TYCHE_COMPANION_VERSION : '0.2.0',
);

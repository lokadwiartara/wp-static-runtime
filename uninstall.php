<?php
/**
 * WordPress only runs uninstall.php from the plugin root (same folder as the main plugin file).
 * Delegates to free/uninstall.php where cleanup logic lives.
 *
 * @package WP_Static_Runtime
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/free/uninstall.php';

<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

class WSR_Uninstall {
	public static function remove_directory( $dir ) {
		if ( ! is_dir( $dir ) ) return;
		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			is_dir( $path ) ? self::remove_directory( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}

global $wpdb;

// Remove database tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsr_cache_index" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsr_dependency" );

// Remove all options
delete_option( 'wsr_settings' );
delete_option( 'wsr_version' );

// Remove advanced-cache.php
$advanced_cache = WP_CONTENT_DIR . '/advanced-cache.php';
if ( file_exists( $advanced_cache ) ) {
	$content = file_get_contents( $advanced_cache );
	if ( $content !== false && strpos( $content, 'WP Static Runtime' ) !== false ) {
		unlink( $advanced_cache );
	}
}

// Remove WP_CACHE from wp-config.php (must match Installer::enable_wp_cache comment)
$config_file = ABSPATH . 'wp-config.php';
if ( file_exists( $config_file ) ) {
	$config = file_get_contents( $config_file );
	if ( $config !== false ) {
		$config = preg_replace(
			"/define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\)\s*;\s*\/\/\s*Added by WP Static Runtime\n?/",
			'',
			$config
		);
		file_put_contents( $config_file, $config );
	}
}

// Remove cache directory
$cache_dir = WP_CONTENT_DIR . '/wsr-cache/';
if ( is_dir( $cache_dir ) ) {
	WSR_Uninstall::remove_directory( $cache_dir );
}

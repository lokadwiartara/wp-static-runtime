<?php
/**
 * PHPUnit bootstrap — stubs for WordPress functions, then loads WSR classes.
 * No WordPress installation required.
 */

// ── WordPress constants ───────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) )       define( 'ABSPATH',       sys_get_temp_dir() . '/wsr-abspath/' );
if ( ! defined( 'WP_CONTENT_DIR' ) ) define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wsr-test-content' );
if ( ! defined( 'WSR_CACHE_DIR' ) )  define( 'WSR_CACHE_DIR',  WP_CONTENT_DIR . '/wsr-cache/' );
if ( ! defined( 'DIRECTORY_SEPARATOR' ) ) define( 'DIRECTORY_SEPARATOR', '/' );

// Create cache dir for tests that write real files
if ( ! is_dir( WSR_CACHE_DIR ) ) mkdir( WSR_CACHE_DIR, 0755, true );

// ── WordPress function stubs ──────────────────────────────────────────────────

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $str ) { return rtrim( (string) $str, '/\\' ) . '/'; }
}
if ( ! function_exists( 'untrailingslashit' ) ) {
    function untrailingslashit( $str ) { return rtrim( (string) $str, '/\\' ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ) ?: ''; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    // passthrough — no filter infrastructure in tests
    function apply_filters( $tag, $value, ...$args ) { return $value; }
}
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) { return 'http://localhost:8080' . $path; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0755, true ); }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) { return date( 'Y-m-d H:i:s' ); }
}
if ( ! function_exists( 'pathinfo' ) ) {
    // Use native — already exists in PHP, listed here for documentation only.
}

// ── Load WSR classes ──────────────────────────────────────────────────────────

$wsr_free = dirname( __DIR__ ) . '/free/';

// Constants must come first (defines WSR\Constants::defaults())
require_once $wsr_free . 'bootstrap/constants.php';

// Core classes
require_once $wsr_free . 'core/host.php';
require_once $wsr_free . 'core/request.php';
require_once $wsr_free . 'core/cache_reader.php';
require_once $wsr_free . 'core/cache_writer.php';

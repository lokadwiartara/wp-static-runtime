<?php
// phpcs:disable
/**
 * PHPUnit bootstrap — stubs for WordPress functions, then loads WSR classes.
 * No WordPress installation required.
 */

// ── WordPress constants ───────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH',       sys_get_temp_dir() . '/wsr-abspath/' );
}
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
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $val ) { return is_array( $val ) ? array_map( 'wp_unslash', $val ) : stripslashes( $val ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $val ) { return is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : trim( strip_tags( $val ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $val ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $val ) ); }
}
if ( ! function_exists( 'map_deep' ) ) {
    function map_deep( $val, $callback ) {
        if ( is_array( $val ) ) {
            foreach ( $val as $k => $v ) {
                $val[ $k ] = map_deep( $v, $callback );
            }
        } elseif ( is_object( $val ) ) {
            foreach ( get_object_vars( $val ) as $k => $v ) {
                $val->$k = map_deep( $v, $callback );
            }
        } else {
            $val = call_user_func( $callback, $val );
        }
        return $val;
    }
}
if ( ! function_exists( 'esc_sql' ) ) {
    function esc_sql( $val ) { return is_array( $val ) ? array_map( 'esc_sql', $val ) : addslashes( $val ); }
}

// ── Load WSR classes ──────────────────────────────────────────────────────────

$wsr_root = dirname( __DIR__ ) . '/';

// Constants must come first (defines WSR\Constants::defaults())
require_once $wsr_root . 'bootstrap/constants.php';

// Core classes
require_once $wsr_root . 'core/host.php';
require_once $wsr_root . 'core/request.php';
require_once $wsr_root . 'core/cache_reader.php';
require_once $wsr_root . 'core/cache_writer.php';

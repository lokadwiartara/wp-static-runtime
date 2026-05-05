<?php
/**
 * WP Static Runtime — Free Core
 *
 * File ini adalah kode inti yang di-bundle di dalam plugin premium.
 * Tidak boleh diaktifkan sebagai plugin tersendiri dari folder ini.
 * Di-load via: wp-static-runtime/wp-static-runtime.php (premium entry point)
 *
 * @package WP_Static_Runtime
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WSR_VERSION',   '1.2.5' );
// WSR_FILE harus menunjuk ke entry point premium (root plugin file)
// agar register_activation_hook() dan plugin_dir_path() bekerja dengan benar.
if ( ! defined( 'WSR_FILE' ) ) define( 'WSR_FILE', dirname( __DIR__ ) . '/wp-static-runtime.php' );
// WSR_PATH menunjuk ke folder free/ (tempat semua kode inti berada)
define( 'WSR_PATH',      plugin_dir_path( __FILE__ ) );
// WSR_URL menunjuk ke URL subfolder free/ agar asset (CSS/JS) dimuat dengan benar
define( 'WSR_URL',       plugins_url( 'free/', WSR_FILE ) );
define( 'WSR_CACHE_DIR', WP_CONTENT_DIR . '/wsr-cache/' );
define( 'WSR_PREMIUM',   false );

// ── i18n ─────────────────────────────────────────────────────────────────────
add_action( 'init', function() {
    load_plugin_textdomain(
        'wp-static-runtime',
        false,
        dirname( plugin_basename( WSR_FILE ) ) . '/languages'
    );
}, 1 );

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once WSR_PATH . 'bootstrap/app.php';

WSR\App::init();

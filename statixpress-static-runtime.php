<?php
/**
 * Plugin Name:       StatixPress Static Runtime
 * Plugin URI:        https://statixpress.site
 * Description:       Static HTML caching runtime for WordPress with CDN, ISR, Redis, Memcached, LiteSpeed, and PageSpeed Optimizer.
 * Version:           1.3.1
 * Author:            Loka Dwiartara
 * Author URI:        https://ilmuwebsite.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       statixpress-static-runtime
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WSR_VERSION',   '1.3.1' );
define( 'WSR_FILE',      __FILE__ );
define( 'WSR_PATH',      plugin_dir_path( __FILE__ ) );
define( 'WSR_URL',       plugins_url( '/', WSR_FILE ) );
define( 'WSR_CACHE_DIR', WP_CONTENT_DIR . '/wsr-cache/' );
define( 'WSR_PREMIUM',   true );

// Premium active flags to unlock all features
define( 'WSR_PREMIUM_ACTIVE', true );
define( 'WSR_PREMIUM_FILE',   __FILE__ );
define( 'WSR_PREMIUM_PATH',   plugin_dir_path( __FILE__ ) );
define( 'WSR_PREMIUM_URL',    plugins_url( '/', WSR_FILE ) );
define( 'WSR_PREMIUM_VER',    WSR_VERSION );

// ── i18n ─────────────────────────────────────────────────────────────────────
add_action( 'init', function() {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
    load_plugin_textdomain(
        'statixpress-static-runtime',
        false,
        dirname( plugin_basename( WSR_FILE ) ) . '/languages'
    );
}, 1 );

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once WSR_PATH . 'bootstrap/app.php';

WSR\App::init();

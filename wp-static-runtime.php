<?php
/**
 * Plugin Name:       StatixPress Wordpress Static Runtime
 * Plugin URI:        https://statixpress.site
 * Description:       Static HTML caching engine — ISR, Smart Dependency Graph, CDN Purge, Redis, Memcached, LiteSpeed, PageSpeed Optimizer.
 * Version:           1.3.0
 * Author:            Loka Dwiartara
 * Author URI:        https://ilmuwebsite.com
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-static-runtime
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Pastikan WSR_FILE = path file utama yang WordPress load (bukan path hasil concat),
// agar register_activation_hook(), plugin_basename(), dan plugins_url() konsisten.
if ( ! defined( 'WSR_FILE' ) ) {
	define( 'WSR_FILE', __FILE__ );
}

// ── Bootstrap free-plugin core (bundled) ──────────────────────────────────────
if ( ! defined( 'WSR_VERSION' ) ) {
    $wsr_free_bootstrap = plugin_dir_path( __FILE__ ) . 'free/wp-static-runtime.php';
    if ( file_exists( $wsr_free_bootstrap ) ) {
        require_once $wsr_free_bootstrap;
    } else {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WP Static Runtime</strong>: ' . esc_html__( 'Folder free/ not found.', 'wp-static-runtime' ) . '</p></div>';
        } );
        return;
    }
}

// ── Constants ─────────────────────────────────────────────────────────────────
if ( ! defined( 'WSR_PREMIUM_ACTIVE' ) ) define( 'WSR_PREMIUM_ACTIVE', true  );
if ( ! defined( 'WSR_PREMIUM_FILE'   ) ) define( 'WSR_PREMIUM_FILE',   __FILE__ );
if ( ! defined( 'WSR_PREMIUM_PATH'   ) ) define( 'WSR_PREMIUM_PATH',   plugin_dir_path( __FILE__ ) );
if ( ! defined( 'WSR_PREMIUM_URL'    ) ) define( 'WSR_PREMIUM_URL',    plugin_dir_url(  __FILE__ ) );
if ( ! defined( 'WSR_PREMIUM_VER'    ) ) define( 'WSR_PREMIUM_VER',    '1.3.0' );

// ── Boot All Features ─────────────────────────────────────────────────────────
add_action( 'plugins_loaded', [ 'WSR_Premium_Boot', 'init' ], 20 );

class WSR_Premium_Boot {

    public static function init(): void {
        // Ensure free core classes are available before loading premium files
        if ( ! class_exists( 'WSR\\Dependency_Graph' ) || ! class_exists( 'WSR\\Request' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p><strong>WP Static Runtime</strong>: ' . esc_html__( 'Core classes not found. Make sure the free/ folder is intact.', 'wp-static-runtime' ) . '</p></div>';
            } );
            return;
        }

        // Load all modules
        require_once WSR_PREMIUM_PATH . 'premium/license/license_guard.php';
        require_once WSR_PREMIUM_PATH . 'premium/isr/incremental_static_regeneration.php';
        require_once WSR_PREMIUM_PATH . 'premium/cdn/cdn_manager.php';
        require_once WSR_PREMIUM_PATH . 'premium/cdn/cloudflare.php';
        require_once WSR_PREMIUM_PATH . 'premium/cdn/bunny.php';
        require_once WSR_PREMIUM_PATH . 'premium/cdn/fastly.php';
        require_once WSR_PREMIUM_PATH . 'premium/redis/redis_index.php';
        require_once WSR_PREMIUM_PATH . 'premium/smart_dependency.php';
        require_once WSR_PREMIUM_PATH . 'premium/admin_premium.php';
        require_once WSR_PREMIUM_PATH . 'premium/updater.php';

        // Boot auto-updater
        \WSR\Premium\Updater::init();

        // Boot all features — no license required
        $settings = get_option( 'wsr_settings', [] );

        // Smart Dependency (override free)
        if ( ! isset( $settings['smart_dependency'] ) || ! empty( $settings['smart_dependency'] ) ) {
            add_filter( 'wsr_dependency_graph_class', function () {
                return 'WSR\\Premium\\Smart_Dependency';
            } );
        }

        \WSR\Premium\CDN\CDN_Manager::boot();
        \WSR\Premium\ISR::boot();
        \WSR\Premium\Redis_Index::boot();

        // Memcached integration
        require_once WSR_PREMIUM_PATH . 'premium/memcached/memcached_index.php';
        \WSR\Premium\Memcached_Index::boot();

        // LiteSpeed integration
        require_once WSR_PREMIUM_PATH . 'premium/server/litespeed.php';
        \WSR\Premium\Server\LiteSpeed::boot();

        // HTML Optimizer (PageSpeed)
        require_once WSR_PREMIUM_PATH . 'premium/optimizer/html_optimizer.php';
        \WSR\Premium\Optimizer\HTML_Optimizer::boot();

        if ( is_admin() ) {
            new \WSR\Premium\Admin_Premium();

            // AJAX: Purge asset cache
            add_action( 'wp_ajax_wsr_purge_asset_cache', function () {
                check_ajax_referer( 'wsr_nonce', 'nonce' );
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die( 'Unauthorized', 403 );
                }

                \WSR\Optimizer\Asset_Optimizer::purge_asset_cache();
                wp_send_json_success( [ 'message' => __( 'Asset cache cleared', 'wp-static-runtime' ) ] );
            } );

            // AJAX: Generate critical CSS with fallback to stylesheet extraction
            add_action( 'wp_ajax_wsr_generate_critical_css', function () {
                check_ajax_referer( 'wsr_nonce', 'nonce' );
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die( 'Unauthorized', 403 );
                }

                $url = isset( $_POST['url'] ) ? sanitize_url( wp_unslash( $_POST['url'] ) ) : home_url( '/' );

                $css    = '';
                $method = '';

                // Try self-request with shorter timeout (8 seconds instead of 15)
                $response = wp_remote_get( $url, [
                    'timeout'    => 8,
                    'sslverify'  => false,
                    'user-agent' => 'WP Static Runtime Critical CSS Generator',
                ] );

                // If self-request succeeds, use HTML-based generation
                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $html = wp_remote_retrieve_body( $response );
                    if ( ! empty( $html ) ) {
                        $css = \WSR\Optimizer\Critical_CSS_Generator::generate( $html, $url );
                        $method = 'html-parse';
                    }
                }

                // Fallback: If self-request fails/times out, generate from registered stylesheets on disk
                if ( empty( $css ) ) {
                    $css = \WSR\Optimizer\Critical_CSS_Generator::generate_from_registered_styles();
                    $method = 'stylesheet-fallback';
                }

                // Return error if both methods failed
                if ( empty( $css ) ) {
                    wp_send_json_error( [
                        'message' => __( 'Unable to generate critical CSS. No styles found via HTML fetch or stylesheet extraction.', 'wp-static-runtime' ),
                    ] );
                    return;
                }

                // Save to settings
                $settings = get_option( 'wsr_settings', [] );
                $settings['opt_critical_css_content'] = $css;
                $settings['opt_critical_css'] = true;
                update_option( 'wsr_settings', $settings );

                wp_send_json_success( [
                    'css'     => $css,
                    'message' => __( 'Critical CSS generated and saved', 'wp-static-runtime' ) . ' (' . $method . ')',
                ] );
            } );
        }

        do_action( 'wsr_premium_loaded' );
    }
}

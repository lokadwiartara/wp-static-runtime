<?php
/**
 * Plugin Name:       WP Static Runtime
 * Plugin URI:        https://statixpress.site
 * Description:       Static HTML caching engine for WordPress — full static cache, smart crawler, dependency graph, and Apache/Nginx rules. Upgrade to Premium for ISR, CDN purge, Redis, and more.
 * Version:           1.2.4
 * Author:            WP Static Runtime
 * Author URI:        https://statixpress.site
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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

// ── Bootstrap free core ───────────────────────────────────────────────────────
if ( ! defined( 'WSR_VERSION' ) ) {
    $wsr_free_bootstrap = plugin_dir_path( __FILE__ ) . 'free/wp-static-runtime.php';
    if ( file_exists( $wsr_free_bootstrap ) ) {
        require_once $wsr_free_bootstrap;
    } else {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WP Static Runtime</strong>: '
                . esc_html__( 'The free/ folder is missing. Please re-install the plugin.', 'wp-static-runtime' )
                . '</p></div>';
        } );
        return;
    }
}

// ── Boot Premium UI shell (admin only — no functional code, no license) ───────
add_action( 'plugins_loaded', function () {
    if ( ! is_admin() ) return;
    if ( ! class_exists( 'WSR\Dependency_Graph' ) ) return;

    require_once plugin_dir_path( __FILE__ ) . 'premium-ui/admin_ui.php';
    new WSR_Premium_UI();
}, 25 );

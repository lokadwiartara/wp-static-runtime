<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main application bootstrap.
 * Registers all core services and hooks.
 */
class App {

    /** @var array Registered services */
    private static $services = [];

    /**
     * Boot the plugin.
     */
    public static function init() {
        require_once WSR_PATH . 'bootstrap/loader.php';
        require_once WSR_PATH . 'bootstrap/constants.php';

        Loader::load_all();

        // Register activation / deactivation hooks
        register_activation_hook(   WSR_FILE, [ __CLASS__, 'on_activate'   ] );
        register_deactivation_hook( WSR_FILE, [ __CLASS__, 'on_deactivate' ] );

        // Priority 5 — after core WP, before most plugins at priority 10
        add_action( 'plugins_loaded', [ __CLASS__, 'boot'       ], 5  );
        add_action( 'plugins_loaded', [ __CLASS__, 'boot_admin' ], 10 );
    }

    /**
     * Plugin activation — Installer already loaded by Loader::load_all().
     */
    public static function on_activate() {
        Installer::run();
    }

    /**
     * Plugin deactivation.
     */
    public static function on_deactivate() {
        Installer::teardown();
    }

    /**
     * Boot front-end and shared services.
     * Called at plugins_loaded priority 5.
     */
    public static function boot() {
        // Register cache invalidation hooks (safe to register anywhere)
        \WSR\Cache_Cleaner::register_hooks();

        // Filter Dependency Graph class mapping to use Smart Dependency Graph
        add_filter( 'wsr_dependency_graph_class', function() {
            return 'WSR\\Premium\\Smart_Dependency';
        } );

        // Load Asset Optimizer (Cache & Minify CSS/JS)
        require_once WSR_PATH . 'optimizer/asset_optimizer.php';
        require_once WSR_PATH . 'optimizer/critical_css_generator.php';

        // Load integrations — each checks internally if its parent plugin exists
        new \WSR\Integrations\Elementor();
        new \WSR\Integrations\WooCommerce();
        \WSR\Integrations\Nonce_Refresh::init();

        // Boot Advanced features
        \WSR\Premium\CDN\CDN_Manager::boot();
        \WSR\Premium\ISR::boot();
        \WSR\Premium\Redis_Index::boot();
        \WSR\Premium\Memcached_Index::boot();
        \WSR\Premium\Server\LiteSpeed::boot();
        \WSR\Premium\Optimizer\HTML_Optimizer::boot();

        // Register output buffer for front-end non-special requests only
        if (
            ! is_admin() &&
            ! ( defined( 'DOING_AJAX'   ) && DOING_AJAX   ) &&
            ! ( defined( 'DOING_CRON'   ) && DOING_CRON   ) &&
            ! ( defined( 'REST_REQUEST' ) && REST_REQUEST  ) &&
            ! ( defined( 'WP_CLI'       ) && WP_CLI        )
        ) {
            new \WSR\Runtime\Output_Buffer();
        }

        // Crawler cron — scheduled cron + ISR is now standard
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        if ( ! empty( $settings['crawler_enabled'] ) ) {
            if ( ! wp_next_scheduled( 'wsr_crawl_event' ) ) {
                wp_schedule_event( time(), 'hourly', 'wsr_crawl_event' );
            }
            add_action( 'wsr_crawl_event', [ \WSR\Crawler\Crawler::class, 'run' ] );
        } else {
            wp_clear_scheduled_hook( 'wsr_crawl_event' );
        }

        // Defer server rule generation to 'init' so is_admin() is fully reliable
        add_action( 'init', [ __CLASS__, 'maybe_write_server_rules' ], 1 );
    }

    /**
     * Write Apache / Nginx rules — deferred to 'init' hook.
     */
    public static function maybe_write_server_rules() {
        if ( ! is_admin() ) return;
        \WSR\Server\Apache::maybe_write_rules();
        \WSR\Server\Nginx::maybe_write_rules();
    }

    /**
     * Boot admin UI — called at plugins_loaded priority 10.
     */
    public static function boot_admin() {
        if ( ! is_admin() ) return;
        new \WSR\Admin\Menu();
        new \WSR\Premium\Admin_Premium();
    }

    /**
     * Register a service in the container.
     *
     * @param string $key
     * @param mixed  $instance
     */
    public static function bind( $key, $instance ) {
        self::$services[ $key ] = $instance;
    }

    /**
     * Resolve a service.
     *
     * @param  string $key
     * @return mixed|null
     */
    public static function make( $key ) {
        return isset( self::$services[ $key ] ) ? self::$services[ $key ] : null;
    }
}

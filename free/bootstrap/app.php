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

        // Load integrations — each checks internally if its parent plugin exists
        new \WSR\Integrations\Elementor();
        new \WSR\Integrations\WooCommerce();
        \WSR\Integrations\Nonce_Refresh::init();

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

        // Crawler cron — premium only
        if ( defined( 'WSR_PREMIUM_ACTIVE' ) ) {
            $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
            if ( ! empty( $settings['crawler_enabled'] ) ) {
                if ( ! wp_next_scheduled( 'wsr_crawl_event' ) ) {
                    wp_schedule_event( time(), 'hourly', 'wsr_crawl_event' );
                }
                add_action( 'wsr_crawl_event', [ \WSR\Crawler\Crawler::class, 'run' ] );
            } else {
                wp_clear_scheduled_hook( 'wsr_crawl_event' );
            }
        } else {
            // Free: clear any scheduled cron hook (migration from older versions)
            wp_clear_scheduled_hook( 'wsr_crawl_event' );
        }

        // Defer server rule generation to 'init' so is_admin() is fully reliable
        add_action( 'init', [ __CLASS__, 'maybe_write_server_rules' ], 1 );

        // Free: show flush + crawler reminder notice after publish
        if ( ! defined( 'WSR_PREMIUM_ACTIVE' ) ) {
            add_action( 'admin_notices', [ __CLASS__, 'notice_published' ] );
        }
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
     * Tampilkan notice himbauan setelah post di-publish (free only).
     */
    public static function notice_published(): void {
        if ( ! get_transient( 'wsr_published_notice' ) ) return;
        delete_transient( 'wsr_published_notice' );

        $flush_url   = wp_nonce_url( admin_url( 'admin-post.php?action=wsr_flush_all' ), 'wsr_flush_all' );
        $crawler_url = admin_url( 'admin.php?page=wsr-crawler' );
        $premium_url = 'https://statixpress.site/premium';
        ?>
        <div class="notice notice-warning is-dismissible" style="border-left-color:#f59e0b;padding:12px 16px;">
            <p style="margin:0 0 6px;font-size:13px;">
                <strong>⚡ WP Static Runtime:</strong>
                <?php esc_html_e( 'New content published. Cache for related pages has been cleared automatically.', 'wp-static-runtime' ); ?>
            </p>
            <p style="margin:0 0 10px;font-size:13px;">
                <?php esc_html_e( 'To rebuild the cache with the latest content, follow these steps:', 'wp-static-runtime' ); ?>
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a href="<?php echo esc_url( $flush_url ); ?>" class="button button-primary" style="background:#f59e0b;border-color:#d97706;">
                    🗑️ <?php esc_html_e( '1. Flush All Cache', 'wp-static-runtime' ); ?>
                </a>
                <a href="<?php echo esc_url( $crawler_url ); ?>" class="button">
                    🕷️ <?php esc_html_e( '2. Start Crawler', 'wp-static-runtime' ); ?>
                </a>
                <span style="font-size:12px;color:#6b7280;margin-left:4px;">— <?php esc_html_e( 'or', 'wp-static-runtime' ); ?> —</span>
                <a href="<?php echo esc_url( $premium_url ); ?>" target="_blank" class="button" style="background:#7c3aed;border-color:#6d28d9;color:#fff;">
                    ⚡ <?php esc_html_e( 'Upgrade to Premium', 'wp-static-runtime' ); ?>
                </a>
                <span style="font-size:12px;color:#6b7280;"><?php esc_html_e( 'for automatic ISR with zero downtime', 'wp-static-runtime' ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Boot admin UI — called at plugins_loaded priority 10.
     */
    public static function boot_admin() {
        if ( ! is_admin() ) return;
        new \WSR\Admin\Menu();
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

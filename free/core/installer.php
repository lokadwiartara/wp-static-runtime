<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Installer — runs on plugin activation to set up tables, directories, and wp-config.
 */
class Installer {

    /**
     * Run on plugin activation.
     */
    public static function run() {
        self::create_tables();
        self::create_directories();
        self::write_advanced_cache();
        self::enable_wp_cache();
        self::set_default_settings();

        update_option( 'wsr_version', WSR_VERSION );

        do_action( 'wsr_activated' );
    }

    /**
     * Teardown on plugin deactivation (keeps cache intact).
     */
    public static function teardown() {
        // Remove advanced-cache.php only if we own it
        $ac = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( file_exists( $ac ) ) {
            $content = file_get_contents( $ac );
            if ( $content !== false && strpos( $content, 'WP Static Runtime' ) !== false ) {
                @unlink( $ac );
            }
        }

        self::disable_wp_cache();

        wp_clear_scheduled_hook( 'wsr_crawl_event' );
        wp_clear_scheduled_hook( 'wsr_isr_process' );

        do_action( 'wsr_deactivated' );
    }

    // ── Private Methods ───────────────────────────────────────────────────────

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_cache = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wsr_cache_index (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url         VARCHAR(2048)   NOT NULL DEFAULT '',
            cache_path  VARCHAR(2048)   NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at  DATETIME        NOT NULL DEFAULT '0000-00-00 00:00:00',
            status      VARCHAR(20)     NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY url_index (url(191))
        ) {$charset};";

        $sql_dep = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wsr_dependency (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            page_url    VARCHAR(2048)   NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY content_idx (content_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_cache );
        dbDelta( $sql_dep );
    }

    private static function create_directories() {
        // WSR_DEP_DIR and WSR_CACHE_LOG defined in bootstrap/constants.php
        $dirs = [
            WSR_CACHE_DIR,
            WSR_CACHE_DIR . 'http/',
            WSR_CACHE_DIR . 'https/',
            defined( 'WSR_DEP_DIR'   ) ? WSR_DEP_DIR   : WSR_CACHE_DIR . 'dependency/',
            defined( 'WSR_CACHE_LOG' ) ? WSR_CACHE_LOG : WSR_CACHE_DIR . 'logs/',
        ];

        foreach ( $dirs as $dir ) {
            if ( $dir && ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }

        // Protect cache root from directory listing
        $htaccess = WSR_CACHE_DIR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Options -Indexes\n" );
        }
    }

    private static function write_advanced_cache() {
        $source = WSR_PATH . 'advanced-cache.php';
        $dest   = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( ! file_exists( $source ) ) return;

        // Don't overwrite another plugin's file
        if ( file_exists( $dest ) ) {
            $existing = file_get_contents( $dest );
            if ( $existing !== false && strpos( $existing, 'WP Static Runtime' ) === false ) {
                // File belongs to another plugin — skip silently
                return;
            }
        }

        @copy( $source, $dest );
    }

    private static function enable_wp_cache() {
        // First check if WP_CACHE is already true — nothing to do
        if ( defined( 'WP_CACHE' ) && WP_CACHE ) return;

        $config = self::find_wp_config();
        if ( ! $config || ! is_writable( $config ) ) return;

        $content = file_get_contents( $config );
        if ( $content === false ) return;

        // Already has WP_CACHE definition
        if ( preg_match( "/define\s*\(\s*['\"]WP_CACHE['\"]/", $content ) ) return;

        // Insert before the "That's all" comment or before closing PHP
        $new_content = preg_replace(
            "/(\/\*\s*That's all[^*]*\*\/|\/\/\s*That's all[^\n]*\n)/i",
            "define('WP_CACHE', true); // Added by WP Static Runtime\n\n$1",
            $content,
            1
        );

        // If pattern not found, prepend after opening <?php
        if ( $new_content === $content ) {
            $new_content = preg_replace(
                '/^<\?php\s*/i',
                "<?php\ndefine('WP_CACHE', true); // Added by WP Static Runtime\n",
                $content,
                1
            );
        }

        if ( $new_content !== $content ) {
            @file_put_contents( $config, $new_content );
        }
    }

    private static function disable_wp_cache() {
        $config = self::find_wp_config();
        if ( ! $config || ! is_writable( $config ) ) return;

        $content = file_get_contents( $config );
        if ( $content === false ) return;

        $new_content = preg_replace(
            "/define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\)\s*;\s*\/\/\s*Added by WP Static Runtime\n?/",
            '',
            $content
        );

        if ( $new_content !== $content ) {
            @file_put_contents( $config, $new_content );
        }
    }

    private static function set_default_settings() {
        if ( ! get_option( 'wsr_settings' ) ) {
            add_option( 'wsr_settings', \WSR\Constants::defaults() );
        }
    }

    /**
     * Find the wp-config.php file (handles non-standard locations).
     *
     * @return string|false
     */
    private static function find_wp_config() {
        // Standard location
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            return ABSPATH . 'wp-config.php';
        }

        // One directory up (common on some hosts)
        $parent = dirname( ABSPATH ) . '/wp-config.php';
        if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            return $parent;
        }

        return false;
    }
}

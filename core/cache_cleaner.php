<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Cleaner — handles cache invalidation triggered by WordPress events.
 */
class Cache_Cleaner {

    /**
     * Register all WordPress invalidation hooks.
     */
    public static function register_hooks(): void {
        // Post save / update / delete
        add_action( 'save_post',        [ __CLASS__, 'on_save_post'   ], 10, 1 );
        add_action( 'delete_post',      [ __CLASS__, 'on_delete_post' ], 10, 1 );
        add_action( 'trash_post',       [ __CLASS__, 'on_delete_post' ], 10, 1 );
        add_action( 'transition_post_status', [ __CLASS__, 'on_status_change' ], 10, 3 );

        // Terms
        add_action( 'edited_terms',    [ __CLASS__, 'on_term_change' ], 10, 1 );
        add_action( 'created_term',    [ __CLASS__, 'on_term_change' ], 10, 1 );
        add_action( 'delete_term',     [ __CLASS__, 'on_term_change' ], 10, 1 );

        // Theme / customizer
        add_action( 'switch_theme',    [ __CLASS__, 'flush_all' ] );
        add_action( 'customize_save',  [ __CLASS__, 'flush_all' ] );

        // Nav menus
        add_action( 'wp_update_nav_menu', [ __CLASS__, 'flush_all' ] );

        // Widget update
        add_action( 'update_option_sidebars_widgets', [ __CLASS__, 'flush_all' ] );

        // WooCommerce
        add_action( 'woocommerce_product_set_stock',     [ __CLASS__, 'on_product_stock_change' ] );
        add_action( 'woocommerce_variation_set_stock',   [ __CLASS__, 'on_product_stock_change' ] );
        add_action( 'woocommerce_product_set_stock_status', [ __CLASS__, 'on_product_stock_status' ] );

        // AJAX purge endpoint
        add_action( 'wp_ajax_wsr_purge_url',  [ __CLASS__, 'ajax_purge_url'  ] );
        add_action( 'wp_ajax_wsr_flush_all',  [ __CLASS__, 'ajax_flush_all'  ] );
    }

    // ── Event Handlers ────────────────────────────────────────────────────────

    public static function on_save_post( int $post_id ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        if ( get_post_status( $post_id ) !== 'publish' ) return;

        self::purge_post( $post_id );

        // Free: tampilkan himbauan flush + crawler setelah publish (bukan premium)
        if ( ! defined( 'WSR_PREMIUM_ACTIVE' ) ) {
            set_transient( 'wsr_published_notice', 1, 60 );
        }
    }

    public static function on_delete_post( int $post_id ): void {
        self::purge_post( $post_id );
    }

    public static function on_status_change( string $new, string $old, $post ): void {
        if ( $new === 'publish' || $old === 'publish' ) {
            self::purge_post( $post->ID );
        }
    }

    public static function on_term_change( int $term_id ): void {
        // Purge the term archive and home
        $term = get_term( $term_id );
        if ( $term && ! is_wp_error( $term ) ) {
            $link = get_term_link( $term );
            if ( ! is_wp_error( $link ) ) {
                self::purge_url( $link );
            }
        }
        self::purge_url( home_url( '/' ) );
    }

    public static function on_product_stock_change( $product ): void {
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            self::purge_post( $product->get_id() );
        }
    }

    public static function on_product_stock_status( $product ): void {
        self::on_product_stock_change( $product );
    }

    // ── Core Purge Methods ────────────────────────────────────────────────────

    /**
     * Purge a single URL.
     * Premium ISR hooks this filter to return false, keeping the stale file and queuing a re-render instead.
     */
    public static function purge_url( string $url ): void {
        // ISR can intercept by returning false — stale file stays, URL queued for background re-render.
        if ( ! apply_filters( 'wsr_before_purge_url', true, $url ) ) return;

        $path = Cache_Reader::resolve_path_from_url( $url );

        if ( file_exists( $path ) ) {
            wp_delete_file( $path );
        }

        // Also remove gzip
        if ( file_exists( $path . '.gz' ) ) {
            wp_delete_file( $path . '.gz' );
        }

        // DB update
        self::mark_stale_in_db( $url );

        do_action( 'wsr_url_purged', $url );
    }

    /**
     * Purge all URLs related to a post.
     * Premium ISR hooks this filter to return false and queue URLs for incremental re-render.
     */
    public static function purge_post( int $post_id ): void {
        // ISR can intercept at the post level — handles URL collection itself.
        if ( ! apply_filters( 'wsr_before_purge_post', true, $post_id ) ) {
            do_action( 'wsr_post_purged', $post_id );
            return;
        }

        $urls = Router::affected_urls( $post_id );
        foreach ( $urls as $url ) {
            self::purge_url( $url );
        }
        do_action( 'wsr_post_purged', $post_id );
    }

    /**
     * Flush entire cache.
     */
    public static function flush_all(): void {
        self::remove_directory_contents( WSR_CACHE_DIR );

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'wsr_cache_index',
            [ 'status' => 'purged', 'updated_at' => current_time( 'mysql' ) ],
            [ 'status' => 'active' ],
            [ '%s', '%s' ],
            [ '%s' ]
        );
        // phpcs:enable

        do_action( 'wsr_cache_flushed' );
    }

    // ── AJAX Handlers ─────────────────────────────────────────────────────────

    public static function ajax_purge_url(): void {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        $url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        if ( $url ) {
            self::purge_url( $url );
            wp_send_json_success( [ 'message' => 'URL purged: ' . $url ] );
        }
        wp_send_json_error( [ 'message' => 'Invalid URL' ] );
    }

    public static function ajax_flush_all(): void {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        self::flush_all();
        wp_send_json_success( [ 'message' => 'Cache flushed successfully.' ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function mark_stale_in_db( string $url ): void {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'wsr_cache_index',
            [ 'status' => 'purged', 'updated_at' => current_time( 'mysql' ) ],
            [ 'url' => $url ],
            [ '%s', '%s' ],
            [ '%s' ]
        );
        // phpcs:enable
    }

    private static function remove_directory_contents( string $dir ): void {
        if ( ! is_dir( $dir ) ) return;

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                // Keep the .htaccess if present
                if ( $file->getFilename() === '.htaccess' ) continue;
                wp_delete_file( $file->getPathname() );
            } elseif ( $file->isDir() && isset( $wp_filesystem ) ) {
                $wp_filesystem->rmdir( $file->getPathname() );
            }
        }
    }
}

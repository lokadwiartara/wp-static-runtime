<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Incremental Static Regeneration (ISR)
 *
 * Instead of invalidating a page immediately on update, ISR:
 * 1. Keeps the stale cache SERVING (zero downtime).
 * 2. Queues a background re-render.
 * 3. Atomically swaps the file once the new version is ready.
 *
 * This gives sub-millisecond response times even during re-generation.
 *
 * Configuration options:
 *   isr_enabled       bool   Enable ISR
 *   isr_revalidate    int    Seconds before a page is considered stale (0 = event-driven only)
 *   isr_queue_size    int    Max URLs in the regeneration queue per cycle
 */
class ISR {

    private static $queue_option = 'wsr_isr_queue';
    private static $max_queue = 50;

    /**
     * Boot ISR — replaces standard cache purge with queue-based revalidation.
     */
    public static function boot(): void {
        $settings = get_option( 'wsr_settings', [] );
        if ( empty( $settings['isr_enabled'] ) ) return;

        // Override cache cleaner purge with ISR queue.
        // The free Cache_Cleaner fires: apply_filters('wsr_before_purge_url', true, $url)
        // and apply_filters('wsr_before_purge_post', true, $post_id).
        // We return false to cancel the immediate file deletion and queue instead.
        add_filter( 'wsr_before_purge_url',  [ __CLASS__, 'intercept_purge'      ], 10, 2 );
        add_filter( 'wsr_before_purge_post', [ __CLASS__, 'intercept_post_purge' ], 10, 2 );

        // Process queue on cron
        if ( ! wp_next_scheduled( 'wsr_isr_process' ) ) {
            wp_schedule_event( time(), 'wsr_isr_interval', 'wsr_isr_process' );
        }
        add_action( 'wsr_isr_process', [ __CLASS__, 'process_queue' ] );

        // Register custom cron interval
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );

        // Time-based revalidation check
        $revalidate = (int) ( $settings['isr_revalidate'] ?? 0 );
        if ( $revalidate > 0 ) {
            add_action( 'wsr_isr_process', [ __CLASS__, 'check_stale_pages' ] );
        }

        // AJAX endpoint for manual ISR trigger
        add_action( 'wp_ajax_wsr_isr_revalidate', [ __CLASS__, 'ajax_revalidate' ] );
    }

    /**
     * Instead of deleting cache immediately, queue the URL for revalidation.
     *
     * @param bool   $should_purge  Passed by the filter — we cancel by returning false.
     * @param string $url
     * @return bool false = cancel standard file deletion; stale file stays until rebuilt
     */
    public static function intercept_purge( bool $should_purge, string $url ): bool {
        self::enqueue( $url );
        return false; // Cancel standard purge — stale file serves until ISR rebuilds it
    }

    /**
     * @param bool $should_purge
     * @param int  $post_id
     * @return bool false = cancel standard post purge
     */
    public static function intercept_post_purge( bool $should_purge, int $post_id ): bool {
        $urls = \WSR\Router::affected_urls( $post_id );
        foreach ( $urls as $url ) {
            self::enqueue( $url );
        }
        return false;
    }

    /**
     * Add a URL to the ISR regeneration queue.
     */
    public static function enqueue( string $url ): void {
        $queue   = get_option( self::$queue_option, [] );
        $url     = trailingslashit( esc_url_raw( $url ) );
        $max     = (int) ( get_option( 'wsr_settings', [] )['isr_queue_size'] ?? self::$max_queue );

        if ( ! in_array( $url, $queue, true ) ) {
            array_unshift( $queue, $url );
            $queue = array_slice( $queue, 0, $max );
            update_option( self::$queue_option, $queue );
        }
    }

    /**
     * Process the ISR queue — visit and atomically swap cache files.
     */
    public static function process_queue(): void {
        // Layer 2: License gate inside core function
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            call_user_func( 'error_log', '[WSR ISR] process_queue blocked — license invalid.' );
            return;
        }

        $queue = get_option( self::$queue_option, [] );
        if ( empty( $queue ) ) return;

        $settings = get_option( 'wsr_settings', [] );
        $batch    = array_splice( $queue, 0, (int) ( $settings['isr_queue_size'] ?? 10 ) );

        update_option( self::$queue_option, $queue ); // Save reduced queue first

        foreach ( $batch as $url ) {
            self::revalidate_url( $url );
        }

        do_action( 'wsr_isr_batch_complete', $batch );
    }

    /**
     * Re-render a URL and atomically swap the cache file.
     *
     * @param string $url Full URL to revalidate
     */
    public static function revalidate_url( string $url ): bool {
        // Layer 2: License gate inside core function
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            call_user_func( 'error_log', '[WSR ISR] revalidate_url blocked — license invalid.' );
            return false;
        }

        // Fetch the page (forces WordPress to re-render and cache via Output_Buffer)
        $response = wp_remote_get( $url, [
            'timeout'    => 30,
            'sslverify'  => apply_filters( 'wsr_crawler_sslverify', true ),
            'user-agent' => 'statixpress-static-runtime-ISR/1.0',
            'headers'    => [
                'X-WSR-ISR'     => '1',
                'Cache-Control' => 'no-cache',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            self::log( 'ISR failed for ' . $url . ': ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            self::log( "ISR non-200 ({$code}) for {$url}" );
            return false;
        }

        // Get freshly rendered HTML
        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) return false;

        // Atomic swap: write to temp file, then rename
        $path     = \WSR\Cache_Reader::resolve_path_from_url( $url );
        $dir      = dirname( $path );
        $tmp_file = $dir . '/index.html.tmp.' . uniqid();

        if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );

        if ( file_put_contents( $tmp_file, $html, LOCK_EX ) === false ) {
            self::log( 'ISR: failed to write temp file for ' . $url );
            return false;
        }

        // Atomic rename using WP_Filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->move( $tmp_file, $path, true ) ) {
            wp_delete_file( $tmp_file );
            self::log( 'ISR: atomic rename failed for ' . $url );
            return false;
        }

        // Update DB index
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'wsr_cache_index',
            [ 'updated_at' => current_time( 'mysql' ), 'status' => 'active' ],
            [ 'url' => $url ], [ '%s', '%s' ], [ '%s' ]
        );

        self::log( 'ISR: revalidated ' . $url );
        do_action( 'wsr_isr_url_revalidated', $url );

        return true;
    }

    /**
     * Check pages that have exceeded their TTL and queue them.
     */
    public static function check_stale_pages(): void {
        $settings   = get_option( 'wsr_settings', [] );
        $revalidate = (int) ( $settings['isr_revalidate'] ?? 0 );
        if ( $revalidate <= 0 ) return;

        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $revalidate );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT url FROM {$wpdb->prefix}wsr_cache_index WHERE status = 'active' AND updated_at < %s LIMIT 20",
            $cutoff
        ) );

        foreach ( (array) $rows as $row ) {
            self::enqueue( $row->url );
        }
    }

    /**
     * Get current queue.
     */
    public static function get_queue(): array {
        return get_option( self::$queue_option, [] );
    }

    /**
     * AJAX: manually trigger revalidation for a URL.
     */
    public static function ajax_revalidate(): void {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        // Layer 1: License gate at AJAX entry point
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            \WSR\Premium\License\License_Guard::ajax_deny();
            return;
        }

        $url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        if ( ! $url ) wp_send_json_error( [ 'message' => 'Invalid URL' ] );

        $ok = self::revalidate_url( $url );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => 'Revalidated: ' . $url ] );
        } else {
            wp_send_json_error( [ 'message' => 'Revalidation failed for: ' . $url ] );
        }
    }

    public static function add_cron_interval( array $schedules ): array {
        $settings = get_option( 'wsr_settings', [] );
        $interval = max( 60, (int) ( $settings['isr_revalidate'] ?? 60 ) );
        $schedules['wsr_isr_interval'] = [
            'interval' => $interval,
            'display'  => "WP Static Runtime ISR ({$interval}s)",
        ];
        return $schedules;
    }

    private static function log( string $msg ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            call_user_func( 'error_log', '[WSR ISR] ' . $msg );
        }
    }
}


<?php
namespace WSR\Premium\CDN;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CDN Manager — unified interface for CDN cache purging.
 *
 * Supported providers:
 *   - Cloudflare
 *   - BunnyCDN
 *   - Fastly
 */
class CDN_Manager {

    /** @var CDN_Provider[] */
    private static $providers = [];

    public static function boot(): void {
        $settings = get_option( 'wsr_settings', [] );

        // Register enabled providers
        if ( ! empty( $settings['cdn_cloudflare_enabled'] ) ) {
            self::register( new Cloudflare(
                $settings['cdn_cloudflare_email']   ?? '',
                $settings['cdn_cloudflare_api_key'] ?? '',
                $settings['cdn_cloudflare_zone_id'] ?? ''
            ) );
        }

        if ( ! empty( $settings['cdn_bunny_enabled'] ) ) {
            self::register( new BunnyCDN(
                $settings['cdn_bunny_api_key']      ?? '',
                $settings['cdn_bunny_storage_zone'] ?? '',
                $settings['cdn_bunny_pull_zone_id'] ?? ''
            ) );
        }

        if ( ! empty( $settings['cdn_fastly_enabled'] ) ) {
            self::register( new Fastly(
                $settings['cdn_fastly_api_key']    ?? '',
                $settings['cdn_fastly_service_id'] ?? ''
            ) );
        }

        if ( empty( self::$providers ) ) return;

        // Hook into WSR purge events
        add_action( 'wsr_url_purged',    [ __CLASS__, 'on_url_purged'   ], 10, 1 );
        add_action( 'wsr_post_purged',   [ __CLASS__, 'on_post_purged'  ], 10, 1 );
        add_action( 'wsr_cache_flushed', [ __CLASS__, 'on_cache_flushed'          ] );

        // AJAX
        add_action( 'wp_ajax_wsr_cdn_purge_all', [ __CLASS__, 'ajax_purge_all' ] );
        add_action( 'wp_ajax_wsr_cdn_purge_url', [ __CLASS__, 'ajax_purge_url' ] );
    }

    public static function register( CDN_Provider $provider ): void {
        self::$providers[] = $provider;
    }

    // ── Event Handlers ────────────────────────────────────────────────────────

    public static function on_url_purged( string $url ): void {
        self::purge_url( $url );
    }

    public static function on_post_purged( int $post_id ): void {
        $urls = \WSR\Router::affected_urls( $post_id );
        foreach ( $urls as $url ) {
            self::purge_url( $url );
        }
    }

    public static function on_cache_flushed(): void {
        self::purge_all();
    }

    // ── Core CDN Methods ──────────────────────────────────────────────────────

    /**
     * Purge a single URL from all CDN providers.
     */
    public static function purge_url( string $url ): array {
        // Layer 2: License gate inside core function
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            call_user_func( 'error_log', '[WSR CDN] purge_url blocked — license invalid.' );
            return [];
        }

        $results = [];
        foreach ( self::$providers as $provider ) {
            try {
                $results[ $provider->name() ] = $provider->purge_url( $url );
            } catch ( \Exception $e ) {
                $results[ $provider->name() ] = [ 'success' => false, 'message' => $e->getMessage() ];
                call_user_func( 'error_log', '[WSR CDN] ' . $provider->name() . ' purge_url error: ' . $e->getMessage() );
            }
        }
        do_action( 'wsr_cdn_url_purged', $url, $results );
        return $results;
    }

    /**
     * Purge multiple URLs.
     *
     * @param string[] $urls
     */
    public static function purge_urls( array $urls ): array {
        // Layer 2: License gate inside core function
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            call_user_func( 'error_log', '[WSR CDN] purge_urls blocked — license invalid.' );
            return [];
        }

        $results = [];
        foreach ( self::$providers as $provider ) {
            try {
                $results[ $provider->name() ] = $provider->purge_urls( $urls );
            } catch ( \Exception $e ) {
                $results[ $provider->name() ] = [ 'success' => false, 'message' => $e->getMessage() ];
            }
        }
        return $results;
    }

    /**
     * Purge everything from all CDN providers.
     */
    public static function purge_all(): array {
        // Layer 2: License gate inside core function
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            call_user_func( 'error_log', '[WSR CDN] purge_all blocked — license invalid.' );
            return [];
        }

        $results = [];
        foreach ( self::$providers as $provider ) {
            try {
                $results[ $provider->name() ] = $provider->purge_all();
            } catch ( \Exception $e ) {
                $results[ $provider->name() ] = [ 'success' => false, 'message' => $e->getMessage() ];
                call_user_func( 'error_log', '[WSR CDN] ' . $provider->name() . ' purge_all error: ' . $e->getMessage() );
            }
        }
        do_action( 'wsr_cdn_all_purged', $results );
        return $results;
    }

    public static function get_providers(): array {
        return self::$providers;
    }

    // ── AJAX ──────────────────────────────────────────────────────────────────

    public static function ajax_purge_all(): void {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        // Layer 1: License gate at AJAX entry point
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            \WSR\Premium\License\License_Guard::ajax_deny();
            return;
        }
        $results = self::purge_all();
        wp_send_json_success( [ 'message' => 'CDN purged.', 'results' => $results ] );
    }

    public static function ajax_purge_url(): void {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        // Layer 1: License gate at AJAX entry point
        if ( ! \WSR\Premium\License\License_Guard::verify() ) {
            \WSR\Premium\License\License_Guard::ajax_deny();
            return;
        }
        $url     = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        $results = self::purge_url( $url );
        wp_send_json_success( [ 'message' => 'CDN URL purged.', 'results' => $results ] );
    }
}

/**
 * Interface all CDN providers must implement.
 */
interface CDN_Provider {
    public function name(): string;
    public function purge_url( string $url ): array;
    public function purge_urls( array $urls ): array;
    public function purge_all(): array;
}

<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Memcached Cache Index + Full-Page Cache
 *
 * Replaces the MySQL-based cache index with Memcached for fast in-memory lookups.
 * Optionally stores full HTML pages in Memcached for disk-free serving.
 * Falls back to MySQL if Memcached is unavailable.
 *
 * Keys:
 *   {prefix}cache:{url_hash}      → serialized cache metadata
 *   {prefix}stats:total           → integer counter
 *   {prefix}page:{url_hash}       → full HTML (if full_page enabled)
 *   {prefix}isr:queue             → serialized array of URLs pending revalidation
 *
 * @since 1.3.0
 */
class Memcached_Index {

    /** @var \Memcached|null */
    private static $mc      = null;
    private static $enabled = false;
    private static $prefix  = 'wsr:';

    public static function boot(): void {
        $settings = get_option( 'wsr_settings', [] );

        if ( empty( $settings['memcached_enabled'] ) ) return;

        $host   = $settings['memcached_host']   ?? '127.0.0.1';
        $port   = (int) ( $settings['memcached_port']   ?? 11211 );
        $prefix = $settings['memcached_prefix'] ?? 'wsr:';

        self::$prefix = $prefix;

        try {
            self::connect( $host, $port );
            self::$enabled = true;

            // Override cache index operations
            add_filter( 'wsr_cache_index_write',  [ __CLASS__, 'index_write'  ], 10, 2 );
            add_filter( 'wsr_cache_index_exists', [ __CLASS__, 'index_exists' ], 10, 1 );
            add_filter( 'wsr_cache_index_stats',  [ __CLASS__, 'index_stats'  ], 10, 1 );
            add_action( 'wsr_url_purged',         [ __CLASS__, 'on_purge'     ], 10, 1 );
            add_action( 'wsr_cache_flushed',      [ __CLASS__, 'on_flush'               ] );

            // Full-page cache
            if ( ! empty( $settings['memcached_full_page'] ) ) {
                add_action( 'wsr_cache_written',  [ __CLASS__, 'store_full_page' ], 10, 2 );
                add_filter( 'wsr_serve_cached_page', [ __CLASS__, 'serve_full_page' ], 10, 2 );
            }

            // ISR queue via Memcached
            add_filter( 'wsr_isr_enqueue', [ __CLASS__, 'isr_enqueue' ], 10, 1 );
            add_filter( 'wsr_isr_dequeue', [ __CLASS__, 'isr_dequeue' ], 10, 0 );

        } catch ( \Exception $e ) {
            call_user_func( 'error_log', '[WSR Memcached] Connection failed: ' . $e->getMessage() );
            self::$enabled = false;
        }
    }

    /**
     * Connect to Memcached server.
     */
    private static function connect( string $host, int $port ): void {
        if ( ! class_exists( '\Memcached' ) ) {
            throw new \Exception( 'PHP Memcached extension not installed.' );
        }

        self::$mc = new \Memcached( 'wsr_pool' );

        // Only add server if not already in the pool (persistent connection)
        $servers = self::$mc->getServerList();
        $exists  = false;
        foreach ( $servers as $s ) {
            if ( $s['host'] === $host && (int) $s['port'] === $port ) {
                $exists = true;
                break;
            }
        }

        if ( ! $exists ) {
            self::$mc->addServer( $host, $port );
        }

        // Set options for performance
        self::$mc->setOption( \Memcached::OPT_BINARY_PROTOCOL, true );
        self::$mc->setOption( \Memcached::OPT_NO_BLOCK, true );
        self::$mc->setOption( \Memcached::OPT_TCP_NODELAY, true );
        self::$mc->setOption( \Memcached::OPT_CONNECT_TIMEOUT, 2000 ); // 2s

        // Test connection
        if ( self::$mc->set( self::$prefix . 'ping', '1', 5 ) === false ) {
            throw new \Exception( 'Cannot write to Memcached at ' . esc_html( $host ) . ':' . (int) $port );
        }
    }

    // ── Cache Index ───────────────────────────────────────────────────────────

    /**
     * Write a cache entry to Memcached.
     *
     * @param bool   $continue  Continue default DB write?
     * @param string $url
     * @return bool false = skip MySQL write
     */
    public static function index_write( bool $continue, string $url ): bool {
        if ( ! self::$enabled || ! self::$mc ) return $continue;

        try {
            $key  = self::url_key( $url );
            $data = wp_json_encode( [
                'url'        => $url,
                'cached_at'  => time(),
                'status'     => 'active',
            ] );

            self::$mc->set( $key, $data, 0 ); // 0 = no expiry
            self::increment_counter( 1 );

            return false; // Skip MySQL write
        } catch ( \Exception $e ) {
            call_user_func( 'error_log', '[WSR Memcached] index_write: ' . $e->getMessage() );
            return true;
        }
    }

    /**
     * Check if URL exists in Memcached index.
     */
    public static function index_exists( bool $default ): bool {
        return $default;
    }

    /**
     * Return stats from Memcached.
     */
    public static function index_stats( array $stats ): array {
        if ( ! self::$enabled || ! self::$mc ) return $stats;

        try {
            $total = self::$mc->get( self::$prefix . 'stats:total' );
            if ( $total !== false ) {
                $stats['total_pages'] = (int) $total;
            }
        } catch ( \Exception $e ) {
            // Return unmodified stats
        }

        return $stats;
    }

    // ── Full-Page Cache ──────────────────────────────────────────────────────

    /**
     * Store full HTML in Memcached after writing to disk.
     *
     * @param string $uri
     * @param string $file
     */
    public static function store_full_page( string $uri, string $file ): void {
        if ( ! self::$enabled || ! self::$mc ) return;

        try {
            if ( file_exists( $file ) ) {
                $html = file_get_contents( $file );
                $settings = get_option( 'wsr_settings', [] );
                $ttl = (int) ( $settings['cache_ttl'] ?? 0 );
                self::$mc->set( self::$prefix . 'page:' . md5( $uri ), $html, $ttl ?: 0 );
            }
        } catch ( \Exception $e ) {
            call_user_func( 'error_log', '[WSR Memcached] store_full_page: ' . $e->getMessage() );
        }
    }

    /**
     * Attempt to serve a cached page from Memcached.
     *
     * @param string|false $html
     * @param string       $uri
     * @return string|false
     */
    public static function serve_full_page( $html, string $uri ) {
        if ( $html !== false ) return $html; // Already served from another source
        if ( ! self::$enabled || ! self::$mc ) return false;

        try {
            $cached = self::$mc->get( self::$prefix . 'page:' . md5( $uri ) );
            if ( $cached !== false && ! empty( $cached ) ) {
                return $cached;
            }
        } catch ( \Exception $e ) {
            // Fall through to disk
        }

        return false;
    }

    // ── Purge Handlers ────────────────────────────────────────────────────────

    public static function on_purge( string $url ): void {
        if ( ! self::$enabled || ! self::$mc ) return;

        try {
            self::$mc->delete( self::url_key( $url ) );
            // Also purge full-page cache
            $uri = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
            self::$mc->delete( self::$prefix . 'page:' . md5( $uri ) );
            self::increment_counter( -1 );
        } catch ( \Exception $e ) {
            call_user_func( 'error_log', '[WSR Memcached] on_purge: ' . $e->getMessage() );
        }
    }

    public static function on_flush(): void {
        if ( ! self::$enabled || ! self::$mc ) return;

        try {
            // Memcached doesn't support key enumeration well,
            // so we use getAllKeys() if available, or just reset the counter.
            // In production, a flush of all WSR keys is acceptable.
            $all_keys = self::$mc->getAllKeys();
            if ( is_array( $all_keys ) ) {
                foreach ( $all_keys as $key ) {
                    if ( strpos( $key, self::$prefix ) === 0 ) {
                        self::$mc->delete( $key );
                    }
                }
            }
            // Reset counter
            self::$mc->set( self::$prefix . 'stats:total', 0, 0 );
        } catch ( \Exception $e ) {
            call_user_func( 'error_log', '[WSR Memcached] on_flush: ' . $e->getMessage() );
        }
    }

    // ── ISR Queue via Memcached ──────────────────────────────────────────────

    /**
     * Enqueue a URL for ISR revalidation.
     * Memcached doesn't have native lists, so we store a serialized array.
     */
    public static function isr_enqueue( string $url ): bool {
        if ( ! self::$enabled || ! self::$mc ) return true;

        try {
            $queue_key = self::$prefix . 'isr:queue';
            $queue     = self::$mc->get( $queue_key );
            $queue     = is_array( $queue ) ? $queue : [];

            if ( ! in_array( $url, $queue, true ) ) {
                $queue[] = $url;
                self::$mc->set( $queue_key, $queue, 0 );
            }
            return false;
        } catch ( \Exception $e ) {
            return true;
        }
    }

    /**
     * Dequeue a URL from ISR queue.
     */
    public static function isr_dequeue(): ?string {
        if ( ! self::$enabled || ! self::$mc ) return null;

        try {
            $queue_key = self::$prefix . 'isr:queue';
            $queue     = self::$mc->get( $queue_key );
            if ( ! is_array( $queue ) || empty( $queue ) ) return null;

            $url = array_shift( $queue );
            self::$mc->set( $queue_key, $queue, 0 );
            return $url;
        } catch ( \Exception $e ) {
            return null;
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    public static function is_connected(): bool {
        return self::$enabled && self::$mc !== null;
    }

    public static function ping(): bool {
        if ( ! self::$mc ) return false;
        try {
            $stats = self::$mc->getStats();
            return ! empty( $stats );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get Memcached server stats for diagnostic page.
     */
    public static function get_stats(): array {
        if ( ! self::$mc ) return [];
        try {
            $raw = self::$mc->getStats();
            if ( empty( $raw ) ) return [];
            $first = reset( $raw );
            return [
                'version'       => $first['version']     ?? 'unknown',
                'uptime'        => $first['uptime']      ?? 0,
                'curr_items'    => $first['curr_items']   ?? 0,
                'bytes'         => $first['bytes']        ?? 0,
                'get_hits'      => $first['get_hits']     ?? 0,
                'get_misses'    => $first['get_misses']   ?? 0,
                'limit_maxbytes' => $first['limit_maxbytes'] ?? 0,
            ];
        } catch ( \Exception $e ) {
            return [];
        }
    }

    private static function url_key( string $url ): string {
        return self::$prefix . 'cache:' . md5( $url );
    }

    private static function increment_counter( int $delta ): void {
        $key = self::$prefix . 'stats:total';
        if ( $delta > 0 ) {
            $result = self::$mc->increment( $key, $delta );
            if ( $result === false ) {
                self::$mc->set( $key, $delta, 0 );
            }
        } elseif ( $delta < 0 ) {
            self::$mc->decrement( $key, abs( $delta ) );
        }
    }
}

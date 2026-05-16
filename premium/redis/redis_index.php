<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Redis Cache Index + Full-Page Cache
 *
 * Replaces the MySQL-based cache index with Redis for sub-millisecond lookups.
 * Optionally stores full HTML pages in Redis for disk-free serving.
 * Falls back to MySQL if Redis is unavailable.
 *
 * Keys:
 *   wsr:cache:{url_hash}      → serialized cache metadata
 *   wsr:cache:index           → sorted set of all cached URLs (score = timestamp)
 *   wsr:cache:stats:total     → integer counter
 *   wsr:page:{uri_hash}       → full HTML content (if full_page enabled)
 *   wsr:isr:queue             → list of URLs pending revalidation
 *
 * @since 1.2.2
 * @since 1.3.0 Added full-page cache support
 */
class Redis_Index {

    private static $redis   = null;
    private static $enabled = false;
    private static $prefix  = 'wsr:';

    public static function boot(): void {
        $settings = get_option( 'wsr_settings', [] );

        if ( empty( $settings['redis_enabled'] ) ) return;

        $host     = $settings['redis_host']     ?? '127.0.0.1';
        $port     = (int) ( $settings['redis_port']     ?? 6379 );
        $password = $settings['redis_password'] ?? '';
        $db       = (int) ( $settings['redis_database'] ?? 0 );
        $prefix   = $settings['redis_prefix']   ?? 'wsr:';

        self::$prefix = $prefix;

        try {
            self::connect( $host, $port, $password, $db );
            self::$enabled = true;

            // Override cache index operations
            add_filter( 'wsr_cache_index_write',  [ __CLASS__, 'index_write'  ], 10, 2 );
            add_filter( 'wsr_cache_index_exists', [ __CLASS__, 'index_exists' ], 10, 1 );
            add_filter( 'wsr_cache_index_stats',  [ __CLASS__, 'index_stats'  ], 10, 1 );
            add_action( 'wsr_url_purged',         [ __CLASS__, 'on_purge'     ], 10, 1 );
            add_action( 'wsr_cache_flushed',      [ __CLASS__, 'on_flush'               ] );

            // ISR queue via Redis list
            add_filter( 'wsr_isr_enqueue', [ __CLASS__, 'isr_enqueue' ], 10, 1 );
            add_filter( 'wsr_isr_dequeue', [ __CLASS__, 'isr_dequeue' ], 10, 0 );

            // Full-page cache
            if ( ! empty( $settings['redis_full_page'] ) ) {
                add_action( 'wsr_cache_written',      [ __CLASS__, 'store_full_page' ], 10, 2 );
                add_filter( 'wsr_serve_cached_page',  [ __CLASS__, 'serve_full_page' ], 10, 2 );
            }

        } catch ( \Exception $e ) {
            error_log( '[WSR Redis] Connection failed: ' . $e->getMessage() );
            self::$enabled = false;
        }
    }

    /**
     * Connect to Redis.
     */
    private static function connect( string $host, int $port, string $password, int $db ): void {
        if ( ! class_exists( '\Redis' ) ) {
            throw new \Exception( 'PHP Redis extension not installed.' );
        }

        $settings = get_option( 'wsr_settings', [] );
        $timeout  = (float) ( $settings['redis_timeout'] ?? 2.0 );

        self::$redis = new \Redis();
        self::$redis->connect( $host, $port, $timeout );

        if ( $password ) {
            self::$redis->auth( $password );
        }

        if ( $db > 0 ) {
            self::$redis->select( $db );
        }

        self::$redis->setOption( \Redis::OPT_PREFIX, self::$prefix );
    }

    // ── Cache Index ───────────────────────────────────────────────────────────

    /**
     * Write a cache entry to Redis.
     *
     * @param bool   $continue  Continue default DB write?
     * @param string $url
     * @return bool false = skip MySQL write
     */
    public static function index_write( bool $continue, string $url ): bool {
        if ( ! self::$enabled || ! self::$redis ) return $continue;

        try {
            $key  = self::url_key( $url );
            $data = wp_json_encode( [
                'url'        => $url,
                'cached_at'  => time(),
                'status'     => 'active',
            ] );

            self::$redis->set( $key, $data );
            self::$redis->zAdd( 'cache:index', time(), $url );
            self::$redis->incr( 'cache:stats:total' );

            return false; // Skip MySQL write
        } catch ( \Exception $e ) {
            error_log( '[WSR Redis] index_write: ' . $e->getMessage() );
            return true; // Fall back to MySQL
        }
    }

    /**
     * Check if URL exists in Redis index.
     */
    public static function index_exists( bool $default ): bool {
        // Note: physical file check is still primary; this is a fast lookup
        return $default;
    }

    /**
     * Return stats from Redis.
     */
    public static function index_stats( array $stats ): array {
        if ( ! self::$enabled || ! self::$redis ) return $stats;

        try {
            $total = (int) self::$redis->get( 'cache:stats:total' );
            $stats['total_pages'] = $total;
        } catch ( \Exception $e ) {
            // Return unmodified stats
        }

        return $stats;
    }

    // ── Full-Page Cache ───────────────────────────────────────────────────────

    /**
     * Store full HTML in Redis after writing to disk.
     */
    public static function store_full_page( string $uri, string $file ): void {
        if ( ! self::$enabled || ! self::$redis ) return;

        try {
            if ( file_exists( $file ) ) {
                $html = file_get_contents( $file );
                $settings = get_option( 'wsr_settings', [] );
                $ttl = (int) ( $settings['cache_ttl'] ?? 0 );
                $key = 'page:' . md5( $uri );
                if ( $ttl > 0 ) {
                    self::$redis->setex( $key, $ttl, $html );
                } else {
                    self::$redis->set( $key, $html );
                }
            }
        } catch ( \Exception $e ) {
            error_log( '[WSR Redis] store_full_page: ' . $e->getMessage() );
        }
    }

    /**
     * Attempt to serve a cached page from Redis.
     *
     * @param string|false $html
     * @param string       $uri
     * @return string|false
     */
    public static function serve_full_page( $html, string $uri ) {
        if ( $html !== false ) return $html;
        if ( ! self::$enabled || ! self::$redis ) return false;

        try {
            $cached = self::$redis->get( 'page:' . md5( $uri ) );
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
        if ( ! self::$enabled || ! self::$redis ) return;
        try {
            self::$redis->del( self::url_key( $url ) );
            self::$redis->zRem( 'cache:index', $url );
            self::$redis->decr( 'cache:stats:total' );
            // Also purge full-page cache
            $uri = parse_url( $url, PHP_URL_PATH ) ?: '/';
            self::$redis->del( 'page:' . md5( $uri ) );
        } catch ( \Exception $e ) {
            error_log( '[WSR Redis] on_purge: ' . $e->getMessage() );
        }
    }

    public static function on_flush(): void {
        if ( ! self::$enabled || ! self::$redis ) return;
        try {
            // Flush all WSR keys (cache index + full-page)
            $keys = self::$redis->keys( 'cache:*' );
            foreach ( $keys as $key ) {
                self::$redis->del( $key );
            }
            $page_keys = self::$redis->keys( 'page:*' );
            foreach ( $page_keys as $key ) {
                self::$redis->del( $key );
            }
        } catch ( \Exception $e ) {
            error_log( '[WSR Redis] on_flush: ' . $e->getMessage() );
        }
    }

    // ── ISR Queue via Redis ───────────────────────────────────────────────────

    /**
     * Push a URL to the ISR queue (Redis list).
     *
     * @param string $url
     * @return bool false = skip default queue
     */
    public static function isr_enqueue( string $url ): bool {
        if ( ! self::$enabled || ! self::$redis ) return true;
        try {
            $queue_key = 'isr:queue';
            // Avoid duplicates
            $members = self::$redis->lRange( $queue_key, 0, -1 );
            if ( ! in_array( $url, $members, true ) ) {
                self::$redis->rPush( $queue_key, $url );
            }
            return false;
        } catch ( \Exception $e ) {
            return true;
        }
    }

    /**
     * Pop a URL from the ISR queue.
     *
     * @return string|null
     */
    public static function isr_dequeue(): ?string {
        if ( ! self::$enabled || ! self::$redis ) return null;
        try {
            $url = self::$redis->lPop( 'isr:queue' );
            return $url ?: null;
        } catch ( \Exception $e ) {
            return null;
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    public static function is_connected(): bool {
        return self::$enabled && self::$redis !== null;
    }

    public static function ping(): bool {
        if ( ! self::$redis ) return false;
        try {
            return self::$redis->ping() === true || self::$redis->ping() === '+PONG';
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get Redis server info for diagnostics.
     */
    public static function get_info(): array {
        if ( ! self::$redis ) return [];
        try {
            $info = self::$redis->info();
            return [
                'version'          => $info['redis_version']    ?? 'unknown',
                'uptime_seconds'   => $info['uptime_in_seconds'] ?? 0,
                'used_memory'      => $info['used_memory_human'] ?? '0B',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'keyspace_hits'    => $info['keyspace_hits']     ?? 0,
                'keyspace_misses'  => $info['keyspace_misses']   ?? 0,
                'total_keys'       => self::$redis->dbSize(),
            ];
        } catch ( \Exception $e ) {
            return [];
        }
    }

    private static function url_key( string $url ): string {
        return 'cache:' . md5( $url );
    }
}

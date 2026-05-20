<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Reader — resolves and reads cached HTML files from disk.
 */
class Cache_Reader {

    /**
     * Check if a cache file exists for the given URI.
     */
    public static function exists( string $uri ): bool {
        $path = self::resolve_path( $uri );
        return $path !== false && file_exists( $path );
    }

    /**
     * Return the raw HTML content from cache, or false if not found.
     *
     * @param  string $uri
     * @return string|false
     */
    public static function get( string $uri ) {
        $path = self::resolve_path( $uri );
        if ( ! $path || ! file_exists( $path ) ) return false;

        // TTL check
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        $ttl      = (int) ( $settings['cache_ttl'] ?? 0 );
        if ( $ttl > 0 && ( time() - filemtime( $path ) ) > $ttl ) {
            return false;
        }

        return file_get_contents( $path );
    }

    /**
     * Resolve the absolute path to the cache file for a URI.
     *
     * @param  string $uri
     * @return string|false
     */
    public static function resolve_path( string $uri ) {
        if ( empty( $uri ) ) return false;

        $scheme = Host::current_scheme();
        $host   = Host::current();
        $uri    = trailingslashit( strtok( $uri, '?' ) );

        return WSR_CACHE_DIR . $scheme . '/' . $host . $uri . 'index.html';
    }

    /**
     * Resolve path from a full URL — port default (80/443) tidak ditulis.
     */
    public static function resolve_path_from_url( string $url ): string {
        $scheme = Host::scheme_from_url( $url );
        $host   = Host::from_url( $url );
        $path   = trailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ?: '/' );

        return WSR_CACHE_DIR . $scheme . '/' . $host . $path . 'index.html';
    }

    /**
     * Get cache statistics.
     */
    public static function stats(): array {
        global $wpdb;

        $total_size  = 0;
        $total_files = 0;
        $table       = esc_sql( $wpdb->prefix . 'wsr_cache_index' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows        = $wpdb->get_results( "SELECT cache_path FROM {$table} WHERE status='active'" );

        foreach ( (array) $rows as $row ) {
            if ( file_exists( $row->cache_path ) ) {
                $total_size  += filesize( $row->cache_path );
                $total_files++;
            }
        }

        // Hit/miss ratio from custom header log (approximation via DB count)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_cached = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='active'" );

        return [
            'total_pages' => (int) $total_cached,
            'disk_size'   => self::human_size( $total_size ),
            'disk_bytes'  => $total_size,
        ];
    }

    private static function human_size( int $bytes ): string {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i = 0;
        while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
            $bytes /= 1024;
            $i++;
        }
        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }
}

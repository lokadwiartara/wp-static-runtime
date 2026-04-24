<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache facade — unified entry point for all cache operations.
 */
class Cache {

    /**
     * Store rendered HTML for a given URI.
     */
    public static function store( string $uri, string $html ): bool {
        return Cache_Writer::write( $uri, $html );
    }

    /**
     * Check if a valid cache file exists for URI.
     */
    public static function has( string $uri ): bool {
        return Cache_Reader::exists( $uri );
    }

    /**
     * Retrieve HTML from cache.
     *
     * @param  string $uri
     * @return string|false
     */
    public static function get( string $uri ) {
        return Cache_Reader::get( $uri );
    }

    /**
     * Purge cache for a single URL.
     */
    public static function purge( string $url ): void {
        Cache_Cleaner::purge_url( $url );
    }

    /**
     * Purge cache for a post and all related pages.
     */
    public static function purge_post( int $post_id ): void {
        $urls = Router::affected_urls( $post_id );
        foreach ( $urls as $url ) {
            Cache_Cleaner::purge_url( $url );
        }
    }

    /**
     * Purge entire cache.
     */
    public static function flush(): void {
        Cache_Cleaner::flush_all();
    }

    /**
     * Get cache statistics.
     */
    public static function stats(): array {
        return Cache_Reader::stats();
    }
}

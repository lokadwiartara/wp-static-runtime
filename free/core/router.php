<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Static Router — checks whether the current request has a valid cache file.
 * The actual early-exit happens in advanced-cache.php (pre-WordPress).
 * This class is used by the Output_Buffer to decide whether to cache the response.
 */
class Router {

    /**
     * Check if the current request is already cached on disk.
     */
    public static function is_cached(): bool {
        $request = Request::instance();

        if ( ! $request->is_cacheable() ) return false;

        return Cache_Reader::exists( $request->uri() );
    }

    /**
     * Resolve the cache file path for a given URI.
     */
    public static function resolve_cache_path( string $uri ): string {
        return WSR_CACHE_DIR . Host::current_scheme() . '/' . Host::current() . trailingslashit( $uri ) . 'index.html';
    }

    /**
     * Resolve the cache dir for a given URI.
     */
    public static function resolve_cache_dir( string $uri ): string {
        return dirname( self::resolve_cache_path( $uri ) ) . '/';
    }

    /**
     * Map a post to all affected URLs (for purging).
     *
     * @param int $post_id
     * @return string[]
     */
    public static function affected_urls( int $post_id ): array {
        $urls = [];

        $permalink = get_permalink( $post_id );
        if ( $permalink ) $urls[] = $permalink;

        // Home
        $urls[] = home_url( '/' );

        // Post type archive
        $post_type = get_post_type( $post_id );
        $archive   = get_post_type_archive_link( $post_type );
        if ( $archive ) $urls[] = $archive;

        // Categories & tags
        $terms = get_the_terms( $post_id, 'category' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $link = get_term_link( $term );
                if ( ! is_wp_error( $link ) ) $urls[] = $link;
            }
        }

        $tags = get_the_terms( $post_id, 'post_tag' );
        if ( $tags && ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $link = get_term_link( $tag );
                if ( ! is_wp_error( $link ) ) $urls[] = $link;
            }
        }

        // Dependency graph override
        $graph = Dependency_Graph::get_affected_urls( $post_id );
        $urls  = array_merge( $urls, $graph );

        return array_unique( $urls );
    }
}

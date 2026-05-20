<?php
namespace WSR\Crawler;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sitemap — discovers URLs to cache.
 *
 * Primary: WordPress DB query (always works, no HTTP).
 * Secondary: XML sitemap fetch (optional, safe, never throws).
 */
class Sitemap {

    /**
     * Get all URLs via WordPress DB — no HTTP needed.
     * Safe to call in any context including AJAX.
     *
     * @return string[]
     */
    public static function from_wordpress() {
        $urls = [];

        // Home
        $urls[] = home_url( '/' );

        // Static front page / posts page
        if ( get_option( 'show_on_front' ) === 'page' ) {
            $front_id = (int) get_option( 'page_on_front' );
            $posts_id  = (int) get_option( 'page_for_posts' );
            if ( $front_id ) {
                $l = get_permalink( $front_id );
                if ( $l ) $urls[] = $l;
            }
            if ( $posts_id ) {
                $l = get_permalink( $posts_id );
                if ( $l ) $urls[] = $l;
            }
        }

        // All published posts/pages/custom types
        $skip_types = [
            'attachment', 'revision', 'nav_menu_item', 'custom_css',
            'customize_changeset', 'oembed_cache', 'wp_block',
            'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
        ];
        $types = array_diff(
            array_values( get_post_types( [ 'public' => true ], 'names' ) ),
            $skip_types
        );

        if ( ! empty( $types ) ) {
            global $wpdb;
            $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $query        = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ( $placeholders )
                 LIMIT 2000",
                ...$types
            );
            $ids = $wpdb->get_col( $query );
            // phpcs:enable

            foreach ( $ids as $id ) {
                $l = get_permalink( (int) $id );
                if ( $l ) $urls[] = $l;
            }

            // Post type archives
            foreach ( $types as $type ) {
                $a = get_post_type_archive_link( $type );
                if ( $a ) $urls[] = $a;
            }
        }

        // Taxonomy term archives
        $taxs = array_values( get_taxonomies( [ 'public' => true ], 'names' ) );
        foreach ( $taxs as $tax ) {
            $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true, 'fields' => 'all', 'number' => 500 ] );
            if ( ! is_array( $terms ) || is_wp_error( $terms ) ) continue;
            foreach ( $terms as $term ) {
                $l = get_term_link( $term );
                if ( ! is_wp_error( $l ) && $l ) $urls[] = $l;
            }
        }

        // WooCommerce shop page
        if ( function_exists( 'wc_get_page_id' ) ) {
            $shop_id = wc_get_page_id( 'shop' );
            if ( $shop_id && $shop_id > 0 ) {
                $l = get_permalink( $shop_id );
                if ( $l ) $urls[] = $l;
            }
        }

        return array_values( array_unique( array_filter( $urls ) ) );
    }

    /**
     * Try to get URLs from XML sitemap.
     * Returns empty array on any error — never throws.
     *
     * @return string[]
     */
    public static function from_sitemap() {
        try {
            $candidates = [
                home_url( '/wp-sitemap.xml' ),
                home_url( '/sitemap.xml' ),
                home_url( '/sitemap_index.xml' ),
            ];

            foreach ( $candidates as $sitemap_url ) {
                $r = wp_remote_get( $sitemap_url, [
                    'timeout'            => 8,
                    'sslverify'          => apply_filters( 'wsr_crawler_sslverify', true ),
                    'reject_unsafe_urls' => false,
                ] );

                if ( is_wp_error( $r ) ) continue;
                if ( wp_remote_retrieve_response_code( $r ) !== 200 ) continue;

                $body = wp_remote_retrieve_body( $r );
                if ( empty( $body ) ) continue;

                $urls = self::parse_xml( $body, 0 );
                if ( ! empty( $urls ) ) return $urls;
            }
        } catch ( \Exception $e ) {
            // Silent fail
        }

        return [];
    }

    /**
     * Parse sitemap XML recursively (handles sitemap indexes).
     *
     * @param  string $xml_body
     * @param  int    $depth
     * @return string[]
     */
    private static function parse_xml( $xml_body, $depth ) {
        if ( $depth > 3 || empty( $xml_body ) ) return [];

        $prev = libxml_use_internal_errors( true );
        $xml  = simplexml_load_string( $xml_body );
        libxml_use_internal_errors( $prev );

        if ( ! $xml ) return [];

        $urls = [];

        // Sitemap index
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $child ) {
                $loc = (string) $child->loc;
                if ( ! $loc ) continue;
                $r = wp_remote_get( $loc, [ 'timeout' => 8, 'sslverify' => apply_filters( 'wsr_crawler_sslverify', true ) ] );
                if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 ) {
                    $urls = array_merge( $urls, self::parse_xml( wp_remote_retrieve_body( $r ), $depth + 1 ) );
                }
            }
        }

        // URL set
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                $loc = (string) $entry->loc;
                if ( $loc ) $urls[] = $loc;
            }
        }

        return array_unique( $urls );
    }
}

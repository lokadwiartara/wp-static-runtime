<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Smart Dependency Graph (Premium)
 *
 * Extends the free Dependency_Graph with:
 * - Gutenberg block-level parsing (tracks reusable blocks, query loops)
 * - WooCommerce product → category/tag chain mapping
 * - Template part tracking (header, footer, sidebar)
 * - Automatic relationship discovery from post meta
 * - DB-first with Redis acceleration
 */
class Smart_Dependency extends \WSR\Dependency_Graph {

    /**
     * Record the current page with deeper analysis.
     * Overrides the parent method.
     */
    public static function record_current_page() {
        parent::record_current_page();

        global $wp_query;
        $obj = $wp_query->get_queried_object();

        if ( $obj instanceof \WP_Post ) {
            self::analyze_post_blocks( $obj );
            self::analyze_post_relations( $obj->ID );
        }
    }

    /**
     * Parse Gutenberg blocks in a post and track reusable blocks and query loops.
     */
    private static function analyze_post_blocks( \WP_Post $post ): void {
        if ( ! function_exists( 'parse_blocks' ) ) return;

        $blocks = parse_blocks( $post->post_content );
        self::traverse_blocks( $blocks, $post->ID );
    }

    private static function traverse_blocks( array $blocks, int $source_post_id ): void {
        foreach ( $blocks as $block ) {
            // Reusable block — the block affects any page that embeds it
            if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
                $ref_id = (int) $block['attrs']['ref'];
                $uri    = \WSR\Request::instance()->uri();
                self::map( $ref_id, $uri );
            }

            // Query loop — page depends on post type
            if ( $block['blockName'] === 'core/query' ) {
                self::map_query_loop_dependencies( $block, $source_post_id );
            }

            // Recurse into inner blocks
            if ( ! empty( $block['innerBlocks'] ) ) {
                self::traverse_blocks( $block['innerBlocks'], $source_post_id );
            }
        }
    }

    private static function map_query_loop_dependencies( array $block, int $source_post_id ): void {
        $post_type = $block['attrs']['query']['postType'] ?? 'post';

        // All posts of this type affect this page
        $posts = get_posts( [
            'post_type'   => $post_type,
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );

        $uri = \WSR\Request::instance()->uri();
        foreach ( $posts as $pid ) {
            self::map( (int) $pid, $uri );
        }
    }

    /**
     * Analyze post relationships (related products, linked posts, etc.).
     */
    private static function analyze_post_relations( int $post_id ): void {
        // WooCommerce related products
        if ( class_exists( 'WooCommerce' ) && get_post_type( $post_id ) === 'product' ) {
            self::map_woo_relations( $post_id );
        }

        // Custom field "related_posts" (common in many themes)
        $related = get_post_meta( $post_id, 'related_posts', true );
        if ( $related ) {
            $ids = is_array( $related ) ? $related : explode( ',', $related );
            $uri = \WSR\Request::instance()->uri();
            foreach ( $ids as $rid ) {
                $rid = (int) trim( $rid );
                if ( $rid ) self::map( $rid, $uri );
            }
        }
    }

    private static function map_woo_relations( int $product_id ): void {
        // Related product IDs
        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        $uri = \WSR\Request::instance()->uri();

        // Upsells and cross-sells
        foreach ( $product->get_upsell_ids() as $id ) {
            self::map( $id, $uri );
        }
        foreach ( $product->get_cross_sell_ids() as $id ) {
            self::map( $id, $uri );
        }

        // Shop page
        $shop_id = (int) wc_get_page_id( 'shop' );
        if ( $shop_id ) self::map( $shop_id, $uri );
    }

    /**
     * Get affected URLs with deeper graph traversal.
     *
     * @param int $post_id
     * @return string[]
     */
    public static function get_affected_urls( $post_id ) {
        $urls = parent::get_affected_urls( $post_id );

        // If this is a reusable block — find all pages that embed it
        if ( get_post_type( $post_id ) === 'wp_block' ) {
            $urls = array_merge( $urls, self::get_reusable_block_pages( $post_id ) );
        }

        // Traverse the graph up to 2 levels
        $urls = array_merge( $urls, self::get_second_level_urls( $post_id ) );

        return array_unique( $urls );
    }

    /**
     * Find all pages that embed a reusable block.
     */
    private static function get_reusable_block_pages( int $block_id ): array {
        global $wpdb;
        $like  = '%"ref":' . $block_id . '%';
        $posts = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_content LIKE %s",
            $like
        ) );

        $urls = [];
        foreach ( $posts as $pid ) {
            $link = get_permalink( (int) $pid );
            if ( $link ) $urls[] = $link;
        }
        return $urls;
    }

    /**
     * Level-2 traversal: URLs that depend on pages that depend on $post_id.
     */
    private static function get_second_level_urls( int $post_id ): array {
        global $wpdb;

        // Get all page_urls for this post
        $page_urls = $wpdb->get_col( $wpdb->prepare(
            "SELECT page_url FROM {$wpdb->prefix}wsr_dependency WHERE content_id = %d",
            $post_id
        ) );

        if ( empty( $page_urls ) ) return [];

        $urls = [];
        foreach ( $page_urls as $page_url ) {
            // Find posts shown ON that page
            $content_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT content_id FROM {$wpdb->prefix}wsr_dependency WHERE page_url = %s",
                $page_url
            ) );
            foreach ( $content_ids as $cid ) {
                $extra = parent::get_affected_urls( (int) $cid );
                $urls  = array_merge( $urls, $extra );
            }
        }

        return $urls;
    }
}



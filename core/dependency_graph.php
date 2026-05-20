<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dependency Graph — tracks which URLs are affected by a given post/content change.
 * Stored as JSON in storage/dependency/map.json and in DB table wp_wsr_dependency.
 */
class Dependency_Graph {

    /** @var string */
    private static $map_file = '';

    /** @var array */
    private static $map = [];

    /** @var bool */
    private static $loaded = false;

    /**
     * Initialise file path.
     */
    private static function setup() {
        if ( ! self::$map_file ) {
            $dep_dir       = defined( 'WSR_DEP_DIR' ) ? WSR_DEP_DIR : ( WSR_CACHE_DIR . 'dependency/' );
            self::$map_file = $dep_dir . 'map.json';
        }
        if ( ! self::$loaded ) {
            self::load();
        }
    }

    /**
     * Load the dependency map from disk.
     */
    private static function load() {
        self::setup_dir();
        if ( file_exists( self::$map_file ) ) {
            $json = file_get_contents( self::$map_file );
            if ( $json !== false ) {
                $decoded = json_decode( $json, true );
                self::$map = is_array( $decoded ) ? $decoded : [];
            }
        }
        self::$loaded = true;
    }

    /**
     * Persist the map to disk.
     */
    private static function save() {
        @file_put_contents( self::$map_file, wp_json_encode( self::$map, JSON_PRETTY_PRINT ), LOCK_EX );
    }

    /**
     * Record all posts queried during the current page render and map them to the URL.
     * Called by Cache_Writer after writing a page.
     */
    public static function record_current_page() {
        global $wp_query;

        $request = Request::instance();
        $uri     = $request->uri();
        if ( empty( $uri ) ) return;

        $post_ids = [];

        if ( ! empty( $wp_query->posts ) ) {
            foreach ( $wp_query->posts as $post ) {
                if ( isset( $post->ID ) ) {
                    $post_ids[] = (int) $post->ID;
                }
            }
        }

        $obj = $wp_query->get_queried_object();
        if ( $obj instanceof \WP_Post ) {
            $post_ids[] = (int) $obj->ID;
        }

        $post_ids = array_unique( $post_ids );

        foreach ( $post_ids as $id ) {
            self::map( $id, $uri );
        }
    }

    /**
     * Associate a post ID with a page URI.
     *
     * @param int    $post_id
     * @param string $uri     e.g. /blog/post-1/
     */
    public static function map( $post_id, $uri ) {
        $post_id = (int) $post_id;
        self::setup();

        if ( ! isset( self::$map[ $post_id ] ) ) {
            self::$map[ $post_id ] = [];
        }
        if ( ! in_array( $uri, self::$map[ $post_id ], true ) ) {
            self::$map[ $post_id ][] = $uri;
            self::save();
        }

        self::write_to_db( $post_id, home_url( $uri ) );
    }

    /**
     * Get all URLs affected by a post update.
     *
     * @param  int $post_id
     * @return string[] Array of full URLs
     */
    public static function get_affected_urls( $post_id ) {
        $post_id = (int) $post_id;
        self::setup();

        $uris    = isset( self::$map[ $post_id ] ) ? self::$map[ $post_id ] : [];
        $db_urls = self::read_from_db( $post_id );

        $all = array_merge(
            array_map( function( $u ) { return home_url( $u ); }, $uris ),
            $db_urls
        );

        return array_unique( $all );
    }

    /**
     * Remove a post from the graph (on delete).
     *
     * @param int $post_id
     */
    public static function remove( $post_id ) {
        $post_id = (int) $post_id;
        self::setup();
        unset( self::$map[ $post_id ] );
        self::save();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'wsr_dependency',
            [ 'content_id' => $post_id ],
            [ '%d' ]
        );
    }

    // ── DB Methods ────────────────────────────────────────────────────────────

    private static function write_to_db( $post_id, $url ) {
        global $wpdb;

        $table    = esc_sql( $wpdb->prefix . 'wsr_dependency' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE content_id = %d AND page_url = %s",
            $post_id, $url
        ) );

        if ( ! $existing ) {
            $wpdb->insert(
                $table,
                [
                    'content_id' => $post_id,
                    'page_url'   => $url,
                ],
                [ '%d', '%s' ]
            );
        }
        // phpcs:enable
    }

    private static function read_from_db( $post_id ) {
        global $wpdb;
        $table  = esc_sql( $wpdb->prefix . 'wsr_dependency' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_col( $wpdb->prepare(
            "SELECT page_url FROM {$table} WHERE content_id = %d",
            $post_id
        ) );
        // phpcs:enable
        return is_array( $result ) ? $result : [];
    }

    private static function setup_dir() {
        $dep_dir       = defined( 'WSR_DEP_DIR' ) ? WSR_DEP_DIR : ( WSR_CACHE_DIR . 'dependency/' );
        self::$map_file = $dep_dir . 'map.json';
        if ( ! is_dir( $dep_dir ) ) {
            wp_mkdir_p( $dep_dir );
        }
    }
}

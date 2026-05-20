<?php
namespace WSR\Premium\Server;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LiteSpeed Cache Integration
 *
 * Integrates WP Static Runtime with LiteSpeed Web Server / OpenLiteSpeed.
 * Handles LSCache purge via X-LiteSpeed-Purge headers, cache tags,
 * and proper cache-control headers for optimal LiteSpeed caching.
 *
 * Works alongside WSR's own static HTML cache — LiteSpeed serves as an
 * additional server-level cache layer in front of WSR's disk-based cache.
 *
 * @since 1.3.0
 */
class LiteSpeed {

    private static $enabled = false;
    private static $settings = [];

    public static function boot(): void {
        $settings = get_option( 'wsr_settings', [] );

        if ( empty( $settings['litespeed_enabled'] ) ) return;
        if ( ! self::is_litespeed() ) return;

        self::$enabled  = true;
        self::$settings = $settings;

        // ── Cache-Control headers on front-end ───────────────────────────────
        add_action( 'template_redirect', [ __CLASS__, 'set_cache_headers' ], 0 );

        // ── Purge hooks — sync WSR purge with LSCache ────────────────────────
        add_action( 'wsr_url_purged',    [ __CLASS__, 'purge_url'  ], 10, 1 );
        add_action( 'wsr_cache_flushed', [ __CLASS__, 'purge_all'           ] );
        add_action( 'wsr_post_purged',   [ __CLASS__, 'purge_post' ], 10, 1 );

        // ── Vary header for logged-in differentiation ────────────────────────
        add_action( 'send_headers', [ __CLASS__, 'set_vary_header' ] );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CACHE-CONTROL HEADERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Set X-LiteSpeed-Cache-Control headers for cacheable pages.
     */
    public static function set_cache_headers(): void {
        if ( is_admin() || is_preview() || is_user_logged_in() ) {
            // Don't cache admin, previews, or logged-in users
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            return;
        }

        $ttl    = (int) ( self::$settings['litespeed_cache_ttl'] ?? 3600 );
        $prefix = self::$settings['litespeed_tag_prefix'] ?? 'wsr_';

        // Set cache-control
        header( 'X-LiteSpeed-Cache-Control: public, max-age=' . $ttl );

        // Set cache tags for granular purging
        $tags = [ $prefix . 'all' ];

        if ( is_singular() ) {
            global $post;
            if ( $post ) {
                $tags[] = $prefix . 'post_' . $post->ID;
                $tags[] = $prefix . 'type_' . $post->post_type;

                // Add term tags
                $taxonomies = get_object_taxonomies( $post->post_type );
                foreach ( $taxonomies as $tax ) {
                    $terms = get_the_terms( $post->ID, $tax );
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $term ) {
                            $tags[] = $prefix . 'term_' . $term->term_id;
                        }
                    }
                }

                // Author tag
                $tags[] = $prefix . 'author_' . $post->post_author;
            }
        } elseif ( is_archive() ) {
            $tags[] = $prefix . 'archive';
            if ( is_category() || is_tag() || is_tax() ) {
                $obj = get_queried_object();
                if ( $obj ) {
                    $tags[] = $prefix . 'term_' . $obj->term_id;
                }
            }
            if ( is_author() ) {
                $obj = get_queried_object();
                if ( $obj ) {
                    $tags[] = $prefix . 'author_' . $obj->ID;
                }
            }
        } elseif ( is_front_page() || is_home() ) {
            $tags[] = $prefix . 'home';
        } elseif ( is_search() ) {
            // Don't cache search results in LiteSpeed
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            return;
        }

        header( 'X-LiteSpeed-Tag: ' . implode( ',', $tags ) );
    }

    /**
     * Set Vary header to differentiate cached versions.
     */
    public static function set_vary_header(): void {
        // Vary by login status — logged-in users get different content
        header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in_' );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PURGE HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Purge a single URL from LiteSpeed cache.
     */
    public static function purge_url( string $url ): void {
        if ( ! self::$enabled ) return;

        $path = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
        self::send_purge_header( $path );

        do_action( 'wsr_litespeed_purged_url', $url );
    }

    /**
     * Purge all LiteSpeed cache.
     */
    public static function purge_all(): void {
        if ( ! self::$enabled ) return;

        if ( ! empty( self::$settings['litespeed_purge_all'] ) ) {
            self::send_purge_header( '*' );
        } else {
            // Purge only WSR-tagged pages
            $prefix = self::$settings['litespeed_tag_prefix'] ?? 'wsr_';
            self::send_purge_header( 'tag=' . $prefix . 'all' );
        }

        do_action( 'wsr_litespeed_purged_all' );
    }

    /**
     * Purge cache related to a specific post.
     * Uses LSCache tag-based purge for precision.
     */
    public static function purge_post( int $post_id ): void {
        if ( ! self::$enabled ) return;

        $prefix = self::$settings['litespeed_tag_prefix'] ?? 'wsr_';
        $post   = get_post( $post_id );
        if ( ! $post ) return;

        $tags = [
            $prefix . 'post_' . $post_id,
            $prefix . 'type_' . $post->post_type,
            $prefix . 'home',
            $prefix . 'archive',
        ];

        // Purge author pages
        $tags[] = $prefix . 'author_' . $post->post_author;

        // Purge term pages
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $tax ) {
            $terms = get_the_terms( $post_id, $tax );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $tags[] = $prefix . 'term_' . $term->term_id;
                }
            }
        }

        self::send_purge_header( 'tag=' . implode( ',', array_unique( $tags ) ) );

        do_action( 'wsr_litespeed_purged_post', $post_id, $tags );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PURGE VIA HEADER
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Send the X-LiteSpeed-Purge header.
     *
     * LiteSpeed processes these headers to invalidate cached pages.
     * Supports:
     *   - Path purge:  /blog/post-1/
     *   - Tag purge:   tag=wsr_post_123
     *   - Full purge:  *
     *
     * @param string $value Purge directive
     */
    private static function send_purge_header( string $value ): void {
        if ( headers_sent() ) {
            // Headers already sent — queue for next request via option
            $pending = get_option( 'wsr_litespeed_pending_purge', [] );
            $pending[] = $value;
            update_option( 'wsr_litespeed_pending_purge', array_unique( $pending ), false );
            return;
        }

        header( 'X-LiteSpeed-Purge: ' . $value, false );
    }

    /**
     * Process any pending purge headers from previous requests.
     * Called on 'init' hook if pending purges exist.
     */
    public static function process_pending_purges(): void {
        $pending = get_option( 'wsr_litespeed_pending_purge', [] );
        if ( empty( $pending ) ) return;

        delete_option( 'wsr_litespeed_pending_purge' );

        foreach ( $pending as $value ) {
            if ( ! headers_sent() ) {
                header( 'X-LiteSpeed-Purge: ' . $value, false );
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DETECTION & DIAGNOSTICS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Detect if LiteSpeed Web Server is running.
     */
    public static function is_litespeed(): bool {
        // Check SERVER_SOFTWARE
        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
        if ( $server_software ) {
            if ( stripos( $server_software, 'litespeed' ) !== false ) {
                return true;
            }
        }

        // Check for LSCACHE constants (set by LiteSpeed)
        if ( defined( 'LSCACHE_ADV_CACHE' ) ) return true;

        // Check for LiteSpeed-specific server variable
        if ( isset( $_SERVER['X-LSCACHE'] ) ) return true;

        // Check for LiteSpeed's HTTP/2 push header capability
        $lsws_edition = isset( $_SERVER['LSWS_EDITION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['LSWS_EDITION'] ) ) : '';
        if ( $lsws_edition ) return true;

        return false;
    }

    /**
     * Get LiteSpeed server info for diagnostics.
     */
    public static function get_server_info(): array {
        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown';
        $lsws_edition    = isset( $_SERVER['LSWS_EDITION'] )    ? sanitize_text_field( wp_unslash( $_SERVER['LSWS_EDITION'] ) )    : 'unknown';
        return [
            'detected'        => self::is_litespeed(),
            'server_software' => $server_software,
            'edition'         => $lsws_edition,
            'enabled'         => self::$enabled,
            'cache_ttl'       => self::$settings['litespeed_cache_ttl'] ?? 3600,
            'tag_prefix'      => self::$settings['litespeed_tag_prefix'] ?? 'wsr_',
        ];
    }

    public static function is_active(): bool {
        return self::$enabled;
    }
}

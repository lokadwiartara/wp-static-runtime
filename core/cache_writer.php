<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Writer — saves rendered HTML to the static cache directory.
 *
 * Path format: {WSR_CACHE_DIR}/{scheme}/{host}{uri}/index.html
 * e.g.  wp-content/wsr-cache/https/example.com/blog/post-1/index.html
 */
class Cache_Writer {

    /**
     * Write HTML for a given URI to disk.
     *
     * @param  string $uri  Normalized URI e.g. /blog/post-1/
     * @param  string $html Full HTML string
     * @return bool
     */
    public static function write( $uri, $html ) {
        if ( empty( $html ) || empty( $uri ) ) return false;

        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );

        // Optional HTML minification
        // Filter allows premium optimizer to skip free-tier minify (prevents double minification)
        if ( ! empty( $settings['minify_html'] ) && apply_filters( 'wsr_should_minify', true ) ) {
            $html = self::minify( $html );
        }

        $scheme = Host::current_scheme();
        $host   = Host::current();
        $uri    = trailingslashit( strtok( $uri, '?' ) );
        // Sanitize URI to prevent directory traversal
        $uri    = '/' . ltrim( str_replace( '..', '', $uri ), '/' );
        $dir    = WSR_CACHE_DIR . $scheme . '/' . $host . $uri;
        $file   = $dir . 'index.html';

        // Create directory recursively
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( '[WSR] Cannot create cache dir: ' . $dir );
                return false;
            }
        }

        // Check directory is writable
        if ( ! wp_is_writable( $dir ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[WSR] Cache dir not writable: ' . $dir );
            return false;
        }

        // Write HTML file
        $bytes = file_put_contents( $file, $html, LOCK_EX );
        if ( $bytes === false ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[WSR] Failed to write: ' . $file );
            return false;
        }

        // Optional gzip companion file
        if ( ! empty( $settings['gzip_cache'] ) ) {
            $gz = gzencode( $html, 9 );
            if ( $gz !== false ) {
                file_put_contents( $file . '.gz', $gz, LOCK_EX );
            }
        }

        // Record in DB index
        self::index( $uri, $file, $scheme, $host );

        do_action( 'wsr_cache_written', $uri, $file );

        return true;
    }

    /**
     * Write HTML for an arbitrary full URL (used by crawler).
     *
     * @param  string $url  Full URL e.g. https://example.com/blog/post-1/
     * @param  string $html HTML content
     * @return bool
     */
    public static function write_url( $url, $html ) {
        $parsed = wp_parse_url( $url );
        $scheme = Host::scheme_from_url( $url );
        $host   = Host::from_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

        if ( empty( $host ) ) return false;

        // Sanitize path to prevent directory traversal
        $path = '/' . ltrim( str_replace( '..', '', $path ), '/' );
        $uri  = trailingslashit( $path );
        $dir  = WSR_CACHE_DIR . $scheme . '/' . $host . $uri;
        $file = $dir . 'index.html';

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[WSR] Cannot create cache dir: ' . $dir );
            return false;
        }

        if ( ! wp_is_writable( $dir ) ) return false;

        $settings = get_option( 'wsr_settings', [] );

        if ( ! empty( $settings['minify_html'] ) && apply_filters( 'wsr_should_minify', true ) ) {
            $html = self::minify( $html );
        }

        $bytes = file_put_contents( $file, $html, LOCK_EX );
        if ( $bytes === false ) return false;

        if ( ! empty( $settings['gzip_cache'] ) ) {
            $gz = gzencode( $html, 9 );
            if ( $gz !== false ) file_put_contents( $file . '.gz', $gz, LOCK_EX );
        }

        self::index( $uri, $file, $scheme, $host );

        return true;
    }

    /**
     * Record entry in DB index.
     */
    private static function index( $uri, $file, $scheme, $host ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wsr_cache_index' );
        $url   = $scheme . '://' . $host . $uri;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE url = %s", $url
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                [ 'updated_at' => current_time( 'mysql' ), 'status' => 'active', 'cache_path' => $file ],
                [ 'id' => $existing ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert( $table, [
                'url'        => $url,
                'cache_path' => $file,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
                'status'     => 'active',
            ], [ '%s', '%s', '%s', '%s', '%s' ] );
        }
        // phpcs:enable
    }

    /**
     * Minimal safe HTML minification.
     * Preserves whitespace inside <pre>, <textarea>, <code> blocks.
     */
    private static function minify( $html ) {
        // Preserve blocks that should not be minified
        $preserved = [];
        $html = preg_replace_callback(
            '/<(pre|textarea|code)(\s[^>]*)?>.*?<\/\1>/is',
            function ( $m ) use ( &$preserved ) {
                $i = count( $preserved );
                $preserved[ $i ] = $m[0];
                return '<!--WSR_P_' . $i . '-->';
            },
            $html
        );

        $html = preg_replace( '/<!--(?!\[if).*?-->/s', '', $html );
        $html = preg_replace( '/>\s{2,}</',             '><', $html );

        // Restore preserved blocks
        foreach ( $preserved as $i => $block ) {
            $html = str_replace( '<!--WSR_P_' . $i . '-->', $block, $html );
        }

        return trim( $html );
    }
}

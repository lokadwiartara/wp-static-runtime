<?php
namespace WSR\Optimizer;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asset Optimizer — Cache & Minify External CSS/JS files.
 *
 * Downloads external CSS/JS files, minifies them, caches on disk,
 * and rewrites href/src in HTML to point to cached copies.
 *
 * @since 1.3.0
 */
class Asset_Optimizer {

    /**
     * Get the asset cache directory path.
     */
    public static function get_cache_dir(): string {
        return WP_CONTENT_DIR . '/wsr-cache/assets/';
    }

    /**
     * Get the asset cache directory URL.
     */
    public static function get_cache_url(): string {
        return content_url( 'wsr-cache/assets/' );
    }

    /**
     * Ensure cache directory exists.
     */
    public static function ensure_cache_dir(): bool {
        $dir = self::get_cache_dir();
        if ( ! is_dir( $dir ) ) {
            return @wp_mkdir_p( $dir );
        }
        return true;
    }

    /**
     * Minify CSS using regex-based approach (safe).
     */
    public static function minify_css( string $css ): string {
        // Remove CSS comments
        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
        // Remove whitespace around selectors and properties
        $css = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $css );
        // Compress multiple whitespace
        $css = preg_replace( '/\s{2,}/', ' ', $css );
        // Remove trailing semicolons before closing braces
        $css = str_replace( ';}', '}', $css );
        // Remove newlines
        $css = str_replace( [ "\r\n", "\r", "\n" ], '', $css );
        return trim( $css );
    }

    /**
     * Minify JavaScript using regex-based approach.
     */
    public static function minify_js( string $js ): string {
        // Remove single-line comments (but not URLs with //)
        $js = preg_replace( '#//(?!(?:https?:|/)).*$#m', '', $js );
        // Remove multi-line comments
        $js = preg_replace( '/\/\*.*?\*\//s', '', $js );
        // Remove whitespace around operators and delimiters
        $js = preg_replace( '/\s*([\{\};:,=\(\)\[\]])\s*/', '$1', $js );
        // Compress multiple spaces
        $js = preg_replace( '/\s+/', ' ', $js );
        // Remove spaces between word chars and operators (carefully)
        $js = preg_replace( '/(\w)\s+([\+\-\*\/])\s+(\w)/', '$1$2$3', $js );
        return trim( $js );
    }

    /**
     * Resolve a URL to absolute disk path (for local files) or fetch content (for remote).
     *
     * @return string|null Absolute disk path for local files, or null on failure.
     */
    public static function resolve_url( string $url ): string|null {
        // Normalize URL
        $url = trim( $url );
        if ( empty( $url ) || $url === '#' ) {
            return null;
        }

        // Handle protocol-relative URLs
        if ( strpos( $url, '//' ) === 0 ) {
            $url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
        }

        // If URL is relative, make it absolute
        if ( strpos( $url, 'http' ) !== 0 ) {
            if ( $url[0] === '/' ) {
                $url = home_url( $url );
            } else {
                return null; // Context-relative URLs not supported
            }
        }

        // Parse URL
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return null;
        }

        $site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );
        $url_host = strtolower( $parsed['host'] );

        // Local file: map to disk
        if ( $url_host === $site_host ) {
            $path = $parsed['path'] ?? '';
            if ( empty( $path ) ) {
                return null;
            }
            $local_path = ABSPATH . ltrim( $path, '/' );
            if ( file_exists( $local_path ) && is_readable( $local_path ) ) {
                return $local_path;
            }
        }

        // Remote file: return URL for fetching
        return $url;
    }

    /**
     * Fetch file content via HTTP or disk.
     */
    public static function fetch_file( string $url_or_path ): string|null {
        // Check if it's a local disk path
        if ( file_exists( $url_or_path ) && is_readable( $url_or_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            return @file_get_contents( $url_or_path );
        }

        // Try HTTP fetch
        if ( filter_var( $url_or_path, FILTER_VALIDATE_URL ) ) {
            $response = wp_remote_get( $url_or_path, [
                'timeout'   => 10,
                'sslverify' => false,
                'user-agent' => 'WP Static Runtime Asset Optimizer',
            ] );

            if ( ! is_wp_error( $response ) ) {
                return wp_remote_retrieve_body( $response );
            }
        }

        return null;
    }

    /**
     * Cache and return URL for a CSS file.
     */
    public static function cache_css( string $href, array $settings = [] ): string|null {
        if ( empty( $href ) ) {
            return null;
        }

        // Check exclude patterns
        $excludes = isset( $settings['opt_cache_css_exclude'] )
            ? array_filter( array_map( 'trim', explode( "\n", $settings['opt_cache_css_exclude'] ) ) )
            : [];

        foreach ( $excludes as $pattern ) {
            if ( ! empty( $pattern ) && stripos( $href, $pattern ) !== false ) {
                return null; // Excluded
            }
        }

        self::ensure_cache_dir();
        $cache_dir = self::get_cache_dir();
        $cache_url = self::get_cache_url();

        // Generate cache filename
        $filename = md5( $href ) . '.min.css';
        $cache_path = $cache_dir . $filename;

        // Check if already cached
        if ( file_exists( $cache_path ) ) {
            return $cache_url . $filename;
        }

        // Resolve and fetch
        $source = self::resolve_url( $href );
        if ( ! $source ) {
            return null;
        }

        $content = self::fetch_file( $source );
        if ( ! $content ) {
            return null;
        }

        // Minify
        $minified = self::minify_css( $content );

        // Write to cache
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( @file_put_contents( $cache_path, $minified ) === false ) {
            return null;
        }

        return $cache_url . $filename;
    }

    /**
     * Cache and return URL for a JS file.
     */
    public static function cache_js( string $src, array $settings = [] ): string|null {
        if ( empty( $src ) ) {
            return null;
        }

        // Skip data: URIs and inline scripts
        if ( strpos( $src, 'data:' ) === 0 || strpos( $src, 'javascript:' ) === 0 ) {
            return null;
        }

        // Check exclude patterns
        $excludes = isset( $settings['opt_cache_js_exclude'] )
            ? array_filter( array_map( 'trim', explode( "\n", $settings['opt_cache_js_exclude'] ) ) )
            : [];

        foreach ( $excludes as $pattern ) {
            if ( ! empty( $pattern ) && stripos( $src, $pattern ) !== false ) {
                return null; // Excluded
            }
        }

        self::ensure_cache_dir();
        $cache_dir = self::get_cache_dir();
        $cache_url = self::get_cache_url();

        // Generate cache filename
        $filename = md5( $src ) . '.min.js';
        $cache_path = $cache_dir . $filename;

        // Check if already cached
        if ( file_exists( $cache_path ) ) {
            return $cache_url . $filename;
        }

        // Resolve and fetch
        $source = self::resolve_url( $src );
        if ( ! $source ) {
            return null;
        }

        $content = self::fetch_file( $source );
        if ( ! $content ) {
            return null;
        }

        // Minify
        $minified = self::minify_js( $content );

        // Write to cache
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( @file_put_contents( $cache_path, $minified ) === false ) {
            return null;
        }

        return $cache_url . $filename;
    }

    /**
     * Purge entire asset cache directory.
     */
    public static function purge_asset_cache(): bool {
        $dir = self::get_cache_dir();
        if ( ! is_dir( $dir ) ) {
            return true; // Already gone
        }

        $files = @glob( $dir . '*' );
        if ( empty( $files ) ) {
            return true;
        }

        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            } elseif ( is_dir( $file ) ) {
                self::recursive_rmdir( $file );
            }
        }

        return true;
    }

    /**
     * Recursively remove directory.
     */
    private static function recursive_rmdir( string $dir ): bool {
        if ( ! is_dir( $dir ) ) {
            return false;
        }

        $files = @scandir( $dir );
        if ( $files === false ) {
            return false;
        }

        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            $path = $dir . '/' . $file;
            if ( is_dir( $path ) ) {
                self::recursive_rmdir( $path );
            } else {
                wp_delete_file( $path );
            }
        }

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( isset( $wp_filesystem ) ) {
            return $wp_filesystem->rmdir( $dir );
        }
        return false;
    }
}

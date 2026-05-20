<?php
namespace WSR\Optimizer;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Critical CSS Generator — Extract above-the-fold CSS heuristically.
 *
 * Uses selector-priority rules to extract critical CSS from rendered HTML,
 * without requiring a headless browser.
 *
 * @since 1.3.0
 */
class Critical_CSS_Generator {

    /**
     * Critical selector patterns that should always be included.
     */
    private static array $critical_selectors = [
        'body', 'html', ':root', '*',
        'h1', 'h2', 'h3', 'p', 'a',
        'header', '.header', '#header', '.navbar', '.nav', 'nav',
        '.hero', '.banner', '.jumbotron', '.featured',
        '.site-header', '.site-title', '.menu', '.primary',
    ];

    /**
     * Non-critical selector patterns.
     */
    private static array $non_critical_patterns = [
        'hover', ':active', ':focus', 'animation', 'transition',
        'footer', '.footer', '#footer', '.sidebar', '.widget',
        'comment', '.post-content', '.entry-content',
    ];

    /**
     * Extract critical CSS from rendered HTML heuristically.
     */
    public static function generate( string $html, string $url = '' ): string {
        $critical_css = '';

        // Extract <style> blocks from <head>
        $head_styles = self::extract_head_styles( $html );
        $critical_css .= $head_styles;

        // Extract critical rules from inline styles in <body>
        $body_critical = self::extract_body_critical_css( $html );
        if ( ! empty( $body_critical ) ) {
            $critical_css .= "\n" . $body_critical;
        }

        // Parse CSS and filter for critical selectors
        $filtered = self::filter_critical_rules( $critical_css );

        // Store in transient for later retrieval (persistent cache)
        if ( ! empty( $url ) ) {
            self::store( $url, $filtered );
        }

        return $filtered;
    }

    /**
     * Extract all <style> blocks from <head> section.
     */
    private static function extract_head_styles( string $html ): string {
        $styles = '';

        // Extract head section
        if ( ! preg_match( '/<head[^>]*>(.*?)<\/head>/is', $html, $head_match ) ) {
            return '';
        }

        $head = $head_match[1];

        // Extract all <style> blocks
        if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $head, $matches ) ) {
            foreach ( $matches[1] as $style_content ) {
                $styles .= trim( $style_content ) . "\n";
            }
        }

        return $styles;
    }

    /**
     * Extract critical CSS from inline styles in body (first 1500 chars).
     */
    private static function extract_body_critical_css( string $html ): string {
        $styles = '';

        // Extract body section
        if ( ! preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $body_match ) ) {
            return '';
        }

        $body = substr( $body_match[1], 0, 1500 );

        // Extract <style> blocks from early body content
        if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $body, $matches ) ) {
            foreach ( $matches[1] as $style_content ) {
                $styles .= trim( $style_content ) . "\n";
            }
        }

        return $styles;
    }

    /**
     * Check if a CSS selector should be included in critical CSS.
     */
    private static function is_critical_selector( string $selector ): bool {
        $selector = strtolower( trim( $selector ) );

        // Skip empty
        if ( empty( $selector ) ) {
            return false;
        }

        // Check non-critical patterns
        foreach ( self::$non_critical_patterns as $pattern ) {
            if ( stripos( $selector, $pattern ) !== false ) {
                return false;
            }
        }

        // Check critical patterns
        foreach ( self::$critical_selectors as $critical ) {
            if ( $selector === $critical ||
                 strpos( $selector, '.' . $critical ) !== false ||
                 strpos( $selector, '#' . $critical ) !== false ||
                 strpos( $selector, $critical . ' ' ) === 0 ||
                 strpos( $selector, $critical . ',' ) !== false ) {
                return true;
            }
        }

        // Include selectors that match common layout classes
        if ( preg_match( '/(container|wrapper|main|sidebar|grid|flex|layout)/i', $selector ) ) {
            return true;
        }

        // Include @font-face and @media
        if ( strpos( $selector, '@font-face' ) === 0 ||
             strpos( $selector, '@media' ) === 0 ||
             strpos( $selector, '@import' ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Filter CSS to include only critical rules.
     */
    private static function filter_critical_rules( string $css ): string {
        if ( empty( $css ) ) {
            return '';
        }

        // Normalize: remove comments
        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );

        $critical = '';
        $depth = 0;
        $current_rule = '';
        $in_rule = false;

        // Simple state machine to parse CSS rules
        for ( $i = 0; $i < strlen( $css ); $i++ ) {
            $char = $css[ $i ];

            if ( $char === '{' ) {
                $in_rule = true;
                $depth++;
            } elseif ( $char === '}' ) {
                $depth--;
                $current_rule .= $char;

                if ( $depth === 0 && $in_rule ) {
                    // Extract selector(s)
                    $parts = explode( '{', $current_rule );
                    $selector = trim( $parts[0] );

                    // Check if any sub-selector is critical
                    $sub_selectors = array_map( 'trim', explode( ',', $selector ) );
                    $is_critical = false;

                    foreach ( $sub_selectors as $sub ) {
                        if ( self::is_critical_selector( $sub ) ) {
                            $is_critical = true;
                            break;
                        }
                    }

                    if ( $is_critical ) {
                        $critical .= $current_rule . "\n";
                    }

                    $current_rule = '';
                    $in_rule = false;
                }
            } else {
                $current_rule .= $char;
            }
        }

        // Minify
        $critical = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $critical );
        $critical = preg_replace( '/\s{2,}/', ' ', $critical );
        $critical = str_replace( ';}', '}', $critical );
        $critical = str_replace( [ "\r\n", "\r", "\n" ], '', $critical );

        return trim( $critical );
    }

    /**
     * Get cached critical CSS for a URL (persistent transient cache).
     */
    public static function get_cached( string $url ): string|null {
        $cache_key = 'wsr_critical_' . md5( $url );
        $cached = get_transient( $cache_key );
        return $cached ?: null;
    }

    /**
     * Store critical CSS for a URL (persistent transient cache, 1 day TTL).
     */
    public static function store( string $url, string $css ): void {
        $cache_key = 'wsr_critical_' . md5( $url );
        set_transient( $cache_key, $css, DAY_IN_SECONDS );
    }

    /**
     * Purge cached critical CSS.
     */
    public static function purge( string $url = '' ): void {
        if ( ! empty( $url ) ) {
            $cache_key = 'wsr_critical_' . md5( $url );
            delete_transient( $cache_key );
        }
    }

    /**
     * Generate critical CSS from registered WordPress stylesheets (fallback method).
     * Reads CSS files from disk instead of making self-request.
     *
     * @return string Critical CSS extracted from registered styles.
     */
    public static function generate_from_registered_styles(): string {
        global $wp_styles;
        if ( ! $wp_styles ) {
            return '';
        }

        $all_css = '';
        $content_url  = rtrim( content_url(), '/' );
        $content_path = rtrim( WP_CONTENT_DIR, DIRECTORY_SEPARATOR );
        $site_url     = rtrim( site_url(), '/' );
        $abspath      = rtrim( ABSPATH, DIRECTORY_SEPARATOR );

        foreach ( $wp_styles->queue as $handle ) {
            $style = $wp_styles->registered[ $handle ] ?? null;
            if ( ! $style || empty( $style->src ) ) {
                continue;
            }

            $src = $style->src;

            // Normalize protocol-relative URLs
            if ( strpos( $src, '//' ) === 0 ) {
                $src = 'https:' . $src;
            }

            // Convert URL to disk path
            if ( strpos( $src, $content_url ) === 0 ) {
                $path = $content_path . substr( $src, strlen( $content_url ) );
            } elseif ( strpos( $src, $site_url ) === 0 ) {
                $path = $abspath . ltrim( substr( $src, strlen( $site_url ) ), "/\\" );
            } else {
                // External CDN URLs — skip
                continue;
            }

            // Remove query string
            $path = strtok( $path, '?' );

            // Read file from disk
            if ( file_exists( $path ) && is_readable( $path ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
                $css = @file_get_contents( $path );
                if ( $css ) {
                    $all_css .= $css . "\n";
                }
            }
        }

        if ( empty( $all_css ) ) {
            return '';
        }

        // Filter for critical rules
        return self::filter_critical_rules( $all_css );
    }
}

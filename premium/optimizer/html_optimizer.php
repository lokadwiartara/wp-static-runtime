<?php
namespace WSR\Premium\Optimizer;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HTML Optimizer — PageSpeed Insights optimization engine.
 *
 * Handles:
 *  1.  HTML Minification (safe — preserves <pre>, <textarea>, <code>, <script>)
 *  2.  JavaScript Deferral
 *  3.  Image Optimization (smart LCP detection + lazy load)
 *  4.  LCP Image <link rel="preload"> injection in <head>
 *  5.  CSS Minification (inline <style> blocks)
 *  6.  Render-Blocking CSS Removal (defer external CSS)
 *  7.  Preconnect / DNS Prefetch injection
 *  8.  Font Display optimization (font-display: swap)
 *  9.  Unused CSS/JS Removal (emoji, embed, jquery-migrate)
 *  10. Critical CSS inlining
 *
 * @since 1.3.0
 */
class HTML_Optimizer {

    /** @var array Preserved blocks placeholder storage */
    private static $preserved = [];

    public static function boot() {
        add_filter( 'wsr_optimize_html', [ __CLASS__, 'optimize' ], 10 );

        // Prevent double minification: if premium optimizer is active,
        // skip free-tier minification in Cache_Writer
        add_filter( 'wsr_should_minify', '__return_false' );
    }

    /**
     * Main optimization pipeline.
     */
    public static function optimize( $html ) {
        $settings = get_option( 'wsr_settings', [] );

        // ── Phase 1: Remove unused assets (must run before defer/minify) ──────
        if ( ! empty( $settings['opt_remove_emoji'] ) ) {
            $html = self::remove_wp_emoji( $html );
        }
        if ( ! empty( $settings['opt_remove_embed'] ) ) {
            $html = self::remove_wp_embed( $html );
        }
        if ( ! empty( $settings['opt_remove_jquery_migrate'] ) ) {
            $html = self::remove_jquery_migrate( $html );
        }
        if ( ! empty( $settings['opt_remove_assets_blacklist'] ) ) {
            $html = self::remove_blacklisted_assets( $html, $settings['opt_remove_assets_blacklist'] );
        }

        // ── Phase 2: Font Display optimization ───────────────────────────────
        if ( ! empty( $settings['opt_font_display'] ) ) {
            $html = self::optimize_fonts( $html );
        }

        // ── Phase 2.5a: Cache & Minify External CSS ──────────────────────────
        if ( ! empty( $settings['opt_cache_external_css'] ) ) {
            $html = self::cache_external_css( $html, $settings );
        }

        // ── Phase 2.5b: Cache & Minify External JS ───────────────────────────
        if ( ! empty( $settings['opt_cache_external_js'] ) ) {
            $html = self::cache_external_js( $html, $settings );
        }

        // ── Phase 3: CSS optimization ────────────────────────────────────────
        if ( ! empty( $settings['opt_minify_css'] ) ) {
            $html = self::minify_inline_css( $html );
        }
        if ( ! empty( $settings['opt_defer_css'] ) ) {
            $html = self::defer_css( $html, $settings['opt_defer_css_exclude'] ?? '' );
        }

        // ── Phase 4: Critical CSS injection ──────────────────────────────────
        if ( ! empty( $settings['opt_critical_css'] ) && ! empty( $settings['opt_critical_css_content'] ) ) {
            $html = self::inject_critical_css( $html, $settings['opt_critical_css_content'] );
        }

        // ── Phase 4.5: Auto-generate Critical CSS ────────────────────────────
        if ( ! empty( $settings['opt_critical_css_auto'] ) ) {
            $html = self::inject_auto_critical_css( $html );
        }

        // ── Phase 5: Preconnect / DNS Prefetch ───────────────────────────────
        if ( ! empty( $settings['opt_preconnect'] ) ) {
            $html = self::inject_preconnect( $html, $settings['opt_preconnect_domains'] ?? '' );
        }

        // ── Phase 6: Defer JS ────────────────────────────────────────────────
        if ( ! empty( $settings['opt_defer_js'] ) ) {
            $html = self::defer_js( $html );
        }

        // ── Phase 7: Image Optimization (LCP + Lazy Load + Preload) ──────────
        if ( ! empty( $settings['opt_lazy_load'] ) ) {
            $html = self::optimize_images( $html );
        }

        // ── Phase 8: HTML Minification (LAST — after all other transforms) ───
        if ( ! empty( $settings['opt_minify_html'] ) ) {
            $html = self::minify_html( $html );
        }

        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. HTML MINIFICATION (safe — preserves pre/textarea/code/script)
    // ═══════════════════════════════════════════════════════════════════════════

    private static function minify_html( $html ) {
        // Preserve blocks that should NOT be minified
        $html = self::preserve_blocks( $html );

        // Remove HTML comments (excluding conditional comments <!--[if... )
        $html = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html );

        // Compress whitespace between tags (safe now that pre/textarea are preserved)
        $html = preg_replace( '/>\s+</', '><', $html );

        // Compress multiple spaces/newlines to single space
        $html = preg_replace( '/\s{2,}/', ' ', $html );

        // Restore preserved blocks
        $html = self::restore_blocks( $html );

        return trim( $html );
    }

    /**
     * Extract <pre>, <textarea>, <code>, <script>, <style> blocks and replace
     * with placeholders to protect them from minification.
     */
    private static function preserve_blocks( $html ) {
        self::$preserved = [];
        $tags = [ 'pre', 'textarea', 'code', 'script', 'style' ];

        foreach ( $tags as $tag ) {
            $html = preg_replace_callback(
                '/<' . $tag . '(\s[^>]*)?>.*?<\/' . $tag . '>/is',
                function ( $m ) {
                    $index = count( self::$preserved );
                    self::$preserved[ $index ] = $m[0];
                    return '<!--WSR_PRESERVE_' . $index . '-->';
                },
                $html
            );
        }

        return $html;
    }

    /**
     * Restore preserved blocks from placeholders.
     */
    private static function restore_blocks( $html ) {
        foreach ( self::$preserved as $index => $block ) {
            $html = str_replace( '<!--WSR_PRESERVE_' . $index . '-->', $block, $html );
        }
        self::$preserved = [];
        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. DEFER JAVASCRIPT
    // ═══════════════════════════════════════════════════════════════════════════

    private static function defer_js( $html ) {
        return preg_replace_callback( '/<script\s+([^>]*src=[^>]*)>/i', function ( $matches ) {
            $attrs = $matches[1];

            // Skip if already has defer or async
            if ( stripos( $attrs, 'defer' ) !== false || stripos( $attrs, 'async' ) !== false ) {
                return $matches[0];
            }

            // Skip jQuery core to avoid breaking inline scripts
            if ( stripos( $attrs, 'jquery.min.js' ) !== false || stripos( $attrs, 'jquery.js' ) !== false ) {
                return $matches[0];
            }

            return '<script defer ' . $attrs . '>';
        }, $html );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3 & 4. IMAGE OPTIMIZATION (Smart LCP + Lazy Load + Preload Link)
    // ═══════════════════════════════════════════════════════════════════════════

    private static function optimize_images( $html ) {
        if ( ! preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $body_match ) ) {
            return $html;
        }

        $body        = $body_match[1];
        $image_count = 0;
        $lcp_src     = '';

        $new_body = preg_replace_callback( '/<img\s+([^>]+)>/i', function ( $matches ) use ( &$image_count, &$lcp_src ) {
            $attrs = $matches[1];

            // Skip data URIs
            if ( stripos( $attrs, 'data:image' ) !== false ) {
                return $matches[0];
            }

            // Check if this is a small image (logo/icon) — skip for LCP candidate
            $is_small = self::is_small_image( $attrs );

            $image_count++;

            if ( empty( $lcp_src ) && ! $is_small ) {
                // This is the LCP candidate — first non-small image
                $attrs = preg_replace( '/loading\s*=\s*["\']lazy["\']/i', '', $attrs );

                if ( stripos( $attrs, 'fetchpriority=' ) === false ) {
                    $attrs .= ' fetchpriority="high"';
                }
                if ( stripos( $attrs, 'loading=' ) === false ) {
                    $attrs .= ' loading="eager"';
                }

                // Extract src for <link rel="preload"> injection
                if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src_match ) ) {
                    $lcp_src = $src_match[1];
                }
            } else {
                // Subsequent images or small images → lazy load
                $attrs = preg_replace( '/loading\s*=\s*["\']eager["\']/i', '', $attrs );

                if ( stripos( $attrs, 'loading=' ) === false ) {
                    $attrs .= ' loading="lazy"';
                }
                if ( stripos( $attrs, 'decoding=' ) === false ) {
                    $attrs .= ' decoding="async"';
                }
            }

            $attrs = preg_replace( '/\s{2,}/', ' ', $attrs );
            return '<img ' . trim( $attrs ) . '>';
        }, $body );

        $html = str_replace( $body_match[1], $new_body, $html );

        // Inject <link rel="preload"> for LCP image in <head>
        if ( $lcp_src ) {
            $preload = '<link rel="preload" as="image" href="' . esc_attr( $lcp_src ) . '" fetchpriority="high">';
            $html    = str_replace( '</head>', $preload . "\n</head>", $html );
        }

        return $html;
    }

    /**
     * Detect if an image is likely a logo, icon, or avatar (not LCP-worthy).
     */
    private static function is_small_image( $attrs ) {
        // Check explicit width/height attributes < 100px
        if ( preg_match( '/\bwidth\s*=\s*["\']?(\d+)/i', $attrs, $w ) ) {
            if ( (int) $w[1] < 100 ) return true;
        }
        if ( preg_match( '/\bheight\s*=\s*["\']?(\d+)/i', $attrs, $h ) ) {
            if ( (int) $h[1] < 100 ) return true;
        }

        // Check CSS class names that indicate logo/icon/avatar
        if ( preg_match( '/class\s*=\s*["\']([^"\']+)["\']/i', $attrs, $cls ) ) {
            $class_str = strtolower( $cls[1] );
            $skip_classes = [ 'logo', 'icon', 'avatar', 'favicon', 'site-logo', 'custom-logo', 'wp-post-image' ];
            foreach ( $skip_classes as $skip ) {
                if ( strpos( $class_str, $skip ) !== false ) return true;
            }
            // Prefer hero/banner classes
            $hero_classes = [ 'hero', 'banner', 'featured', 'cover', 'jumbotron' ];
            foreach ( $hero_classes as $hero ) {
                if ( strpos( $class_str, $hero ) !== false ) return false;
            }
        }

        // Check SVG sources (usually icons)
        if ( preg_match( '/src\s*=\s*["\'][^"\']*\.svg/i', $attrs ) ) {
            return true;
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. CSS MINIFICATION (inline <style> blocks)
    // ═══════════════════════════════════════════════════════════════════════════

    private static function minify_inline_css( $html ) {
        return preg_replace_callback( '/<style(\s[^>]*)?>(.+?)<\/style>/is', function ( $matches ) {
            $tag_attrs = $matches[1] ?? '';
            $css       = $matches[2];

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

            return '<style' . $tag_attrs . '>' . trim( $css ) . '</style>';
        }, $html );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. RENDER-BLOCKING CSS REMOVAL (defer external CSS)
    // ═══════════════════════════════════════════════════════════════════════════

    private static function defer_css( $html, $exclude_patterns = '' ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", $exclude_patterns ) ) );

        return preg_replace_callback(
            '/<link\s+([^>]*rel\s*=\s*["\']stylesheet["\'][^>]*)>/i',
            function ( $matches ) use ( $excludes ) {
                $attrs    = $matches[1];
                $full_tag = $matches[0];

                // Skip if already has media != "all" or "screen" (e.g. "print")
                if ( preg_match( '/media\s*=\s*["\'](?!all|screen)[^"\']+["\']/i', $attrs ) ) {
                    return $full_tag;
                }

                // Check exclude patterns
                foreach ( $excludes as $pattern ) {
                    if ( ! empty( $pattern ) && stripos( $attrs, $pattern ) !== false ) {
                        return $full_tag;
                    }
                }

                // Extract href for noscript fallback
                $href = '';
                if ( preg_match( '/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $href_match ) ) {
                    $href = $href_match[1];
                }

                // Convert to non-blocking: media="print" onload="this.media='all'"
                // Remove existing media attribute
                $attrs = preg_replace( '/media\s*=\s*["\'][^"\']*["\']/i', '', $attrs );
                $attrs = trim( $attrs ) . ' media="print" onload="this.media=\'all\'"';

                $deferred  = '<link ' . $attrs . '>';
                $deferred .= '<noscript><link rel="stylesheet" href="' . esc_attr( $href ) . '"></noscript>';

                return $deferred;
            },
            $html
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 7. PRECONNECT / DNS PREFETCH
    // ═══════════════════════════════════════════════════════════════════════════

    private static function inject_preconnect( $html, $custom_domains = '' ) {
        $domains = [];

        // Auto-detect external domains from href/src attributes
        preg_match_all( '/(?:href|src)\s*=\s*["\']https?:\/\/([^"\'\/]+)/i', $html, $matches );
        if ( ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $domain ) {
                $domain = strtolower( $domain );
                // Skip same-site domain
                $site_host = strtolower( parse_url( home_url(), PHP_URL_HOST ) ?: '' );
                if ( $domain === $site_host ) continue;
                $domains[ $domain ] = true;
            }
        }

        // Add user-specified custom domains
        if ( ! empty( $custom_domains ) ) {
            foreach ( explode( "\n", $custom_domains ) as $d ) {
                $d = trim( $d );
                if ( $d ) {
                    // Strip protocol if provided
                    $d = preg_replace( '#^https?://#', '', $d );
                    $d = rtrim( $d, '/' );
                    $domains[ strtolower( $d ) ] = true;
                }
            }
        }

        if ( empty( $domains ) ) return $html;

        // Known origins that benefit from preconnect (with crossorigin)
        $crossorigin_domains = [
            'fonts.googleapis.com', 'fonts.gstatic.com',
            'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com',
        ];

        $links = '';
        foreach ( array_keys( $domains ) as $domain ) {
            $needs_crossorigin = in_array( $domain, $crossorigin_domains, true )
                || strpos( $domain, 'font' ) !== false
                || strpos( $domain, 'cdn' ) !== false;

            $cross = $needs_crossorigin ? ' crossorigin' : '';
            $links .= '<link rel="preconnect" href="https://' . esc_attr( $domain ) . '"' . $cross . '>' . "\n";
            $links .= '<link rel="dns-prefetch" href="//' . esc_attr( $domain ) . '">' . "\n";
        }

        return str_replace( '</head>', $links . '</head>', $html );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 8. FONT DISPLAY OPTIMIZATION
    // ═══════════════════════════════════════════════════════════════════════════

    private static function optimize_fonts( $html ) {
        // Inject font-display: swap into @font-face blocks that don't have it
        $html = preg_replace_callback( '/@font-face\s*\{([^}]+)\}/i', function ( $matches ) {
            $block = $matches[1];
            if ( stripos( $block, 'font-display' ) === false ) {
                $block = rtrim( $block, "; \n\r\t" ) . '; font-display: swap;';
            }
            return '@font-face {' . $block . '}';
        }, $html );

        // Add &display=swap to Google Fonts URLs that don't have it
        $html = preg_replace_callback(
            '/(href\s*=\s*["\']https?:\/\/fonts\.googleapis\.com\/css2?[^"\']*)/i',
            function ( $matches ) {
                $url = $matches[1];
                if ( stripos( $url, 'display=' ) === false ) {
                    $separator = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
                    $url .= $separator . 'display=swap';
                }
                return $url;
            },
            $html
        );

        // Preload .woff2 font files found in @font-face src declarations
        preg_match_all( '/url\s*\(\s*["\']?([^"\')\s]+\.woff2)["\']?\s*\)/i', $html, $font_matches );
        if ( ! empty( $font_matches[1] ) ) {
            $font_preloads = '';
            $seen = [];
            foreach ( $font_matches[1] as $font_url ) {
                if ( isset( $seen[ $font_url ] ) ) continue;
                $seen[ $font_url ] = true;
                $font_preloads .= '<link rel="preload" as="font" href="' . esc_attr( $font_url ) . '" type="font/woff2" crossorigin>' . "\n";
            }
            if ( $font_preloads ) {
                $html = str_replace( '</head>', $font_preloads . '</head>', $html );
            }
        }

        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 9. UNUSED CSS/JS REMOVAL
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Remove WordPress emoji script and inline styles.
     */
    private static function remove_wp_emoji( $html ) {
        // Remove inline emoji detection script
        $html = preg_replace( '/<script[^>]*>.*?(?:twemoji|wp-emoji).*?<\/script>/is', '', $html );
        // Remove emoji CSS
        $html = preg_replace( '/<style[^>]*>.*?(?:img\.wp-smiley|img\.emoji).*?<\/style>/is', '', $html );
        // Remove emoji <link> stylesheet
        $html = preg_replace( '/<link[^>]*wp-emoji[^>]*>/i', '', $html );
        return $html;
    }

    /**
     * Remove WordPress embed script.
     */
    private static function remove_wp_embed( $html ) {
        $html = preg_replace( '/<script[^>]*wp-embed\.min\.js[^>]*><\/script>/i', '', $html );
        $html = preg_replace( '/<script[^>]*wp-embed\.js[^>]*><\/script>/i', '', $html );
        return $html;
    }

    /**
     * Remove jQuery Migrate (optional — may break old plugins).
     */
    private static function remove_jquery_migrate( $html ) {
        $html = preg_replace( '/<script[^>]*jquery-migrate[^>]*><\/script>/i', '', $html );
        return $html;
    }

    /**
     * Remove assets matching user-defined blacklist patterns.
     */
    private static function remove_blacklisted_assets( $html, $blacklist ) {
        $patterns = array_filter( array_map( 'trim', explode( "\n", $blacklist ) ) );
        foreach ( $patterns as $pattern ) {
            if ( empty( $pattern ) ) continue;
            $escaped = preg_quote( $pattern, '/' );
            // Remove <script> tags matching pattern
            $html = preg_replace( '/<script[^>]*' . $escaped . '[^>]*>.*?<\/script>/is', '', $html );
            // Remove <link> tags matching pattern
            $html = preg_replace( '/<link[^>]*' . $escaped . '[^>]*>/i', '', $html );
        }
        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 10. CRITICAL CSS INLINING
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Inject user-provided critical CSS into <head>.
     */
    private static function inject_critical_css( $html, $critical_css ) {
        $critical_css = trim( $critical_css );
        if ( empty( $critical_css ) ) return $html;

        $style = '<style id="wsr-critical-css">' . $critical_css . '</style>';
        // Inject right after <head> opening tag (before any other stylesheets)
        $html = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $style, $html, 1 );

        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 11. CACHE & MINIFY EXTERNAL CSS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Cache and minify external CSS files.
     */
    private static function cache_external_css( $html, $settings ) {
        if ( ! class_exists( 'WSR\\Optimizer\\Asset_Optimizer' ) ) {
            return $html;
        }

        return preg_replace_callback(
            '/<link\s+([^>]*rel\s*=\s*["\']stylesheet["\'][^>]*)>/i',
            function ( $matches ) use ( $settings ) {
                $attrs = $matches[1];
                $full_tag = $matches[0];

                // Extract href
                if ( ! preg_match( '/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $href_match ) ) {
                    return $full_tag;
                }

                $href = $href_match[1];

                // Try to cache
                $cached_url = \WSR\Optimizer\Asset_Optimizer::cache_css( $href, $settings );
                if ( ! $cached_url ) {
                    return $full_tag; // Return original if caching fails
                }

                // Rewrite href
                $new_attrs = preg_replace(
                    '/href\s*=\s*["\']([^"\']+)["\']/i',
                    'href="' . esc_attr( $cached_url ) . '"',
                    $attrs,
                    1
                );

                return '<link ' . $new_attrs . '>';
            },
            $html
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 12. CACHE & MINIFY EXTERNAL JS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Cache and minify external JS files.
     */
    private static function cache_external_js( $html, $settings ) {
        if ( ! class_exists( 'WSR\\Optimizer\\Asset_Optimizer' ) ) {
            return $html;
        }

        return preg_replace_callback(
            '/<script\s+([^>]*src=[^>]*)>/i',
            function ( $matches ) use ( $settings ) {
                $attrs = $matches[1];
                $full_tag = $matches[0];

                // Skip if already has defer or async (let defer_js handle it)
                if ( stripos( $attrs, 'defer' ) !== false || stripos( $attrs, 'async' ) !== false ) {
                    return $full_tag;
                }

                // Extract src
                if ( ! preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src_match ) ) {
                    return $full_tag;
                }

                $src = $src_match[1];

                // Try to cache
                $cached_url = \WSR\Optimizer\Asset_Optimizer::cache_js( $src, $settings );
                if ( ! $cached_url ) {
                    return $full_tag; // Return original if caching fails
                }

                // Rewrite src
                $new_attrs = preg_replace(
                    '/src\s*=\s*["\']([^"\']+)["\']/i',
                    'src="' . esc_attr( $cached_url ) . '"',
                    $attrs,
                    1
                );

                return '<script ' . $new_attrs . '>';
            },
            $html
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 13. AUTO-GENERATE CRITICAL CSS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Inject auto-generated critical CSS into <head>.
     */
    private static function inject_auto_critical_css( $html ) {
        if ( ! class_exists( 'WSR\\Optimizer\\Critical_CSS_Generator' ) ) {
            return $html;
        }

        $url = isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] )
            ? ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            : home_url( '/' );

        // Try to get cached critical CSS
        $critical_css = \WSR\Optimizer\Critical_CSS_Generator::get_cached( $url );

        if ( empty( $critical_css ) ) {
            // Generate if not cached
            $critical_css = \WSR\Optimizer\Critical_CSS_Generator::generate( $html, $url );
        }

        if ( empty( $critical_css ) ) {
            return $html;
        }

        $style = '<style id="wsr-critical-css-auto">' . $critical_css . '</style>';
        $html = preg_replace( '/(<head[^>]*>)/i', '$1' . "\n" . $style, $html, 1 );

        return $html;
    }
}

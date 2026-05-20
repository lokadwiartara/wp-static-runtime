<?php

namespace WSR\Server;

if (! defined('ABSPATH')) exit;

/**
 * Apache — generates .htaccess rewrite rules so Apache serves static HTML
 * directly without invoking PHP at all.
 *
 * Benchmark: ~5–15ms TTFB vs ~200–800ms with PHP.
 */
class Apache
{

    private static $marker_start = '# BEGIN StatixPress';
    private static $marker_end   = '# END StatixPress';

    /**
     * Write or update rules if Apache is detected and the option is enabled.
     */
    public static function maybe_write_rules(): void
    {
        if (! is_admin()) return;

        $settings = get_option('wsr_settings', \WSR\Constants::defaults());
        if (empty($settings['apache_rewrite'])) {
            self::remove_rules();
            return;
        }

        if (self::is_apache()) {
            self::write_rules();
        }
    }

    /**
     * Write WSR rewrite block to .htaccess.
     */
    public static function write_rules(): bool
    {
        $htaccess = ABSPATH . '.htaccess';
        if (! file_exists($htaccess) || ! wp_is_writable($htaccess)) return false;

        $rules   = self::generate_rules();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $content = file_get_contents($htaccess);

        // Remove old block
        $content = self::remove_block($content);

        // Insert before # BEGIN WordPress
        if (strpos($content, '# BEGIN WordPress') !== false) {
            $content = str_replace('# BEGIN WordPress', $rules . "\n# BEGIN WordPress", $content);
        } else {
            $content = $rules . "\n" . $content;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return (bool) file_put_contents($htaccess, $content, LOCK_EX);
    }

    /**
     * Remove WSR block from .htaccess.
     */
    public static function remove_rules(): void
    {
        $htaccess = ABSPATH . '.htaccess';
        if (! file_exists($htaccess)) return;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $content = file_get_contents($htaccess);
        $content = self::remove_block($content);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($htaccess, $content, LOCK_EX);
    }

    /**
     * Generate the full .htaccess rewrite block.
     */
    public static function generate_rules(): string
    {
        $home   = home_url();
        $scheme = \WSR\Host::scheme_from_url( $home );
        $host   = \WSR\Host::from_url( $home );

        // detect subdirectory (e.g. /new/)
        $path = wp_parse_url($home, PHP_URL_PATH);
        $base = $path ? rtrim($path, '/') . '/' : '/';

        // cache path FIXED (no dynamic bug)
        $cache_path = trim($base, '/') . '/wp-content/wsr-cache';

        $rules  = self::$marker_start . "\n";
        $rules .= "<IfModule mod_rewrite.c>\n";
        $rules .= "RewriteEngine On\n";
        $rules .= "RewriteBase {$base}\n\n";

        // ① SKIP WP INTERNALS — admin-ajax.php, admin, login, json, cron
        //    Must come FIRST before any other rule so AJAX calls are never touched.
        $rules .= "# skip wp internals (admin-ajax, admin, login, json, cron)\n";
        $rules .= "RewriteCond %{REQUEST_URI} ^{$base}wp-(admin|login|cron) [NC,OR]\n";
        $rules .= "RewriteCond %{REQUEST_URI} ^{$base}wp-admin/admin-ajax\\.php [NC]\n";
        $rules .= "RewriteRule . - [L]\n\n";

        // ② FORCE TRAILING SLASH — only for extensionless, non-admin URLs
        $rules .= "# force trailing slash (skip .php and other files)\n";
        $rules .= "RewriteCond %{REQUEST_URI} !/$\n";
        $rules .= "RewriteCond %{REQUEST_URI} !\\.[a-zA-Z0-9]{1,5}$\n";
        $rules .= "RewriteCond %{REQUEST_URI} !^{$base}wp-(admin|login|json|cron) [NC]\n";
        $rules .= "RewriteRule ^(.*)$ %{REQUEST_URI}/ [R=301,L]\n\n";

        // ③ SERVE CACHE — only when NO query string present
        $rules .= "# serve cache — skip entirely if query string present\n";
        $rules .= "RewriteCond %{QUERY_STRING} ^$\n";
        $rules .= "RewriteCond %{DOCUMENT_ROOT}/{$cache_path}/{$scheme}/{$host}%{REQUEST_URI}/index.html -f\n";
        $rules .= "RewriteRule ^(.*)$ /{$cache_path}/{$scheme}/{$host}%{REQUEST_URI}/index.html [L]\n\n";

        // ④ FALLBACK TO WORDPRESS
        $rules .= "# fallback WP\n";
        $rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $rules .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $rules .= "RewriteRule . {$base}index.php [L]\n";

        $rules .= "</IfModule>\n";
        $rules .= self::$marker_end . "\n";

        return $rules;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function remove_block(string $content): string
    {
        return preg_replace(
            '/' . preg_quote(self::$marker_start, '/') . '.*?' . preg_quote(self::$marker_end, '/') . '\n?/s',
            '',
            $content
        );
    }

    private static function get_relative_cache_path(): string
    {
        $home_path = wp_parse_url(home_url(), PHP_URL_PATH);
        $home_path = $home_path ? trim($home_path, '/') : '';

        $content_path = str_replace(ABSPATH, '', WP_CONTENT_DIR);
        $content_path = trim($content_path, '/');

        return ($home_path ? $home_path . '/' : '') . $content_path . '/wsr-cache';
    }

    private static function is_apache(): bool
    {
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $software = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
            return stripos($software, 'apache') !== false
                || stripos($software, 'litespeed') !== false;
        }
        return file_exists(ABSPATH . '.htaccess');
    }
}

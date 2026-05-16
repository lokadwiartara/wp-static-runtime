<?php
namespace WSR\Server;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Nginx — generates a server block snippet for Nginx to serve
 * WP Static Runtime cache files directly without PHP-FPM.
 *
 * Note: Nginx config cannot be written programmatically by PHP in most setups.
 * This class generates the snippet for copy-paste into nginx.conf or a vhost include.
 */
class Nginx {

    /**
     * Called on admin boot — show a notice if Nginx is detected and no manual config exists.
     */
    public static function maybe_write_rules(): void {
        if ( ! self::is_nginx() ) return;

        add_action( 'admin_notices', [ __CLASS__, 'nginx_notice' ] );
    }

    /**
     * Generate the Nginx location block snippet.
     */
    public static function generate_config(): string {
        $rel_cache_path = self::get_cache_path_relative_to_docroot();
        $home     = home_url();
        $scheme   = \WSR\Host::scheme_from_url( $home );
        $host     = \WSR\Host::from_url( $home );
        $doc_root = rtrim( $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH, '/' );

        $conf  = "# ── WP Static Runtime — Nginx Static Serving ──────────────────────\n";
        $conf .= "# Add this inside your server {} block, BEFORE the WordPress location.\n\n";

        $conf .= "set \$wsr_cache_file \"{$doc_root}/{$rel_cache_path}/{$scheme}/{$host}\$uri/index.html\";\n\n";

        $conf .= "# Skip cache conditions\n";
        $conf .= "set \$wsr_skip 0;\n";
        $conf .= "if (\$request_method = POST)             { set \$wsr_skip 1; }\n";
        $conf .= "if (\$query_string != \"\")              { set \$wsr_skip 1; }\n";
        $conf .= "if (\$http_cookie ~* \"wordpress_logged_in\") { set \$wsr_skip 1; }\n";
        $conf .= "if (\$http_cookie ~* \"woocommerce_cart_hash\")     { set \$wsr_skip 1; }\n";
        $conf .= "if (\$http_cookie ~* \"woocommerce_items_in_cart\") { set \$wsr_skip 1; }\n";
        $conf .= "if (\$request_uri ~* \"/(wp-admin|wp-login\.php|wp-cron\.php|wp-json)\") { set \$wsr_skip 1; }\n\n";

        $conf .= "# Serve static HTML\n";
        $conf .= "location / {\n";
        $conf .= "    try_files \$uri \$uri/ @wsr_static @wordpress;\n";
        $conf .= "}\n\n";

        $conf .= "location @wsr_static {\n";
        $conf .= "    internal;\n";
        $conf .= "    add_header X-Cache \"HIT\" always;\n";
        $conf .= "    add_header X-Cache-Engine \"WP-Static-Runtime\" always;\n";
        $conf .= "    if (\$wsr_skip = 0) {\n";
        $conf .= "        try_files \$wsr_cache_file =404;\n";
        $conf .= "    }\n";
        $conf .= "}\n\n";

        $conf .= "location @wordpress {\n";
        $conf .= "    internal;\n";
        $conf .= "    add_header X-Cache \"MISS\" always;\n";
        $conf .= "    fastcgi_pass unix:/run/php/php8.1-fpm.sock; # adjust to your PHP-FPM socket\n";
        $conf .= "    include fastcgi_params;\n";
        $conf .= "    fastcgi_param SCRIPT_FILENAME \$document_root/index.php;\n";
        $conf .= "}\n";
        $conf .= "# ──────────────────────────────────────────────────────────────────\n";

        return $conf;
    }

    /**
     * Admin notice to prompt manual Nginx configuration.
     */
    public static function nginx_notice(): void {
        $settings = get_option( 'wsr_settings', [] );
        if ( ! empty( $settings['nginx_notice_dismissed'] ) ) return;

        $config = self::generate_config();
        ?>
        <div class="notice notice-info is-dismissible" id="wsr-nginx-notice">
            <p><strong>WP Static Runtime:</strong> Nginx detected.
            Add the snippet below to your server block to enable static file serving at the server level (fastest possible TTFB).</p>
            <details>
                <summary style="cursor:pointer;font-weight:600;">Show Nginx config snippet</summary>
                <pre style="background:#1e1e2e;color:#cdd6f4;padding:16px;overflow:auto;border-radius:4px;margin-top:8px;"><?php echo esc_html( $config ); ?></pre>
            </details>
        </div>
        <?php
    }

    /**
     * Get cache path relative to DOCUMENT_ROOT (handles WordPress in subfolder).
     *
     * @return string e.g. "new/wp-content/wsr-cache"
     */
    private static function get_cache_path_relative_to_docroot(): string {
        $cache_dir = realpath( WSR_CACHE_DIR );
        $doc_root  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : ABSPATH;
        if ( ! $doc_root ) {
            $doc_root = ABSPATH;
        }
        $rel = str_replace( $doc_root, '', $cache_dir );
        $rel = ltrim( $rel, DIRECTORY_SEPARATOR );
        return str_replace( DIRECTORY_SEPARATOR, '/', $rel );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function is_nginx(): bool {
        return isset( $_SERVER['SERVER_SOFTWARE'] )
            && stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false;
    }
}
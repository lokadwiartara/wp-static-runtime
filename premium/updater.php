<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GitHub Releases auto-updater.
 *
 * Setup:
 *  1. Upload plugin zip sebagai release asset di GitHub (nama file: wp-static-runtime.zip)
 *  2. Opsional: define( 'WSR_GITHUB_REPO', 'username/repo' ) di wp-config.php
 *     Default: wpstaticruntime/wp-static-runtime
 *
 * Cara kerja:
 *  - Setiap 6 jam cek GitHub API untuk release terbaru
 *  - Jika versi lebih baru ditemukan, WordPress menampilkan notif update di dashboard
 *  - Admin klik "Update Now" → WordPress download & install otomatis
 */
class Updater {

    const PLUGIN_FILE   = 'wp-static-runtime/wp-static-runtime.php';
    const PLUGIN_SLUG   = 'wp-static-runtime';
    const DEFAULT_REPO  = 'wpstaticruntime/wp-static-runtime';
    const TRANSIENT_KEY = 'wsr_github_release';
    const CACHE_TTL     = 21600; // 6 jam

    public static function init(): void {
        add_filter( 'site_transient_update_plugins', [ __CLASS__, 'inject_update'  ] );
        add_filter( 'plugins_api',                   [ __CLASS__, 'plugin_info'    ], 20, 3 );
        add_filter( 'upgrader_source_selection',     [ __CLASS__, 'fix_folder'     ], 10, 4 );
        add_action( 'upgrader_process_complete',     [ __CLASS__, 'clear_cache'    ], 10, 2 );
        add_action( 'admin_init',                    [ __CLASS__, 'maybe_bust_cache' ] );
    }

    // ── Inject update info into WordPress transient ───────────────────────────

    public static function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = self::get_latest_release();
        if ( ! $release ) return $transient;

        $latest = ltrim( $release['tag_name'] ?? '', 'v' );
        if ( ! $latest || ! version_compare( $latest, WSR_PREMIUM_VER, '>' ) ) return $transient;

        $zip_url = self::get_zip_url( $release );
        if ( ! $zip_url ) return $transient;

        $obj                  = new \stdClass();
        $obj->id              = self::PLUGIN_FILE;
        $obj->slug            = self::PLUGIN_SLUG;
        $obj->plugin          = self::PLUGIN_FILE;
        $obj->new_version     = $latest;
        $obj->url             = 'https://statixpress.site/premium';
        $obj->package         = $zip_url;
        $obj->icons           = [];
        $obj->banners         = [];
        $obj->banners_rtl     = [];
        $obj->tested          = '6.7';
        $obj->requires_php    = '7.4';
        $obj->compatibility   = new \stdClass();

        $transient->response[ self::PLUGIN_FILE ] = $obj;
        return $transient;
    }

    // ── Plugin info popup (Details link in updates screen) ───────────────────

    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_SLUG ) return $result;

        $release = self::get_latest_release();
        if ( ! $release ) return $result;

        $latest  = ltrim( $release['tag_name'] ?? '', 'v' );
        $zip_url = self::get_zip_url( $release );

        $obj                = new \stdClass();
        $obj->name          = 'WP Static Runtime Premium';
        $obj->slug          = self::PLUGIN_SLUG;
        $obj->version       = $latest;
        $obj->author        = '<a href="https://statixpress.site">WP Static Runtime</a>';
        $obj->homepage      = 'https://statixpress.site/premium';
        $obj->requires      = '5.8';
        $obj->requires_php  = '7.4';
        $obj->tested        = '6.7';
        $obj->last_updated  = $release['published_at'] ?? '';
        $obj->download_link = $zip_url;
        $obj->sections      = [
            'description' => '<p>Static HTML caching engine — ISR, Smart Dependency Graph, CDN Purge, Redis.</p>',
            'changelog'   => '<pre>' . esc_html( $release['body'] ?? 'See GitHub releases for changelog.' ) . '</pre>',
        ];

        return $obj;
    }

    // ── Fix folder name after GitHub zipball download ─────────────────────────
    // GitHub source zips are named like "wpstaticruntime-wp-static-runtime-a1b2c3/"
    // This filter renames it to "wp-static-runtime/" so WordPress installs correctly.

    public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_FILE ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG . '/';

        if ( basename( untrailingslashit( $source ) ) === self::PLUGIN_SLUG ) {
            return $source; // Already correct
        }

        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->move( $source, $corrected ) ) {
            return $corrected;
        }

        // Fallback via rename()
        if ( @rename( $source, $corrected ) ) {
            return $corrected;
        }

        return $source;
    }

    // ── Cache management ──────────────────────────────────────────────────────

    public static function clear_cache( $upgrader, $options ): void {
        if ( ( $options['action'] ?? '' ) === 'update' && ( $options['type'] ?? '' ) === 'plugin' ) {
            delete_transient( self::TRANSIENT_KEY );
        }
    }

    public static function maybe_bust_cache(): void {
        if ( isset( $_GET['wsr_check_updates'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( self::TRANSIENT_KEY );
            wp_safe_redirect( admin_url( 'plugins.php' ) );
            exit;
        }
    }

    // ── GitHub API ────────────────────────────────────────────────────────────

    private static function get_latest_release(): ?array {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) return $cached ?: null;

        $repo     = defined( 'WSR_GITHUB_REPO' ) ? WSR_GITHUB_REPO : self::DEFAULT_REPO;
        $response = wp_remote_get(
            'https://api.github.com/repos/' . $repo . '/releases/latest',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WP-Static-Runtime-Updater/' . WSR_PREMIUM_VER,
                ],
            ]
        );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( self::TRANSIENT_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            set_transient( self::TRANSIENT_KEY, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * Get download ZIP URL.
     * Prefers an attached release asset named *.zip over the GitHub source zipball.
     */
    private static function get_zip_url( array $release ): string {
        foreach ( ( $release['assets'] ?? [] ) as $asset ) {
            if ( substr( $asset['name'] ?? '', -4 ) === '.zip' ) {
                return $asset['browser_download_url'] ?? '';
            }
        }
        // Fallback: GitHub source zipball (folder name will be fixed by fix_folder())
        return $release['zipball_url'] ?? '';
    }
}

<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Host — single source of truth untuk scheme + host + port.
 *
 * Aturan port:
 *   - Port 80  (http)  → tidak ditulis → "example.com"
 *   - Port 443 (https) → tidak ditulis → "example.com"
 *   - Port lain        → ditulis       → "localhost:8080"
 *
 * Semua komponen WSR wajib pakai class ini, bukan parse_url() langsung.
 */
class Host {

    /**
     * Resolve host string dari request aktif (HTTP_HOST).
     * Dipakai oleh: advanced-cache.php, cache_writer, cache_reader, router, request.
     *
     * Menormalkan port default (80/443) agar tidak masuk ke nama folder cache.
     *
     * @return string  e.g. "example.com" atau "localhost:8080"
     */
    public static function current(): string {
        if ( isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] !== '' ) {
            return self::normalize( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
        }

        // Fallback ke home_url() saat CLI / ob_render
        return self::from_url( home_url() );
    }

    /**
     * Resolve host dari URL arbitrari (home_url, sitemap URL, crawler URL).
     * Dipakai oleh: cache_reader::resolve_path_from_url, cache_writer::write_url,
     *               apache, nginx, router, crawler.
     *
     * @param  string $url  Full URL e.g. "https://example.com/blog/"
     * @return string       e.g. "example.com" atau "localhost:8080"
     */
    public static function from_url( string $url ): string {
        $parsed = wp_parse_url( $url );
        $h      = $parsed['host']   ?? 'localhost';
        $p      = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;
        $scheme = $parsed['scheme'] ?? 'http';

        return self::build( $h, $p, $scheme );
    }

    /**
     * Resolve scheme dari URL.
     *
     * @param  string $url
     * @return string  "http" atau "https"
     */
    public static function scheme_from_url( string $url ): string {
        return wp_parse_url( $url, PHP_URL_SCHEME ) ?: 'http';
    }

    /**
     * Resolve scheme dari request aktif.
     *
     * @return string  "http" atau "https"
     */
    public static function current_scheme(): string {
        if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off' ) {
            return 'https';
        }
        if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
            return 'https';
        }
        if ( isset( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443 ) {
            return 'https';
        }
        return 'http';
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Normalkan string HTTP_HOST — strip port default (80/443).
     *
     * @param  string $http_host  e.g. "example.com", "example.com:80", "localhost:8080"
     * @return string
     */
    private static function normalize( string $http_host ): string {
        if ( strpos( $http_host, ':' ) === false ) {
            return $http_host; // tidak ada port, langsung return
        }

        [ $h, $p ] = explode( ':', $http_host, 2 );
        $port   = (int) $p;

        // Deteksi scheme dari $_SERVER untuk menentukan port default
        $scheme = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off' )
                    ? 'https' : 'http';

        return self::build( $h, $port, $scheme );
    }

    /**
     * Bangun string host, hapus port default.
     *
     * @param  string $host
     * @param  int    $port    0 = tidak ada port eksplisit
     * @param  string $scheme  "http" atau "https"
     * @return string
     */
    private static function build( string $host, int $port, string $scheme ): string {
        $default = ( $scheme === 'https' ) ? 443 : 80;

        if ( $port === 0 || $port === $default ) {
            return $host;
        }

        // Pakai "_port_" bukan ":" agar aman di Windows filesystem (colon reserved)
        return $host . '_port_' . $port;
    }
}

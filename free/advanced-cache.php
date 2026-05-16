<?php
/**
 * WP Static Runtime — Early Static Router
 * Installed at: wp-content/advanced-cache.php
 *
 * This file runs BEFORE WordPress boots.
 * Intercepts GET requests and serves pre-rendered static HTML directly from disk.
 */

// advanced-cache.php berjalan SEBELUM WordPress boot — tidak ada ABSPATH, WP_CONTENT_DIR, dll.
// Semua path harus di-resolve secara mandiri dari lokasi file ini.
$_wsr_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __FILE__ );

// ── Bail conditions ───────────────────────────────────────────────────────────

// Skip CLI / cron
if ( php_sapi_name() === 'cli' || defined( 'DOING_CRON' ) ) return;

// Skip non-GET
if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) return;

// Skip admin / login
if ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin' ) !== false ) return;
if ( strpos( $_SERVER['REQUEST_URI'], '/wp-login.php' ) !== false ) return;

// Skip if query string present
if ( ! empty( $_SERVER['QUERY_STRING'] ) ) return;

// Always skip WordPress native search (?s=) — even if WSR_ALLOW_QUERY_STRING is on
if ( isset( $_GET['s'] ) ) return;

// Skip logged-in / special cookies
$skip_cookies = [
    'wordpress_logged_in',
    'wp-postpass_',
    'woocommerce_cart_hash',
    'woocommerce_items_in_cart',
    'comment_author_',
];
foreach ( $skip_cookies as $prefix ) {
    foreach ( array_keys( $_COOKIE ) as $key ) {
        if ( strpos( $key, $prefix ) === 0 ) return;
    }
}

// ── Resolve cache path ────────────────────────────────────────────────────────

$uri = strtok( $_SERVER['REQUEST_URI'], '?' );
$uri = rtrim( $uri, '/' ) . '/';

// Detect scheme
$is_https = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off' )
         || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' )
         || ( isset( $_SERVER['SERVER_PORT'] ) && (int) $_SERVER['SERVER_PORT'] === 443 );
$scheme = $is_https ? 'https' : 'http';

// Host — pakai HTTP_HOST, normalkan port default (80/443 tidak ditulis ke path)
$_wsr_raw_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
if ( strpos( $_wsr_raw_host, ':' ) !== false ) {
    [ $_wsr_h, $_wsr_p ] = explode( ':', $_wsr_raw_host, 2 );
    $_wsr_default_port   = $is_https ? 443 : 80;
    // Port default → tidak ditulis. Port non-default → pakai "_port_" (aman di Windows)
    $host = ( (int) $_wsr_p === $_wsr_default_port )
        ? $_wsr_h
        : $_wsr_h . '_port_' . (int) $_wsr_p;
    unset( $_wsr_h, $_wsr_p, $_wsr_default_port );
} else {
    $host = $_wsr_raw_host;
}
unset( $_wsr_raw_host );

$cache_dir  = $_wsr_content_dir . '/wsr-cache/' . $scheme . '/' . $host . $uri;
$cache_file = $cache_dir . 'index.html';

// ── Serve static file ─────────────────────────────────────────────────────────

if ( file_exists( $cache_file ) ) {

    // Optional TTL check
    $ttl = defined( 'WSR_CACHE_TTL' ) ? (int) WSR_CACHE_TTL : 0;
    if ( $ttl > 0 && ( time() - filemtime( $cache_file ) ) > $ttl ) {
        header( 'X-Cache: EXPIRED' );
        return;
    }

    $file_mtime = filemtime( $cache_file );
    $file_size  = filesize( $cache_file );
    $etag       = '"' . md5( $cache_file . $file_mtime . $file_size ) . '"';
    $last_mod   = gmdate( 'D, d M Y H:i:s', $file_mtime ) . ' GMT';
    $max_age    = defined( 'WSR_CACHE_TTL' ) && (int) WSR_CACHE_TTL > 0 ? (int) WSR_CACHE_TTL : 3600;

    // ── 304 Not Modified — browser already has this version ───────────────
    $if_none_match    = isset( $_SERVER['HTTP_IF_NONE_MATCH'] )    ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
    $if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : '';

    if ( ( $if_none_match && $if_none_match === $etag )
      || ( $if_modified_since && strtotime( $if_modified_since ) >= $file_mtime ) ) {
        header( 'HTTP/1.1 304 Not Modified' );
        header( 'ETag: ' . $etag );
        header( 'X-Cache: HIT' );
        header( 'X-Cache-Engine: WP-Static-Runtime/1.0' );
        exit;
    }

    // ── Full response headers ────────────────────────────────────────────
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'X-Cache: HIT' );
    header( 'X-Cache-Engine: WP-Static-Runtime/1.0' );
    header( 'X-Cached-At: ' . $last_mod );
    header( 'ETag: ' . $etag );
    header( 'Last-Modified: ' . $last_mod );
    header( 'Cache-Control: public, max-age=' . $max_age . ', must-revalidate' );
    header( 'Vary: Accept-Encoding' );

    // Serve gzip if browser supports it
    $gzip_file = $cache_dir . 'index.html.gz';
    if ( file_exists( $gzip_file ) ) {
        $ae = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
        if ( strpos( $ae, 'gzip' ) !== false ) {
            header( 'Content-Encoding: gzip' );
            readfile( $gzip_file );
            exit;
        }
    }

    // Inject meta tag so frontend JS can detect this page is served from cache.
    // This is used by wsr-nonce-refresh.js to trigger a pre-fetch of fresh nonces.
    $html = file_get_contents( $cache_file );
    $meta = '<meta name="x-cache" content="HIT">';
    $html = str_replace( '</head>', $meta . "\n</head>", $html );
    echo $html;
    exit;
}

// Cache MISS — WordPress loads normally, output_buffer.php will cache the response
header( 'X-Cache: MISS' );

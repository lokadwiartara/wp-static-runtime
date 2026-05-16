<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Request — normalizes the current HTTP request URI.
 * Singleton so the normalized URI is computed once per request.
 */
class Request {

    /** @var Request|null */
    private static $instance = null;

    /** @var string */
    private $uri;

    /** @var string */
    private $scheme;

    /** @var string */
    private $host;

    /**
     * @return self
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->scheme = Host::current_scheme();
        $this->host   = Host::current();

        $raw = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
        $this->uri = $this->normalize( $raw );
    }

    /**
     * Normalize a raw REQUEST_URI:
     * - strip query string
     * - decode percent-encoding
     * - lowercase
     * - add trailing slash (unless file extension present)
     *
     * @param  string $raw
     * @return string
     */
    public function normalize( $raw ) {
        $uri = (string) strtok( $raw, '?' );
        $uri = rawurldecode( $uri );
        $uri = strtolower( $uri );
        if ( ! pathinfo( $uri, PATHINFO_EXTENSION ) ) {
            $uri = trailingslashit( $uri );
        }
        return $uri;
    }

    /** @return string e.g. /blog/post-1/ */
    public function uri()    { return $this->uri; }

    /** @return string http|https */
    public function scheme() { return $this->scheme; }

    /** @return string e.g. example.com */
    public function host()   { return $this->host; }

    /**
     * Absolute path to the cache file for this request.
     * @return string
     */
    public function cache_path() {
        return WSR_CACHE_DIR . $this->scheme . '/' . $this->host . $this->uri . 'index.html';
    }

    /**
     * Absolute path to the cache directory for this request.
     * @return string
     */
    public function cache_dir() {
        return WSR_CACHE_DIR . $this->scheme . '/' . $this->host . $this->uri;
    }

    /**
     * Whether this request is eligible to be cached.
     * Mirrors the bail conditions in advanced-cache.php.
     *
     * @return bool
     */
    public function is_cacheable(): bool {
        // Skip non-GET
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] !== 'GET' ) return false;

        // Skip admin / login / cron / REST
        $uri = $this->uri;
        if ( strpos( $uri, '/wp-admin' ) !== false ) return false;
        if ( strpos( $uri, '/wp-login.php' ) !== false ) return false;
        if ( strpos( $uri, '/wp-cron.php' ) !== false ) return false;
        if ( strpos( $uri, '/wp-json' ) !== false ) return false;

        // Skip query strings
        if ( ! empty( $_SERVER['QUERY_STRING'] ) ) return false;
        if ( isset( $_GET['s'] ) ) return false;

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
                if ( strpos( $key, $prefix ) === 0 ) return false;
            }
        }

        return (bool) apply_filters( 'wsr_is_cacheable', true, $uri );
    }
}

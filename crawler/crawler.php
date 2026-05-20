<?php
// phpcs:disable WordPress.WP.AlternativeFunctions, Squiz.PHP.DiscouragedFunctions
namespace WSR\Crawler;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Crawler – pre-builds static cache for all public URLs.
 *
 * FETCH METHOD PRIORITY:
 * 1. fgc        – PHP file_get_contents() – fastest, bypasses WP loopback block
 * 2. curl       – direct cURL
 * 3. wp_remote  – WP HTTP API
 *
 * NS_BINDING_ABORTED fix: BATCH_SIZE = 1, each AJAX call processes exactly
 * one URL then returns – prevents long-running requests from being aborted.
 */
class Crawler {

    const BATCH_SIZE = 1;
    const QUEUE_KEY  = 'wsr_crawl_queue';
    const STATUS_KEY = 'wsr_crawler_status';

    public static function register_ajax() {
        add_action( 'wp_ajax_wsr_crawl_init',   [ __CLASS__, 'ajax_init'   ] );
        add_action( 'wp_ajax_wsr_crawl_batch',  [ __CLASS__, 'ajax_batch'  ] );
        add_action( 'wp_ajax_wsr_crawl_status', [ __CLASS__, 'ajax_status' ] );
        add_action( 'wp_ajax_wsr_crawl_cancel', [ __CLASS__, 'ajax_cancel' ] );
    }

    public static function ajax_init() {
        self::check_auth();

        $urls     = Sitemap::from_wordpress();
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        $urls     = array_values( array_unique( self::filter_urls( $urls, $settings ) ) );

        if ( empty( $urls ) ) {
            wp_send_json_error( [ 'message' => 'No public URLs found.' ] );
            return;
        }

        $method = self::detect_fetch_method();

        if ( $method === 'none' ) {
            wp_send_json_error( [
                'message' => 'No fetch method available. Enable allow_url_fopen=On or curl extension in php.ini.',
            ] );
            return;
        }

        update_option( self::QUEUE_KEY,  $urls,  false );
        update_option( 'wsr_crawl_method', $method, false );
        update_option( self::STATUS_KEY, [
            'running'     => true,
            'method'      => $method,
            'total'       => count( $urls ),
            'done'        => 0,
            'cached'      => 0,
            'skipped'     => 0,
            'failed'      => 0,
            'errors'      => [],
            'started_at'  => current_time( 'mysql' ),
            'finished_at' => null,
        ], false );

        $labels = [
            'fgc'       => 'file_get_contents',
            'curl'      => 'cURL direct',
            'wp_remote' => 'WP HTTP API',
        ];

        wp_send_json_success( [
            'total'   => count( $urls ),
            'method'  => $method,
            'message' => count( $urls ) . ' URLs queued. Method: ' . ( $labels[ $method ] ?? $method ),
        ] );
    }

    public static function ajax_batch() {
        self::check_auth();

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 60 );
        }

        $queue  = get_option( self::QUEUE_KEY, [] );
        $method = get_option( 'wsr_crawl_method', 'fgc' );

        if ( empty( $queue ) ) {
            self::complete();
            wp_send_json_success( [
                'done'   => true,
                'status' => get_option( self::STATUS_KEY, [] ),
            ] );
            return;
        }

        $batch = array_splice( $queue, 0, self::BATCH_SIZE );
        update_option( self::QUEUE_KEY, array_values( $queue ), false );

        $results = [];
        foreach ( $batch as $url ) {
            $r = self::cache_url( $url, $method );
            $results[] = $r;
            self::tally( $r );
        }

        wp_send_json_success( [
            'done'      => false,
            'remaining' => count( $queue ),
            'results'   => $results,
            'status'    => get_option( self::STATUS_KEY, [] ),
        ] );
    }

    public static function ajax_status() {
        self::check_auth();
        $s = get_option( self::STATUS_KEY, [] );
        $s['remaining'] = count( get_option( self::QUEUE_KEY, [] ) );
        wp_send_json_success( $s );
    }

    public static function ajax_cancel() {
        self::check_auth();
        delete_option( self::QUEUE_KEY );
        delete_option( 'wsr_crawl_method' );
        $s = get_option( self::STATUS_KEY, [] );
        $s['running']     = false;
        $s['cancelled']   = true;
        $s['finished_at'] = current_time( 'mysql' );
        update_option( self::STATUS_KEY, $s, false );
        wp_send_json_success( [ 'message' => 'Cancelled.' ] );
    }

    private static function cache_url( $url, $method ) {
        $cache_file = \WSR\Cache_Reader::resolve_path_from_url( $url );
        if ( $cache_file && file_exists( $cache_file ) ) {
            return self::r( $url, 'skipped', 'Already cached' );
        }

        $body = self::fetch( $url, $method );

        if ( $body === false || $body === '' ) {
            foreach ( [ 'fgc', 'curl', 'wp_remote' ] as $fb ) {
                if ( $fb === $method ) continue;
                $body = self::fetch( $url, $fb );
                if ( $body !== false && $body !== '' ) break;
            }
        }

        if ( $body === false || $body === '' ) {
            return self::r( $url, 'error', 'All fetch methods failed' );
        }

        if ( stripos( $body, '<html' ) === false && stripos( $body, '<!DOCTYPE' ) === false ) {
            return self::r( $url, 'error', 'Not HTML: ' . substr( $body, 0, 80 ) );
        }

        if ( $cache_file && file_exists( $cache_file ) ) {
            return self::r( $url, 'cached' );
        }

        $stamped = $body . "\n<!-- Cached by WP Static Runtime Crawler at " . gmdate( 'Y-m-d H:i:s' ) . " UTC -->\n";

        if ( \WSR\Cache_Writer::write_url( $url, $stamped ) ) {
            return self::r( $url, 'cached' );
        }

        return self::r( $url, 'error', 'write_url() failed – check cache directory permissions' );
    }

    private static function fetch( $url, $method ) {
        switch ( $method ) {
            case 'fgc':       return self::fetch_fgc( $url );
            case 'curl':      return self::fetch_curl( $url );
            case 'wp_remote': return self::fetch_wp_remote( $url );
            default:          return false;
        }
    }

    private static function fetch_fgc( $url ) {
        if ( ! function_exists( 'file_get_contents' ) ) return false;
        if ( ini_get( 'allow_url_fopen' ) == '0' ) return false;

        $context = stream_context_create( [
            'http' => [
                'method'          => 'GET',
                'timeout'         => 20,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'user_agent'      => 'statixpress-static-runtime-Crawler/1.0',
                'header'          => "X-WSR-Crawler: 1\r\nAccept: text/html,*/*\r\nConnection: close",
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ] );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $body = @file_get_contents( $url, false, $context );
        if ( $body === false ) return false;

        if ( isset( $http_response_header ) && is_array( $http_response_header ) ) {
            $line = $http_response_header[0] ?? '';
            if ( preg_match( '#HTTP/\S+\s+(\d+)#', $line, $m ) && (int) $m[1] !== 200 ) {
                return false;
            }
        }

        return (string) $body;
    }

    private static function fetch_curl( $url ) {
        if ( ! function_exists( 'curl_init' ) ) return false;

        $parsed = wp_parse_url( $url );
        $port   = isset( $parsed['port'] ) ? (int) $parsed['port'] : 0;

        $ch   = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'statixpress-static-runtime-Crawler/1.0',
            CURLOPT_HTTPHEADER     => [ 'X-WSR-Crawler: 1', 'Accept: text/html,*/*', 'Connection: close' ],
            CURLOPT_ENCODING       => '',
        ];
        if ( $port ) $opts[ CURLOPT_PORT ] = $port;
        curl_setopt_array( $ch, $opts );

        $body = curl_exec( $ch );
        $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $err  = curl_error( $ch );
        curl_close( $ch );

        if ( $err || $code !== 200 || $body === false ) return false;
        return (string) $body;
    }

    private static function fetch_wp_remote( $url ) {
        add_filter( 'http_request_host_is_external', '__return_true' );
        $resp = wp_remote_get( $url, [
            'timeout'            => 20,
            'sslverify'          => apply_filters( 'wsr_crawler_sslverify', true ),
            'reject_unsafe_urls' => false,
            'cookies'            => [],
            'user-agent'         => 'statixpress-static-runtime-Crawler/1.0',
            'headers'            => [ 'X-WSR-Crawler' => '1' ],
        ] );
        remove_filter( 'http_request_host_is_external', '__return_true' );

        if ( is_wp_error( $resp ) ) return false;
        if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) return false;
        return (string) wp_remote_retrieve_body( $resp );
    }


    public static function detect_fetch_method() {
        $test_url = home_url( '/' );
        $parsed   = wp_parse_url( $test_url );
        $host     = isset( $parsed['host'] )   ? $parsed['host']   : 'localhost';
        $port     = isset( $parsed['port'] )   ? (int) $parsed['port'] : 0;
        $scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'http';
        if ( ! $port ) $port = ( $scheme === 'https' ) ? 443 : 80;

        // TCP pre-check (used only as informational hint, NOT a blocker)
        $socket_ok = false;
        if ( function_exists( 'fsockopen' ) ) {
            $sock = @fsockopen( $host, $port, $errno, $errstr, 3 );
            if ( is_resource( $sock ) ) { $socket_ok = true; fclose( $sock ); }
        }

        // Always attempt fgc regardless of socket_ok (PHP built-in server / loopback quirk)
        if ( function_exists( 'file_get_contents' ) && ini_get( 'allow_url_fopen' ) != '0' ) {
            $ctx = stream_context_create( [
                'http' => [ 'method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => true, 'header' => 'X-WSR-Crawler: 1' ],
                'ssl'  => [ 'verify_peer' => false, 'verify_peer_name' => false ],
            ] );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
            if ( @file_get_contents( $test_url, false, $ctx ) !== false ) return 'fgc';
        }

        // Always attempt cURL regardless of socket_ok
        if ( function_exists( 'curl_init' ) ) {
            $ch = curl_init();
            curl_setopt_array( $ch, [
                CURLOPT_URL            => $test_url,
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_PORT           => $port,
            ] );
            curl_exec( $ch );
            $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );
            if ( $code > 0 ) return 'curl';
        }

        // wp_remote — only works reliably when socket_ok (prevents WP loopback block hanging)
        if ( $socket_ok ) {
            add_filter( 'http_request_host_is_external', '__return_true' );
            $r = wp_remote_head( $test_url, [ 'timeout' => 5, 'sslverify' => apply_filters( 'wsr_crawler_sslverify', true ), 'reject_unsafe_urls' => false ] );
            remove_filter( 'http_request_host_is_external', '__return_true' );
            if ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) > 0 ) return 'wp_remote';
        }

        return 'none';
    }


    public static function run() {
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        if ( empty( $settings['crawler_enabled'] ) ) return;

        $urls = Sitemap::from_wordpress();
        if ( empty( $urls ) ) return;

        $urls   = array_values( array_unique( self::filter_urls( $urls, $settings ) ) );
        $method = self::detect_fetch_method();
        if ( $method === 'none' ) return;

        update_option( self::STATUS_KEY, [
            'running' => true, 'method' => $method, 'total' => count( $urls ),
            'done' => 0, 'cached' => 0, 'skipped' => 0, 'failed' => 0,
            'errors' => [], 'started_at' => current_time( 'mysql' ), 'finished_at' => null,
        ], false );

        foreach ( $urls as $url ) {
            self::tally( self::cache_url( $url, $method ) );
        }
        self::complete();
    }

    private static function filter_urls( $urls, $settings ) {
        $woo_ex  = isset( $settings['woo_exclude'] )   ? (array) $settings['woo_exclude']   : [];
        $path_ex = isset( $settings['excluded_urls'] ) ? (array) $settings['excluded_urls'] : [];
        $out = [];
        foreach ( $urls as $url ) {
            $path = (string) wp_parse_url( $url, PHP_URL_PATH );
            $skip = false;
            foreach ( $woo_ex as $s ) {
                $s = trim( $s );
                if ( $s && strpos( $path, '/' . $s ) !== false ) { $skip = true; break; }
            }
            if ( ! $skip ) {
                foreach ( $path_ex as $p ) {
                    $p = trim( $p );
                    if ( $p && strpos( $path, $p ) !== false ) { $skip = true; break; }
                }
            }
            if ( ! $skip ) $out[] = $url;
        }
        return $out;
    }

    private static function r( $url, $status, $msg = '' ) {
        return [ 'url' => $url, 'status' => $status, 'msg' => $msg ];
    }

    private static function tally( $r ) {
        $s = get_option( self::STATUS_KEY, [] );
        $s['done'] = isset( $s['done'] ) ? (int)$s['done'] + 1 : 1;
        $st = isset( $r['status'] ) ? $r['status'] : '';
        if ( $st === 'cached' ) {
            $s['cached']  = isset( $s['cached']  ) ? (int)$s['cached']  + 1 : 1;
        } elseif ( $st === 'skipped' ) {
            $s['skipped'] = isset( $s['skipped'] ) ? (int)$s['skipped'] + 1 : 1;
        } else {
            $s['failed']  = isset( $s['failed']  ) ? (int)$s['failed']  + 1 : 1;
            if ( ! isset( $s['errors'] ) ) $s['errors'] = [];
            if ( count( $s['errors'] ) < 50 ) {
                $s['errors'][] = [ 'url' => $r['url'], 'reason' => $r['msg'] ];
            }
        }
        update_option( self::STATUS_KEY, $s, false );
    }

    private static function complete() {
        delete_option( self::QUEUE_KEY );
        delete_option( 'wsr_crawl_method' );
        $s = get_option( self::STATUS_KEY, [] );
        $s['running']     = false;
        $s['finished_at'] = current_time( 'mysql' );
        update_option( self::STATUS_KEY, $s, false );
        do_action( 'wsr_crawl_complete', $s );
    }

    private static function check_auth() {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
            exit;
        }
    }
}

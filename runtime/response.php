<?php
namespace WSR\Runtime;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Response helper – utility for sending cache-related HTTP headers.
 */
class Response {

    /**
     * Send cache HIT headers.
     */
    public static function send_hit_headers( string $cache_file ): void {
        self::send_common_headers();
        header( 'X-Cache: HIT' );
        header( 'X-Cached-At: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_file ) ) . ' GMT' );
    }

    /**
     * Send cache MISS headers.
     */
    public static function send_miss_headers(): void {
        self::send_common_headers();
        header( 'X-Cache: MISS' );
    }

    private static function send_common_headers(): void {
        header( 'X-Cache-Engine: statixpress-static-runtime/' . WSR_VERSION );
        header( 'Vary: Accept-Encoding' );
    }
}

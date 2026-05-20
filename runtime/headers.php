<?php
namespace WSR\Runtime;

if ( ! defined( 'ABSPATH' ) ) exit;

class Headers {
    public static function no_cache(): void {
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }

    public static function static_cache( int $max_age = 3600 ): void {
        header( "Cache-Control: public, max-age={$max_age}" );
    }
}

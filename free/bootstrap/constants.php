<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Runtime constants and settings defaults.
 */
class Constants {

    public static function define_all(): void {
        // Cache subdirectories
        if ( ! defined( 'WSR_CACHE_LOG' ) ) {
            define( 'WSR_CACHE_LOG', WP_CONTENT_DIR . '/wsr-cache/logs/' );
        }
        if ( ! defined( 'WSR_DEP_DIR' ) ) {
            define( 'WSR_DEP_DIR', WP_CONTENT_DIR . '/wsr-cache/dependency/' );
        }
    }

    /**
     * Default plugin settings.
     */
    public static function defaults(): array {
        return [
            'cache_enabled'        => true,
            'cache_ttl'            => 0,           // 0 = never expire unless purged
            'skip_logged_in'       => true,
            'skip_query_strings'   => true,
            'mobile_cache'         => false,
            'gzip_cache'           => false,
            'minify_html'          => false,
            'crawler_enabled'      => true,
            'woo_hybrid'           => true,
            'woo_exclude'          => [ 'cart', 'checkout', 'my-account', 'order' ],
            'woo_excluded_cookies' => [ 'woocommerce_cart_hash', 'woocommerce_items_in_cart' ],
            'excluded_urls'        => [],
            'excluded_cookies'     => [ 'wordpress_logged_in', 'wp-postpass_' ],
            'excluded_useragents'  => [],
            'apache_rewrite'       => true,
            'nginx_conf_path'      => '',
        ];
    }
}

Constants::define_all();

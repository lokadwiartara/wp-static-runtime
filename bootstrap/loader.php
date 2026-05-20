<?php
namespace WSR;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Loader — loads all plugin classes in dependency order.
 */
class Loader {

    private static $files = [
        // Core (no dependencies on other WSR classes)
        'core/host.php',
        'core/request.php',
        'core/router.php',
        'core/cache_reader.php',
        'core/cache_writer.php',
        'core/cache.php',
        'core/cache_cleaner.php',
        'core/dependency_graph.php',
        'core/installer.php',

        // Advanced / License / Dependency features
        'license/license_guard.php',
        'core/smart_dependency.php',

        // Runtime
        'runtime/headers.php',
        'runtime/response.php',
        'runtime/output_buffer.php',

        // Crawler
        'crawler/sitemap.php',
        'crawler/crawler.php',

        // Server
        'server/apache.php',
        'server/nginx.php',
        'server/litespeed.php',

        // Integrations
        'integrations/elementor.php',
        'integrations/woocommerce.php',
        'integrations/nonce_refresh.php',

        // CDN
        'cdn/cdn_manager.php',
        'cdn/cloudflare.php',
        'cdn/bunny.php',
        'cdn/fastly.php',

        // ISR
        'isr/incremental_static_regeneration.php',

        // Redis & Memcached
        'redis/redis_index.php',
        'memcached/memcached_index.php',

        // Optimisation
        'optimizer/html_optimizer.php',

        // Admin
        'admin/diagnostic.php',
        'admin/menu.php',
        'admin/dashboard.php',
        'admin/settings.php',
        'admin/cache_page.php',
        'admin/admin_premium.php',
    ];

    public static function load_all() {
        foreach ( self::$files as $file ) {
            $path = WSR_PATH . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }
}

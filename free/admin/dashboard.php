<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dashboard — main overview page.
 */
class Dashboard {

    public static function render() {
        $stats    = \WSR\Cache::stats();
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        $crawler  = get_option( \WSR\Crawler\Crawler::STATUS_KEY, [] );

        $crawler_icon = '—';
        if ( ! empty( $crawler['running'] ) ) {
            $crawler_icon = '🟡';
        } elseif ( ! empty( $crawler['finished_at'] ) ) {
            $crawler_icon = '✅';
        }
        ?>
        <div class="wrap wsr-wrap">
            <h1 class="wsr-page-title">
                <span class="wsr-logo">⚡</span>
                WP Static Runtime
                <span class="wsr-version">v<?php echo esc_html( WSR_VERSION ); ?></span>
            </h1>

            <?php self::status_bar( $settings ); ?>

            <div class="wsr-cards">
                <div class="wsr-card">
                    <div class="wsr-card-icon">📄</div>
                    <div class="wsr-card-value"><?php echo esc_html( $stats['total_pages'] ?? 0 ); ?></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Cached Pages', 'wp-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon">💾</div>
                    <div class="wsr-card-value"><?php echo esc_html( $stats['disk_size'] ?? '0 B' ); ?></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Cache Size', 'wp-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon">🕒</div>
                    <div class="wsr-card-value">10–40<small>ms</small></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Target TTFB', 'wp-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon"><?php echo $crawler_icon; ?></div>
                    <div class="wsr-card-value" style="font-size:14px;">
                        <?php
                        if ( ! empty( $crawler['running'] ) ) {
                            esc_html_e( 'Running', 'wp-static-runtime' );
                        } elseif ( ! empty( $crawler['done'] ) ) {
                            echo esc_html( $crawler['cached'] ?? 0 ) . ' ' . esc_html__( 'cached', 'wp-static-runtime' );
                        } else {
                            esc_html_e( 'Not run', 'wp-static-runtime' );
                        }
                        ?>
                    </div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Crawler', 'wp-static-runtime' ); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Quick Actions', 'wp-static-runtime' ); ?></h2>
                <div class="wsr-actions">
                    <button id="wsr-flush-btn" class="button button-primary wsr-btn">
                        🗑️ <?php esc_html_e( 'Flush All Cache', 'wp-static-runtime' ); ?>
                    </button>
                    <button id="wsr-crawl-start-btn" class="button wsr-btn">
                        🕷️ <?php esc_html_e( 'Start Crawler', 'wp-static-runtime' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wsr-settings' ) ); ?>" class="button wsr-btn">
                        ⚙️ <?php esc_html_e( 'Settings', 'wp-static-runtime' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wsr-diagnostic' ) ); ?>" class="button wsr-btn">
                        🔍 <?php esc_html_e( 'Diagnostic', 'wp-static-runtime' ); ?>
                    </a>
                </div>
                <div id="wsr-action-result" class="wsr-notice" style="display:none;margin-top:12px;"></div>

                <div id="wsr-progress-wrap" style="display:none;margin-top:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                        <span id="wsr-progress-label" style="font-size:13px;color:#475569;"><?php esc_html_e( 'Starting...', 'wp-static-runtime' ); ?></span>
                        <span id="wsr-progress-count" style="font-size:12px;color:#94a3b8;"></span>
                    </div>
                    <div class="wsr-progress-bar-bg">
                        <div id="wsr-progress-bar" class="wsr-progress-bar-fill" style="width:0%"></div>
                    </div>
                    <div id="wsr-progress-stats" style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748b;"></div>
                    <button id="wsr-crawl-cancel-btn" class="button" style="display:none;margin-top:8px;">■ <?php esc_html_e( 'Cancel', 'wp-static-runtime' ); ?></button>
                </div>
            </div>

            <!-- Engine Status -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Engine Status', 'wp-static-runtime' ); ?></h2>
                <table class="wsr-status-table widefat">
                    <tbody>
                        <?php self::status_row( __( 'Cache Engine',             'wp-static-runtime' ), ! empty( $settings['cache_enabled'] ) ); ?>
                        <?php self::status_row( __( 'WP_CACHE Defined',         'wp-static-runtime' ), defined( 'WP_CACHE' ) && WP_CACHE ); ?>
                        <?php self::status_row( __( 'advanced-cache.php',       'wp-static-runtime' ), self::check_advanced_cache() ); ?>
                        <?php self::status_row( __( 'Cache Directory Writable', 'wp-static-runtime' ), is_dir( WSR_CACHE_DIR ) && is_writable( WSR_CACHE_DIR ) ); ?>
                        <?php self::status_row( __( 'WooCommerce Hybrid',       'wp-static-runtime' ), class_exists( 'WooCommerce' ) ); ?>
                        <?php self::status_row( __( 'Elementor Integration',    'wp-static-runtime' ), defined( 'ELEMENTOR_VERSION' ) ); ?>
                        <?php self::status_row( __( 'Gzip Cache',               'wp-static-runtime' ), ! empty( $settings['gzip_cache'] ) ); ?>
                        <?php self::status_row( __( 'HTML Minification',        'wp-static-runtime' ), ! empty( $settings['minify_html'] ) ); ?>
                    </tbody>
                </table>
            </div>

            <?php if ( ! WSR_PREMIUM ) : ?>
            <div class="wsr-upgrade-banner">
                <h3>🚀 <?php esc_html_e( 'Upgrade to WP Static Runtime Premium', 'wp-static-runtime' ); ?></h3>
                <p><?php esc_html_e( 'Unlock Incremental Static Regeneration, Smart Dependency Graph, WooCommerce Smart Cache, CDN Purge (Cloudflare, BunnyCDN, Fastly), and Redis cache index.', 'wp-static-runtime' ); ?></p>
                <a href="https://statixpress.site/premium" target="_blank" class="button button-primary"><?php esc_html_e( 'Upgrade Now', 'wp-static-runtime' ); ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function status_bar( $settings ) {
        $on    = ! empty( $settings['cache_enabled'] );
        $class = $on ? 'wsr-status-bar active' : 'wsr-status-bar inactive';
        $label = $on
            ? '✅ ' . __( 'Cache Active — Serving static HTML', 'wp-static-runtime' )
            : '⚠️ ' . __( 'Cache Disabled — Enable in Settings', 'wp-static-runtime' );
        echo '<div class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</div>';
    }

    private static function status_row( $label, $on ) {
        $badge = $on
            ? '<span class="wsr-badge ok">✔ ' . esc_html__( 'ACTIVE',   'wp-static-runtime' ) . '</span>'
            : '<span class="wsr-badge off">✘ ' . esc_html__( 'INACTIVE', 'wp-static-runtime' ) . '</span>';
        echo '<tr><td>' . esc_html( $label ) . '</td><td>' . $badge . '</td></tr>';
    }

    private static function check_advanced_cache() {
        $f = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( ! file_exists( $f ) ) return false;
        $c = file_get_contents( $f );
        return $c !== false && strpos( $c, 'WP Static Runtime' ) !== false;
    }
}

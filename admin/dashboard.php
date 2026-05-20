<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dashboard – main overview page.
 */
class Dashboard {

    public static function render() {
        $stats    = \WSR\Cache::stats();
        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        $crawler  = get_option( \WSR\Crawler\Crawler::STATUS_KEY, [] );

        $crawler_icon = '<span class="dashicons dashicons-networking"></span>';
        if ( ! empty( $crawler['running'] ) ) {
            $crawler_icon = '<span class="dashicons dashicons-update"></span>';
        } elseif ( ! empty( $crawler['finished_at'] ) ) {
            $crawler_icon = '<span class="dashicons dashicons-yes"></span>';
        }
        ?>
        <div class="wrap wsr-wrap">
            <h1 class="wsr-page-title">
                <span class="dashicons dashicons-superhero wsr-logo"></span>
                StatixPress
                <span class="wsr-version">v<?php echo esc_html( WSR_VERSION ); ?></span>
            </h1>

            <?php self::status_bar( $settings ); ?>

            <div class="wsr-cards">
                <div class="wsr-card">
                    <div class="wsr-card-icon"><span class="dashicons dashicons-media-document"></span></div>
                    <div class="wsr-card-value"><?php echo esc_html( $stats['total_pages'] ?? 0 ); ?></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Cached Pages', 'statixpress-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon"><span class="dashicons dashicons-database"></span></div>
                    <div class="wsr-card-value"><?php echo esc_html( $stats['disk_size'] ?? '0 B' ); ?></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Cache Size', 'statixpress-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon"><span class="dashicons dashicons-clock"></span></div>
                    <div class="wsr-card-value">10-40<small>ms</small></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Target TTFB', 'statixpress-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card">
                    <div class="wsr-card-icon"><?php echo wp_kses_post( $crawler_icon ); ?></div>
                    <div class="wsr-card-value" style="font-size:14px;">
                        <?php
                        if ( ! empty( $crawler['running'] ) ) {
                            esc_html_e( 'Running', 'statixpress-static-runtime' );
                        } elseif ( ! empty( $crawler['done'] ) ) {
                            echo esc_html( $crawler['cached'] ?? 0 ) . ' ' . esc_html__( 'cached', 'statixpress-static-runtime' );
                        } else {
                            esc_html_e( 'Not run', 'statixpress-static-runtime' );
                        }
                        ?>
                    </div>
                    <div class="wsr-card-label"><?php esc_html_e( 'Crawler', 'statixpress-static-runtime' ); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Quick Actions', 'statixpress-static-runtime' ); ?></h2>
                <div class="wsr-actions">
                    <button id="wsr-flush-btn" class="button button-primary wsr-btn">
                        <span class="dashicons dashicons-trash" style="margin-top:2px;"></span> <?php esc_html_e( 'Flush All Cache', 'statixpress-static-runtime' ); ?>
                    </button>
                    <button id="wsr-crawl-start-btn" class="button wsr-btn">
                        <span class="dashicons dashicons-networking" style="margin-top:2px;"></span> <?php esc_html_e( 'Start Crawler', 'statixpress-static-runtime' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wsr-settings' ) ); ?>" class="button wsr-btn">
                        <span class="dashicons dashicons-admin-settings" style="margin-top:2px;"></span> <?php esc_html_e( 'Settings', 'statixpress-static-runtime' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wsr-diagnostic' ) ); ?>" class="button wsr-btn">
                        <span class="dashicons dashicons-search" style="margin-top:2px;"></span> <?php esc_html_e( 'Diagnostic', 'statixpress-static-runtime' ); ?>
                    </a>
                </div>
                <div id="wsr-action-result" class="wsr-notice" style="display:none;margin-top:12px;"></div>

                <div id="wsr-progress-wrap" style="display:none;margin-top:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                        <span id="wsr-progress-label" style="font-size:13px;color:#475569;"><?php esc_html_e( 'Starting...', 'statixpress-static-runtime' ); ?></span>
                        <span id="wsr-progress-count" style="font-size:12px;color:#94a3b8;"></span>
                    </div>
                    <div class="wsr-progress-bar-bg">
                        <div id="wsr-progress-bar" class="wsr-progress-bar-fill" style="width:0%"></div>
                    </div>
                    <div id="wsr-progress-stats" style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748b;"></div>
                    <button id="wsr-crawl-cancel-btn" class="button" style="display:none;margin-top:8px;">■ <?php esc_html_e( 'Cancel', 'statixpress-static-runtime' ); ?></button>
                </div>
            </div>

            <!-- Engine Status -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Engine Status', 'statixpress-static-runtime' ); ?></h2>
                <table class="wsr-status-table widefat">
                    <tbody>
                        <?php self::status_row( __( 'Cache Engine',             'statixpress-static-runtime' ), ! empty( $settings['cache_enabled'] ) ); ?>
                        <?php self::status_row( __( 'WP_CACHE Defined',         'statixpress-static-runtime' ), defined( 'WP_CACHE' ) && WP_CACHE ); ?>
                        <?php self::status_row( __( 'advanced-cache.php',       'statixpress-static-runtime' ), self::check_advanced_cache() ); ?>
                        <?php self::status_row( __( 'Cache Directory Writable', 'statixpress-static-runtime' ), is_dir( WSR_CACHE_DIR ) && wp_is_writable( WSR_CACHE_DIR ) ); ?>
                        <?php self::status_row( __( 'WooCommerce Hybrid',       'statixpress-static-runtime' ), class_exists( 'WooCommerce' ) ); ?>
                        <?php self::status_row( __( 'Elementor Integration',    'statixpress-static-runtime' ), defined( 'ELEMENTOR_VERSION' ) ); ?>
                        <?php self::status_row( __( 'Gzip Cache',               'statixpress-static-runtime' ), ! empty( $settings['gzip_cache'] ) ); ?>
                        <?php self::status_row( __( 'HTML Minification',        'statixpress-static-runtime' ), ! empty( $settings['minify_html'] ) ); ?>
                    </tbody>
                </table>
            </div>


        </div>
        <?php
    }

    private static function status_bar( $settings ) {
        $on    = ! empty( $settings['cache_enabled'] );
        $class = $on ? 'wsr-status-bar active' : 'wsr-status-bar inactive';
        $label = $on
            ? '<span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;"></span> ' . __( 'Cache Active – Serving static HTML', 'statixpress-static-runtime' )
            : '<span class="dashicons dashicons-warning" style="vertical-align:text-bottom;"></span> ' . __( 'Cache Disabled – Enable in Settings', 'statixpress-static-runtime' );
        echo '<div class="' . esc_attr( $class ) . '">' . wp_kses_post( $label ) . '</div>';
    }

    private static function status_row( $label, $on ) {
        $badge = $on
            ? '<span class="wsr-badge ok"><span class="dashicons dashicons-yes-alt" style="font-size:12px;line-height:1;margin-right:2px;vertical-align:text-top;"></span>' . esc_html__( 'ACTIVE',   'statixpress-static-runtime' ) . '</span>'
            : '<span class="wsr-badge off"><span class="dashicons dashicons-dismiss" style="font-size:12px;line-height:1;margin-right:2px;vertical-align:text-top;"></span>' . esc_html__( 'INACTIVE', 'statixpress-static-runtime' ) . '</span>';
        echo '<tr><td>' . esc_html( $label ) . '</td><td>' . wp_kses_post( $badge ) . '</td></tr>';
    }

    private static function check_advanced_cache() {
        $f = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( ! file_exists( $f ) ) return false;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
        $c = file_get_contents( $f );
        return $c !== false && strpos( $c, 'WP Static Runtime' ) !== false;
    }
}

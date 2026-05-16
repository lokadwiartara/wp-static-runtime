<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Page — cache manager + crawler UI.
 */
class Cache_Page {

    // ── Cache Manager ─────────────────────────────────────────────────────────

    public static function render() {
        global $wpdb;
        $stats = \WSR\Cache::stats();
        $table = $wpdb->prefix . 'wsr_cache_index';
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY updated_at DESC LIMIT 500"
        );
        ?>
        <div class="wrap wsr-wrap">
            <h1>🗂️ <?php esc_html_e( 'Cache Manager', 'wp-static-runtime' ); ?></h1>

            <div class="wsr-cache-stats-bar">
                <span>📄 <strong><?php echo esc_html( $stats['total_pages'] ?? 0 ); ?></strong> <?php esc_html_e( 'pages cached', 'wp-static-runtime' ); ?></span>
                <span>💾 <strong><?php echo esc_html( $stats['disk_size'] ?? '0 B' ); ?></strong> <?php esc_html_e( 'on disk', 'wp-static-runtime' ); ?></span>
                <button id="wsr-flush-btn" class="button button-primary">🗑️ <?php esc_html_e( 'Flush All Cache', 'wp-static-runtime' ); ?></button>
            </div>

            <div id="wsr-action-result" class="wsr-notice" style="display:none;"></div>

            <table class="widefat wsr-cache-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th><?php esc_html_e( 'Cached At', 'wp-static-runtime' ); ?></th>
                        <th><?php esc_html_e( 'File Size', 'wp-static-runtime' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wp-static-runtime' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;">
                            <?php esc_html_e( 'No cached pages yet. Start the crawler or visit your site while logged out.', 'wp-static-runtime' ); ?>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) :
                            $exists = file_exists( $row->cache_path );
                            $size   = $exists ? size_format( filesize( $row->cache_path ) ) : '—';
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $row->url ); ?>" target="_blank"><?php echo esc_html( $row->url ); ?></a>
                                <?php if ( ! $exists ) echo '<span class="wsr-badge off">' . esc_html__( 'missing', 'wp-static-runtime' ) . '</span>'; ?>
                            </td>
                            <td><?php echo esc_html( $row->updated_at ); ?></td>
                            <td><?php echo esc_html( $size ); ?></td>
                            <td>
                                <button class="button wsr-purge-btn" data-url="<?php echo esc_attr( $row->url ); ?>"><?php esc_html_e( 'Purge', 'wp-static-runtime' ); ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── Crawler Page ──────────────────────────────────────────────────────────

    public static function render_crawler() {
        $status  = get_option( \WSR\Crawler\Crawler::STATUS_KEY, [] );

        $fetch_method = \WSR\Crawler\Crawler::detect_fetch_method();
        $loopback_ok   = ( $fetch_method !== 'none' );

        $url_count = count( \WSR\Crawler\Sitemap::from_wordpress() );
        ?>
        <div class="wrap wsr-wrap">
            <h1>🕷️ <?php esc_html_e( 'Static Crawler', 'wp-static-runtime' ); ?></h1>

            <?php if ( ! defined( 'WSR_PREMIUM_ACTIVE' ) ) : ?>
            <div style="background:#fef9c3;border-left:4px solid #eab308;padding:12px 16px;border-radius:4px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <strong style="font-size:13px;">⚠️ <?php esc_html_e( 'Manual Mode — Free Version', 'wp-static-runtime' ); ?></strong>
                    <p style="margin:4px 0 0;font-size:12px;color:#713f12;">
                        <?php esc_html_e( 'The crawler must be run manually each time new content is published. Auto-Crawler (scheduled cron + ISR) is available in Premium.', 'wp-static-runtime' ); ?>
                    </p>
                </div>
                <a href="https://statixpress.site/premium" target="_blank"
                   style="white-space:nowrap;background:#7c3aed;color:#fff;border:none;padding:6px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;">
                    ⚡ <?php esc_html_e( 'Upgrade to Premium', 'wp-static-runtime' ); ?>
                </a>
            </div>
            <?php endif; ?>

            <p style="color:#475569;font-size:13px;"><?php esc_html_e( 'Visits all public URLs and builds a static HTML cache. No external sitemap required.', 'wp-static-runtime' ); ?></p>

            <!-- Connectivity Status -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Server Connectivity', 'wp-static-runtime' ); ?></h2>
                <table class="wsr-status-table widefat" style="max-width:640px;">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Loopback HTTP', 'wp-static-runtime' ); ?></strong></td>
                            <td>
                <?php
                $method_names = [
                    'wp_remote' => '✔ ' . __( 'WordPress HTTP (standard mode)', 'wp-static-runtime' ),
                    'fgc'       => '✔ ' . __( 'PHP file_get_contents (bypasses loopback restriction)', 'wp-static-runtime' ),
                    'curl'      => '✔ ' . __( 'cURL direct', 'wp-static-runtime' ),
                    'none'      => '✘ ' . __( 'No working fetch method found', 'wp-static-runtime' ),
                ];
                $label = isset( $method_names[ $fetch_method ] ) ? $method_names[ $fetch_method ] : $fetch_method;
                $style = ( $fetch_method === 'none' ) ? 'wsr-badge off' : 'wsr-badge ok';
                ?>
                <span class="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $label ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'URLs to Cache', 'wp-static-runtime' ); ?></strong></td>
                            <td>
                                <span class="wsr-badge ok">✔ <?php echo esc_html( $url_count ); ?> <?php esc_html_e( 'URLs discovered from WordPress DB', 'wp-static-runtime' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Cache Directory', 'wp-static-runtime' ); ?></strong></td>
                            <td>
                                <?php if ( is_dir( WSR_CACHE_DIR ) && is_writable( WSR_CACHE_DIR ) ) : ?>
                                    <span class="wsr-badge ok">✔ <?php esc_html_e( 'Writable', 'wp-static-runtime' ); ?></span>
                                <?php else : ?>
                                    <span class="wsr-badge off">✘ <?php esc_html_e( 'Not writable — fix permissions on', 'wp-static-runtime' ); ?> <?php echo esc_html( WSR_CACHE_DIR ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Controls -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Crawl Controls', 'wp-static-runtime' ); ?></h2>
                <div class="wsr-crawler-panel">
                    <div class="wsr-actions">
                        <button id="wsr-crawl-start-btn" class="button button-primary wsr-btn">
                            ▶ <?php esc_html_e( 'Start Crawl', 'wp-static-runtime' ); ?>
                        </button>
                    </div>

                    <div id="wsr-progress-wrap" style="display:none;margin-top:20px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                            <span id="wsr-progress-label" style="font-size:13px;color:#475569;"><?php esc_html_e( 'Starting...', 'wp-static-runtime' ); ?></span>
                            <span id="wsr-progress-count" style="font-size:12px;color:#94a3b8;"></span>
                        </div>
                        <div class="wsr-progress-bar-bg">
                            <div id="wsr-progress-bar" class="wsr-progress-bar-fill" style="width:0%"></div>
                        </div>
                        <div id="wsr-progress-stats" style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748b;"></div>
                        <button id="wsr-crawl-cancel-btn" class="button" style="display:none;margin-top:10px;">■ <?php esc_html_e( 'Cancel', 'wp-static-runtime' ); ?></button>
                    </div>

                    <div id="wsr-action-result" class="wsr-notice" style="display:none;margin-top:12px;"></div>
                </div>
            </div>

            <!-- Last Run Results -->
            <?php if ( ! empty( $status ) && isset( $status['total'] ) ) : ?>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Last Crawl Results', 'wp-static-runtime' ); ?></h2>
                <div class="wsr-cards" style="grid-template-columns:repeat(4,1fr);">
                    <?php
                    $pairs = [
                        [ '📊', $status['total']   ?? 0, __( 'Total',   'wp-static-runtime' ), '#7c3aed' ],
                        [ '✅', $status['cached']  ?? 0, __( 'Cached',  'wp-static-runtime' ), '#15803d' ],
                        [ '⏭️', $status['skipped'] ?? 0, __( 'Skipped', 'wp-static-runtime' ), '#0369a1' ],
                        [ '❌', $status['failed']  ?? 0, __( 'Failed',  'wp-static-runtime' ), '#b91c1c' ],
                    ];
                    foreach ( $pairs as $p ) :
                    ?>
                    <div class="wsr-card">
                        <div class="wsr-card-icon"><?php echo $p[0]; ?></div>
                        <div class="wsr-card-value" style="color:<?php echo esc_attr( $p[3] ); ?>;"><?php echo (int) $p[1]; ?></div>
                        <div class="wsr-card-label"><?php echo esc_html( $p[2] ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( ! empty( $status['started_at'] ) ) : ?>
                <p style="font-size:12px;color:#94a3b8;margin-top:6px;">
                    <?php esc_html_e( 'Started', 'wp-static-runtime' ); ?>: <?php echo esc_html( $status['started_at'] ); ?>
                    <?php if ( ! empty( $status['finished_at'] ) ) echo ' · ' . esc_html__( 'Finished', 'wp-static-runtime' ) . ': ' . esc_html( $status['finished_at'] ); ?>
                </p>
                <?php endif; ?>

                <?php if ( ! empty( $status['errors'] ) ) : ?>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#b91c1c;">
                        ⚠ <?php echo count( $status['errors'] ); ?> <?php esc_html_e( 'errors', 'wp-static-runtime' ); ?>
                    </summary>
                    <table class="widefat" style="margin-top:8px;">
                        <thead><tr><th>URL</th><th><?php esc_html_e( 'Reason', 'wp-static-runtime' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( (array) $status['errors'] as $err ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $err['url'] ?? '' ); ?>" target="_blank"><?php echo esc_html( $err['url'] ?? '' ); ?></a></td>
                                <td><code><?php echo esc_html( $err['reason'] ?? '' ); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Page – cache manager + crawler UI.
 */
class Cache_Page {

    // ── Cache Manager ─────────────────────────────────────────────────────────

    public static function render() {
        global $wpdb;
        $stats = \WSR\Cache::stats();
        $table = esc_sql( $wpdb->prefix . 'wsr_cache_index' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY updated_at DESC LIMIT 500"
        );
        // phpcs:enable
        ?>
        <div class="wrap wsr-wrap">
            <h1>🗂️  <?php esc_html_e( 'Cache Manager', 'statixpress-static-runtime' ); ?></h1>

            <div class="wsr-cache-stats-bar">
                <span>📄 <strong><?php echo esc_html( $stats['total_pages'] ?? 0 ); ?></strong> <?php esc_html_e( 'pages cached', 'statixpress-static-runtime' ); ?></span>
                <span>💾 <strong><?php echo esc_html( $stats['disk_size'] ?? '0 B' ); ?></strong> <?php esc_html_e( 'on disk', 'statixpress-static-runtime' ); ?></span>
                <button id="wsr-flush-btn" class="button button-primary">🗑️ <?php esc_html_e( 'Flush All Cache', 'statixpress-static-runtime' ); ?></button>
            </div>

            <div id="wsr-action-result" class="wsr-notice" style="display:none;"></div>

            <table class="widefat wsr-cache-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th><?php esc_html_e( 'Cached At', 'statixpress-static-runtime' ); ?></th>
                        <th><?php esc_html_e( 'File Size', 'statixpress-static-runtime' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'statixpress-static-runtime' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;">
                            <?php esc_html_e( 'No cached pages yet. Start the crawler or visit your site while logged out.', 'statixpress-static-runtime' ); ?>
                        </td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) :
                            $exists = file_exists( $row->cache_path );
                            $size   = $exists ? size_format( filesize( $row->cache_path ) ) : '–';
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $row->url ); ?>" target="_blank"><?php echo esc_html( $row->url ); ?></a>
                                <?php if ( ! $exists ) echo '<span class="wsr-badge off">' . esc_html__( 'missing', 'statixpress-static-runtime' ) . '</span>'; ?>
                            </td>
                            <td><?php echo esc_html( $row->updated_at ); ?></td>
                            <td><?php echo esc_html( $size ); ?></td>
                            <td>
                                <button class="button wsr-purge-btn" data-url="<?php echo esc_attr( $row->url ); ?>"><?php esc_html_e( 'Purge', 'statixpress-static-runtime' ); ?></button>
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
            <h1>🕷️  <?php esc_html_e( 'Static Crawler', 'statixpress-static-runtime' ); ?></h1>


            <p style="color:#475569;font-size:13px;"><?php esc_html_e( 'Visits all public URLs and builds a static HTML cache. No external sitemap required.', 'statixpress-static-runtime' ); ?></p>

            <!-- Connectivity Status -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Server Connectivity', 'statixpress-static-runtime' ); ?></h2>
                <table class="wsr-status-table widefat" style="max-width:640px;">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Loopback HTTP', 'statixpress-static-runtime' ); ?></strong></td>
                            <td>
                <?php
                $method_names = [
                    'wp_remote' => '✔ ' . __( 'WordPress HTTP (standard mode)', 'statixpress-static-runtime' ),
                    'fgc'       => '✔ ' . __( 'PHP file_get_contents (bypasses loopback restriction)', 'statixpress-static-runtime' ),
                    'curl'      => '✔ ' . __( 'cURL direct', 'statixpress-static-runtime' ),
                    'none'      => '✘ ' . __( 'No working fetch method found', 'statixpress-static-runtime' ),
                ];
                $label = isset( $method_names[ $fetch_method ] ) ? $method_names[ $fetch_method ] : $fetch_method;
                $style = ( $fetch_method === 'none' ) ? 'wsr-badge off' : 'wsr-badge ok';
                ?>
                <span class="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $label ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'URLs to Cache', 'statixpress-static-runtime' ); ?></strong></td>
                            <td>
                                <span class="wsr-badge ok">✔ <?php echo esc_html( $url_count ); ?> <?php esc_html_e( 'URLs discovered from WordPress DB', 'statixpress-static-runtime' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Cache Directory', 'statixpress-static-runtime' ); ?></strong></td>
                            <td>
                                <?php if ( is_dir( WSR_CACHE_DIR ) && wp_is_writable( WSR_CACHE_DIR ) ) : ?>
                                    <span class="wsr-badge ok">✔ <?php esc_html_e( 'Writable', 'statixpress-static-runtime' ); ?></span>
                                <?php else : ?>
                                    <span class="wsr-badge off">✘ <?php esc_html_e( 'Not writable – fix permissions on', 'statixpress-static-runtime' ); ?> <?php echo esc_html( WSR_CACHE_DIR ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Controls -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Crawl Controls', 'statixpress-static-runtime' ); ?></h2>
                <div class="wsr-crawler-panel">
                    <div class="wsr-actions">
                        <button id="wsr-crawl-start-btn" class="button button-primary wsr-btn">
                            ▶ <?php esc_html_e( 'Start Crawl', 'statixpress-static-runtime' ); ?>
                        </button>
                    </div>

                    <div id="wsr-progress-wrap" style="display:none;margin-top:20px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                            <span id="wsr-progress-label" style="font-size:13px;color:#475569;"><?php esc_html_e( 'Starting...', 'statixpress-static-runtime' ); ?></span>
                            <span id="wsr-progress-count" style="font-size:12px;color:#94a3b8;"></span>
                        </div>
                        <div class="wsr-progress-bar-bg">
                            <div id="wsr-progress-bar" class="wsr-progress-bar-fill" style="width:0%"></div>
                        </div>
                        <div id="wsr-progress-stats" style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748b;"></div>
                        <button id="wsr-crawl-cancel-btn" class="button" style="display:none;margin-top:10px;">■ <?php esc_html_e( 'Cancel', 'statixpress-static-runtime' ); ?></button>
                    </div>

                    <div id="wsr-action-result" class="wsr-notice" style="display:none;margin-top:12px;"></div>
                </div>
            </div>

            <!-- Last Run Results -->
            <?php if ( ! empty( $status ) && isset( $status['total'] ) ) : ?>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Last Crawl Results', 'statixpress-static-runtime' ); ?></h2>
                <div class="wsr-cards" style="grid-template-columns:repeat(4,1fr);">
                    <?php
                    $pairs = [
                        [ '📊', $status['total']   ?? 0, __( 'Total',   'statixpress-static-runtime' ), '#7c3aed' ],
                        [ '✅', $status['cached']  ?? 0, __( 'Cached',  'statixpress-static-runtime' ), '#15803d' ],
                        [ '⭐', $status['skipped'] ?? 0, __( 'Skipped', 'statixpress-static-runtime' ), '#0369a1' ],
                        [ '❌', $status['failed']  ?? 0, __( 'Failed',  'statixpress-static-runtime' ), '#b91c1c' ],
                    ];
                    foreach ( $pairs as $p ) :
                    ?>
                    <div class="wsr-card">
                        <div class="wsr-card-icon"><?php echo esc_html( $p[0] ); ?></div>
                        <div class="wsr-card-value" style="color:<?php echo esc_attr( $p[3] ); ?>;"><?php echo (int) $p[1]; ?></div>
                        <div class="wsr-card-label"><?php echo esc_html( $p[2] ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( ! empty( $status['started_at'] ) ) : ?>
                <p style="font-size:12px;color:#94a3b8;margin-top:6px;">
                    <?php esc_html_e( 'Started', 'statixpress-static-runtime' ); ?>: <?php echo esc_html( $status['started_at'] ); ?>
                    <?php if ( ! empty( $status['finished_at'] ) ) echo ' · ' . esc_html__( 'Finished', 'statixpress-static-runtime' ) . ': ' . esc_html( $status['finished_at'] ); ?>
                </p>
                <?php endif; ?>

                <?php if ( ! empty( $status['errors'] ) ) : ?>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#b91c1c;">
                        ⚠️ <?php echo count( $status['errors'] ); ?> <?php esc_html_e( 'errors', 'statixpress-static-runtime' ); ?>
                    </summary>
                    <table class="widefat" style="margin-top:8px;">
                        <thead><tr><th>URL</th><th><?php esc_html_e( 'Reason', 'statixpress-static-runtime' ); ?></th></tr></thead>
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

<?php
namespace WSR\Premium;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Premium — extends the free admin with Premium-only tabs and settings.
 * v1.2.2 — tambah License tab
 */
class Admin_Premium {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_premium_pages'   ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_premium_assets' ] );
        add_action( 'wsr_settings_tabs_html', [ $this, 'render_premium_tabs' ] );
        add_action( 'wsr_settings_content_html', [ $this, 'render_premium_content' ] );
        add_action( 'wsr_save_settings_extra', [ $this, 'save_premium_settings' ] );
        add_action( 'admin_footer',          [ $this, 'maybe_render_license_overlay' ] );
    }

    public function add_premium_pages(): void {
        add_submenu_page( 'wsr-dashboard', __( 'CDN', 'wp-static-runtime' ),
            '🌍 ' . __( 'CDN', 'wp-static-runtime' ), 'manage_options', 'wsr-cdn', [ $this, 'render_cdn_page' ] );
        add_submenu_page( 'wsr-dashboard', __( 'ISR', 'wp-static-runtime' ),
            '⚡ ' . __( 'ISR', 'wp-static-runtime' ), 'manage_options', 'wsr-isr', [ $this, 'render_isr_page' ] );
    }

    public function enqueue_premium_assets( $hook ): void {
        if ( strpos( $hook, 'wsr-' ) === false ) return;
        wp_enqueue_style( 'wsr-premium-admin', WSR_PREMIUM_URL . 'assets/css/admin.css', [], WSR_PREMIUM_VER );
        wp_enqueue_script( 'wsr-admin-premium', WSR_PREMIUM_URL . 'assets/js/admin-premium.js', [ 'jquery' ], WSR_PREMIUM_VER, true );
        wp_localize_script( 'wsr-admin-premium', 'wsrLicense', [
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'wsr_nonce' ),
            'activating'         => __( 'Activating...', 'wp-static-runtime' ),
            'deactivate_confirm' => __( 'Are you sure? This will deactivate your license.', 'wp-static-runtime' ),
        ] );
    }

    public function add_settings_tabs( array $tabs ): array {
        $tabs['cdn']     = '🌍 ' . __( 'CDN', 'wp-static-runtime' );
        $tabs['isr']     = '⚡ ' . __( 'ISR', 'wp-static-runtime' );
        return $tabs;
    }



    // ══════════════════════════════════════════════════════════════════════════
    // CDN PAGE
    // ══════════════════════════════════════════════════════════════════════════
    public function render_cdn_page(): void {
        $settings  = get_option( 'wsr_settings', [] );
        $providers = \WSR\Premium\CDN\CDN_Manager::get_providers();
        ?>
        <div class="wrap wsr-wrap">
            <h1><span class="dashicons dashicons-admin-site-alt3" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span> <?php esc_html_e( 'CDN Management', 'wp-static-runtime' ); ?></h1>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Active CDN Providers', 'wp-static-runtime' ); ?></h2>
                <?php if ( empty( $providers ) ) : ?>
                    <div class="notice notice-info" style="display:block;padding:12px;"><?php esc_html_e( 'No CDN providers configured. Configure in Settings → CDN.', 'wp-static-runtime' ); ?></div>
                <?php else : ?>
                    <div class="wsr-cards">
                        <?php foreach ( $providers as $p ) : ?>
                        <div class="wsr-card">
                            <div class="wsr-card-icon"><span class="dashicons dashicons-admin-site-alt3"></span></div>
                            <div class="wsr-card-value" style="font-size:16px;"><?php echo esc_html( strtoupper( $p->name() ) ); ?></div>
                            <div class="wsr-badge ok" style="margin-top:8px;"><?php esc_html_e( 'Active', 'wp-static-runtime' ); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'CDN Cache Control', 'wp-static-runtime' ); ?></h2>
                <button id="wsr-cdn-purge-btn" class="button button-primary" <?php disabled( empty( $providers ) ); ?>><span class="dashicons dashicons-update-alt" style="margin-top:2px;"></span> <?php esc_html_e( 'Purge All CDN Cache', 'wp-static-runtime' ); ?></button>
                <div id="wsr-action-result" class="notice" style="display:none;margin-top:8px;"></div>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <input type="url" id="wsr-cdn-purge-url" class="regular-text" placeholder="https://yoursite.com/some-page/" />
                    <button id="wsr-cdn-purge-url-btn" class="button"><?php esc_html_e( 'Purge URL', 'wp-static-runtime' ); ?></button>
                </div>
            </div>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Provider Status', 'wp-static-runtime' ); ?></h2>
                <table class="widefat wsr-status-table">
                    <thead><tr><th><?php esc_html_e( 'Provider', 'wp-static-runtime' ); ?></th><th><?php esc_html_e( 'Status', 'wp-static-runtime' ); ?></th><th><?php esc_html_e( 'Config', 'wp-static-runtime' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ([
                            ['Cloudflare', $settings['cdn_cloudflare_enabled']??false, !empty($settings['cdn_cloudflare_zone_id'])],
                            ['BunnyCDN',   $settings['cdn_bunny_enabled']??false,       !empty($settings['cdn_bunny_api_key'])],
                            ['Fastly',     $settings['cdn_fastly_enabled']??false,      !empty($settings['cdn_fastly_api_key'])],
                        ] as [$name,$enabled,$cfg]) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($name); ?></strong></td>
                            <td><?php echo $enabled ? '<span class="wsr-badge ok">' . esc_html__( 'Enabled', 'wp-static-runtime' ) . '</span>' : '<span class="wsr-badge">' . esc_html__( 'Disabled', 'wp-static-runtime' ) . '</span>'; ?></td>
                            <td><?php echo $cfg ? '<span class="wsr-badge ok">' . esc_html__( 'Configured', 'wp-static-runtime' ) . '</span>' : '<span class="wsr-badge error">' . esc_html__( 'Not Configured', 'wp-static-runtime' ) . '</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ISR PAGE
    // ══════════════════════════════════════════════════════════════════════════
    public function render_isr_page(): void {
        $queue    = \WSR\Premium\ISR::get_queue();
        $settings = get_option( 'wsr_settings', [] );
        ?>
        <div class="wrap wsr-wrap">
            <h1><span class="dashicons dashicons-superhero" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span> <?php esc_html_e( 'Incremental Static Regeneration', 'wp-static-runtime' ); ?></h1>
            <p><?php esc_html_e( 'ISR keeps the stale page serving while regenerating in the background — zero downtime cache updates.', 'wp-static-runtime' ); ?></p>
            <div class="wsr-cards">
                <div class="wsr-card"><div class="wsr-card-icon"><span class="dashicons dashicons-media-document"></span></div><div class="wsr-card-value"><?php echo count($queue); ?></div><div class="wsr-card-label"><?php esc_html_e( 'URLs in Queue', 'wp-static-runtime' ); ?></div></div>
                <div class="wsr-card"><div class="wsr-card-icon"><span class="dashicons dashicons-clock"></span></div><div class="wsr-card-value"><?php echo esc_html($settings['isr_revalidate']??'0'); ?><small>s</small></div><div class="wsr-card-label">TTL</div></div>
                <div class="wsr-card"><div class="wsr-card-icon"><?php echo empty($settings['isr_enabled']) ? '<span class="dashicons dashicons-controls-pause"></span>' : '<span class="dashicons dashicons-controls-play"></span>'; ?></div><div class="wsr-card-value" style="font-size:16px;"><?php echo empty($settings['isr_enabled']) ? esc_html__( 'Disabled', 'wp-static-runtime' ) : esc_html__( 'Active', 'wp-static-runtime' ); ?></div><div class="wsr-card-label"><?php esc_html_e( 'ISR Status', 'wp-static-runtime' ); ?></div></div>
            </div>
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Regeneration Queue', 'wp-static-runtime' ); ?></h2>
                <?php if ( empty( $queue ) ) : ?><p style="color:#64748b;"><?php esc_html_e( 'Queue is empty.', 'wp-static-runtime' ); ?></p>
                <?php else : ?>
                <table class="widefat"><thead><tr><th>#</th><th>URL</th><th><?php esc_html_e( 'Action', 'wp-static-runtime' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $queue as $i => $url ) : ?>
                    <tr><td><?php echo $i+1; ?></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></td>
                    <td><button class="button wsr-isr-revalidate-btn" data-url="<?php echo esc_attr($url); ?>"><?php esc_html_e( 'Revalidate Now', 'wp-static-runtime' ); ?></button></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
                <?php endif; ?>
                <div id="wsr-action-result" class="notice" style="display:none;margin-top:12px;"></div>
            </div>
        </div>
        <?php
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PREMIUM SETTINGS
    // ══════════════════════════════════════════════════════════════════════════
    public function render_premium_tabs(): void {
        ?>
        <a href="#core" class="nav-tab"><?php esc_html_e( 'Core Features', 'wp-static-runtime' ); ?></a>
        <a href="#opt" class="nav-tab"><?php esc_html_e( 'Advanced Opt', 'wp-static-runtime' ); ?></a>
        <a href="#cdn" class="nav-tab"><?php esc_html_e( 'CDN Integrations', 'wp-static-runtime' ); ?></a>
        <a href="#cache" class="nav-tab"><?php esc_html_e( 'Object Cache', 'wp-static-runtime' ); ?></a>
        <?php
    }

    public function render_premium_content(): void {
        $settings = get_option( 'wsr_settings', [] );
        ?>
                
                <!-- Core Features -->
                <div id="tab-core" class="wsr-tab-content" style="display:none;">
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-superhero" style="vertical-align:middle;margin-right:4px;"></span> ISR — <?php esc_html_e( 'Incremental Static Regeneration', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable ISR', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="isr_enabled" value="1" <?php checked(!empty($settings['isr_enabled'])); ?> /> <?php esc_html_e( 'Enable background regeneration', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Revalidate TTL (seconds)', 'wp-static-runtime' ); ?></th><td><input type="number" name="isr_revalidate" value="<?php echo esc_attr($settings['isr_revalidate']??0); ?>" min="0" step="60" class="small-text" /><p class="description"><?php esc_html_e( '0 = event-driven only (recommended)', 'wp-static-runtime' ); ?></p></td></tr>
                        <tr><th><?php esc_html_e( 'Queue Batch Size', 'wp-static-runtime' ); ?></th><td><input type="number" name="isr_queue_size" value="<?php echo esc_attr($settings['isr_queue_size']??10); ?>" min="1" max="50" class="small-text" /></td></tr>
                    </table>

                    <?php
                    $home_url  = home_url('/');
                    $cron_url  = esc_url( $home_url . 'wp-cron.php' );
                    $cron_cmd  = '* * * * * curl -s --max-time 10 "' . $cron_url . '" > /dev/null 2>&1';
                    $wp_cmd    = '* * * * * cd ' . rtrim(ABSPATH, '/') . ' && wp cron event run --due-now --quiet 2>/dev/null';
                    ?>
                    <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:14px 18px;border-radius:4px;margin-top:16px;">
                        <p style="margin:0 0 8px;font-weight:600;font-size:13px;">
                            🕐 <?php esc_html_e( 'Recommendation: Enable System Cron', 'wp-static-runtime' ); ?>
                        </p>
                        <p style="margin:0 0 10px;font-size:13px;color:#374151;">
                            <?php esc_html_e( 'ISR relies on WP-Cron to process the background queue. WordPress\'s built-in WP-Cron only runs when a request hits your site — on low traffic this can delay processing by minutes or hours.', 'wp-static-runtime' ); ?>
                            <strong><?php esc_html_e( 'For responsive ISR, disable the built-in WP-Cron and use a system cron job.', 'wp-static-runtime' ); ?></strong>
                        </p>

                        <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#1e40af;">Step 1 — <?php esc_html_e( 'Add to wp-config.php:', 'wp-static-runtime' ); ?></p>
                        <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;margin:0 0 12px;overflow:auto;">define( 'DISABLE_WP_CRON', true );</pre>

                        <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#1e40af;">Step 2 — <?php esc_html_e( 'Add to server crontab', 'wp-static-runtime' ); ?> (<code>crontab -e</code>):</p>
                        <p style="margin:0 0 4px;font-size:11px;color:#6b7280;"><?php esc_html_e( 'Option A — via curl (most common):', 'wp-static-runtime' ); ?></p>
                        <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;margin:0 0 8px;overflow:auto;"><?php echo esc_html( $cron_cmd ); ?></pre>
                        <p style="margin:0 0 4px;font-size:11px;color:#6b7280;"><?php esc_html_e( 'Option B — via WP-CLI (lighter):', 'wp-static-runtime' ); ?></p>
                        <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;margin:0 0 10px;overflow:auto;"><?php echo esc_html( $wp_cmd ); ?></pre>

                        <p style="margin:0;font-size:12px;color:#6b7280;">
                            💡 <?php esc_html_e( 'With a system cron running every minute, ISR will process the queue within seconds of content changing.', 'wp-static-runtime' ); ?>
                        </p>
                    </div>
                </div>
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-networking" style="vertical-align:middle;margin-right:4px;"></span> Auto-Crawler</h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Auto-Crawler', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="crawler_enabled" value="1" <?php checked( ! empty( $settings['crawler_enabled'] ) ); ?> />
                                    <?php esc_html_e( 'Pre-build cache from sitemap hourly (WP-Cron)', 'wp-static-runtime' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Crawler runs automatically every hour to keep all page caches up to date.', 'wp-static-runtime' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-share-alt2" style="vertical-align:middle;margin-right:4px;"></span> <?php esc_html_e( 'Smart Dependency', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable Smart Dependency', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="smart_dependency" value="1" <?php checked($settings['smart_dependency']??true); ?> /> <?php esc_html_e( 'Gutenberg block tracking, WooCommerce relations, L2 traversal', 'wp-static-runtime' ); ?></label></td></tr>
                    </table>
                </div>
                </div>

                <!-- Optimization -->
                <div id="tab-opt" class="wsr-tab-content" style="display:none;">
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-dashboard" style="vertical-align:middle;margin-right:4px;"></span> <?php esc_html_e( 'HTML Optimizer (PageSpeed)', 'wp-static-runtime' ); ?></h2>
                    <p><?php esc_html_e( 'Improves PageSpeed scores (LCP, FCP, CLS) by optimizing HTML before caching.', 'wp-static-runtime' ); ?></p>
                    <button type="button" id="wsr-purge-asset-cache-btn" class="button button-secondary" style="margin-bottom:16px;">🗑️ <?php esc_html_e( 'Purge Asset Cache', 'wp-static-runtime' ); ?></button>
                    <div id="wsr-asset-cache-status" style="margin-bottom:16px;display:none;" class="notice"></div>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Minify HTML', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_minify_html" value="1" <?php checked(!empty($settings['opt_minify_html'])); ?> /> <?php esc_html_e( 'Strip whitespace & comments (preserves pre/textarea/code)', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Minify CSS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_minify_css" value="1" <?php checked(!empty($settings['opt_minify_css'])); ?> /> <?php esc_html_e( 'Minify inline &lt;style&gt; blocks', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Cache & Minify External CSS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_cache_external_css" value="1" <?php checked(!empty($settings['opt_cache_external_css'])); ?> /> <?php esc_html_e( 'Download, minify, and cache external CSS files to disk', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Exclude patterns (one per line):', 'wp-static-runtime' ); ?></p>
                            <textarea name="opt_cache_css_exclude" rows="3" class="large-text code" placeholder="critical.css&#10;style.min.css"><?php echo esc_textarea($settings['opt_cache_css_exclude']??''); ?></textarea>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Cache & Minify External JS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_cache_external_js" value="1" <?php checked(!empty($settings['opt_cache_external_js'])); ?> /> <?php esc_html_e( 'Download, minify, and cache external JS files to disk', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Exclude patterns (one per line):', 'wp-static-runtime' ); ?></p>
                            <textarea name="opt_cache_js_exclude" rows="3" class="large-text code" placeholder="jquery.min.js&#10;bootstrap.js"><?php echo esc_textarea($settings['opt_cache_js_exclude']??''); ?></textarea>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Defer JS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_defer_js" value="1" <?php checked(!empty($settings['opt_defer_js'])); ?> /> <?php esc_html_e( 'Add defer attribute to blocking scripts', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Defer CSS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_defer_css" value="1" <?php checked(!empty($settings['opt_defer_css'])); ?> /> <?php esc_html_e( 'Convert render-blocking CSS to non-blocking (media=print)', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Exclude patterns (one per line):', 'wp-static-runtime' ); ?></p>
                            <textarea name="opt_defer_css_exclude" rows="3" class="large-text code" placeholder="critical.css&#10;style.min.css"><?php echo esc_textarea($settings['opt_defer_css_exclude']??''); ?></textarea>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Image Optimization', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_lazy_load" value="1" <?php checked(!empty($settings['opt_lazy_load'])); ?> /> <?php esc_html_e( 'Lazy load images, smart LCP detection & preload link injection', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Critical CSS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_critical_css" value="1" <?php checked(!empty($settings['opt_critical_css'])); ?> /> <?php esc_html_e( 'Inject manual critical CSS inline in &lt;head&gt;', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Paste your above-the-fold critical CSS below:', 'wp-static-runtime' ); ?></p>
                            <textarea name="opt_critical_css_content" rows="6" class="large-text code" placeholder="/* Critical CSS here */"><?php echo esc_textarea($settings['opt_critical_css_content']??''); ?></textarea>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Auto-Generate Critical CSS', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_critical_css_auto" value="1" <?php checked(!empty($settings['opt_critical_css_auto'])); ?> /> <?php esc_html_e( 'Auto-extract above-the-fold CSS heuristically', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Automatically extract critical CSS from rendered HTML using selector priority rules.', 'wp-static-runtime' ); ?></p>
                            <button type="button" id="wsr-generate-critical-css-btn" class="button button-secondary" style="margin-top:8px;">🔄 <?php esc_html_e( 'Generate Critical CSS Now', 'wp-static-runtime' ); ?></button>
                            <div id="wsr-critical-css-status" style="margin-top:8px;display:none;" class="notice"></div>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Preconnect', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_preconnect" value="1" <?php checked(!empty($settings['opt_preconnect'])); ?> /> <?php esc_html_e( 'Auto-detect & inject preconnect/dns-prefetch for external domains', 'wp-static-runtime' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Additional domains (one per line):', 'wp-static-runtime' ); ?></p>
                            <textarea name="opt_preconnect_domains" rows="3" class="large-text code" placeholder="fonts.googleapis.com&#10;cdn.example.com"><?php echo esc_textarea($settings['opt_preconnect_domains']??''); ?></textarea>
                        </td></tr>
                        <tr><th><?php esc_html_e( 'Font Display', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_font_display" value="1" <?php checked(!empty($settings['opt_font_display'])); ?> /> <?php esc_html_e( 'Inject font-display: swap & preload .woff2 fonts', 'wp-static-runtime' ); ?></label></td></tr>
                    </table>
                    <h3><span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span> <?php esc_html_e( 'Remove Unused Assets', 'wp-static-runtime' ); ?></h3>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'WP Emoji', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_remove_emoji" value="1" <?php checked(!empty($settings['opt_remove_emoji'])); ?> /> <?php esc_html_e( 'Remove wp-emoji scripts & styles', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'WP Embed', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_remove_embed" value="1" <?php checked(!empty($settings['opt_remove_embed'])); ?> /> <?php esc_html_e( 'Remove wp-embed.min.js', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'jQuery Migrate', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="opt_remove_jquery_migrate" value="1" <?php checked(!empty($settings['opt_remove_jquery_migrate'])); ?> /> <?php esc_html_e( 'Remove jquery-migrate (may break old plugins)', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Asset Blacklist', 'wp-static-runtime' ); ?></th><td>
                            <textarea name="opt_remove_assets_blacklist" rows="3" class="large-text code" placeholder="plugin-name/script.js&#10;unused-style.css"><?php echo esc_textarea($settings['opt_remove_assets_blacklist']??''); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Script/style filename patterns to remove (one per line).', 'wp-static-runtime' ); ?></p>
                        </td></tr>
                    </table>
                </div>
                </div>

                <!-- CDN Integrations -->
                <div id="tab-cdn" class="wsr-tab-content" style="display:none;">
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-admin-site-alt3" style="vertical-align:middle;margin-right:4px;"></span> CDN — Cloudflare</h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="cdn_cloudflare_enabled" value="1" <?php checked(!empty($settings['cdn_cloudflare_enabled'])); ?> /></label></td></tr>
                        <tr><th><?php esc_html_e( 'Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" name="cdn_cloudflare_zone_id" class="regular-text" value="<?php echo esc_attr($settings['cdn_cloudflare_zone_id']??''); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Email', 'wp-static-runtime' ); ?></th><td><input type="email" name="cdn_cloudflare_email" class="regular-text" value="<?php echo esc_attr($settings['cdn_cloudflare_email']??''); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" autocomplete="off" name="cdn_cloudflare_api_key" class="regular-text" value="<?php echo esc_attr($settings['cdn_cloudflare_api_key']??''); ?>" /></td></tr>
                    </table>
                    <h2><span class="dashicons dashicons-admin-site-alt3" style="vertical-align:middle;margin-right:4px;"></span> BunnyCDN</h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="cdn_bunny_enabled" value="1" <?php checked(!empty($settings['cdn_bunny_enabled'])); ?> /></label></td></tr>
                        <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" autocomplete="off" name="cdn_bunny_api_key" class="regular-text" value="<?php echo esc_attr($settings['cdn_bunny_api_key']??''); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Pull Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" name="cdn_bunny_zone_id" class="regular-text" value="<?php echo esc_attr($settings['cdn_bunny_zone_id']??''); ?>" /></td></tr>
                    </table>
                    <h2><span class="dashicons dashicons-admin-site-alt3" style="vertical-align:middle;margin-right:4px;"></span> Fastly</h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="cdn_fastly_enabled" value="1" <?php checked(!empty($settings['cdn_fastly_enabled'])); ?> /></label></td></tr>
                        <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" autocomplete="off" name="cdn_fastly_api_key" class="regular-text" value="<?php echo esc_attr($settings['cdn_fastly_api_key']??''); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Service ID', 'wp-static-runtime' ); ?></th><td><input type="text" name="cdn_fastly_service_id" class="regular-text" value="<?php echo esc_attr($settings['cdn_fastly_service_id']??''); ?>" /></td></tr>
                    </table>
                </div>
                </div>

                <!-- Object Cache -->
                <div id="tab-cache" class="wsr-tab-content" style="display:none;">
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-database-add" style="vertical-align:middle;margin-right:4px;"></span> Redis</h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable Redis', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="redis_enabled" value="1" <?php checked(!empty($settings['redis_enabled'])); ?> /></label></td></tr>
                        <tr><th><?php esc_html_e( 'Full-Page Cache', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="redis_full_page" value="1" <?php checked(!empty($settings['redis_full_page'])); ?> /> <?php esc_html_e( 'Store HTML pages in Redis for disk-free serving', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Host', 'wp-static-runtime' ); ?></th><td><input type="text" name="redis_host" class="regular-text" value="<?php echo esc_attr($settings['redis_host']??'127.0.0.1'); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Port', 'wp-static-runtime' ); ?></th><td><input type="number" name="redis_port" class="small-text" value="<?php echo esc_attr($settings['redis_port']??6379); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Password', 'wp-static-runtime' ); ?></th><td><input type="password" autocomplete="off" name="redis_password" class="regular-text" value="<?php echo esc_attr($settings['redis_password']??''); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Database', 'wp-static-runtime' ); ?></th><td><input type="number" name="redis_database" class="small-text" value="<?php echo esc_attr($settings['redis_database']??0); ?>" min="0" max="15" /></td></tr>
                        <tr><th><?php esc_html_e( 'Timeout (seconds)', 'wp-static-runtime' ); ?></th><td><input type="number" name="redis_timeout" class="small-text" value="<?php echo esc_attr($settings['redis_timeout']??'2.0'); ?>" min="0.5" max="10" step="0.5" /></td></tr>
                    </table>
                </div>
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-database-add" style="vertical-align:middle;margin-right:4px;"></span> Memcached</h2>
                    <?php $mc_ext = class_exists('\Memcached'); ?>
                    <?php if ( ! $mc_ext ) : ?>
                        <div class="notice notice-warning" style="display:block;padding:10px 14px;">⚠️ <?php esc_html_e( 'PHP Memcached extension is not installed.', 'wp-static-runtime' ); ?></div>
                    <?php endif; ?>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable Memcached', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="memcached_enabled" value="1" <?php checked(!empty($settings['memcached_enabled'])); ?> <?php disabled(!$mc_ext); ?> /></label></td></tr>
                        <tr><th><?php esc_html_e( 'Full-Page Cache', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="memcached_full_page" value="1" <?php checked(!empty($settings['memcached_full_page'])); ?> /> <?php esc_html_e( 'Store HTML pages in Memcached', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Host', 'wp-static-runtime' ); ?></th><td><input type="text" name="memcached_host" class="regular-text" value="<?php echo esc_attr($settings['memcached_host']??'127.0.0.1'); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Port', 'wp-static-runtime' ); ?></th><td><input type="number" name="memcached_port" class="small-text" value="<?php echo esc_attr($settings['memcached_port']??11211); ?>" /></td></tr>
                        <tr><th><?php esc_html_e( 'Key Prefix', 'wp-static-runtime' ); ?></th><td><input type="text" name="memcached_prefix" class="regular-text" value="<?php echo esc_attr($settings['memcached_prefix']??'wsr:'); ?>" /></td></tr>
                    </table>
                </div>
                <div class="wsr-section">
                    <h2><span class="dashicons dashicons-database-add" style="vertical-align:middle;margin-right:4px;"></span> LiteSpeed</h2>
                    <?php $ls_detected = \WSR\Premium\Server\LiteSpeed::is_litespeed(); ?>
                    <div class="notice <?php echo $ls_detected ? 'notice-success' : 'notice-info'; ?>" style="display:block;padding:10px 14px;">
                        <?php echo $ls_detected ? '✅ ' . esc_html__( 'LiteSpeed Web Server detected.', 'wp-static-runtime' ) : 'ℹ️ ' . esc_html__( 'LiteSpeed Web Server not detected on this server.', 'wp-static-runtime' ); ?>
                    </div>
                    <table class="form-table">
                        <tr><th><?php esc_html_e( 'Enable LiteSpeed Integration', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="litespeed_enabled" value="1" <?php checked(!empty($settings['litespeed_enabled'])); ?> /> <?php esc_html_e( 'Sync cache purge with LSCache', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Purge All on Flush', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" name="litespeed_purge_all" value="1" <?php checked($settings['litespeed_purge_all']??true); ?> /> <?php esc_html_e( 'Purge entire LSCache when WSR flushes (recommended)', 'wp-static-runtime' ); ?></label></td></tr>
                        <tr><th><?php esc_html_e( 'Cache TTL (seconds)', 'wp-static-runtime' ); ?></th><td><input type="number" name="litespeed_cache_ttl" class="small-text" value="<?php echo esc_attr($settings['litespeed_cache_ttl']??3600); ?>" min="60" step="60" /></td></tr>
                        <tr><th><?php esc_html_e( 'Tag Prefix', 'wp-static-runtime' ); ?></th><td><input type="text" name="litespeed_tag_prefix" class="regular-text" value="<?php echo esc_attr($settings['litespeed_tag_prefix']??'wsr_'); ?>" /></td></tr>
                    </table>
                </div>
                </div>
                <?php
    }

    public function maybe_render_license_overlay(): void {
        return;
    }

    public function save_premium_settings(): void {
        $settings = get_option( 'wsr_settings', [] );

        // Boolean settings — explicitly set to false if not in POST
        $booleans = [
            'isr_enabled', 'smart_dependency', 'crawler_enabled',
            'cdn_cloudflare_enabled', 'cdn_bunny_enabled', 'cdn_fastly_enabled',
            'redis_enabled', 'redis_full_page',
            'memcached_enabled', 'memcached_full_page',
            'litespeed_enabled', 'litespeed_purge_all',
            'opt_minify_html', 'opt_minify_css', 'opt_defer_js', 'opt_defer_css',
            'opt_lazy_load', 'opt_critical_css', 'opt_critical_css_auto', 'opt_preconnect', 'opt_font_display',
            'opt_cache_external_css', 'opt_cache_external_js',
            'opt_remove_emoji', 'opt_remove_embed', 'opt_remove_jquery_migrate',
        ];
        foreach ( $booleans as $k ) {
            $settings[$k] = !empty($_POST[$k]);
        }

        // Integer settings
        $integers = [
            'isr_revalidate', 'isr_queue_size',
            'redis_port', 'redis_database',
            'memcached_port',
            'litespeed_cache_ttl',
        ];
        foreach ( $integers as $k ) {
            if (isset($_POST[$k])) $settings[$k] = absint($_POST[$k]);
        }

        // Float settings
        if ( isset($_POST['redis_timeout']) ) {
            $settings['redis_timeout'] = max( 0.5, min( 10, (float) $_POST['redis_timeout'] ) );
        } elseif ( !isset( $settings['redis_timeout'] ) ) {
            $settings['redis_timeout'] = 2.0;
        }

        // String settings
        $strings = [
            'cdn_cloudflare_zone_id', 'cdn_cloudflare_email', 'cdn_cloudflare_api_key',
            'cdn_bunny_api_key', 'cdn_bunny_zone_id',
            'cdn_fastly_api_key', 'cdn_fastly_service_id',
            'redis_host', 'redis_password',
            'memcached_host', 'memcached_prefix',
            'litespeed_tag_prefix',
        ];
        foreach ( $strings as $k ) {
            if (isset($_POST[$k])) {
                $settings[$k] = sanitize_text_field($_POST[$k]);
            } elseif ( !isset( $settings[$k] ) ) {
                $settings[$k] = '';
            }
        }

        // Textarea settings (allow newlines)
        $textareas = [
            'opt_defer_css_exclude', 'opt_critical_css_content',
            'opt_cache_css_exclude', 'opt_cache_js_exclude',
            'opt_preconnect_domains', 'opt_remove_assets_blacklist',
        ];
        foreach ( $textareas as $k ) {
            if (isset($_POST[$k])) {
                $settings[$k] = sanitize_textarea_field($_POST[$k]);
            } elseif ( !isset( $settings[$k] ) ) {
                $settings[$k] = '';
            }
        }

        update_option( 'wsr_settings', $settings );
    }
}

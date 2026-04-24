<?php
/**
 * Premium UI Shell — shows locked premium pages to entice upgrade.
 * No functional code, no license system, no CDN/ISR/Redis classes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WSR_Premium_UI {

    const UPGRADE_URL = 'https://statixpress.site/premium';

    private static $styles_printed = false;

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_pages'      ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'wsr_settings_tabs',     [ $this, 'add_tabs'       ] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function add_pages(): void {
        add_submenu_page(
            'wsr-dashboard',
            __( 'Get Premium', 'wp-static-runtime' ),
            '⭐ ' . __( 'Get Premium', 'wp-static-runtime' ),
            'manage_options',
            'wsr-get-premium',
            [ $this, 'render_get_premium' ]
        );
        add_submenu_page(
            'wsr-dashboard',
            __( 'CDN', 'wp-static-runtime' ),
            '🌍 ' . __( 'CDN', 'wp-static-runtime' ),
            'manage_options',
            'wsr-cdn',
            [ $this, 'render_cdn' ]
        );
        add_submenu_page(
            'wsr-dashboard',
            __( 'ISR', 'wp-static-runtime' ),
            '⚡ ' . __( 'ISR', 'wp-static-runtime' ),
            'manage_options',
            'wsr-isr',
            [ $this, 'render_isr' ]
        );
        add_submenu_page(
            'wsr-dashboard',
            __( 'Premium Settings', 'wp-static-runtime' ),
            '⚙️ ' . __( 'Premium', 'wp-static-runtime' ),
            'manage_options',
            'wsr-premium',
            [ $this, 'render_premium_settings' ]
        );
    }

    public function add_tabs( array $tabs ): array {
        $tabs['cdn'] = '🌍 ' . __( 'CDN', 'wp-static-runtime' );
        $tabs['isr'] = '⚡ ' . __( 'ISR', 'wp-static-runtime' );
        return $tabs;
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'wsr-' ) === false ) return;
        // Piggyback on the free admin CSS (handle: wsr-admin) with locked-state additions
        wp_add_inline_style( 'wsr-admin', $this->locked_css() );
    }

    // ── Shared: Print styles once ─────────────────────────────────────────────

    private function maybe_print_styles(): void {
        if ( self::$styles_printed ) return;
        self::$styles_printed = true;
        echo '<style>' . $this->locked_css() . '</style>';
    }

    private function locked_css(): string {
        return '
/* ── Upgrade Gate Banner ─────────────────────────────────────── */
.wsr-upgrade-gate {
    background: linear-gradient(135deg, #4338ca 0%, #7c3aed 60%, #a21caf 100%);
    border-radius: 12px;
    padding: 28px 32px;
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 28px;
    flex-wrap: wrap;
    box-shadow: 0 4px 24px rgba(124,58,237,.25);
}
.wsr-ug-body { flex: 1; min-width: 240px; }
.wsr-ug-body h2 {
    color: #fff !important;
    font-size: 20px !important;
    margin: 0 0 8px !important;
    padding: 0 !important;
    border: none !important;
    font-weight: 800 !important;
}
.wsr-ug-body p {
    color: rgba(255,255,255,.88);
    margin: 0 0 16px;
    font-size: 13.5px;
    line-height: 1.65;
}
.wsr-ug-pills { display: flex; flex-wrap: wrap; gap: 8px; }
.wsr-ug-pill {
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.28);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 999px;
}
.wsr-ug-cta {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-shrink: 0;
}
.wsr-ug-btn {
    display: inline-block;
    background: #fff;
    color: #4338ca !important;
    text-decoration: none !important;
    font-weight: 800;
    font-size: 14px;
    padding: 11px 26px;
    border-radius: 8px;
    white-space: nowrap;
    box-shadow: 0 2px 12px rgba(0,0,0,.18);
    transition: opacity .15s, transform .15s;
}
.wsr-ug-btn:hover { opacity: .92; transform: translateY(-1px); color: #4338ca !important; }
.wsr-ug-note {
    font-size: 11.5px;
    color: rgba(255,255,255,.72);
    text-align: center;
    line-height: 1.4;
}

/* ── Locked Form ─────────────────────────────────────────────── */
.wsr-locked-form fieldset[disabled] {
    opacity: 0.55;
}
.wsr-locked-form fieldset[disabled] * {
    cursor: not-allowed !important;
    pointer-events: none;
}
.wsr-locked-form fieldset[disabled] input,
.wsr-locked-form fieldset[disabled] select,
.wsr-locked-form fieldset[disabled] textarea {
    background: #f1f5f9 !important;
    color: #94a3b8 !important;
}
.wsr-section-lock-badge {
    float: right;
    font-size: 11px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 3px 10px;
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: .3px;
    margin-top: 2px;
}
.wsr-unlock-submit {
    display: inline-block;
    background: linear-gradient(135deg, #4338ca, #7c3aed);
    color: #fff !important;
    text-decoration: none !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 24px;
    border-radius: 7px;
    transition: opacity .15s, transform .15s;
    box-shadow: 0 2px 8px rgba(79,70,229,.3);
}
.wsr-unlock-submit:hover { opacity: .9; transform: translateY(-1px); color: #fff !important; }

/* ── Get Premium Page ────────────────────────────────────────── */
.wsr-premium-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 50%, #7c3aed 100%);
    border-radius: 16px;
    padding: 48px 40px;
    text-align: center;
    margin-bottom: 36px;
    position: relative;
    overflow: hidden;
}
.wsr-premium-hero::before {
    content: "";
    position: absolute;
    top: -60px; right: -60px;
    width: 280px; height: 280px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
}
.wsr-premium-hero h1 {
    color: #fff !important;
    font-size: 30px !important;
    font-weight: 900 !important;
    margin: 0 0 12px !important;
    line-height: 1.2;
    position: relative;
}
.wsr-premium-hero p {
    color: rgba(255,255,255,.82);
    font-size: 16px;
    margin: 0 0 28px;
    position: relative;
}
.wsr-hero-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; position: relative; }
.wsr-hero-btn-primary {
    display: inline-block;
    background: #fff;
    color: #4338ca !important;
    text-decoration: none !important;
    font-weight: 800;
    font-size: 15px;
    padding: 13px 30px;
    border-radius: 9px;
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
    transition: opacity .15s, transform .15s;
}
.wsr-hero-btn-primary:hover { opacity: .93; transform: translateY(-2px); color: #4338ca !important; }
.wsr-hero-btn-secondary {
    display: inline-block;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.3);
    color: #fff !important;
    text-decoration: none !important;
    font-weight: 700;
    font-size: 15px;
    padding: 13px 30px;
    border-radius: 9px;
    transition: background .15s;
}
.wsr-hero-btn-secondary:hover { background: rgba(255,255,255,.2); color: #fff !important; }
.wsr-feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 36px;
}
.wsr-feature-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    transition: box-shadow .2s, transform .2s;
}
.wsr-feature-card:hover {
    box-shadow: 0 6px 24px rgba(124,58,237,.12);
    transform: translateY(-2px);
}
.wsr-feature-card-icon { font-size: 32px; margin-bottom: 12px; display: block; }
.wsr-feature-card h3 {
    font-size: 15px;
    font-weight: 800;
    color: #1e1e3f;
    margin: 0 0 8px;
}
.wsr-feature-card p {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 14px;
    line-height: 1.6;
}
.wsr-feature-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 12.5px;
    color: #475569;
}
.wsr-feature-card ul li { padding: 3px 0; }
.wsr-feature-card ul li::before { content: "✓ "; color: #7c3aed; font-weight: 700; }
.wsr-compare-table { width: 100%; border-collapse: collapse; background: #fff;
                     border-radius: 12px; overflow: hidden;
                     box-shadow: 0 1px 8px rgba(0,0,0,.06); margin-bottom: 36px; }
.wsr-compare-table th { padding: 14px 20px; font-size: 13px; font-weight: 700; }
.wsr-compare-table th:first-child { background: #f8fafc; color: #374151; text-align: left; }
.wsr-compare-table th.col-free { background: #f1f5f9; color: #475569; text-align: center; }
.wsr-compare-table th.col-premium { background: linear-gradient(135deg, #4338ca, #7c3aed);
                                     color: #fff; text-align: center; }
.wsr-compare-table td { padding: 11px 20px; border-top: 1px solid #f1f5f9; font-size: 13px; }
.wsr-compare-table td:first-child { color: #374151; font-weight: 500; }
.wsr-compare-table td:not(:first-child) { text-align: center; }
.wsr-compare-table tr:hover td { background: #fafafa; }
.wsr-compare-table .check-yes { color: #16a34a; font-size: 16px; }
.wsr-compare-table .check-no  { color: #d1d5db; font-size: 16px; }
.wsr-compare-table .check-premium { color: #7c3aed; font-size: 16px; }
.wsr-plan-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 36px; }
@media (max-width: 700px) { .wsr-plan-cards { grid-template-columns: 1fr; } }
.wsr-plan-card {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px 24px;
    text-align: center;
    position: relative;
    transition: border-color .2s, box-shadow .2s;
}
.wsr-plan-card:hover { border-color: #7c3aed; box-shadow: 0 4px 20px rgba(124,58,237,.12); }
.wsr-plan-card.featured {
    border-color: #7c3aed;
    box-shadow: 0 4px 20px rgba(124,58,237,.15);
}
.wsr-plan-card .plan-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(90deg, #f59e0b, #f97316);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 12px;
    border-radius: 999px;
    letter-spacing: .5px;
    white-space: nowrap;
}
.wsr-plan-card h3 { font-size: 20px; font-weight: 800; color: #1e1e3f; margin: 0 0 6px; }
.wsr-plan-card .plan-sites { font-size: 13px; color: #7c3aed; font-weight: 600; margin: 0 0 20px; }
.wsr-plan-card ul { list-style: none; padding: 0; margin: 0 0 24px; text-align: left; }
.wsr-plan-card ul li { font-size: 13px; color: #475569; padding: 5px 0; border-bottom: 1px solid #f8fafc; }
.wsr-plan-card ul li::before { content: "✓ "; color: #7c3aed; font-weight: 700; }
.wsr-plan-btn {
    display: block;
    background: linear-gradient(135deg, #4338ca, #7c3aed);
    color: #fff !important;
    text-decoration: none !important;
    font-weight: 700;
    font-size: 14px;
    padding: 11px 20px;
    border-radius: 8px;
    transition: opacity .15s, transform .15s;
}
.wsr-plan-btn:hover { opacity: .9; transform: translateY(-1px); color: #fff !important; }
.wsr-locked-stat-card .wsr-card-value { color: #94a3b8 !important; }
';
    }

    // ── Shared: Upgrade Gate Banner ───────────────────────────────────────────

    private function upgrade_gate( string $icon, string $title, string $description, array $pills, string $btn_label = '' ): void {
        $upgrade_url = self::UPGRADE_URL;
        $btn_label   = $btn_label ?: __( 'Get Premium →', 'wp-static-runtime' );
        ?>
        <div class="wsr-upgrade-gate">
            <div class="wsr-ug-body">
                <h2><?php echo esc_html( $icon . ' ' . $title ); ?></h2>
                <p><?php echo esc_html( $description ); ?></p>
                <div class="wsr-ug-pills">
                    <?php foreach ( $pills as $pill ) : ?>
                        <span class="wsr-ug-pill"><?php echo esc_html( $pill ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wsr-ug-cta">
                <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-ug-btn">
                    <?php echo esc_html( $btn_label ); ?>
                </a>
                <span class="wsr-ug-note">
                    <?php esc_html_e( 'Personal & Agency plans', 'wp-static-runtime' ); ?><br>
                    <?php esc_html_e( '1-year license · instant delivery', 'wp-static-runtime' ); ?>
                </span>
            </div>
        </div>
        <?php
    }

    // ── Page: Get Premium ─────────────────────────────────────────────────────

    public function render_get_premium(): void {
        $this->maybe_print_styles();
        $upgrade_url = self::UPGRADE_URL;
        ?>
        <div class="wrap wsr-wrap">

            <div class="wsr-premium-hero">
                <h1>⚡ WP Static Runtime Premium</h1>
                <p><?php esc_html_e( 'The fastest static site engine for WordPress — now with ISR, CDN purge, Redis, and Smart Dependency Graph.', 'wp-static-runtime' ); ?></p>
                <div class="wsr-hero-btns">
                    <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-hero-btn-primary">
                        🚀 <?php esc_html_e( 'Get Premium License', 'wp-static-runtime' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $upgrade_url . '#compare' ); ?>" target="_blank" class="wsr-hero-btn-secondary">
                        <?php esc_html_e( 'Compare Plans', 'wp-static-runtime' ); ?>
                    </a>
                </div>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'What You Unlock with Premium', 'wp-static-runtime' ); ?></h2>
                <div class="wsr-feature-grid">

                    <div class="wsr-feature-card">
                        <span class="wsr-feature-card-icon">⚡</span>
                        <h3><?php esc_html_e( 'Incremental Static Regeneration', 'wp-static-runtime' ); ?></h3>
                        <p><?php esc_html_e( 'Zero-downtime cache updates. Stale pages keep serving while new versions regenerate silently in the background.', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Event-driven or TTL-based revalidation', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Atomic file swap — no flicker, no downtime', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Background queue processing via cron', 'wp-static-runtime' ); ?></li>
                        </ul>
                    </div>

                    <div class="wsr-feature-card">
                        <span class="wsr-feature-card-icon">🌍</span>
                        <h3><?php esc_html_e( 'CDN Cache Purging', 'wp-static-runtime' ); ?></h3>
                        <p><?php esc_html_e( 'Automatically purge your CDN cache on every post update. Supports Cloudflare, BunnyCDN, and Fastly.', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Auto-purge on publish / update', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Smart dependency-aware purging', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Manual purge all or single URL', 'wp-static-runtime' ); ?></li>
                        </ul>
                    </div>

                    <div class="wsr-feature-card">
                        <span class="wsr-feature-card-icon">🧠</span>
                        <h3><?php esc_html_e( 'Smart Dependency Graph', 'wp-static-runtime' ); ?></h3>
                        <p><?php esc_html_e( 'Gutenberg block tracking, WooCommerce relations, and level-2 dependency traversal for surgical cache invalidation.', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Block-level dependency detection', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'WooCommerce category & tag tracking', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( '2-hop dependency resolution', 'wp-static-runtime' ); ?></li>
                        </ul>
                    </div>

                    <div class="wsr-feature-card">
                        <span class="wsr-feature-card-icon">🔴</span>
                        <h3><?php esc_html_e( 'Redis Cache Index', 'wp-static-runtime' ); ?></h3>
                        <p><?php esc_html_e( 'Replace the MySQL cache index with Redis for sub-millisecond lookups on high-traffic sites.', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( '10× faster than MySQL index', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'ISR queue backed by Redis list', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Automatic fallback to MySQL', 'wp-static-runtime' ); ?></li>
                        </ul>
                    </div>

                </div>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'Free vs Premium', 'wp-static-runtime' ); ?></h2>
                <table class="wsr-compare-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Feature', 'wp-static-runtime' ); ?></th>
                            <th class="col-free"><?php esc_html_e( 'Free', 'wp-static-runtime' ); ?></th>
                            <th class="col-premium">⭐ <?php esc_html_e( 'Premium', 'wp-static-runtime' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = [
                            [ __( 'Static HTML caching',                  'wp-static-runtime' ), true,  true  ],
                            [ __( 'Apache & Nginx rules generator',        'wp-static-runtime' ), true,  true  ],
                            [ __( 'Sitemap crawler',                       'wp-static-runtime' ), true,  true  ],
                            [ __( 'Cache dependency graph (basic)',        'wp-static-runtime' ), true,  true  ],
                            [ __( 'WooCommerce hybrid cache',              'wp-static-runtime' ), true,  true  ],
                            [ __( 'Diagnostic & engine status',            'wp-static-runtime' ), true,  true  ],
                            [ __( 'Auto-updates via GitHub Releases',      'wp-static-runtime' ), false, true  ],
                            [ __( 'Incremental Static Regeneration (ISR)', 'wp-static-runtime' ), false, true  ],
                            [ __( 'Smart Dependency Graph (L2, Gutenberg)','wp-static-runtime' ), false, true  ],
                            [ __( 'CDN Purge (Cloudflare, BunnyCDN, Fastly)', 'wp-static-runtime' ), false, true ],
                            [ __( 'Redis Cache Index',                     'wp-static-runtime' ), false, true  ],
                            [ __( 'Priority support',                      'wp-static-runtime' ), false, true  ],
                        ];
                        foreach ( $rows as [ $label, $free, $premium ] ) :
                        ?>
                        <tr>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td><?php echo $free    ? '<span class="check-yes">✓</span>' : '<span class="check-no">—</span>'; ?></td>
                            <td><?php echo $premium ? '<span class="check-premium">✓</span>' : '<span class="check-no">—</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'Choose Your Plan', 'wp-static-runtime' ); ?></h2>
                <div class="wsr-plan-cards">

                    <div class="wsr-plan-card">
                        <h3>Personal</h3>
                        <p class="plan-sites">1 <?php esc_html_e( 'website', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'All premium features', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( '1 year of updates', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Email support', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'License for 1 domain', 'wp-static-runtime' ); ?></li>
                        </ul>
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-plan-btn">
                            <?php esc_html_e( 'Get Personal', 'wp-static-runtime' ); ?> →
                        </a>
                    </div>

                    <div class="wsr-plan-card featured">
                        <div class="plan-badge">BEST VALUE</div>
                        <h3>Agency</h3>
                        <p class="plan-sites"><?php esc_html_e( 'Unlimited websites', 'wp-static-runtime' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'All premium features', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( '1 year of updates', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Priority support', 'wp-static-runtime' ); ?></li>
                            <li><?php esc_html_e( 'Unlimited domains', 'wp-static-runtime' ); ?></li>
                        </ul>
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-plan-btn">
                            <?php esc_html_e( 'Get Agency', 'wp-static-runtime' ); ?> →
                        </a>
                    </div>

                </div>
            </div>

        </div>
        <?php
    }

    // ── Page: CDN ─────────────────────────────────────────────────────────────

    public function render_cdn(): void {
        $this->maybe_print_styles();
        $upgrade_url = self::UPGRADE_URL;
        ?>
        <div class="wrap wsr-wrap">
            <h1>🌍 <?php esc_html_e( 'CDN Management', 'wp-static-runtime' ); ?> <span class="wsr-premium-badge">PREMIUM</span></h1>

            <?php $this->upgrade_gate(
                '🌍',
                __( 'CDN Cache Purging', 'wp-static-runtime' ),
                __( 'Automatically purge your CDN cache whenever a post is updated or cache is flushed. Configure Cloudflare, BunnyCDN, or Fastly — or all three at once.', 'wp-static-runtime' ),
                [
                    __( '3 CDN Providers',      'wp-static-runtime' ),
                    __( 'Auto-purge on update', 'wp-static-runtime' ),
                    __( 'Manual purge all',      'wp-static-runtime' ),
                    __( 'Per-URL purge',         'wp-static-runtime' ),
                ]
            ); ?>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'Active CDN Providers', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒 <?php esc_html_e( 'Premium', 'wp-static-runtime' ); ?></span></h2>
                <div class="wsr-cards">
                    <?php foreach ( [
                        [ '🟠', 'Cloudflare',  __( 'Purge cache via Cloudflare API Zone.', 'wp-static-runtime' ) ],
                        [ '🐰', 'BunnyCDN',    __( 'Purge pull zone via BunnyCDN API.', 'wp-static-runtime' ) ],
                        [ '⚡', 'Fastly',      __( 'Purge service cache via Fastly API.', 'wp-static-runtime' ) ],
                    ] as [ $icon, $name, $desc ] ) : ?>
                    <div class="wsr-card wsr-locked-stat-card">
                        <div class="wsr-card-icon"><?php echo esc_html( $icon ); ?></div>
                        <div class="wsr-card-value" style="font-size:15px;"><?php echo esc_html( $name ); ?></div>
                        <div class="wsr-card-label"><?php echo esc_html( $desc ); ?></div>
                        <div style="margin-top:10px;"><span class="wsr-badge" style="background:#f1f5f9;color:#64748b;">🔒 <?php esc_html_e( 'Locked', 'wp-static-runtime' ); ?></span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'CDN Cache Control', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒 <?php esc_html_e( 'Premium', 'wp-static-runtime' ); ?></span></h2>
                <div class="wsr-locked-form">
                    <fieldset disabled>
                        <button class="button button-primary" disabled>🌐 <?php esc_html_e( 'Purge All CDN Cache', 'wp-static-runtime' ); ?></button>
                        <div style="display:flex;gap:8px;margin-top:12px;">
                            <input type="url" class="regular-text" placeholder="https://yoursite.com/some-page/" />
                            <button class="button" disabled><?php esc_html_e( 'Purge URL', 'wp-static-runtime' ); ?></button>
                        </div>
                    </fieldset>
                    <p style="margin-top:14px;">
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-unlock-submit">
                            ⚡ <?php esc_html_e( 'Unlock CDN Purging', 'wp-static-runtime' ); ?> →
                        </a>
                    </p>
                </div>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'Provider Configuration', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒 <?php esc_html_e( 'Premium', 'wp-static-runtime' ); ?></span></h2>
                <div class="wsr-locked-form">
                    <fieldset disabled>
                        <h3 style="font-size:14px;margin:0 0 12px;">🟠 Cloudflare</h3>
                        <table class="form-table" style="margin-bottom:20px;">
                            <tr><th style="width:200px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="abc123..." /></td></tr>
                            <tr><th><?php esc_html_e( 'Email', 'wp-static-runtime' ); ?></th><td><input type="email" class="regular-text" placeholder="you@example.com" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                        </table>
                        <h3 style="font-size:14px;margin:0 0 12px;">🐰 BunnyCDN</h3>
                        <table class="form-table" style="margin-bottom:20px;">
                            <tr><th style="width:200px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                            <tr><th><?php esc_html_e( 'Pull Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="12345" /></td></tr>
                        </table>
                        <h3 style="font-size:14px;margin:0 0 12px;">⚡ Fastly</h3>
                        <table class="form-table">
                            <tr><th style="width:200px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                            <tr><th><?php esc_html_e( 'Service ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="abc123..." /></td></tr>
                        </table>
                    </fieldset>
                    <p style="margin-top:14px;">
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-unlock-submit">
                            ⚡ <?php esc_html_e( 'Unlock to Configure CDN', 'wp-static-runtime' ); ?> →
                        </a>
                    </p>
                </div>
            </div>

        </div>
        <?php
    }

    // ── Page: ISR ─────────────────────────────────────────────────────────────

    public function render_isr(): void {
        $this->maybe_print_styles();
        $upgrade_url = self::UPGRADE_URL;
        $home_url    = home_url( '/' );
        $cron_curl   = '* * * * * curl -s --max-time 10 "' . esc_url( $home_url . 'wp-cron.php' ) . '" > /dev/null 2>&1';
        $cron_wpcli  = '* * * * * cd ' . rtrim( ABSPATH, '/' ) . ' && wp cron event run --due-now --quiet 2>/dev/null';
        ?>
        <div class="wrap wsr-wrap">
            <h1>⚡ <?php esc_html_e( 'Incremental Static Regeneration', 'wp-static-runtime' ); ?> <span class="wsr-premium-badge">PREMIUM</span></h1>
            <p style="color:#64748b;font-size:14px;margin-bottom:24px;"><?php esc_html_e( 'ISR keeps the stale page serving while regenerating in the background — zero-downtime cache updates.', 'wp-static-runtime' ); ?></p>

            <?php $this->upgrade_gate(
                '⚡',
                __( 'Background Cache Regeneration', 'wp-static-runtime' ),
                __( 'When a post is updated, ISR queues it for regeneration without ever taking the cached page offline. The visitor always gets a response — even while the new version is being built.', 'wp-static-runtime' ),
                [
                    __( 'Zero downtime',         'wp-static-runtime' ),
                    __( 'Atomic file swap',       'wp-static-runtime' ),
                    __( 'TTL or event-driven',    'wp-static-runtime' ),
                    __( 'Manual revalidate',      'wp-static-runtime' ),
                ]
            ); ?>

            <div class="wsr-cards">
                <div class="wsr-card wsr-locked-stat-card">
                    <div class="wsr-card-icon">📋</div>
                    <div class="wsr-card-value">—</div>
                    <div class="wsr-card-label"><?php esc_html_e( 'URLs in Queue', 'wp-static-runtime' ); ?></div>
                </div>
                <div class="wsr-card wsr-locked-stat-card">
                    <div class="wsr-card-icon">⏱️</div>
                    <div class="wsr-card-value">—<small>s</small></div>
                    <div class="wsr-card-label">TTL</div>
                </div>
                <div class="wsr-card wsr-locked-stat-card">
                    <div class="wsr-card-icon">🔒</div>
                    <div class="wsr-card-value" style="font-size:14px;"><?php esc_html_e( 'Locked', 'wp-static-runtime' ); ?></div>
                    <div class="wsr-card-label"><?php esc_html_e( 'ISR Status', 'wp-static-runtime' ); ?></div>
                </div>
            </div>

            <div class="wsr-section">
                <h2><?php esc_html_e( 'ISR Settings', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒 <?php esc_html_e( 'Premium', 'wp-static-runtime' ); ?></span></h2>
                <div class="wsr-locked-form">
                    <fieldset disabled>
                        <table class="form-table">
                            <tr>
                                <th style="width:240px;"><?php esc_html_e( 'Enable ISR', 'wp-static-runtime' ); ?></th>
                                <td><label><input type="checkbox" /> <?php esc_html_e( 'Enable background regeneration', 'wp-static-runtime' ); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Revalidate TTL (seconds)', 'wp-static-runtime' ); ?></th>
                                <td>
                                    <input type="number" value="0" class="small-text" />
                                    <p class="description"><?php esc_html_e( '0 = event-driven only (recommended)', 'wp-static-runtime' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Queue Batch Size', 'wp-static-runtime' ); ?></th>
                                <td><input type="number" value="10" min="1" max="50" class="small-text" /></td>
                            </tr>
                        </table>
                    </fieldset>
                    <p style="margin-top:14px;">
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-unlock-submit">
                            ⚡ <?php esc_html_e( 'Unlock ISR', 'wp-static-runtime' ); ?> →
                        </a>
                    </p>
                </div>
            </div>

            <div class="wsr-section">
                <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:14px 18px;border-radius:4px;">
                    <p style="margin:0 0 8px;font-weight:700;font-size:13px;">
                        🕐 <?php esc_html_e( 'Recommendation: Enable System Cron for Responsive ISR', 'wp-static-runtime' ); ?>
                    </p>
                    <p style="margin:0 0 10px;font-size:13px;color:#374151;">
                        <?php esc_html_e( "ISR uses WP-Cron to process its queue. WordPress's built-in WP-Cron only fires when a request hits your site — on low traffic this may delay regeneration by minutes. Use a system cron for reliable ISR.", 'wp-static-runtime' ); ?>
                    </p>
                    <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#1e40af;"><?php esc_html_e( 'Step 1 — Add to wp-config.php:', 'wp-static-runtime' ); ?></p>
                    <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;margin:0 0 12px;overflow:auto;">define( 'DISABLE_WP_CRON', true );</pre>
                    <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#1e40af;"><?php esc_html_e( 'Step 2 — Add to crontab', 'wp-static-runtime' ); ?> (<code>crontab -e</code>):</p>
                    <p style="margin:0 0 4px;font-size:11px;color:#6b7280;"><?php esc_html_e( 'Option A — via curl:', 'wp-static-runtime' ); ?></p>
                    <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;margin:0 0 8px;overflow:auto;"><?php echo esc_html( $cron_curl ); ?></pre>
                    <p style="margin:0 0 4px;font-size:11px;color:#6b7280;"><?php esc_html_e( 'Option B — via WP-CLI:', 'wp-static-runtime' ); ?></p>
                    <pre style="background:#1e1e2e;color:#cdd6f4;padding:10px 14px;border-radius:4px;font-size:12px;overflow:auto;"><?php echo esc_html( $cron_wpcli ); ?></pre>
                </div>
            </div>

        </div>
        <?php
    }

    // ── Page: Premium Settings ────────────────────────────────────────────────

    public function render_premium_settings(): void {
        $this->maybe_print_styles();
        $upgrade_url = self::UPGRADE_URL;
        ?>
        <div class="wrap wsr-wrap">
            <h1>⚙️ <?php esc_html_e( 'Premium Settings', 'wp-static-runtime' ); ?> <span class="wsr-premium-badge">PREMIUM</span></h1>

            <?php $this->upgrade_gate(
                '⚙️',
                __( 'Premium Settings', 'wp-static-runtime' ),
                __( 'Configure ISR, CDN providers, Redis index, and Smart Dependency settings. All premium features are managed from this single page.', 'wp-static-runtime' ),
                [
                    __( 'ISR config',         'wp-static-runtime' ),
                    __( 'CDN providers',      'wp-static-runtime' ),
                    __( 'Redis index',        'wp-static-runtime' ),
                    __( 'Smart Dependency',   'wp-static-runtime' ),
                ],
                __( 'Unlock All Settings →', 'wp-static-runtime' )
            ); ?>

            <div class="wsr-locked-form">

                <div class="wsr-section">
                    <h2>⚡ ISR — <?php esc_html_e( 'Incremental Static Regeneration', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable ISR', 'wp-static-runtime' ); ?></th><td><label><input type="checkbox" /> <?php esc_html_e( 'Enable background regeneration', 'wp-static-runtime' ); ?></label></td></tr>
                            <tr><th><?php esc_html_e( 'Revalidate TTL (seconds)', 'wp-static-runtime' ); ?></th><td><input type="number" value="0" class="small-text" /><p class="description"><?php esc_html_e( '0 = event-driven only (recommended)', 'wp-static-runtime' ); ?></p></td></tr>
                            <tr><th><?php esc_html_e( 'Queue Batch Size', 'wp-static-runtime' ); ?></th><td><input type="number" value="10" class="small-text" /></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>🕷️ <?php esc_html_e( 'Auto-Crawler', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable Auto-Crawler', 'wp-static-runtime' ); ?></th>
                            <td><label><input type="checkbox" /> <?php esc_html_e( 'Pre-build cache from sitemap hourly (WP-Cron)', 'wp-static-runtime' ); ?></label></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>🧠 <?php esc_html_e( 'Smart Dependency', 'wp-static-runtime' ); ?> <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable Smart Dependency', 'wp-static-runtime' ); ?></th>
                            <td><label><input type="checkbox" checked /> <?php esc_html_e( 'Gutenberg block tracking, WooCommerce relations, L2 traversal', 'wp-static-runtime' ); ?></label></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>🌍 CDN — Cloudflare <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="abc123..." /></td></tr>
                            <tr><th><?php esc_html_e( 'Email', 'wp-static-runtime' ); ?></th><td><input type="email" class="regular-text" placeholder="you@cloudflare.com" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>🐰 BunnyCDN <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                            <tr><th><?php esc_html_e( 'Pull Zone ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="12345" /></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>⚡ Fastly <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'API Key', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="••••••••••••" /></td></tr>
                            <tr><th><?php esc_html_e( 'Service ID', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" placeholder="abc123..." /></td></tr>
                        </table>
                    </fieldset>
                </div>

                <div class="wsr-section">
                    <h2>🔴 Redis <span class="wsr-section-lock-badge">🔒</span></h2>
                    <fieldset disabled>
                        <table class="form-table">
                            <tr><th style="width:240px;"><?php esc_html_e( 'Enable Redis', 'wp-static-runtime' ); ?></th><td><input type="checkbox" /></td></tr>
                            <tr><th><?php esc_html_e( 'Host', 'wp-static-runtime' ); ?></th><td><input type="text" class="regular-text" value="127.0.0.1" /></td></tr>
                            <tr><th><?php esc_html_e( 'Port', 'wp-static-runtime' ); ?></th><td><input type="number" class="small-text" value="6379" /></td></tr>
                            <tr><th><?php esc_html_e( 'Password', 'wp-static-runtime' ); ?></th><td><input type="password" class="regular-text" placeholder="<?php esc_attr_e( 'Optional', 'wp-static-runtime' ); ?>" /></td></tr>
                        </table>
                    </fieldset>
                </div>

                <p class="submit">
                    <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="wsr-unlock-submit">
                        ⚡ <?php esc_html_e( 'Unlock Premium to Save Settings', 'wp-static-runtime' ); ?> →
                    </a>
                </p>

            </div>
        </div>
        <?php
    }
}

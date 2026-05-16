<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Settings — plugin configuration page.
 */
class Settings {

    public static function render(): void {
        if ( isset( $_POST['wsr_save_settings'] ) ) {
            check_admin_referer( 'wsr_settings_save' );
            self::save();
            do_action( 'wsr_save_settings_extra' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-static-runtime' ) . '</p></div>';
        }

        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        ?>
        <div class="wrap wsr-wrap">
            <h1 style="margin-bottom:20px;"><span class="dashicons dashicons-admin-settings" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span> <?php esc_html_e( 'StatixPress — Settings', 'wp-static-runtime' ); ?></h1>

            <h2 class="nav-tab-wrapper wsr-nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'wp-static-runtime' ); ?></a>
                <a href="#optimisation" class="nav-tab"><?php esc_html_e( 'Optimisation', 'wp-static-runtime' ); ?></a>
                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <a href="#woocommerce" class="nav-tab"><?php esc_html_e( 'WooCommerce', 'wp-static-runtime' ); ?></a>
                <?php endif; ?>
                <a href="#exclusions" class="nav-tab"><?php esc_html_e( 'Exclusions', 'wp-static-runtime' ); ?></a>
                <a href="#server" class="nav-tab"><?php esc_html_e( 'Server Config', 'wp-static-runtime' ); ?></a>
                <?php do_action( 'wsr_settings_tabs_html' ); ?>
            </h2>

            <form method="post" action="?page=<?php echo esc_attr( $_GET['page'] ?? 'wsr-settings' ); ?>">
                <?php wp_nonce_field( 'wsr_settings_save' ); ?>

                <!-- General -->
                <div id="tab-general" class="wsr-tab-content">
                <div class="wsr-settings-section">
                    <h2><?php esc_html_e( 'General', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Cache', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cache_enabled" value="1"
                                        <?php checked( $settings['cache_enabled'] ?? true ); ?> />
                                    <?php esc_html_e( 'Serve static HTML to visitors', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Cache TTL', 'wp-static-runtime' ); ?></th>
                            <td>
                                <input type="number" name="cache_ttl" min="0" step="60"
                                    value="<?php echo esc_attr( $settings['cache_ttl'] ?? 0 ); ?>" />
                                <span class="description"><?php esc_html_e( 'Seconds (0 = never expire)', 'wp-static-runtime' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Skip Logged-In Users', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="skip_logged_in" value="1"
                                        <?php checked( $settings['skip_logged_in'] ?? true ); ?> />
                                    <?php esc_html_e( 'Recommended: logged-in users always see dynamic content', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Skip Query Strings', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="skip_query_strings" value="1"
                                        <?php checked( $settings['skip_query_strings'] ?? true ); ?> />
                                    <?php esc_html_e( 'Don\'t cache URLs with query parameters', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                </div>

                <!-- Optimisation -->
                <div id="tab-optimisation" class="wsr-tab-content" style="display:none;">
                <div class="wsr-settings-section">
                    <h2><?php esc_html_e( 'Optimisation', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Gzip Cache Files', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gzip_cache" value="1"
                                        <?php checked( $settings['gzip_cache'] ?? false ); ?> />
                                    <?php esc_html_e( 'Store a gzip-compressed version alongside plain HTML', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Minify HTML', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="minify_html" value="1"
                                        <?php checked( $settings['minify_html'] ?? false ); ?> />
                                    <?php esc_html_e( 'Remove whitespace and HTML comments from cached pages', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Apache Rewrite Rules', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="apache_rewrite" value="1"
                                        <?php checked( $settings['apache_rewrite'] ?? true ); ?> />
                                    <?php esc_html_e( 'Auto-write .htaccess rules to serve static files without PHP', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                </div>

                <!-- WooCommerce -->
                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <div id="tab-woocommerce" class="wsr-tab-content" style="display:none;">
                <div class="wsr-settings-section">
                    <h2><?php esc_html_e( 'WooCommerce Hybrid Mode', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Hybrid Cache', 'wp-static-runtime' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_hybrid" value="1"
                                        <?php checked( $settings['woo_hybrid'] ?? true ); ?> />
                                    <?php esc_html_e( 'Cache shop, products, categories. Skip cart/checkout.', 'wp-static-runtime' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Excluded WooCommerce Pages', 'wp-static-runtime' ); ?></th>
                            <td>
                                <textarea name="woo_exclude" rows="4" cols="40"><?php
                                    echo esc_textarea( implode( "\n", (array) ( $settings['woo_exclude'] ?? [] ) ) );
                                ?></textarea>
                                <p class="description"><?php esc_html_e( 'One slug per line. e.g. cart, checkout, my-account', 'wp-static-runtime' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                </div>
                <?php endif; ?>

                <!-- Exclusions -->
                <div id="tab-exclusions" class="wsr-tab-content" style="display:none;">
                <div class="wsr-settings-section">
                    <h2><?php esc_html_e( 'Exclusions', 'wp-static-runtime' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Excluded URLs', 'wp-static-runtime' ); ?></th>
                            <td>
                                <textarea name="excluded_urls" rows="5" cols="60"><?php
                                    echo esc_textarea( implode( "\n", (array) ( $settings['excluded_urls'] ?? [] ) ) );
                                ?></textarea>
                                <p class="description"><?php esc_html_e( 'One URL path per line. e.g. /members, /private', 'wp-static-runtime' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Excluded Cookies', 'wp-static-runtime' ); ?></th>
                            <td>
                                <textarea name="excluded_cookies" rows="4" cols="60"><?php
                                    echo esc_textarea( implode( "\n", (array) ( $settings['excluded_cookies'] ?? [] ) ) );
                                ?></textarea>
                                <p class="description"><?php esc_html_e( 'Skip caching if these cookie names are present.', 'wp-static-runtime' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                </div>

                <?php do_action( 'wsr_settings_content_html' ); ?>

                <!-- Server Configuration Tab -->
                <div id="tab-server" class="wsr-tab-content" style="display:none;">
                <?php self::render_server_config(); ?>
                </div>

                <p class="submit">
                    <input type="hidden" name="wsr_save_settings" value="1" />
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-static-runtime' ); ?></button>
                </p>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('.wsr-nav-tab-wrapper .nav-tab').on('click', function(e) {
                    e.preventDefault();
                    $('.wsr-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    $('.wsr-tab-content').hide();
                    $($(this).attr('href').replace('#', '#tab-')).show();
                    
                    // Update URL hash without scroll
                    if(history.pushState) {
                        history.pushState(null, null, $(this).attr('href'));
                    } else {
                        window.location.hash = $(this).attr('href');
                    }
                });
                
                // On load, if hash exists, click that tab
                if (window.location.hash) {
                    var tab = $('.wsr-nav-tab-wrapper .nav-tab[href="' + window.location.hash + '"]');
                    if (tab.length) {
                        tab.click();
                    }
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Section: Apache .htaccess generator + Nginx config snippet.
     */
    private static function render_server_config(): void {
        $htaccess_path = ABSPATH . '.htaccess';
        $htaccess_exists  = file_exists( $htaccess_path );
        $htaccess_writable = $htaccess_exists && is_writable( $htaccess_path );

        $apache_rules  = \WSR\Server\Apache::generate_rules();
        $nginx_snippet = \WSR\Server\Nginx::generate_config();
        ?>

        <!-- ── Apache .htaccess ───────────────────────────────────────────── -->
        <div class="wsr-settings-section">
            <h2><span class="dashicons dashicons-media-code" style="vertical-align:middle;margin-right:4px;"></span> <?php esc_html_e( 'Apache — .htaccess Rules', 'wp-static-runtime' ); ?></h2>
            <p><?php esc_html_e( 'These rules let Apache serve cached HTML directly — without invoking PHP at all (5–15ms TTFB).', 'wp-static-runtime' ); ?></p>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( '.htaccess path', 'wp-static-runtime' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $htaccess_path ); ?></code>
                        <?php if ( ! $htaccess_exists ) : ?>
                            <span style="color:#dc2626;margin-left:8px;">— <?php esc_html_e( 'file not found', 'wp-static-runtime' ); ?></span>
                        <?php elseif ( ! $htaccess_writable ) : ?>
                            <span style="color:#f59e0b;margin-left:8px;">— <?php esc_html_e( 'not writable (chmod)', 'wp-static-runtime' ); ?></span>
                        <?php else : ?>
                            <span style="color:#16a34a;margin-left:8px;">— <?php esc_html_e( 'exists & writable', 'wp-static-runtime' ); ?> ✔</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Generated Rules', 'wp-static-runtime' ); ?></th>
                    <td>
                        <textarea readonly rows="20" style="width:100%;font-family:monospace;font-size:12px;background:#1e1e2e;color:#cdd6f4;border:none;border-radius:6px;padding:12px;"><?php echo esc_textarea( $apache_rules ); ?></textarea>
                        <p class="description" style="margin-top:6px;">
                            <?php if ( $htaccess_writable ) : ?>
                                <?php esc_html_e( 'Rules are written automatically to .htaccess when "Apache Rewrite Rules" is enabled above. You can also copy-paste manually.', 'wp-static-runtime' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Copy and paste into your .htaccess manually, placing it before the # BEGIN WordPress block.', 'wp-static-runtime' ); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Nginx ──────────────────────────────────────────────────────── -->
        <div class="wsr-settings-section">
            <h2><span class="dashicons dashicons-media-code" style="vertical-align:middle;margin-right:4px;"></span> <?php esc_html_e( 'Nginx — Server Block Snippet', 'wp-static-runtime' ); ?></h2>
            <p>
                <?php esc_html_e( 'Nginx cannot be configured by PHP directly. Add the following snippet inside your', 'wp-static-runtime' ); ?>
                <code>server { }</code>
                <?php esc_html_e( 'block in your Nginx config file, BEFORE the existing WordPress block.', 'wp-static-runtime' ); ?>
            </p>
            <div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:12px 16px;border-radius:4px;margin-bottom:12px;font-size:13px;">
                <strong><span class="dashicons dashicons-media-document" style="font-size:16px;line-height:20px;vertical-align:text-bottom;"></span> <?php esc_html_e( 'How to use:', 'wp-static-runtime' ); ?></strong><br>
                1. <?php esc_html_e( 'Open your Nginx config file (e.g.', 'wp-static-runtime' ); ?> <code>/etc/nginx/sites-available/yoursite.conf</code>)<br>
                2. <?php esc_html_e( 'Copy the snippet below into the', 'wp-static-runtime' ); ?> <code>server { }</code> <?php esc_html_e( 'block, before the', 'wp-static-runtime' ); ?> <code>location / { ... }</code> <?php esc_html_e( 'line', 'wp-static-runtime' ); ?><br>
                3. <?php esc_html_e( 'Run', 'wp-static-runtime' ); ?> <code>nginx -t</code> <?php esc_html_e( 'to verify, then', 'wp-static-runtime' ); ?> <code>systemctl reload nginx</code>
            </div>
            <textarea readonly rows="30" style="width:100%;font-family:monospace;font-size:12px;background:#1e1e2e;color:#cdd6f4;border:none;border-radius:6px;padding:12px;"><?php echo esc_textarea( $nginx_snippet ); ?></textarea>
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e( 'Adjust the PHP-FPM socket path in the fastcgi_pass line if it differs from the default.', 'wp-static-runtime' ); ?>
            </p>
        </div>
        <?php
    }

    private static function save(): void {
        $defaults = \WSR\Constants::defaults();
        $settings = [];

        $settings['cache_enabled']      = ! empty( $_POST['cache_enabled'] );
        $settings['cache_ttl']          = max( 0, intval( $_POST['cache_ttl'] ?? 0 ) );
        $settings['skip_logged_in']     = ! empty( $_POST['skip_logged_in'] );
        $settings['skip_query_strings'] = ! empty( $_POST['skip_query_strings'] );
        $settings['gzip_cache']         = ! empty( $_POST['gzip_cache'] );
        $settings['minify_html']        = ! empty( $_POST['minify_html'] );
        $settings['apache_rewrite']     = ! empty( $_POST['apache_rewrite'] );
        $settings['woo_hybrid']         = ! empty( $_POST['woo_hybrid'] );

        // Parse textarea lists
        // woo_exclude: only allow valid URL slugs (lowercase, alphanumeric, hyphens)
        $settings['woo_exclude'] = array_values( array_filter( array_map( function( $slug ) {
            $slug = strtolower( trim( $slug ) );
            return preg_replace( '/[^a-z0-9\-_\/]/', '', $slug );
        }, explode( "\n", sanitize_textarea_field( $_POST['woo_exclude'] ?? '' ) ) ) ) );

        // excluded_urls: sanitize each line as a URL path
        $settings['excluded_urls'] = array_values( array_filter( array_map( function( $url ) {
            return sanitize_text_field( trim( $url ) );
        }, explode( "\n", sanitize_textarea_field( $_POST['excluded_urls'] ?? '' ) ) ) ) );

        // excluded_cookies: only allow valid cookie name characters
        $settings['excluded_cookies'] = array_values( array_filter( array_map( function( $cookie ) {
            return preg_replace( '/[^a-zA-Z0-9_\-]/', '', trim( $cookie ) );
        }, explode( "\n", sanitize_textarea_field( $_POST['excluded_cookies'] ?? '' ) ) ) ) );

        // Carry over premium fields if present
        $existing = get_option( 'wsr_settings', $defaults );
        $settings = array_merge( $existing, $settings );

        update_option( 'wsr_settings', $settings );

        // Re-generate server rules
        if ( $settings['apache_rewrite'] ) {
            \WSR\Server\Apache::write_rules();
        } else {
            \WSR\Server\Apache::remove_rules();
        }
    }
}

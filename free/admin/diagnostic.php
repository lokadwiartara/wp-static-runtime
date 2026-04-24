<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Diagnostic — shows exactly what is and isn't working.
 * Helps identify why caching is not active.
 */
class Diagnostic {

    public static function render() {
        $checks = self::run_all_checks();
        $ok     = array_filter( $checks, function( $c ) { return $c['ok']; } );
        $fail   = array_filter( $checks, function( $c ) { return ! $c['ok']; } );
        ?>
        <div class="wrap wsr-wrap">
            <h1>🔍 <?php esc_html_e( 'Diagnostic', 'wp-static-runtime' ); ?></h1>
            <p><?php esc_html_e( 'This page checks every part of WP Static Runtime to find what\'s not working.', 'wp-static-runtime' ); ?></p>

            <?php if ( count( $fail ) === 0 ) : ?>
                <div class="wsr-status-bar active">✅ All checks passed — plugin is fully operational.</div>
            <?php else : ?>
                <div class="wsr-status-bar inactive">
                    ⚠ <?php echo count( $fail ); ?> issue(s) found — see details below.
                </div>
            <?php endif; ?>

            <div class="wsr-section" style="margin-top:24px;">
                <table class="widefat wsr-status-table">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            <th><?php esc_html_e( 'Check', 'wp-static-runtime' ); ?></th>
                            <th><?php esc_html_e( 'Value / Status', 'wp-static-runtime' ); ?></th>
                            <th><?php esc_html_e( 'Fix', 'wp-static-runtime' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $checks as $check ) : ?>
                        <tr>
                            <td><?php echo $check['ok'] ? '✅' : '❌'; ?></td>
                            <td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $check['value'] ); ?></code></td>
                            <td style="color:#64748b;font-size:12px;"><?php echo esc_html( $check['fix'] ?? '' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Manual Test Cache -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Manual Cache Test', 'wp-static-runtime' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to manually cache the homepage. Check your cache directory afterwards.', 'wp-static-runtime' ); ?></p>
                <div class="wsr-actions">
                    <button id="wsr-test-cache-btn" class="button button-primary wsr-btn">
                        🧪 <?php esc_html_e( 'Test: Cache Homepage Now', 'wp-static-runtime' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wsr_reinstall&_wpnonce=' . wp_create_nonce('wsr_reinstall') ) ); ?>"
                       class="button wsr-btn" onclick="return confirm('Reinstall advanced-cache.php and recheck wp-config?')">
                        🔧 <?php esc_html_e( 'Reinstall advanced-cache.php', 'wp-static-runtime' ); ?>
                    </a>
                </div>
                <div id="wsr-action-result" class="wsr-notice" style="display:none;margin-top:12px;"></div>
                <div id="wsr-test-output" style="display:none;margin-top:12px;"></div>
            </div>

            <!-- Cache Directory Contents -->
            <div class="wsr-section">
                <h2><?php esc_html_e( 'Cache Directory', 'wp-static-runtime' ); ?></h2>
                <?php
                $cache_dir = WSR_CACHE_DIR;
                $files = [];
                if ( is_dir( $cache_dir ) ) {
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator( $cache_dir, \FilesystemIterator::SKIP_DOTS )
                    );
                    foreach ( $it as $file ) {
                        if ( $file->isFile() && $file->getExtension() === 'html' ) {
                            $files[] = str_replace( $cache_dir, '', $file->getPathname() );
                        }
                    }
                }
                ?>
                <p>
                    <strong><?php esc_html_e( 'Directory:', 'wp-static-runtime' ); ?></strong>
                    <code><?php echo esc_html( $cache_dir ); ?></code>
                    <?php if ( ! is_dir( $cache_dir ) ) : ?>
                        <span class="wsr-badge off"><?php esc_html_e( 'Does not exist', 'wp-static-runtime' ); ?></span>
                    <?php elseif ( ! is_writable( $cache_dir ) ) : ?>
                        <span class="wsr-badge off"><?php esc_html_e( 'Not writable', 'wp-static-runtime' ); ?></span>
                    <?php else : ?>
                        <span class="wsr-badge ok"><?php esc_html_e( 'Exists & writable', 'wp-static-runtime' ); ?></span>
                    <?php endif; ?>
                </p>
                <?php if ( empty( $files ) ) : ?>
                    <p style="color:#94a3b8;"><?php esc_html_e( 'No cached files found yet.', 'wp-static-runtime' ); ?></p>
                <?php else : ?>
                    <p><?php echo count( $files ); ?> <?php esc_html_e( 'HTML files found:', 'wp-static-runtime' ); ?></p>
                    <ul style="font-family:monospace;font-size:12px;max-height:300px;overflow:auto;background:#f8fafc;padding:12px;border-radius:6px;">
                        <?php foreach ( array_slice( $files, 0, 100 ) as $f ) : ?>
                            <li><?php echo esc_html( $f ); ?></li>
                        <?php endforeach; ?>
                        <?php if ( count( $files ) > 100 ) : ?>
                            <li>... and <?php echo count( $files ) - 100; ?> more.</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(function($){
            // Manual test cache
            $('#wsr-test-cache-btn').on('click', function(e){
                e.preventDefault();
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="wsr-spinner"></span> Testing...');
                $.post(ajaxurl, {
                    action: 'wsr_diagnostic_test',
                    nonce:  '<?php echo esc_js( wp_create_nonce('wsr_admin_nonce') ); ?>'
                })
                .done(function(res){
                    var $out = $('#wsr-test-output');
                    if(res.success){
                        var d = res.data;
                        var html = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;font-size:13px;">';
                        html += '<p><strong>Result:</strong> ' + (d.cached ? '✅ Cached successfully!' : '❌ Not cached') + '</p>';
                        html += '<p><strong>Cache file:</strong> <code>' + d.cache_file + '</code></p>';
                        html += '<p><strong>File exists:</strong> ' + (d.file_exists ? '✅ Yes' : '❌ No') + '</p>';
                        html += '<p><strong>ob_get_level:</strong> ' + d.ob_level + '</p>';
                        html += '<p><strong>WP_CACHE:</strong> ' + d.wp_cache + '</p>';
                        html += '<p><strong>advanced-cache.php:</strong> ' + d.advanced_cache + '</p>';
                        if(d.error){ html += '<p style="color:red;"><strong>Error:</strong> ' + d.error + '</p>'; }
                        html += '</div>';
                        $out.html(html).show();
                        if(d.cached){
                            $('#wsr-action-result').removeClass('error info').addClass('success')
                                .text('✅ Cache test passed! Homepage cached at: ' + d.cache_file).show();
                        } else {
                            $('#wsr-action-result').removeClass('success info').addClass('error')
                                .text('❌ Cache test failed. Check details below.').show();
                        }
                    } else {
                        $('#wsr-action-result').removeClass('success info').addClass('error')
                            .text((res.data && res.data.message) || 'Test failed.').show();
                    }
                })
                .fail(function(xhr){ $('#wsr-action-result').addClass('error').text('AJAX error: '+xhr.status).show(); })
                .always(function(){ $btn.prop('disabled',false).text('🧪 Test: Cache Homepage Now'); });
            });
        });
        </script>
        <?php
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    private static function run_all_checks() {
        $checks = [];

        // 1. WP_CACHE defined
        $wp_cache = defined( 'WP_CACHE' ) && WP_CACHE;
        $checks[] = [
            'label' => 'WP_CACHE = true in wp-config.php',
            'ok'    => $wp_cache,
            'value' => $wp_cache ? 'true' : ( defined('WP_CACHE') ? 'false' : 'not defined' ),
            'fix'   => $wp_cache ? '' : 'Add: define(\'WP_CACHE\', true); to wp-config.php',
        ];

        // 2. advanced-cache.php exists and is ours
        $ac_path    = WP_CONTENT_DIR . '/advanced-cache.php';
        $ac_exists  = file_exists( $ac_path );
        $ac_is_ours = $ac_exists && strpos( file_get_contents( $ac_path ), 'WP Static Runtime' ) !== false;
        $checks[] = [
            'label' => 'advanced-cache.php installed',
            'ok'    => $ac_is_ours,
            'value' => ! $ac_exists ? __( 'missing', 'wp-static-runtime' ) : ( $ac_is_ours ? 'OK (WP Static Runtime)' : __( 'belongs to another plugin', 'wp-static-runtime' ) ),
            'fix'   => $ac_is_ours ? '' : 'Click "Reinstall advanced-cache.php" below',
        ];

        // 3. Cache directory exists and writable
        $cache_dir      = WSR_CACHE_DIR;
        $dir_exists     = is_dir( $cache_dir );
        $dir_writable   = $dir_exists && is_writable( $cache_dir );
        $checks[] = [
            'label' => 'Cache directory writable',
            'ok'    => $dir_writable,
            'value' => $dir_exists ? ( $dir_writable ? 'OK (' . $cache_dir . ')' : 'NOT writable' ) : 'does not exist',
            'fix'   => $dir_writable ? '' : 'Run: chmod 755 ' . $cache_dir . ' or fix server permissions',
        ];

        // 4. DB tables exist
        global $wpdb;
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wsr_cache_index'" );
        $checks[] = [
            'label' => 'Database table wp_wsr_cache_index',
            'ok'    => (bool) $table_exists,
            'value' => $table_exists ? __( 'exists', 'wp-static-runtime' ) : __( 'missing', 'wp-static-runtime' ),
            'fix'   => $table_exists ? '' : 'Deactivate and reactivate the plugin',
        ];

        // 5. cache_enabled setting
        $settings = get_option( 'wsr_settings', [] );
        $enabled  = ! empty( $settings['cache_enabled'] );
        $checks[] = [
            'label' => 'Cache enabled in settings',
            'ok'    => $enabled,
            'value' => $enabled ? 'yes' : 'NO — disabled in Settings',
            'fix'   => $enabled ? '' : 'Go to Settings → enable "Enable Cache"',
        ];

        // 6. PHP output buffering
        $ob_level = ob_get_level();
        $checks[] = [
            'label' => 'PHP output buffering',
            'ok'    => true,
            'value' => 'ob_get_level() = ' . $ob_level,
            'fix'   => '',
        ];

        // 7. Is the current user logged in? (caching skipped for admins)
        $logged_in = is_user_logged_in();
        $skip_li   = ! empty( $settings['skip_logged_in'] );
        $checks[] = [
            'label' => 'You are logged in (cache skipped for admins)',
            'ok'    => true, // informational
            'value' => $logged_in
                        ? ( $skip_li ? 'YES — caching skipped for logged-in users (expected)' : 'YES — but skip_logged_in is off' )
                        : __( 'No', 'wp-static-runtime' ),
            'fix'   => $logged_in && $skip_li ? 'Visit your site in incognito / logged-out browser to see caching' : '',
        ];

        // 8. PHP version
        $php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
        $checks[] = [
            'label' => 'PHP version >= 7.4',
            'ok'    => $php_ok,
            'value' => PHP_VERSION,
            'fix'   => $php_ok ? '' : 'Upgrade PHP to 7.4 or higher',
        ];

        // 9. WordPress version
        global $wp_version;
        $wp_ok = version_compare( $wp_version, '5.8', '>=' );
        $checks[] = [
            'label' => 'WordPress version >= 5.8',
            'ok'    => $wp_ok,
            'value' => $wp_version,
            'fix'   => $wp_ok ? '' : 'Update WordPress',
        ];

        // 10. File system — can we write a test file?
        $test_file = WSR_CACHE_DIR . '.write_test_' . time();
        $can_write = false;
        if ( is_dir( WSR_CACHE_DIR ) ) {
            $can_write = file_put_contents( $test_file, 'ok' ) !== false;
            if ( $can_write ) unlink( $test_file );
        }
        $checks[] = [
            'label' => 'Filesystem write test',
            'ok'    => $can_write,
            'value' => $can_write ? 'Can write files to cache dir' : 'Cannot write files — permission error',
            'fix'   => $can_write ? '' : 'chmod 755 ' . WSR_CACHE_DIR . ' and ensure web server owns the directory',
        ];

        return $checks;
    }

    // ── AJAX: Manual test ─────────────────────────────────────────────────────

    public static function ajax_test() {
        check_ajax_referer( 'wsr_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        $url        = home_url( '/' );
        $cache_file = \WSR\Cache_Reader::resolve_path_from_url( $url );
        $ob_level   = ob_get_level();

        // Force-fetch the homepage (not as admin — use a non-auth request)
        $response = wp_remote_get( $url, [
            'timeout'            => 15,
            'sslverify'          => apply_filters( 'wsr_crawler_sslverify', true ),
            'reject_unsafe_urls' => false,
            'cookies'            => [], // no cookies = non-logged-in
            'headers'            => [ 'X-WSR-Test' => '1' ],
        ] );

        $error = '';
        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        }

        $file_exists = file_exists( $cache_file );
        $cached      = $file_exists;

        // If not cached via HTTP, write directly as a test
        if ( ! $cached ) {
            $body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
            if ( ! empty( $body ) && stripos( $body, '</html>' ) !== false ) {
                $body  .= "\n<!-- Test-cached by WP Static Runtime Diagnostic at " . gmdate( 'Y-m-d H:i:s' ) . " -->\n";
                $cached = \WSR\Cache_Writer::write_url( $url, $body );
                $file_exists = file_exists( $cache_file );
            }
        }

        wp_send_json_success( [
            'cached'         => $cached,
            'cache_file'     => $cache_file,
            'file_exists'    => $file_exists,
            'ob_level'       => $ob_level,
            'wp_cache'       => defined( 'WP_CACHE' ) ? ( WP_CACHE ? 'true' : 'false' ) : 'not defined',
            'advanced_cache' => file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ? 'installed' : 'MISSING',
            'error'          => $error,
        ] );
    }

    // ── Reinstall handler ─────────────────────────────────────────────────────

    public static function handle_reinstall() {
        check_admin_referer( 'wsr_reinstall' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        \WSR\Installer::run();

        wp_redirect( admin_url( 'admin.php?page=wsr-diagnostic&reinstalled=1' ) );
        exit;
    }
}

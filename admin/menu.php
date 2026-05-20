<?php
namespace WSR\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Menu – registers all StatixPress admin pages.
 */
class Menu {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_pages'   ] );
        add_action( 'admin_menu',            [ $this, 'register_settings_last' ], 99 );
        add_action( 'admin_bar_menu',        [ $this, 'admin_bar_button' ], 100 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'   ] );
        add_action( 'admin_notices',         [ $this, 'show_reinstalled_notice' ] );

        // AJAX handlers
        \WSR\Crawler\Crawler::register_ajax();
        add_action( 'wp_ajax_wsr_diagnostic_test', [ Diagnostic::class, 'ajax_test' ] );

        // Admin-post handlers
        add_action( 'admin_post_wsr_flush_all',  [ $this, 'handle_flush_all'  ] );
        add_action( 'admin_post_wsr_purge_page', [ $this, 'handle_purge_page' ] );
        add_action( 'admin_post_wsr_reinstall',  [ Diagnostic::class, 'handle_reinstall' ] );
    }

    public function register_pages() {
        // Custom SVG icon – satu warna, mengikuti warna teks menu WordPress (currentColor)
        $svg_icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"'
            . ' stroke="black" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>'
            . '</svg>'
        );

        add_menu_page(
            'StatixPress',
            'Static Runtime',
            'manage_options',
            'wsr-dashboard',
            [ Dashboard::class, 'render' ],
            $svg_icon,
            80
        );

        add_submenu_page( 'wsr-dashboard', __( 'Dashboard',  'statixpress-static-runtime' ), '<span class="wsr-menu-item wsr-menu-dashboard">'  . __( 'Dashboard',  'statixpress-static-runtime' ) . '</span>', 'manage_options', 'wsr-dashboard',  [ Dashboard::class,  'render'         ] );
        add_submenu_page( 'wsr-dashboard', __( 'Cache',      'statixpress-static-runtime' ), '<span class="wsr-menu-item wsr-menu-cache">'      . __( 'Cache',      'statixpress-static-runtime' ) . '</span>', 'manage_options', 'wsr-cache',      [ Cache_Page::class, 'render'         ] );
        add_submenu_page( 'wsr-dashboard', __( 'Crawler',    'statixpress-static-runtime' ), '<span class="wsr-menu-item wsr-menu-crawler">'    . __( 'Crawler',    'statixpress-static-runtime' ) . '</span>', 'manage_options', 'wsr-crawler',    [ Cache_Page::class, 'render_crawler' ] );
        add_submenu_page( 'wsr-dashboard', __( 'Diagnostic', 'statixpress-static-runtime' ), '<span class="wsr-menu-item wsr-menu-diagnostic">' . __( 'Diagnostic', 'statixpress-static-runtime' ) . '</span>', 'manage_options', 'wsr-diagnostic', [ Diagnostic::class, 'render'         ] );
    }

    public function register_settings_last() {
        add_submenu_page( 'wsr-dashboard', __( 'Settings',   'statixpress-static-runtime' ), '<span class="wsr-menu-item wsr-menu-settings">'   . __( 'Settings',   'statixpress-static-runtime' ) . '</span>', 'manage_options', 'wsr-settings',   [ Settings::class,   'render'         ] );
    }

    /**
     * Admin bar: Flush Cache + Purge This Page.
     */
    public function admin_bar_button( $bar ) {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $bar->add_node( [
            'id'    => 'wsr-flush',
            'title' => '⚡ Flush Cache',
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=wsr_flush_all' ), 'wsr_flush_all' ),
            'meta'  => [ 'title' => 'StatixPress: Flush All Cache' ],
        ] );

        if ( is_singular() ) {
            $bar->add_node( [
                'parent' => 'wsr-flush',
                'id'     => 'wsr-purge-page',
                'title'  => 'Purge This Page',
                'href'   => wp_nonce_url(
                    admin_url( 'admin-post.php?action=wsr_purge_page&url=' . rawurlencode( get_permalink() ) ),
                    'wsr_purge_page'
                ),
            ] );
        }
    }

    public function handle_flush_all() {
        check_admin_referer( 'wsr_flush_all' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        \WSR\Cache_Cleaner::flush_all();
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wsr-dashboard' ) );
        exit;
    }

    public function handle_purge_page() {
        check_admin_referer( 'wsr_purge_page' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        $url = esc_url_raw( wp_unslash( $_GET['url'] ?? '' ) );
        if ( $url ) \WSR\Cache_Cleaner::purge_url( $url );
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wsr-cache' ) );
        exit;
    }

    public function show_reinstalled_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $reinstalled = isset( $_GET['reinstalled'] ) ? sanitize_text_field( wp_unslash( $_GET['reinstalled'] ) ) : '';
        $page        = isset( $_GET['page'] )        ? sanitize_text_field( wp_unslash( $_GET['page'] ) )        : '';
        if ( $reinstalled && 'wsr-diagnostic' === $page ) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>StatixPress:</strong> Reinstalled successfully.</p></div>';
        }
        // phpcs:enable
    }


    public function enqueue_assets( $hook ) {
        // Menu icon CSS dimuat di semua halaman admin agar icon sidebar selalu tampil
        add_action( 'admin_head', [ $this, 'print_menu_icon_css' ] );

        if ( strpos( $hook, 'wsr-' ) === false ) return;

        wp_enqueue_style( 'wsr-admin', WSR_URL . 'assets/css/admin.css', [], WSR_VERSION );
        wp_enqueue_script( 'wsr-admin', WSR_URL . 'assets/js/admin.js', [ 'jquery' ], WSR_VERSION, true );

        wp_localize_script( 'wsr-admin', 'WSR', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wsr_admin_nonce' ),
            'strings'  => [
                'flushing' => 'Flushing cache...',
                'flushed'  => 'Cache flushed!',
                'crawling' => 'Crawl started...',
                'error'    => 'Something went wrong.',
            ],
        ] );
    }

    /**
     * Inject CSS untuk icon submenu sidebar – satu warna, konsisten dengan tema WP.
     * Menggunakan ::before pseudo-element dengan SVG mask agar warna mengikuti sidebar.
     */
    public function print_menu_icon_css() {
        // SVG icons sebagai mask – warna mengikuti --wsr-menu-color (currentColor sidebar WP)
        $icons = [
            'dashboard'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
            'cache'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
            'crawler'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M11 8v3l2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg>',
            'settings'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'diagnostic' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 11l3 3L22 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        // Encode setiap icon jadi data URI
        $encoded = [];
        foreach ( $icons as $key => $svg ) {
            $encoded[ $key ] = 'url(data:image/svg+xml;base64,' . base64_encode( $svg ) . ')';
        }
        ?>
<style id="wsr-menu-icons">
/* ── StatixPress: Sidebar Submenu Icons ─────────────────────────────── */
/* Teknik: mask-image agar icon mengikuti warna teks sidebar secara otomatis   */
.wsr-menu-item {
    display: inline-flex;
    align-items: center;
    gap: 0;
}
.wsr-menu-item::before {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-right: 6px;
    flex-shrink: 0;
    background-color: currentColor;
    -webkit-mask-size: contain;
    mask-size: contain;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-position: center;
    mask-position: center;
    opacity: 0.85;
    vertical-align: middle;
    position: relative;
    top: -1px;
}

/* Tiap submenu pakai icon berbeda */
.wsr-menu-dashboard::before  { -webkit-mask-image: <?php echo esc_attr( $encoded['dashboard'] );  ?>; mask-image: <?php echo esc_attr( $encoded['dashboard'] );  ?>; }
.wsr-menu-cache::before      { -webkit-mask-image: <?php echo esc_attr( $encoded['cache'] );      ?>; mask-image: <?php echo esc_attr( $encoded['cache'] );      ?>; }
.wsr-menu-crawler::before    { -webkit-mask-image: <?php echo esc_attr( $encoded['crawler'] );    ?>; mask-image: <?php echo esc_attr( $encoded['crawler'] );    ?>; }
.wsr-menu-settings::before   { -webkit-mask-image: <?php echo esc_attr( $encoded['settings'] );   ?>; mask-image: <?php echo esc_attr( $encoded['settings'] );   ?>; }
.wsr-menu-diagnostic::before { -webkit-mask-image: <?php echo esc_attr( $encoded['diagnostic'] ); ?>; mask-image: <?php echo esc_attr( $encoded['diagnostic'] ); ?>; }

/* Saat menu aktif / hover – opacity penuh */
.current .wsr-menu-item::before,
a:hover .wsr-menu-item::before,
.wp-menu-open .wsr-menu-item::before {
    opacity: 1;
}
</style>
        <?php
    }
}

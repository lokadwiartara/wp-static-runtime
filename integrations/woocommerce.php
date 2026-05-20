<?php
namespace WSR\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Integration — Hybrid caching mode.
 *
 * CACHED:   shop, product, category, tag pages (static HTML)
 * DYNAMIC:  cart, checkout, my-account, order-received (never cached)
 * AJAX:     wc-ajax endpoints pass through PHP as normal
 */
class WooCommerce {

    /** Pages that must NEVER be cached */
    private array $dynamic_pages = [];

    public function __construct() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );
        $this->dynamic_pages = (array) ( $settings['woo_exclude'] ?? [ 'cart', 'checkout', 'my-account', 'order' ] );

        // Ensure dynamic pages are never cached
        add_filter( 'wsr_request_cacheable', [ $this, 'filter_cacheability' ], 10, 2 );

        // Purge product caches on stock / price / status changes
        add_action( 'woocommerce_product_set_stock',         [ $this, 'purge_product'        ] );
        add_action( 'woocommerce_variation_set_stock',       [ $this, 'purge_product'        ] );
        add_action( 'woocommerce_product_set_stock_status',  [ $this, 'purge_product_status' ] );
        add_action( 'woocommerce_update_product',            [ $this, 'purge_product_by_id'  ] );
        add_action( 'save_post_product',                     [ $this, 'purge_product_by_id'  ] );

        // Purge shop/category on product create/delete
        add_action( 'woocommerce_new_product',               [ $this, 'purge_shop_pages'     ] );
        add_action( 'woocommerce_delete_product',            [ $this, 'purge_shop_pages'     ] );

        // Purge order-related pages when order status changes
        add_action( 'woocommerce_order_status_changed',      [ $this, 'on_order_status'      ], 10, 3 );

        // Inject cart fragment script for AJAX cart on static pages
        add_action( 'wp_footer', [ $this, 'inject_cart_fragment_support' ] );
    }

    // ── Cacheability Filter ───────────────────────────────────────────────────

    /**
     * Prevent dynamic WooCommerce pages from being cached.
     */
    public function filter_cacheability( bool $cacheable, string $uri ): bool {
        if ( ! $cacheable ) return false;

        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );

        // Check special cookies
        $woo_cookies = (array) ( $settings['woo_excluded_cookies'] ?? [] );
        foreach ( $woo_cookies as $cookie ) {
            if ( isset( $_COOKIE[ $cookie ] ) && ! empty( $_COOKIE[ $cookie ] ) ) {
                return false;
            }
        }

        // Check dynamic page slugs in URI
        foreach ( $this->dynamic_pages as $slug ) {
            if ( strpos( $uri, '/' . $slug ) !== false ) {
                return false;
            }
        }

        // WooCommerce-aware page checks (requires WP to be loaded)
        if ( function_exists( 'is_cart' ) ) {
            if ( is_cart() || is_checkout() || is_account_page() ) {
                return false;
            }

            // Order received / thank you page
            if ( is_wc_endpoint_url( 'order-received' ) ) {
                return false;
            }
        }

        // Skip wc-ajax
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['wc-ajax'] ) ) {
            return false;
        }

        return $cacheable;
    }

    // ── Cache Purge Methods ───────────────────────────────────────────────────

    /**
     * @param \WC_Product $product
     */
    public function purge_product( $product ): void {
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            $this->purge_product_by_id( $product->get_id() );
        }
    }

    /**
     * @param \WC_Product $product
     */
    public function purge_product_status( $product ): void {
        $this->purge_product( $product );
    }

    public function purge_product_by_id( int $product_id ): void {
        if ( ! $product_id ) return;

        // Purge the product page
        \WSR\Cache::purge_post( $product_id );

        // Purge the shop page
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id && $shop_page_id !== -1 ) {
            \WSR\Cache::purge_post( $shop_page_id );
        }

        // Purge product categories
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $link = get_term_link( $term );
                if ( ! is_wp_error( $link ) ) {
                    \WSR\Cache::purge( $link );
                }
            }
        }

        // Purge product tags
        $tags = get_the_terms( $product_id, 'product_tag' );
        if ( $tags && ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $link = get_term_link( $tag );
                if ( ! is_wp_error( $link ) ) {
                    \WSR\Cache::purge( $link );
                }
            }
        }
    }

    public function purge_shop_pages(): void {
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id && $shop_page_id !== -1 ) {
            \WSR\Cache::purge_post( $shop_page_id );
        }
        \WSR\Cache::purge( home_url( '/' ) );
    }

    public function on_order_status( int $order_id, string $old_status, string $new_status ): void {
        // No static pages to purge for order status changes
        // but we can hook here for notification / CDN purge (Premium)
        do_action( 'wsr_woo_order_status_changed', $order_id, $old_status, $new_status );
    }

    // ── AJAX Cart Fragment Support ────────────────────────────────────────────

    /**
     * Inject a small inline script so WooCommerce's cart fragment AJAX
     * works correctly on fully static pages.
     */
    public function inject_cart_fragment_support(): void {
        if ( is_cart() || is_checkout() || is_account_page() ) return;

        // WooCommerce already enqueues wc-cart-fragments script.
        // We just ensure the nonce endpoint is accessible.
        if ( ! wp_script_is( 'wc-cart-fragments', 'enqueued' ) ) {
            wp_enqueue_script( 'wc-cart-fragments' );
        }
    }

    /**
     * Return the list of page slugs that are excluded from caching.
     */
    public function get_dynamic_pages(): array {
        return $this->dynamic_pages;
    }
}

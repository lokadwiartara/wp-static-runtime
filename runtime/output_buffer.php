<?php
namespace WSR\Runtime;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Output Buffer — captures WordPress page output and saves it as static HTML.
 *
 * HOW IT WORKS:
 * 1. At template_redirect (priority 1), we record the current ob_get_level()
 *    and start our own buffer with ob_start().
 * 2. At shutdown (priority 0) — BEFORE WordPress's wp_ob_end_flush_all runs
 *    at priority 1 — we:
 *    a. Flush any inner buffers (added after ours) INTO ours.
 *    b. Capture our buffer with ob_get_clean().
 *    c. Write to cache, then echo to output.
 *
 * This guarantees we get the FULL rendered HTML before it's sent to the browser.
 */
class Output_Buffer {

    /** @var bool Did we actually start a buffer? */
    private $started = false;

    /** @var int ob_get_level() before we started our buffer */
    private $ob_level_before = 0;

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'start'  ], 1   );
        // Priority 0 = run BEFORE WordPress's wp_ob_end_flush_all at priority 1
        add_action( 'shutdown',          [ $this, 'finish' ], 0   );
    }

    /**
     * Start capturing output.
     * Fires at template_redirect priority 1 — before WordPress renders anything.
     */
    public function start() {
        if ( $this->should_skip() ) return;

        $this->ob_level_before = ob_get_level();
        ob_start();
        $this->started = true;
    }

    /**
     * Capture, cache, and output the page.
     * Fires at shutdown priority 0 — before wp_ob_end_flush_all.
     */
    public function finish() {
        if ( ! $this->started ) return;

        $our_level = $this->ob_level_before + 1;

        // Flush any buffers started AFTER ours (inner) into our buffer
        // so our buffer contains the complete rendered page.
        while ( ob_get_level() > $our_level ) {
            ob_end_flush();
        }

        // Check our buffer still exists
        if ( ob_get_level() < $our_level ) {
            // Something else already flushed/cleaned our buffer — nothing to do
            return;
        }

        // Capture our buffer
        $html = ob_get_clean();

        if ( empty( $html ) ) {
            return;
        }

        // Only cache full HTML documents
        $is_html = stripos( $html, '</html>' ) !== false
                || stripos( $html, '</body>' ) !== false;

        if ( $is_html && ! $this->should_skip() ) {
            // Apply HTML optimization filters (Premium feature hooks in here)
            $html = apply_filters( 'wsr_optimize_html', $html );

            $stamped_html = $html
                . "\n<!-- Cached by StatixPress at "
                . gmdate( 'Y-m-d H:i:s' )
                . " UTC -->\n";

            $request = \WSR\Request::instance();
            \WSR\Cache_Writer::write( $request->uri(), $stamped_html );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $stamped_html;
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;
        }
    }

    /**
     * Check all conditions that should prevent caching.
     * Safe to call at both template_redirect and shutdown.
     *
     * @return bool true = skip caching for this request
     */
    private function should_skip() {
        // Must be GET
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        // No admin, AJAX, REST, CLI, Cron
        if ( is_admin() )                                          return true;
        if ( defined( 'DOING_AJAX'   ) && DOING_AJAX   )          return true;
        if ( defined( 'DOING_CRON'   ) && DOING_CRON   )          return true;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST )           return true;
        if ( defined( 'WP_CLI'       ) && WP_CLI       )          return true;

        // Feed requests
        if ( function_exists( 'is_feed' ) && is_feed() )          return true;

        $settings = get_option( 'wsr_settings', \WSR\Constants::defaults() );

        // Cache globally disabled
        if ( empty( $settings['cache_enabled'] ) )                 return true;

        // Skip logged-in users (is_user_logged_in() is safe at template_redirect)
        if ( ! empty( $settings['skip_logged_in'] ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
            return true;
        }

        // Query string
        $qs = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
        if ( ! empty( $settings['skip_query_strings'] ) && $qs !== '' ) {
            return true;
        }

        // Cookies
        $excluded_cookies = isset( $settings['excluded_cookies'] ) ? (array) $settings['excluded_cookies'] : [];
        foreach ( $excluded_cookies as $prefix ) {
            $prefix = trim( $prefix );
            if ( empty( $prefix ) ) continue;
            foreach ( array_keys( $_COOKIE ) as $key ) {
                if ( strpos( $key, $prefix ) === 0 ) return true;
            }
        }

        // Excluded URLs
        $request = \WSR\Request::instance();
        $uri     = $request->uri();
        foreach ( (array) ( $settings['excluded_urls'] ?? [] ) as $pattern ) {
            $pattern = trim( $pattern );
            if ( $pattern && strpos( $uri, $pattern ) !== false ) return true;
        }

        // WooCommerce dynamic pages
        if ( ! empty( $settings['woo_hybrid'] ) ) {
            foreach ( (array) ( $settings['woo_exclude'] ?? [] ) as $slug ) {
                $slug = trim( $slug );
                if ( $slug && strpos( $uri, '/' . $slug ) !== false ) return true;
            }
            foreach ( (array) ( $settings['woo_excluded_cookies'] ?? [] ) as $cookie_name ) {
                if ( ! empty( $cookie_name ) && ! empty( $_COOKIE[ $cookie_name ] ) ) return true;
            }
        }

        return false;
    }
}

<?php

namespace WSR\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Nonce_Refresh — solves 403 on AJAX calls from cached pages.
 *
 * PROBLEM:
 *   Static cached HTML has frozen WordPress nonces embedded in JS.
 *   Nonces expire after ~12 hours, causing all AJAX calls to return 403.
 *
 * SOLUTION (3 layers):
 *   1. Endpoint `?action=wsr_refresh_nonce` — returns fresh nonces, no login required.
 *   2. JS interceptor (XMLHttpRequest + fetch) — auto-retry once with fresh nonce on 403.
 *   3. Pre-fetch on DOMContentLoaded when page is served from cache (x-cache: HIT).
 */
class Nonce_Refresh {

    const ACTION = 'wsr_refresh_nonce';

    public static function init(): void {
        add_action( 'wp_ajax_nopriv_' . self::ACTION, [ __CLASS__, 'handle_refresh' ] );
        add_action( 'wp_ajax_'        . self::ACTION, [ __CLASS__, 'handle_refresh' ] );
        add_action( 'wp_footer', [ __CLASS__, 'inject_script' ], 999 );
    }

    /**
     * AJAX handler — returns fresh nonces for requested actions.
     * Rate-limited to 60 requests per minute per IP.
     */
    public static function handle_refresh(): void {
        // Rate limit: 60 req/min per IP
        $ip     = self::get_client_ip();
        $tk     = 'wsr_nr_' . md5( $ip );
        $count  = (int) get_transient( $tk );
        if ( $count >= 60 ) {
            wp_send_json_error( [ 'message' => 'Rate limit exceeded' ], 429 );
        }
        set_transient( $tk, $count + 1, 60 );

        $raw_actions = isset( $_GET['actions'] ) ? (array) $_GET['actions'] : [];
        $actions     = array_map( 'sanitize_key', $raw_actions );
        $actions     = array_slice( $actions, 0, 20 ); // cap at 20 actions per request

        if ( empty( $actions ) ) {
            wp_send_json_error( [ 'message' => 'No actions requested' ], 400 );
        }

        $nonces = [];
        foreach ( $actions as $action ) {
            $nonces[ $action ] = wp_create_nonce( $action );
        }

        wp_send_json_success( [
            'nonces'     => $nonces,
            'expires_in' => (int) apply_filters( 'nonce_life', DAY_IN_SECONDS ) / 2,
            'generated'  => time(),
        ] );
    }

    /**
     * Inject JS interceptor into page footer.
     * Patches any global JS objects with a `.nonce` property and intercepts
     * XHR/fetch calls to auto-retry with fresh nonce on 403.
     *
     * Theme/plugin authors can register their global objects via filter:
     *   add_filter( 'wsr_nonce_global_objects', function( $globals ) {
     *       $globals[] = 'myPluginAjax';
     *       return $globals;
     *   });
     */
    public static function inject_script(): void {
        $ajax_url      = admin_url( 'admin-ajax.php' );
        $action        = self::ACTION;
        $global_objects = apply_filters( 'wsr_nonce_global_objects', [] );
        $globals_json   = wp_json_encode( array_map( 'sanitize_key', (array) $global_objects ) );
        ?>
<script id="wsr-nonce-refresh">
(function () {
    'use strict';

    var AJAX_URL     = <?php echo wp_json_encode( $ajax_url ); ?>;
    var ACTION       = <?php echo wp_json_encode( $action ); ?>;
    var GLOBALS      = <?php echo $globals_json; ?>;
    var _fetching    = null;
    var _cache       = {};

    /* ── 1. Fetch fresh nonces from server ──────────────────────────────── */
    function fetchFreshNonces(actions) {
        if (_fetching) return _fetching;

        var url = AJAX_URL + '?action=' + ACTION;
        actions.forEach(function (a) { url += '&actions[]=' + encodeURIComponent(a); });

        _fetching = fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _fetching = null;
                if (data && data.success && data.data && data.data.nonces) {
                    var nonces = data.data.nonces;
                    Object.keys(nonces).forEach(function (k) { _cache[k] = nonces[k]; });
                    patchGlobalNonces(nonces);
                    return nonces;
                }
                return {};
            })
            .catch(function () { _fetching = null; return {}; });

        return _fetching;
    }

    /* ── 2. Patch registered global JS objects ───────────────────────────── */
    function patchGlobalNonces(nonces) {
        var firstNonce = nonces[Object.keys(nonces)[0]] || null;

        GLOBALS.forEach(function (g) {
            if (!window[g] || typeof window[g] !== 'object') return;

            // Update .nonce property with the first (or matching) nonce
            if ('nonce' in window[g] && firstNonce) {
                window[g].nonce = firstNonce;
            }
            // Update action-specific nonce properties e.g. myObj.my_action_nonce
            Object.keys(nonces).forEach(function (action) {
                if (window[g][action + '_nonce'] !== undefined) {
                    window[g][action + '_nonce'] = nonces[action];
                }
            });
        });
    }

    /* ── 3. URL helpers ──────────────────────────────────────────────────── */
    function parseAjaxParams(url) {
        try {
            var u   = new URL(url, window.location.href);
            var act = u.searchParams.get('action') || '';
            var non = u.searchParams.get('nonce')  || '';
            return { action: act, nonce: non, url: u };
        } catch (e) { return null; }
    }

    function isAjaxUrl(url) {
        return typeof url === 'string' && url.indexOf('admin-ajax.php') !== -1;
    }

    function buildUrlWithNonce(url, newNonce) {
        try {
            var u = new URL(url, window.location.href);
            u.searchParams.set('nonce', newNonce);
            return u.toString();
        } catch (e) { return url; }
    }

    /* ── 4. Intercept fetch() ────────────────────────────────────────────── */
    var _originalFetch = window.fetch;
    window.fetch = function (resource, options) {
        var url = (typeof resource === 'string') ? resource : (resource && resource.url) || '';

        if (!isAjaxUrl(url)) return _originalFetch.apply(this, arguments);

        var params = parseAjaxParams(url);

        if (params && params.action && _cache[params.action]) {
            url      = buildUrlWithNonce(url, _cache[params.action]);
            resource = (typeof resource === 'string') ? url : new Request(url, resource);
        }

        return _originalFetch.call(window, resource, options)
            .then(function (response) {
                if (response.status !== 403 || !params || !params.action) return response;

                return fetchFreshNonces([params.action]).then(function (nonces) {
                    var freshNonce = nonces[params.action];
                    if (!freshNonce) return response;
                    return _originalFetch.call(window, buildUrlWithNonce(url, freshNonce), options);
                });
            });
    };

    /* ── 5. Intercept XMLHttpRequest (jQuery $.ajax etc.) ───────────────── */
    var _XHR_open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        this._wsr_url    = url;
        this._wsr_method = method;
        return _XHR_open.apply(this, arguments);
    };

    var _XHR_send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function (body) {
        var xhr    = this;
        var url    = xhr._wsr_url || '';
        var params = isAjaxUrl(url) ? parseAjaxParams(url) : null;

        if (!params) return _XHR_send.apply(this, arguments);

        if (params.action && _cache[params.action]) {
            _XHR_open.call(xhr, xhr._wsr_method || 'GET', buildUrlWithNonce(url, _cache[params.action]), true);
        }

        var _orig_onrsc = xhr.onreadystatechange;
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 403 && params && params.action) {
                fetchFreshNonces([params.action]).then(function (nonces) {
                    var freshNonce = nonces[params.action];
                    if (!freshNonce) {
                        if (_orig_onrsc) _orig_onrsc.call(xhr);
                        return;
                    }
                    var retryXhr = new XMLHttpRequest();
                    retryXhr.onload             = xhr.onload;
                    retryXhr.onerror            = xhr.onerror;
                    retryXhr.onreadystatechange = _orig_onrsc;
                    _XHR_open.call(retryXhr, xhr._wsr_method || 'GET', buildUrlWithNonce(url, freshNonce), true);
                    _XHR_send.call(retryXhr, body);
                });
                return;
            }
            if (_orig_onrsc) _orig_onrsc.call(xhr);
        };

        return _XHR_send.apply(this, arguments);
    };

    /* ── 6. Pre-fetch on DOMContentLoaded if page is from cache ─────────── */
    document.addEventListener('DOMContentLoaded', function () {
        var meta        = document.querySelector('meta[name="x-cache"]');
        var isFromCache = meta && meta.content === 'HIT';

        if (isFromCache && GLOBALS.length > 0) {
            // Collect actions from registered global objects
            var actions = [];
            GLOBALS.forEach(function (g) {
                if (window[g] && window[g].action) actions.push(window[g].action);
            });
            if (actions.length > 0) fetchFreshNonces(actions);
        }
    });

})();
</script>
        <?php
    }

    private static function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                return sanitize_text_field( explode( ',', $_SERVER[ $h ] )[0] );
            }
        }
        return 'unknown';
    }
}

/**
 * WP Static Runtime Premium — Admin JS
 * Handles license activation, deactivation, refresh, CDN purge, ISR revalidation.
 */
(function($) {
    'use strict';

    // ── License Page ──────────────────────────────────────────────────────────
    var $notice = $('#wsr-license-notice');

    function showNotice(msg, type) {
        $notice
            .removeClass('notice-success notice-error notice-warning notice-info')
            .addClass('notice-' + (type || 'info'))
            .html(msg)
            .slideDown();
    }

    function licenseAjax(action, data, btn, btnLabel) {
        var $btn = $(btn);
        var orig = $btn.text();
        $btn.text(btnLabel || 'Memproses...').prop('disabled', true);
        $notice.hide();

        $.post(wsrLicense.ajax_url, $.extend({
            action: 'wsr_license_' + action,
            nonce: wsrLicense.nonce
        }, data), function(res) {
            $btn.text(orig).prop('disabled', false);
            if (res.success) {
                showNotice('✅ ' + res.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotice('❌ ' + (res.message || 'Terjadi kesalahan.'), 'error');
            }
        }).fail(function() {
            $btn.text(orig).prop('disabled', false);
            showNotice('❌ Tidak bisa menghubungi server. Coba lagi.', 'error');
        });
    }

    // Save server URL via AJAX (sederhana — pakai wp.ajax tidak tersedia, pakai admin-post)
    $('#wsr-save-server-url').on('click', function(e) {
        e.preventDefault();
        var url = $('#wsr-license-server-url').val().trim();
        if (!url) { showNotice('❌ Masukkan URL license server terlebih dahulu.', 'error'); return; }
        $.post(wsrLicense.ajax_url, {
            action: 'wsr_save_license_server_url',
            nonce: wsrLicense.nonce,
            server_url: url
        }, function(res) {
            if (res.success) showNotice('✅ Server URL disimpan.', 'success');
            else showNotice('❌ ' + (res.message || 'Gagal menyimpan.'), 'error');
        });
    });

    // Activate
    $('#wsr-activate-btn').on('click', function(e) {
        e.preventDefault();
        var key = $('#wsr-license-key-input').val().trim();
        if (!key) { showNotice('❌ Masukkan license key terlebih dahulu.', 'error'); return; }
        licenseAjax('activate', { license_key: key }, this, wsrLicense.activating);
    });

    // Deactivate
    $('#wsr-deactivate-btn').on('click', function(e) {
        e.preventDefault();
        if (!confirm(wsrLicense.deactivate_confirm)) return;
        licenseAjax('deactivate', {}, this, 'Menonaktifkan...');
    });

    // Refresh / Validate
    $('#wsr-refresh-btn').on('click', function(e) {
        e.preventDefault();
        licenseAjax('refresh', {}, this, 'Memvalidasi...');
    });

    // ── CDN Page ──────────────────────────────────────────────────────────────
    var $cdnResult = $('#wsr-action-result');

    $('#wsr-cdn-purge-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text('Purging...');
        $.post(wsrLicense.ajax_url, { action: 'wsr_cdn_purge_all', nonce: wsrLicense.nonce }, function(res) {
            $btn.prop('disabled', false).text('🌐 Purge All CDN Cache');
            $cdnResult.removeClass('notice-error notice-success')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .html((res.success ? '✅ ' : '❌ ') + (res.message || '')).show();
        });
    });

    $('#wsr-cdn-purge-url-btn').on('click', function(e) {
        e.preventDefault();
        var url = $('#wsr-cdn-purge-url').val().trim();
        if (!url) return;
        $.post(wsrLicense.ajax_url, { action: 'wsr_cdn_purge_url', nonce: wsrLicense.nonce, url: url }, function(res) {
            $cdnResult.removeClass('notice-error notice-success')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .html((res.success ? '✅ ' : '❌ ') + (res.message || '')).show();
        });
    });

    // ── ISR Page ──────────────────────────────────────────────────────────────
    $(document).on('click', '.wsr-isr-revalidate-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var url  = $btn.data('url');
        $btn.prop('disabled', true).text('Revalidating...');
        $.post(wsrLicense.ajax_url, { action: 'wsr_isr_revalidate', nonce: wsrLicense.nonce, url: url }, function(res) {
            $btn.prop('disabled', false).text('Revalidate Now');
            $('#wsr-action-result').removeClass('notice-error notice-success')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .html((res.success ? '✅ ' : '❌ ') + (res.message || '')).show();
        });
    });

    // ── Settings Page: Purge Asset Cache ───────────────────────────────────
    $('#wsr-purge-asset-cache-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $status = $('#wsr-asset-cache-status');
        var origText = $btn.text();

        $btn.prop('disabled', true).text('Purging...');
        $status.hide();

        $.post(wsrLicense.ajax_url, {
            action: 'wsr_purge_asset_cache',
            nonce: wsrLicense.nonce
        }, function(res) {
            $btn.prop('disabled', false).text(origText);
            $status
                .removeClass('notice-error notice-success notice-warning notice-info')
                .addClass(res.success ? 'notice-success' : 'notice-error')
                .html((res.success ? '✅ ' : '❌ ') + (res.message || 'Error'))
                .slideDown();
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
            $status
                .removeClass('notice-error notice-success notice-warning notice-info')
                .addClass('notice-error')
                .html('❌ Failed to connect to server')
                .slideDown();
        });
    });

    // ── Settings Page: Generate Critical CSS ───────────────────────────────
    $('#wsr-generate-critical-css-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $status = $('#wsr-critical-css-status');
        var $textarea = $('textarea[name="opt_critical_css_content"]');
        var origText = $btn.text();

        $btn.prop('disabled', true).text('Generating...');
        $status.hide();

        $.post(wsrLicense.ajax_url, {
            action: 'wsr_generate_critical_css',
            nonce: wsrLicense.nonce,
            url: window.location.href.replace(/[?#].*$/, '')
        }, function(res) {
            $btn.prop('disabled', false).text(origText);

            if (res.success) {
                $textarea.val(res.data.css);
                $status
                    .removeClass('notice-error notice-warning notice-info')
                    .addClass('notice-success')
                    .html('✅ ' + (res.data.message || 'Critical CSS generated'))
                    .slideDown();
            } else {
                $status
                    .removeClass('notice-success notice-warning notice-info')
                    .addClass('notice-error')
                    .html('❌ ' + (res.data.message || 'Failed to generate'))
                    .slideDown();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(origText);
            $status
                .removeClass('notice-success notice-warning notice-info')
                .addClass('notice-error')
                .html('❌ Failed to connect to server')
                .slideDown();
        });
    });

})(jQuery);

/* global jQuery, WSR */
(function ($) {
    'use strict';

    // ── Flush All Cache ──────────────────────────────────────────────────────
    $(document).on('click', '#wsr-flush-btn', function (e) {
        e.preventDefault();
        if ( ! confirm('Flush all static cache?') ) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="wsr-spinner"></span> Flushing...');

        $.post(WSR.ajax_url, { action: 'wsr_flush_all', nonce: WSR.nonce })
        .done(function (res) {
            if (res.success) {
                showNotice(res.data.message || 'Cache flushed.', 'success');
                $('.wsr-cache-table tbody').html(
                    '<tr><td colspan="4" style="text-align:center;padding:24px;">Cache cleared.</td></tr>'
                );
                // Reload after 1.5s to update dashboard stats
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                showNotice((res.data && res.data.message) || 'Error.', 'error');
            }
        })
        .fail(function () { showNotice('Request failed.', 'error'); })
        .always(function () { $btn.prop('disabled', false).html('🗑️ Flush All Cache'); });
    });

    // ── Purge Single URL ─────────────────────────────────────────────────────
    $(document).on('click', '.wsr-purge-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var url  = $btn.data('url');
        $btn.prop('disabled', true).text('Purging...');

        $.post(WSR.ajax_url, { action: 'wsr_purge_url', nonce: WSR.nonce, url: url })
        .done(function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300);
                showNotice('Purged: ' + url, 'success');
            } else {
                showNotice('Error.', 'error');
                $btn.prop('disabled', false).text('Purge');
            }
        })
        .fail(function () {
            showNotice('Request failed.', 'error');
            $btn.prop('disabled', false).text('Purge');
        });
    });

    // ── Crawler ───────────────────────────────────────────────────────────────

    var crawlRunning = false;
    var crawlTotal   = 0;
    var crawlDone    = 0;

    // Start Crawl — works from both dashboard (#wsr-crawl-start-btn) and crawler page
    $(document).on('click', '#wsr-crawl-start-btn', function (e) {
        e.preventDefault();
        if (crawlRunning) return;

        crawlRunning = true;
        crawlTotal   = 0;
        crawlDone    = 0;

        $('#wsr-crawl-start-btn').prop('disabled', true).html('<span class="wsr-spinner"></span> Initializing...');
        $('#wsr-crawl-cancel-btn').show();
        $('#wsr-progress-wrap').show();
        setProgress(0, 0, 'Discovering URLs...');
        showNotice('Starting crawl...', 'info');

        $.post(WSR.ajax_url, { action: 'wsr_crawl_init', nonce: WSR.nonce })
        .done(function (res) {
            if ( ! res.success ) {
                crawlStop('Init failed: ' + ((res.data && res.data.message) || 'Unknown error'));
                return;
            }
            crawlTotal = res.data.total || 0;
            setProgress(0, crawlTotal, 'Caching pages...');
            $('#wsr-crawl-start-btn').html('<span class="wsr-spinner"></span> Crawling...');
            showNotice(res.data.message || 'Crawl started.', 'info');
            runBatch();
        })
        .fail(function (xhr) {
            var detail = '';
            try { detail = JSON.parse(xhr.responseText).message; } catch(x) { detail = xhr.status + ' ' + xhr.statusText; }
            crawlStop('Server error: ' + detail);
        });
    });

    function runBatch() {
        if ( ! crawlRunning ) return;

        $.post(WSR.ajax_url, { action: 'wsr_crawl_batch', nonce: WSR.nonce })
        .done(function (res) {
            if ( ! res.success ) {
                crawlStop('Batch error: ' + ((res.data && res.data.message) || 'Unknown'));
                return;
            }
            var d = res.data;
            var s = d.status || {};

            crawlDone = s.done || crawlDone;

            var pct = crawlTotal > 0 ? Math.round((crawlDone / crawlTotal) * 100) : 0;
            setProgress(pct, crawlTotal, 'Caching... ' + crawlDone + ' / ' + crawlTotal);

            var cached  = s.cached  || 0;
            var skipped = s.skipped || 0;
            var failed  = s.failed  || 0;
            $('#wsr-progress-stats').html(
                '<span style="color:#15803d">✔ ' + cached  + ' cached</span> ' +
                '<span style="color:#0369a1">⏭ ' + skipped + ' skipped</span>' +
                (failed > 0 ? ' <span style="color:#b91c1c">✘ ' + failed + ' failed</span>' : '')
            );

            if (d.done) {
                crawlFinish(s);
            } else {
                // Small delay so browser stays responsive
                setTimeout(runBatch, 200);
            }
        })
        .fail(function (xhr) {
            // Retry once on network glitch
            if ( ! this._retried ) {
                this._retried = true;
                setTimeout(runBatch, 3000);
            } else {
                crawlStop('Network error during batch.');
            }
        });
    }

    function crawlFinish(s) {
        crawlRunning = false;
        setProgress(100, s.total || crawlTotal, 'Complete!');
        var msg = '✅ Done — '
            + (s.cached  || 0) + ' cached, '
            + (s.skipped || 0) + ' skipped'
            + ((s.failed || 0) > 0 ? ', ' + s.failed + ' failed.' : '.');
        showNotice(msg, 'success');
        resetButtons();
        // Reload after 1.5s to update stats
        setTimeout(function () { window.location.reload(); }, 1500);
    }

    function crawlStop(msg) {
        crawlRunning = false;
        showNotice('❌ ' + msg, 'error');
        setProgress(0, crawlTotal, 'Stopped.');
        resetButtons();
    }

    $(document).on('click', '#wsr-crawl-cancel-btn', function (e) {
        e.preventDefault();
        crawlRunning = false;
        $.post(WSR.ajax_url, { action: 'wsr_crawl_cancel', nonce: WSR.nonce });
        showNotice('Crawl cancelled.', 'info');
        resetButtons();
        setProgress(0, 0, 'Cancelled.');
    });

    function resetButtons() {
        $('#wsr-crawl-start-btn').prop('disabled', false).html('🕷️ Start Crawler');
        $('#wsr-crawl-cancel-btn').hide();
    }

    function setProgress(pct, total, label) {
        $('#wsr-progress-label').text(label || '');
        $('#wsr-progress-count').text(total > 0 ? pct + '%' : '');
        $('#wsr-progress-bar').css('width', Math.min(pct, 100) + '%');
    }

    // ── Manual test (Diagnostic page) ────────────────────────────────────────
    $(document).on('click', '#wsr-test-cache-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="wsr-spinner"></span> Testing...');

        $.post(WSR.ajax_url, { action: 'wsr_diagnostic_test', nonce: WSR.nonce })
        .done(function (res) {
            var $out = $('#wsr-test-output');
            if (res.success) {
                var d   = res.data;
                var ok  = d.cached ? '✅' : '❌';
                var html = '<div class="wsr-diag-result">'
                    + '<p><strong>Result:</strong> ' + ok + ' ' + (d.cached ? 'Cache file written successfully!' : 'Cache file NOT written') + '</p>'
                    + '<p><strong>Cache file:</strong><br><code>' + d.cache_file + '</code></p>'
                    + '<p><strong>File exists on disk:</strong> ' + (d.file_exists ? '✅ Yes' : '❌ No') + '</p>'
                    + '<p><strong>WP_CACHE:</strong> ' + d.wp_cache + '</p>'
                    + '<p><strong>advanced-cache.php:</strong> ' + d.advanced_cache + '</p>'
                    + (d.error ? '<p style="color:red"><strong>Error:</strong> ' + d.error + '</p>' : '')
                    + '</div>';
                $out.html(html).show();
                showNotice(d.cached ? '✅ Homepage cached successfully!' : '❌ Cache write failed — see details.', d.cached ? 'success' : 'error');
            } else {
                showNotice('Test failed: ' + ((res.data && res.data.message) || 'Unknown'), 'error');
            }
        })
        .fail(function (xhr) { showNotice('Request failed: ' + xhr.status, 'error'); })
        .always(function () { $btn.prop('disabled', false).html('🧪 Test: Cache Homepage Now'); });
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function showNotice(msg, type) {
        $('#wsr-action-result')
            .removeClass('success error info')
            .addClass(type)
            .text(msg)
            .stop(true, true)
            .fadeIn(200)
            .delay(7000)
            .fadeOut(400);
    }

})(jQuery);

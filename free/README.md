# WP Static Runtime — Free Version

**Version:** 1.0.0  
**Requires WordPress:** 5.8+  
**Requires PHP:** 7.4+

---

## Overview

WP Static Runtime transforms WordPress into a **static HTML server**.  
Instead of PHP + MySQL for every request, it pre-renders pages and serves them as flat files — achieving a **TTFB of 10–40ms**.

```
Normal WordPress:
  Request → PHP → WordPress → Database → Render → Serve

WP Static Runtime:
  Request → advanced-cache.php → Read file → Serve (0 PHP, 0 DB)
```

---

## How It Works

1. **First Visit**: WordPress renders the page normally. The output buffer captures the HTML and saves it to `wp-content/wsr-cache/{scheme}/{host}{uri}/index.html`.
2. **Subsequent Visits**: `advanced-cache.php` intercepts the request *before* WordPress boots, reads the file from disk, and serves it instantly.
3. **On Content Change**: When a post is saved/deleted or a term is edited, the plugin purges the affected cache files and re-queues them for regeneration.

---

## Free Features

| Feature | Description |
|---|---|
| ✅ Static HTML caching | Full page cache to disk |
| ✅ Early router | `advanced-cache.php` hook — zero WordPress overhead on cache hits |
| ✅ Cache reader / writer | Efficient file I/O with optional gzip |
| ✅ HTML minification | Strips whitespace & comments |
| ✅ Cache invalidation | Hooks into save_post, delete_post, edited_terms, switch_theme |
| ✅ Dependency graph | Maps posts → affected pages for smart purging |
| ✅ Static crawler | Visits sitemap URLs to pre-build cache |
| ✅ Elementor integration | Purges on editor save + CSS regeneration |
| ✅ WooCommerce hybrid | Caches shop/product pages; skips cart/checkout |
| ✅ Apache .htaccess rules | Serves static files without PHP on Apache/LiteSpeed |
| ✅ Nginx config snippet | Generated config for zero-PHP Nginx serving |
| ✅ Admin dashboard | Stats, cache manager, crawler control |
| ✅ Admin bar button | One-click flush from the front-end |
| ✅ Security layer | Skips cache for logged-in users, special cookies |

---

## Installation

1. Upload `wp-static-runtime/` to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Plugin automatically:
   - Creates `wp-content/wsr-cache/` directory
   - Writes `wp-content/advanced-cache.php`
   - Adds `define('WP_CACHE', true)` to `wp-config.php`
   - Creates database tables `wp_wsr_cache_index` and `wp_wsr_dependency`

---

## Cache Storage Layout

```
wp-content/wsr-cache/
  https/
    yoursite.com/
      index.html                ← Homepage
      blog/
        index.html              ← Blog archive
        my-post/
          index.html            ← Single post
      shop/
        index.html              ← WooCommerce shop
        product-a/
          index.html            ← Product page
```

---

## Security

The following requests are **never** cached:
- POST requests
- Logged-in users (`wordpress_logged_in` cookie)
- Admin / wp-login / REST API / AJAX
- WooCommerce cart/checkout/my-account
- Any URL matching the exclusion list

---

## Upgrade to Premium

Premium adds:

- ⚡ **Incremental Static Regeneration (ISR)** — zero-downtime background re-renders
- 🧠 **Smart Dependency Graph** — Gutenberg block-level tracking
- 🛒 **WooCommerce Smart Cache** — stock/price-aware invalidation
- 🌍 **CDN Purge** — Cloudflare, BunnyCDN, Fastly
- 🔴 **Redis cache index** — sub-millisecond lookups
- 🕷️ **Edge pre-building** — distributed crawler

Visit **https://statixpress.site/premium** to upgrade.

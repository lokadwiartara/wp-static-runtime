=== StatixPress Static Runtime ===
Contributors: lokadwiartara
Tags: static, cache, speed, performance, seo
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Static HTML caching engine — ISR, Smart Dependency Graph, CDN Purge, Redis, Memcached, LiteSpeed, PageSpeed Optimizer.

== Description ==

🚀 StatixPress Static Runtime — 100% Free & Open Source

**Version:** 1.3.0  
**License:** GPL-2.0-or-later  
**Requires WordPress:** 5.8+  
**Requires PHP:** 7.4+

---

## ⚡ What is WP Static Runtime?

WP Static Runtime transforms your WordPress site into a **blazing-fast static HTML cache engine**. Every page is pre-generated and served as static HTML—eliminating PHP processing overhead.

**Result:** From **850ms TTFB** to **12ms TTFB** (70× faster ⚡)

---

## 🎁 All Features — 100% FREE

### 📄 Static HTML Caching

Convert WordPress pages into optimized static HTML files. On every publish/update:
- WordPress content is auto-rendered to `/wp-content/cache/`
- Static files are served instead of running PHP
- **Zero PHP overhead** — Apache/Nginx serves plain HTML

**Cached Content:**
- ✅ Pages & posts
- ✅ Custom post types
- ✅ Archive pages (category, tag, date)
- ✅ Sitemap (XML, HTML)
- ✅ Home page
- ✅ 404 pages

---

### 🧠 Smart Dependency Graph

Understand which pages need to be purged when content changes:

**Auto-Purge on:**
- Post/page publish or update
- Category/tag changes
- Customizer changes (colors, logos, menus)
- Theme option updates
- WooCommerce product/catalog changes
- Reusable Gutenberg blocks
- Related posts (custom fields)

**Dependency Tracking:**
- Posts → affected pages
- Tags/categories → archive pages
- Menus → all pages using that menu
- Query loops → pages embedding a post type

---

### 🗜️ Asset Optimization

Minify & optimize CSS & JavaScript for lightning-fast load times.

#### **CSS Minification & Optimization**
- Remove comments and whitespace
- Combine multiple stylesheets (reduce HTTP requests)
- Lazy-load non-critical CSS
- **Critical CSS** — inline above-the-fold CSS into `<head>` (see below)

#### **JavaScript Minification**
- Remove comments, whitespace, unused code
- Combine scripts (reduce HTTP requests)
- Defer non-critical scripts
- Async loading for third-party scripts

**Result:** 40–60% asset size reduction

---

### 🎯 Critical CSS Generator

Extract above-the-fold CSS automatically — no headless browser needed.

**How it works:**
1. Heuristic selector analysis (not DOM-based)
2. Prioritizes selectors: `body`, `h1-h3`, `nav`, `header`, layout classes
3. Excludes non-critical: `:hover`, `:active`, `footer`, `sidebar`, animations
4. Inlines critical CSS into `<head>` for instant styling

**Features:**
- **One-click generation** — WordPress Admin → Static Runtime → Advanced Opt
- **Persistent caching** — 1-day TTL (transient cache)
- **Fallback method** — If self-request times out, reads CSS from registered stylesheets on disk
- **Zero external dependencies** — no Puppeteer, no API calls

**Result:** Eliminates render-blocking CSS, improves LCP (Largest Contentful Paint)

---

### 🔴 Redis Cache Index (Optional)

Replace MySQL-based cache index with Redis for sub-millisecond lookups.

**Benefits:**
- Near-zero latency cache metadata queries
- Scales to millions of cached URLs
- Reduces database load
- Automatic fallback to MySQL if Redis unavailable

**Requires:** `php-redis` extension  
**Optional:** Not required if not installed

---

### 💾 Memcached Integration (Optional)

Use Memcached as a persistent cache backend.

**Benefits:**
- Distributed cache across multiple servers
- Reduces disk I/O
- Automatic expiration (TTL)
- Falls back to file cache if unavailable

**Requires:** `php-memcached` extension  
**Optional:** Works alongside file cache

---

### 🌍 ISR — Incremental Static Regeneration

Instead of deleting cache on update, ISR:

1. **Serves stale cache immediately** — no downtime
2. **Queues URL for re-render** — background job
3. **Atomically replaces file** — once new version ready

**Benefits:**
- Zero downtime on content updates
- Users always see a page (no blank screens)
- Reduced server load (async regeneration)

**Configuration:**
- `isr_revalidate` — seconds before page is considered stale
- `isr_queue_size` — URLs processed per cron batch

---

### 🌐 CDN Purge Integration

Automatically purge CDN cache when content changes.

**Supported Providers:**
- 🔵 **Cloudflare** — Purge URLs & full purge
- 🟡 **BunnyCDN** — Purge URLs
- ⚡ **Fastly** — Purge URLs

**Setup:**
- Add API key in WordPress Admin → Static Runtime → CDN
- On content update, WP Static Runtime automatically purges CDN cache
- Keep origin + CDN perfectly in sync

---

### 📈 Performance Optimizer — PageSpeed Integration

Built-in HTML optimizer to improve Core Web Vitals:

**Optimization Techniques:**
- Remove inline `<style>` tags (extract to `<head>`)
- Defer non-critical CSS
- Async third-party scripts
- Optimize image attributes
- Remove unused CSS rules
- Minify HTML output

**Result:** 95–100 PageSpeed Insights score

---

### 📊 Advanced Caching

**LiteSpeed Cache Integration** — Automatically purge LiteSpeed cache on updates

**Supported Cache Layers:**
1. **Browser cache** — Serve cached HTML from edge (3600s TTL)
2. **CDN cache** — Global edge servers (24h TTL)
3. **Redis index** — Cache metadata lookups (persistent)
4. **Memcached** — Persistent object cache (optional)
5. **File cache** — `/wp-content/cache/` (primary)

---

### 📋 Cache Management Dashboard

**WordPress Admin → Static Runtime:**

- **Dashboard** — At-a-glance cache stats
  - Total cached URLs
  - Cache size (disk usage)
  - Last cache update

- **Cache Pages** — Manage individual cached pages
  - List all cached URLs
  - View cache status
  - Manually purge specific pages
  - Batch operations

- **Settings**
  - Enable/disable caching
  - Cache TTL (1–365 days)
  - Exclude URLs (regex patterns)
  - Auto-purge on post/page update

- **Advanced Opt**
  - CSS/JS minification
  - Critical CSS generation
  - Asset optimization
  - Cache compression
  - Purge all cache

- **Diagnostic**
  - Cache hit/miss ratio
  - Performance metrics
  - Server resource usage
  - Error logs

---

## 🚀 Installation

### Step 1: Download
Download `wp-static-runtime.zip` from [releases](https://github.com/lokadwiartara/wp-static-runtime/releases)

### Step 2: Upload
```
/wp-content/plugins/wp-static-runtime/
```

### Step 3: Activate
WordPress Admin → Plugins → Activate **WP Static Runtime**

### Step 4: Configure
WordPress Admin → Static Runtime → Settings
- Enable static caching
- Adjust cache TTL
- Configure asset optimization

---

## ⚙️ Configuration

### wp-config.php Overrides (Optional)

```php
// Cache settings
define('WSR_CACHE_TTL', 86400);          // 1 day in seconds
define('WSR_CACHE_GZIP', true);          // Compress cached files
define('WSR_AUTO_PURGE', true);          // Auto-purge on post update

// ISR (Incremental Static Regeneration)
define('WSR_ISR_ENABLED', true);
define('WSR_ISR_QUEUE_SIZE', 10);        // URLs per cron batch
define('WSR_ISR_REVALIDATE', 3600);      // Stale page TTL (seconds)

// Redis (optional)
define('WSR_REDIS_HOST', '127.0.0.1');
define('WSR_REDIS_PORT', 6379);
define('WSR_REDIS_PASSWORD', '');
define('WSR_REDIS_DATABASE', 0);

// Memcached (optional)
define('WSR_MEMCACHED_HOST', '127.0.0.1');
define('WSR_MEMCACHED_PORT', 11211);

// CDN Purge
define('WSR_CDN_PROVIDER', 'cloudflare');  // cloudflare | bunny | fastly
define('WSR_CDN_API_KEY', 'your-key');

// LiteSpeed
define('WSR_LITESPEED_ENABLED', true);
```

---

## 📊 Performance Benchmarks

| Scenario | TTFB |
|---|---|
| WordPress (no cache) | 200–800ms |
| WP Static Runtime (file cache) | 10–40ms |
| + Apache .htaccess | 5–15ms |
| + Nginx X-Accel | 3–10ms |
| + CDN edge cache | < 5ms globally |

**Real-world examples:**
- Shared hosting (2 vCPU, 2GB RAM): 850ms → 25ms (34× faster)
- VPS (4 vCPU, 8GB RAM): 600ms → 15ms (40× faster)
- With Redis + CDN: < 5ms TTFB globally

---

## 🔌 Developer API

### Hooks & Filters

#### Actions

```php
// Fired after a page is cached
add_action('wsr_page_cached', function(string $url, int $status_code) {
    error_log("Cached: $url ($status_code)");
});

// Fired after cache is purged
add_action('wsr_cache_purged', function(string $url) {
    error_log("Purged: $url");
});

// Fired after ISR revalidates a URL
add_action('wsr_isr_url_revalidated', function(string $url) {
    // Custom logging, notifications, etc.
});

// Fired after CDN purge
add_action('wsr_cdn_url_purged', function(string $url, array $results) {
    // Handle CDN purge result
});
```

#### Filters

```php
// Exclude URLs from caching (regex patterns)
add_filter('wsr_exclude_urls', function(array $excludes): array {
    $excludes[] = '/admin';
    $excludes[] = '/cart';
    return $excludes;
});

// Override cache TTL per URL
add_filter('wsr_cache_ttl', function(int $ttl, string $url): int {
    if (strpos($url, '/news/') === 0) {
        return 300; // 5 minutes for news articles
    }
    return $ttl;
}, 10, 2);

// Customize dependency graph
add_filter('wsr_dependency_graph', function(array $graph, string $url): array {
    // Add custom dependencies
    return $graph;
}, 10, 2);

// Override critical CSS generation
add_filter('wsr_critical_css_content', function(string $css): string {
    // Custom CSS modifications
    return $css;
});
```

---

## 🔒 Security

- **CSRF Protected** — All AJAX actions use WordPress nonces
- **Capability Checks** — Admin actions require `manage_options`
- **Input Sanitization** — All user input sanitized/escaped
- **SQL Injection Safe** — Prepared statements throughout
- **XSS Safe** — All output escaped with `esc_*` functions

---

## 📝 Changelog

### v1.3.0 (May 2026)
- **NEW:** Critical CSS Generator with fallback stylesheet extraction
- **FIX:** Reduce critical CSS AJAX timeout from 15s to 8s
- **IMPROVED:** Persistent transient caching for critical CSS (1-day TTL)
- **FEATURE:** Configurable download URL via WordPress Admin
- **ALL FEATURES:** Now 100% free and open source

### v1.2.6
- CDN purge integration (Cloudflare, BunnyCDN, Fastly)
- Redis cache index support
- Memcached integration
- LiteSpeed server integration
- HTML optimizer improvements

### v1.0.0
- Initial release: Static HTML caching
- Smart dependency graph
- Asset minification (CSS/JS)
- ISR (Incremental Static Regeneration)

---

## 🤝 Contributing

We welcome contributions! This is 100% open source.

**How to contribute:**
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Submit a pull request

---

## 📖 Documentation

Full documentation available at: [https://statixpress.site/docs](https://statixpress.site/docs)

---

## 🎯 Support

- **Issues:** [GitHub Issues](https://github.com/lokadwiartara/wp-static-runtime/issues)
- **Discussion:** [GitHub Discussions](https://github.com/lokadwiartara/wp-static-runtime/discussions)
- **Email:** [support@statixpress.site](mailto:support@statixpress.site)

---

## 📄 License

GPL-2.0-or-later. See `LICENSE` file.

---

**Made with ❤️ for the WordPress community**  
**100% Free • 100% Open Source • Zero Paywalls**

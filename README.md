# WP Static Runtime — Premium

**Version:** 1.0.0  
**Requires:** WP Static Runtime (free) 1.0.0+  
**Requires WordPress:** 5.8+  
**Requires PHP:** 7.4+

---

## Premium Features

### ⚡ Incremental Static Regeneration (ISR)

Instead of deleting the cached page on update, ISR:
1. **Keeps the stale page serving** — no downtime, no blank pages
2. **Queues the URL** for background re-render
3. **Atomically swaps** the file once the new version is ready (`rename()` — POSIX atomic)

Configure in **Premium → ISR**:
- `isr_revalidate` — seconds before a page is considered stale (0 = event-driven only)
- `isr_queue_size` — URLs processed per cron cycle

### 🧠 Smart Dependency Graph

The free version maps posts → affected pages using standard WordPress data.  
The Smart Dependency Graph adds:

- **Gutenberg Reusable Blocks** — if Block #42 is saved, every page embedding it is purged
- **Query Loop blocks** — tracks which post types feed a query loop
- **WooCommerce product relations** — upsells, cross-sells, related products
- **Level-2 traversal** — pages that depend on pages that depend on the changed post
- **Custom field "related_posts"** support

### 🌍 CDN Purge

Automatically purges CDN cache on WordPress content changes.

| Provider | Purge URL | Purge All | Batch |
|---|---|---|---|
| **Cloudflare** | ✅ | ✅ | 30 URLs/req |
| **BunnyCDN** | ✅ | ✅ | per URL |
| **Fastly** | ✅ | ✅ | per URL |

Configure API keys in **Premium → CDN Settings**.

### 🔴 Redis Cache Index

Replaces the MySQL-based cache index with Redis for near-zero latency lookups:

- Cache metadata stored as Redis hashes
- URL index as a Redis sorted set (score = timestamp)
- ISR queue as a Redis list (FIFO, deduplication)
- Automatic fallback to MySQL if Redis is unavailable

Requires `php-redis` extension.

---

## Installation

1. Install and activate **WP Static Runtime (free)** first
2. Upload `wp-static-runtime-premium/` to `/wp-content/plugins/`
3. Activate — Premium modules load automatically
4. Navigate to **Static Runtime → Premium** to configure

---

## Configuration Reference

### ISR

```php
// wp-config.php overrides (optional)
define('WSR_ISR_QUEUE_SIZE', 10);    // URLs per cron batch
define('WSR_ISR_REVALIDATE', 3600);  // TTL in seconds
```

### Redis

```php
define('WSR_REDIS_HOST',     '127.0.0.1');
define('WSR_REDIS_PORT',     6379);
define('WSR_REDIS_PASSWORD', '');
define('WSR_REDIS_DATABASE', 0);
```

### CDN — Cloudflare

```php
define('WSR_CF_EMAIL',   'you@example.com');
define('WSR_CF_API_KEY', 'your-global-api-key');
define('WSR_CF_ZONE',    'your-zone-id');
```

---

## Hooks & Filters

```php
// Fired after ISR revalidates a URL
add_action('wsr_isr_url_revalidated', function(string $url) { ... });

// Fired after a CDN purge
add_action('wsr_cdn_url_purged', function(string $url, array $results) { ... });

// Fired after full CDN purge
add_action('wsr_cdn_all_purged', function(array $results) { ... });

// Override ISR queue enqueue (e.g. use your own queue)
add_filter('wsr_isr_enqueue', function(string $url): bool {
    // Return false to skip default queue
    return false;
}, 10, 1);
```

---

## Performance Benchmarks

| Setup | TTFB |
|---|---|
| WordPress (no cache) | 200–800ms |
| WP Static Runtime (PHP reads file) | 10–40ms |
| WP Static Runtime + Apache .htaccess | 5–15ms |
| WP Static Runtime + Nginx static | 3–10ms |
| CDN edge cache hit | < 5ms globally |

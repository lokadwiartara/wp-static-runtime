# ⚡ WP Static Runtime

**Static HTML caching engine for WordPress.** Serve pages at CDN speed from any shared host — zero PHP execution on cached pages.

[![Version](https://img.shields.io/badge/version-1.2.5-7c3aed?style=flat-square)](https://github.com/lokadwiartara/wp-static-runtime/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Cara Kerja / How It Works

```
❌ WordPress Biasa (setiap request):
   Request → PHP → WordPress → Database → Render → Response
   TTFB: 300ms – 2000ms

✅ WP Static Runtime (setelah cache terbangun):
   Request → advanced-cache.php → Baca file HTML → Response
   TTFB: 5ms – 40ms  (zero PHP, zero database)
```

Saat pertama kali halaman dikunjungi, WordPress merender halaman secara normal. Output buffer menangkap HTML dan menyimpannya ke disk. Kunjungan berikutnya dilayani langsung dari file — **sebelum WordPress boot sepenuhnya**.

Ketika konten diupdate, plugin menghapus cache halaman terkait secara otomatis berdasarkan **dependency graph**.

---

## Fitur Gratis / Free Features

| Fitur | Keterangan |
|---|---|
| ✅ Static HTML caching | Full page cache ke disk |
| ✅ Early router | `advanced-cache.php` hook — zero WordPress overhead saat cache hit |
| ✅ HTML minification | Strip whitespace & komentar |
| ✅ Cache invalidation otomatis | Hook ke `save_post`, `delete_post`, `edited_terms`, `switch_theme` |
| ✅ Dependency graph | Peta post → halaman terdampak untuk purging yang presisi |
| ✅ Sitemap crawler | Kunjungi semua URL dari sitemap untuk pre-build cache |
| ✅ WooCommerce hybrid | Cache shop & product; skip cart, checkout, my-account |
| ✅ Apache .htaccess rules | Sajikan file statis tanpa PHP di Apache/LiteSpeed |
| ✅ Nginx config snippet | Konfigurasi Nginx zero-PHP otomatis ter-generate |
| ✅ Admin dashboard | Statistik, cache manager, kontrol crawler |
| ✅ Admin bar button | Flush cache satu klik dari front-end |
| ✅ Elementor integration | Purge otomatis saat save di Elementor editor |
| ✅ Diagnostic page | Cek engine status, WP_CACHE, advanced-cache.php |
| ✅ Security layer | Skip cache untuk logged-in user, REST API, AJAX |

---

## ⭐ Premium Features

| Fitur | Keterangan |
|---|---|
| ⚡ **Incremental Static Regeneration (ISR)** | Halaman stale tetap tampil sementara versi baru dibangun di background — zero downtime |
| 🧠 **Smart Dependency Graph** | Pelacakan Gutenberg block, relasi WooCommerce, traversal L2 |
| 🌍 **CDN Cache Purge** | Purge otomatis ke Cloudflare, BunnyCDN, dan Fastly saat konten update |
| 🔴 **Redis Cache Index** | Ganti MySQL index dengan Redis untuk lookup sub-milidetik |
| 🕷️ **Auto-Crawler** | Pre-build cache dari sitemap setiap jam via cron |
| 🔑 **License System** | Domain-locked AES-256-CBC token, server permit dengan TTL 15 menit |
| 🔄 **Auto-Updater** | Update otomatis via GitHub Releases langsung dari dashboard WordPress |

👉 **[Dapatkan Premium di statixpress.site](https://statixpress.site/premium)**

---

## Perbandingan / Comparison

|  | Free | Premium |
|--|:--:|:--:|
| Static HTML caching | ✅ | ✅ |
| Apache & Nginx rules | ✅ | ✅ |
| Sitemap crawler | ✅ | ✅ |
| Dependency graph (basic) | ✅ | ✅ |
| WooCommerce hybrid cache | ✅ | ✅ |
| Diagnostic & engine status | ✅ | ✅ |
| Auto-crawler (hourly cron) | ❌ | ✅ |
| Incremental Static Regeneration | ❌ | ✅ |
| Smart Dependency Graph (L2, Gutenberg) | ❌ | ✅ |
| CDN Purge (Cloudflare, BunnyCDN, Fastly) | ❌ | ✅ |
| Redis Cache Index | ❌ | ✅ |
| Auto-updater via GitHub Releases | ❌ | ✅ |
| Priority support | ❌ | ✅ |

---

## Instalasi / Installation

### Nama file unduhan browser / Browser download names

Chrome dan browser lain menambahkan sufiks seperti `(1)`, `(5)` pada zip berulang. WordPress menyederhanakan nama itu sehingga folder plugin bisa menjadi **`wp-static-runtime5`** alih-alih **`wp-static-runtime`** — itu perilaku normal WP dari **nama file zip**, bukan isi plugin.

**Sebelum upload:** ubah nama file menjadi **`wp-static-runtime.zip`** (tanpa angka atau kurung).  
**Sudah terpasang salah:** dari SSH/FTP, di `wp-content/plugins/` jalankan  
`mv wp-static-runtime5 wp-static-runtime`  
(lihat juga **`INSTALL.txt`** di dalam paket plugin.)

### Via WordPress Dashboard
1. Download **`wp-static-runtime.zip`** dari [Releases](../../releases/latest) (aset bernama persis itu — **bukan** “Source code” dari tab Code).
2. **Rename** file jika browser menambahkan `(1)` / `(5)` pada nama — harus **`wp-static-runtime.zip`** sebelum upload.
3. Masuk ke **WordPress Admin → Plugins → Add New → Upload Plugin**
4. Upload zip → klik **Install Now** → **Activate**

### Via FTP / File Manager
1. Download dan ekstrak `wp-static-runtime.zip`
2. Upload folder **`wp-static-runtime/`** ke `wp-content/plugins/` sehingga file utama ada di  
   `wp-content/plugins/wp-static-runtime/wp-static-runtime.php`  
   (**satu** folder di bawah `plugins/`, bukan bersarang dua tingkat.)
3. Aktifkan via **Plugins → Installed Plugins**

### Error: « Plugin file does not exist » / File plugin tidak ada

WordPress hanya mendukung bentuk path **`plugins/nama-folder/file-utama.php`** (tepat **satu** subfolder).  
Jika Anda melihat URL aktivasi seperti `.../wp-static-runtime-1.2.x/wp-static-runtime/wp-static-runtime.php` (dua folder bertingkat), berarti struktur di server salah.

**Perbaikan:**
1. Di `wp-content/plugins/`, pastikan isinya seperti ini (contoh):  
   `wp-static-runtime/wp-static-runtime.php` + folder `free/`, `premium-ui/`, dll.  
   **Salah:** `plugins/wp-static-runtime-1.2.x/wp-static-runtime/wp-static-runtime.php`  
   **Benar:** `plugins/wp-static-runtime/wp-static-runtime.php`
2. Hapus folder plugin yang bentuknya bersarang / duplikat, lalu pasang ulang dengan **`wp-static-runtime.zip`** dari halaman Releases (bukan zip “Source code” GitHub).
3. Untuk build dari source lokal, jalankan `tools/build-release.ps1` — zip keluar di `dist/wp-static-runtime.zip` dengan struktur yang sudah divalidasi.

### Yang terjadi saat aktivasi:
- ✅ Membuat direktori `wp-content/wsr-cache/`
- ✅ Menulis `wp-content/advanced-cache.php`
- ✅ Menambahkan `define('WP_CACHE', true)` ke `wp-config.php`
- ✅ Membuat tabel database `wp_wsr_cache_index` dan `wp_wsr_dependency`

---

## Struktur Cache / Cache Storage Layout

```
wp-content/wsr-cache/
  https/
    yoursite.com/
      index.html              ← Homepage
      blog/
        index.html            ← Blog archive
        my-post/
          index.html          ← Single post
      shop/
        index.html            ← WooCommerce shop
        product-name/
          index.html          ← Product page
```

---

## Keamanan / Security

Request berikut **tidak pernah** di-cache:
- POST requests
- Pengguna yang sedang login (`wordpress_logged_in` cookie)
- Admin, wp-login, REST API, AJAX
- WooCommerce: cart, checkout, my-account
- URL yang ada di daftar exclusion

---

## Requirements

| | Minimum |
|--|--|
| WordPress | 5.8 |
| PHP | 7.4 |
| Web Server | Apache, Nginx, atau LiteSpeed |
| MySQL | 5.6+ |

---

## Changelog

### v1.2.5
- **`INSTALL.txt`:** Petunjuk rename zip / rename folder (`wp-static-runtime5` → `wp-static-runtime`) ketika browser mengubah nama unduhan.
- **README:** Penjelasan bahwa sufiks `(5)` pada nama file zip membuat WordPress membuat folder `wp-static-runtime5`.

### v1.2.4
- **Dokumentasi & rilis zip:** Penjelasan error *Plugin file does not exist* akibat folder plugin bersarang (`.../versi/.../wp-static-runtime.php`). WordPress membutuhkan tepat satu folder plugin di bawah `plugins/`.
- **Skrip `tools/build-release.ps1`:** Membangun `dist/wp-static-runtime.zip` dengan satu root folder `wp-static-runtime/` (struktur dicek otomatis).

### v1.2.3
- **Perbaikan plugin tidak ter-load / aktivasi:** `WSR_FILE` kini selalu diset dari file utama plugin (`__FILE__`) sehingga `register_activation_hook`, `plugin_basename`, dan URL aset selaras dengan path yang WordPress gunakan (aman untuk folder hasil zip GitHub, symlink, dan normalisasi path).
- **Uninstall:** `uninstall.php` di root plugin (persyaratan WordPress) meneruskan ke `free/uninstall.php`; urutan definisi kelas diperbaiki; penghapusan baris `WP_CACHE` di `wp-config.php` diselaraskan dengan komentar yang ditulis installer (`Added by WP Static Runtime`).

### v1.2.2
- Tambah `wsr_crawler_sslverify` filter untuk self-signed cert support
- Perbaikan SQL query di `flush_all()` menggunakan `wpdb::update()`
- Perbaikan konten: semua URL diperbarui ke `statixpress.site`

### v1.2.0
- Rilis perdana versi free + premium UI shell
- Static caching, crawler, dependency graph, WooCommerce hybrid
- Admin dashboard dengan stat cards dan diagnostic page

---

## Support

- 🌐 Website: [statixpress.site](https://statixpress.site)
- ⭐ Premium: [statixpress.site/premium](https://statixpress.site/premium)
- 🐛 Bug report: [GitHub Issues](../../issues)

---

## License

Free version dilisensikan di bawah **GPL-2.0-or-later**.  
Premium version menggunakan lisensi proprietary — satu license key per domain.

---

<p align="center">
  Dibuat dengan ❤️ oleh <a href="https://statixpress.site">StatixPress</a>
</p>

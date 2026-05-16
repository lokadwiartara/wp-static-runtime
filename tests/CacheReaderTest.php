<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for WSR\Cache_Reader — path resolution.
 */
class CacheReaderTest extends TestCase {

    private string $cache_dir;

    protected function setUp(): void {
        $this->cache_dir = WSR_CACHE_DIR;
        // Ensure clean state for $_SERVER
        unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT'] );
    }

    // ── resolve_path_from_url ─────────────────────────────────────────────────

    public function test_resolve_path_from_url_standard(): void {
        $url      = 'https://example.com/blog/post-1/';
        $expected = $this->cache_dir . 'https/example.com/blog/post-1/index.html';
        $this->assertSame( $expected, \WSR\Cache_Reader::resolve_path_from_url( $url ) );
    }

    public function test_resolve_path_from_url_root(): void {
        $url      = 'https://example.com/';
        // parse_url path = '/', trailingslashit('/') = '/' → no double slash
        $expected = $this->cache_dir . 'https/example.com/index.html';
        $this->assertSame( $expected, \WSR\Cache_Reader::resolve_path_from_url( $url ) );
    }

    public function test_resolve_path_from_url_non_default_port(): void {
        $url      = 'http://localhost:8080/my-page/';
        $expected = $this->cache_dir . 'http/localhost_port_8080/my-page/index.html';
        $this->assertSame( $expected, \WSR\Cache_Reader::resolve_path_from_url( $url ) );
    }

    public function test_resolve_path_from_url_strips_default_port(): void {
        $url      = 'http://example.com:80/page/';
        $expected = $this->cache_dir . 'http/example.com/page/index.html';
        $this->assertSame( $expected, \WSR\Cache_Reader::resolve_path_from_url( $url ) );
    }

    public function test_resolve_path_from_url_https_non_default_port(): void {
        $url      = 'https://example.com:8443/secure/';
        $expected = $this->cache_dir . 'https/example.com_port_8443/secure/index.html';
        $this->assertSame( $expected, \WSR\Cache_Reader::resolve_path_from_url( $url ) );
    }

    // ── resolve_path (uses $_SERVER) ──────────────────────────────────────────

    public function test_resolve_path_empty_uri_returns_false(): void {
        $this->assertFalse( \WSR\Cache_Reader::resolve_path( '' ) );
    }

    public function test_resolve_path_uri_strips_query_string(): void {
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset( $_SERVER['HTTPS'] );

        $path = \WSR\Cache_Reader::resolve_path( '/post/?foo=bar' );
        $this->assertStringEndsWith( 'example.com/post/index.html', $path );
        $this->assertStringNotContainsString( 'foo=bar', $path );

        unset( $_SERVER['HTTP_HOST'] );
    }

    public function test_resolve_path_adds_trailing_slash(): void {
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset( $_SERVER['HTTPS'] );

        $path = \WSR\Cache_Reader::resolve_path( '/my-post' );
        $this->assertStringEndsWith( 'example.com/my-post/index.html', $path );

        unset( $_SERVER['HTTP_HOST'] );
    }

    // ── exists ────────────────────────────────────────────────────────────────

    public function test_exists_returns_false_when_no_file(): void {
        $_SERVER['HTTP_HOST'] = 'nonexistent-host-12345.test';
        unset( $_SERVER['HTTPS'] );

        $result = \WSR\Cache_Reader::exists( '/no-such-page/' );
        $this->assertFalse( $result );

        unset( $_SERVER['HTTP_HOST'] );
    }

    public function test_exists_returns_true_when_file_present(): void {
        $_SERVER['HTTP_HOST'] = 'testhost.local';
        unset( $_SERVER['HTTPS'] );

        $dir  = WSR_CACHE_DIR . 'http/testhost.local/exists-test/';
        $file = $dir . 'index.html';
        wp_mkdir_p( $dir );
        file_put_contents( $file, '<html>cached</html>' );

        $result = \WSR\Cache_Reader::exists( '/exists-test/' );
        $this->assertTrue( $result );

        // Cleanup
        unlink( $file );
        rmdir( $dir );
        unset( $_SERVER['HTTP_HOST'] );
    }
}

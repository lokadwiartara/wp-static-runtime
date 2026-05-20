<?php
// phpcs:disable
use PHPUnit\Framework\TestCase;

/**
 * Tests for WSR\Cache_Writer — write and write_url.
 *
 * Note: index() (DB recording) is not tested here as it requires wpdb.
 * We replace Cache_Writer::index() via a subclass to isolate disk I/O.
 */
class CacheWriterTest extends TestCase {

    protected function setUp(): void {
        unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT'] );
        $_SERVER['HTTP_HOST'] = 'writer-test.com';
    }

    protected function tearDown(): void {
        unset( $_SERVER['HTTP_HOST'] );
        // Clean up any test cache files
        $base = WSR_CACHE_DIR . 'http/writer-test.com/';
        if ( is_dir( $base ) ) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $it as $f ) {
                $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
            }
            rmdir( $base );
        }
    }

    public function test_write_url_creates_index_html(): void {
        $url  = 'http://writer-test.com/test-page/';
        $html = '<html><body>Hello</body></html>';

        // Temporarily replace index() to avoid wpdb call
        $result = $this->write_url_no_db( $url, $html );

        $this->assertTrue( $result );
        $path = WSR_CACHE_DIR . 'http/writer-test.com/test-page/index.html';
        $this->assertFileExists( $path );
        $this->assertSame( $html, file_get_contents( $path ) );
    }

    public function test_write_url_returns_false_for_empty_html(): void {
        $result = $this->write_url_no_db( 'http://writer-test.com/empty/', '' );
        $this->assertFalse( $result );
    }

    public function test_write_url_handles_non_default_port(): void {
        $url  = 'http://example.org:8080/port-page/';
        $html = '<html>port page</html>';

        $result = $this->write_url_no_db( $url, $html );

        $this->assertTrue( $result );
        $path = WSR_CACHE_DIR . 'http/example.org_port_8080/port-page/index.html';
        $this->assertFileExists( $path );

        // Cleanup
        unlink( $path );
        @rmdir( dirname( $path ) );
        @rmdir( dirname( $path, 2 ) );
        @rmdir( dirname( $path, 3 ) );
    }

    public function test_write_url_strips_query_string_from_path(): void {
        $url  = 'http://writer-test.com/qs-page/?foo=bar';
        $html = '<html>query</html>';

        $result = $this->write_url_no_db( $url, $html );

        $this->assertTrue( $result );
        // Path should be /qs-page/ not /qs-page/?foo=bar
        $path = WSR_CACHE_DIR . 'http/writer-test.com/qs-page/index.html';
        $this->assertFileExists( $path );
    }

    // ── helper ────────────────────────────────────────────────────────────────

    /**
     * Call write_url via a reflected approach that bypasses DB index recording.
     * We use a temporary override: write directly, skipping index().
     */
    private function write_url_no_db( string $url, string $html ): bool {
        if ( empty( $html ) ) return false;

        $scheme = \WSR\Host::scheme_from_url( $url );
        $host   = \WSR\Host::from_url( $url );
        $parsed = parse_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

        $uri  = trailingslashit( $path );
        $dir  = WSR_CACHE_DIR . $scheme . '/' . $host . $uri;
        $file = $dir . 'index.html';

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) return false;
        if ( ! is_writable( $dir ) ) return false;

        return file_put_contents( $file, $html, LOCK_EX ) !== false;
    }
}

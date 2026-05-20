<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for WSR\Host — scheme and host resolution.
 */
class HostTest extends TestCase {

    // ── from_url ──────────────────────────────────────────────────────────────

    public function test_from_url_plain_domain(): void {
        $this->assertSame( 'example.com', \WSR\Host::from_url( 'https://example.com/' ) );
    }

    public function test_from_url_strips_default_https_port(): void {
        $this->assertSame( 'example.com', \WSR\Host::from_url( 'https://example.com:443/page/' ) );
    }

    public function test_from_url_strips_default_http_port(): void {
        $this->assertSame( 'example.com', \WSR\Host::from_url( 'http://example.com:80/page/' ) );
    }

    public function test_from_url_non_default_port_uses_underscore_separator(): void {
        $this->assertSame( 'example.org_port_8080', \WSR\Host::from_url( 'http://example.org:8080/' ) );
    }

    public function test_from_url_non_default_https_port(): void {
        $this->assertSame( 'example.org_port_8443', \WSR\Host::from_url( 'https://example.org:8443/' ) );
    }

    public function test_from_url_no_port_component(): void {
        $this->assertSame( 'mysite.com', \WSR\Host::from_url( 'http://mysite.com/blog/' ) );
    }

    // ── scheme_from_url ───────────────────────────────────────────────────────

    public function test_scheme_from_url_https(): void {
        $this->assertSame( 'https', \WSR\Host::scheme_from_url( 'https://example.com/' ) );
    }

    public function test_scheme_from_url_http(): void {
        $this->assertSame( 'http', \WSR\Host::scheme_from_url( 'http://example.com/' ) );
    }

    public function test_scheme_from_url_missing_defaults_to_http(): void {
        $this->assertSame( 'http', \WSR\Host::scheme_from_url( '//example.com/' ) );
    }

    // ── current_scheme (uses $_SERVER) ────────────────────────────────────────

    public function test_current_scheme_http_by_default(): void {
        unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT'] );
        $this->assertSame( 'http', \WSR\Host::current_scheme() );
    }

    public function test_current_scheme_https_via_server_var(): void {
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame( 'https', \WSR\Host::current_scheme() );
        unset( $_SERVER['HTTPS'] );
    }

    public function test_current_scheme_https_via_forwarded_proto(): void {
        unset( $_SERVER['HTTPS'] );
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertSame( 'https', \WSR\Host::current_scheme() );
        unset( $_SERVER['HTTP_X_FORWARDED_PROTO'] );
    }

    public function test_current_scheme_https_via_port_443(): void {
        unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );
        $_SERVER['SERVER_PORT'] = '443';
        $this->assertSame( 'https', \WSR\Host::current_scheme() );
        unset( $_SERVER['SERVER_PORT'] );
    }

    // ── current (uses HTTP_HOST) ──────────────────────────────────────────────

    public function test_current_plain_host(): void {
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset( $_SERVER['HTTPS'] );
        $this->assertSame( 'example.com', \WSR\Host::current() );
        unset( $_SERVER['HTTP_HOST'] );
    }

    public function test_current_strips_default_port_80(): void {
        $_SERVER['HTTP_HOST'] = 'example.com:80';
        unset( $_SERVER['HTTPS'] );
        $this->assertSame( 'example.com', \WSR\Host::current() );
        unset( $_SERVER['HTTP_HOST'] );
    }

    public function test_current_non_default_port_uses_underscore_separator(): void {
        $_SERVER['HTTP_HOST'] = 'example.org:8080';
        unset( $_SERVER['HTTPS'] );
        $this->assertSame( 'example.org_port_8080', \WSR\Host::current() );
        unset( $_SERVER['HTTP_HOST'] );
    }
}

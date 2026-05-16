<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for WSR\Request — URI normalization and is_cacheable().
 *
 * Request is a singleton; we reset it between tests via reflection.
 */
class RequestTest extends TestCase {

    protected function setUp(): void {
        $this->reset_singleton();
        // Clear relevant $_SERVER and superglobals
        unset(
            $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'],
            $_SERVER['SERVER_PORT'], $_SERVER['QUERY_STRING'],
            $_SERVER['REQUEST_METHOD']
        );
        $_SERVER['HTTP_HOST']  = 'test.local';
        $_SERVER['REQUEST_URI'] = '/';
        $_COOKIE = [];
        $_GET    = [];
    }

    protected function tearDown(): void {
        $this->reset_singleton();
        unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
        $_COOKIE = [];
        $_GET    = [];
    }

    // ── normalize ─────────────────────────────────────────────────────────────

    public function test_normalize_strips_query_string(): void {
        $request = $this->get_request_instance();
        $method  = $this->get_normalize_method();
        $this->assertSame( '/blog/post/', $method->invoke( $request, '/blog/post?foo=bar' ) );
    }

    public function test_normalize_adds_trailing_slash_to_path(): void {
        $request = $this->get_request_instance();
        $method  = $this->get_normalize_method();
        $this->assertSame( '/blog/post/', $method->invoke( $request, '/blog/post' ) );
    }

    public function test_normalize_preserves_trailing_slash(): void {
        $request = $this->get_request_instance();
        $method  = $this->get_normalize_method();
        $this->assertSame( '/blog/post/', $method->invoke( $request, '/blog/post/' ) );
    }

    public function test_normalize_lowercases_uri(): void {
        $request = $this->get_request_instance();
        $method  = $this->get_normalize_method();
        $this->assertSame( '/blog/my-post/', $method->invoke( $request, '/Blog/My-Post' ) );
    }

    public function test_normalize_does_not_add_slash_to_file_extension(): void {
        $request = $this->get_request_instance();
        $method  = $this->get_normalize_method();
        // Files with extension should not get a trailing slash
        $this->assertSame( '/feed.xml', $method->invoke( $request, '/feed.xml' ) );
    }

    // ── is_cacheable ──────────────────────────────────────────────────────────

    public function test_is_cacheable_returns_true_for_normal_get(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/blog/post/';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertTrue( $request->is_cacheable() );
    }

    public function test_is_cacheable_returns_false_for_post_method(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/contact/';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
    }

    public function test_is_cacheable_returns_false_for_wp_admin(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/wp-admin/edit.php';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
    }

    public function test_is_cacheable_returns_false_for_wp_login(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/wp-login.php';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
    }

    public function test_is_cacheable_returns_false_when_query_string_present(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/blog/';
        $_SERVER['QUERY_STRING']   = 'page=2';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
    }

    public function test_is_cacheable_returns_false_for_search_get_param(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        unset( $_SERVER['QUERY_STRING'] );
        $_GET['s'] = 'hello';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
        unset( $_GET['s'] );
    }

    public function test_is_cacheable_returns_false_when_logged_in_cookie(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/profile/';
        unset( $_SERVER['QUERY_STRING'] );
        $_COOKIE['wordpress_logged_in_abc123'] = '1';
        $this->reset_singleton();
        $request = $this->get_request_instance();
        $this->assertFalse( $request->is_cacheable() );
        unset( $_COOKIE['wordpress_logged_in_abc123'] );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function reset_singleton(): void {
        $ref = new ReflectionClass( \WSR\Request::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );
    }

    private function get_request_instance(): \WSR\Request {
        $ref  = new ReflectionClass( \WSR\Request::class );
        $ctor = $ref->getConstructor();
        $ctor->setAccessible( true );
        $obj = $ref->newInstanceWithoutConstructor();
        $ctor->invoke( $obj );
        return $obj;
    }

    private function get_normalize_method(): ReflectionMethod {
        $method = new ReflectionMethod( \WSR\Request::class, 'normalize' );
        $method->setAccessible( true );
        return $method;
    }
}

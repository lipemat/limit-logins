<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 */
class UsersTest extends \WP_Test_REST_TestCase {
	private const DISABLED_ENDPOINTS = [
		'/wp/v2/users',
		'/wp/v2/users/(?P<id>[\d]+)',
		'/wp/v2/users/me',
	];


	public function test_disable_users_endpoint(): void {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_USER_REST ) );
		foreach ( self::DISABLED_ENDPOINTS as $endpoint ) {
			$this->assertArrayHasKey( $endpoint, $routes );
		}

		Settings::in()->update_option( Settings::DISABLE_USER_REST, true );
		do_action( 'cmb2_after_init' );
		$routes = rest_get_server()->get_routes();
		foreach ( self::DISABLED_ENDPOINTS as $endpoint ) {
			$this->assertArrayNotHasKey( $endpoint, $routes );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$routes = rest_get_server()->get_routes();
		foreach ( self::DISABLED_ENDPOINTS as $endpoint ) {
			$this->assertArrayNotHasKey( $endpoint, $routes );
		}

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		grant_super_admin( wp_get_current_user()->ID );
		$routes = rest_get_server()->get_routes();
		foreach ( self::DISABLED_ENDPOINTS as $endpoint ) {
			$this->assertArrayHasKey( $endpoint, $routes );
		}
	}


	public function test_disable_author_links_on_rest_responses(): void {
		$user = self::factory()->user->create_and_get();
		$post = self::factory()->post->create_and_get( [
			'post_author' => $user->ID,
		] );

		$response = $this->get_response( '/wp/v2/posts/' . $post->ID, [], 'GET' );
		$this->assertArrayHasKey( 'author', $response->get_links() );

		Settings::in()->update_option( Settings::DISABLE_USER_REST, true );
		unset( $GLOBALS['wp_rest_server'] );
		$response = $this->get_response( '/wp/v2/posts/' . $post->ID, [], 'GET' );
		$this->assertArrayNotHasKey( 'author', $response->get_links() );
	}


	public function test_disable_author_query_var(): void {
		$wp = new \WP();
		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE ) );
		$query_vars = apply_filters( 'query_vars', [ 'author', 'another', 'author_name' ] );
		$this->assertContains( 'author', $query_vars );
		$this->assertContains( 'another', $query_vars );
		$this->assertContains( 'author_name', $query_vars );

		$wp->parse_request( '' );
		$this->assertContains( 'author', $wp->public_query_vars );
		$this->assertContains( 'page', $wp->public_query_vars );
		$this->assertContains( 'author_name', $wp->public_query_vars );

		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, true );
		do_action( 'cmb2_after_init' );
		$query_vars = apply_filters( 'query_vars', [ 'author', 'another', 'author_name' ] );
		$this->assertNotContains( 'author', $query_vars );
		$this->assertContains( 'another', $query_vars );
		$this->assertNotContains( 'author_name', $query_vars );

		$wp->parse_request( '' );
		$this->assertNotContains( 'author', $wp->public_query_vars );
		$this->assertContains( 'page', $wp->public_query_vars );
		$this->assertNotContains( 'author_name', $wp->public_query_vars );
	}


	public function test_disable_author_archives(): void {
		global $wp_rewrite;
		$author = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_author_permastruct(), EP_AUTHORS );
		$this->assertCount( 5, $author );

		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE ) );
		$this->assertCount( 5, apply_filters( 'author_rewrite_rules', $author ) );

		$rules = $wp_rewrite->wp_rewrite_rules();
		foreach ( $author as $key => $value ) {
			$this->assertArrayHasKey( $key, $rules );
		}

		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, true );
		do_action( 'cmb2_after_init' );
		$this->assertEmpty( apply_filters( 'author_rewrite_rules', $author ) );

		$rules = $wp_rewrite->wp_rewrite_rules();
		$author = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_author_permastruct(), EP_AUTHORS );
		foreach ( $author as $key => $value ) {
			$this->assertArrayNotHasKey( $key, $rules );
		}
	}


	#[DataProvider( 'provideIllegalUsernames' )]
	public function test_prevent_admin_username( string $username ): void {
		$result = wp_create_user( $username, '$#sDGW@3wesd24EE', 'does@notmatter.com' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_username', $result->get_error_code() );
	}


	public function test_disable_user_sitemap(): void {
		$sitemap = wp_sitemaps_get_server()->registry->get_provider( 'users' );
		$this->assertInstanceOf( \WP_Sitemaps_Users::class, $sitemap );

		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, true );
		$GLOBALS['wp_sitemaps'] = null; // Reset the sitemaps server to ensure it re-initializes.
		wp_sitemaps_get_server()->init();
		$sitemap = wp_sitemaps_get_server()->registry->get_provider( 'users' );
		$this->assertNull( $sitemap );
	}


	public function test_disable_author_links(): void {
		$user = self::factory()->user->create_and_get( [
			'user_login'   => 'iamuser',
			'display_name' => 'I am user',
			'user_url'     => 'https://example.com/author/iamuser',
		] );
		wp_set_current_user( $user->ID );
		$GLOBALS['authordata'] = $user;
		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE ) );

		$this->assertSame( '<a href="https://example.com/author/iamuser" title="Visit I am user&#8217;s website" rel="author external">I am user</a>', get_the_author_link() );
		$this->assertSame( 'http://limit-logins.loc/author/iamuser/', get_author_posts_url( $user->ID ) );

		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, true );
		$this->assertSame( get_the_author(), get_the_author_link() );
		$this->assertSame( '', get_author_posts_url( $user->ID ) );
	}


	public function test_remove_body_classes(): void {
		$user = self::factory()->user->create_and_get( [
			'user_login'   => 'iamuser',
			'display_name' => 'I am user',
		] );
		$this->go_to( get_author_posts_url( $user->ID ) );
		$classes = get_body_class();
		$this->assertContains( 'author', $classes );
		foreach ( $classes as $class ) {
			$this->assertStringNotContainsString( 'author-', $class );
		}
	}


	public static function provideIllegalUsernames(): array {
		return [
			'admin'         => [ 'username' => 'admin' ],
			'administrator' => [ 'username' => 'administrator' ],
			'dev'           => [ 'username' => 'dev' ],
			'root'          => [ 'username' => 'root' ],
			'superadmin'    => [ 'username' => 'superadmin' ],
			'webmaster'     => [ 'username' => 'webmaster' ],
			'sysadmin'      => [ 'username' => 'sysadmin' ],
			'support'       => [ 'username' => 'support' ],
		];
	}
}

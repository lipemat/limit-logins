<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 */
class Oembed_EndpointTest extends \WP_Test_REST_TestCase {

	public function test_remove_oembed_endpoint(): void {
		Settings::in()->update_option( Settings::DISABLE_OEMBED, true );
		$this->assertTrue( Settings::in()->get_option( Settings::DISABLE_OEMBED ) );
		do_action( 'cmb2_after_init' );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/oembed/1.0/embed', $routes );
		$this->assertArrayHasKey( '/oembed/1.0/proxy', $routes );

		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
	}


	public function test_remove_author_data(): void {
		$user = self::factory()->user->create( [
			'role'         => 'administrator',
			'display_name' => 'Do not expose me',
		] );
		$post = self::factory()->post->create( [
			'post_title'  => 'Test Post',
			'post_author' => $user,
		] );
		$result = $this->get_response( '/oembed/1.0/proxy', [
			'url' => get_the_permalink( $post ),
		], 'GET' );
		$this->assertSame( 401, $result->get_status() );
		$this->assertSame( 'rest_forbidden', $result->get_data()['code'] );

		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_OEMBED ) );
		wp_set_current_user( $user );

		$result = (array) $this->get_response( '/oembed/1.0/proxy', [
			'url' => get_the_permalink( $post ),
		], 'GET' )->get_data();
		unset( $result['html'] );

		$with_author = [
			'version'       => '1.0',
			'provider_name' => 'Limit Login Tests',
			'provider_url'  => 'http://limit-logins.loc',
			'author_name'   => 'Do not expose me',
			'author_url'    => get_author_posts_url( $user ),
			'title'         => 'Test Post',
			'type'          => 'rich',
			'width'         => 600,
			'height'        => 338,
		];
		$this->assertSame( $with_author, $result );
		$result = (array) get_oembed_response_data( $post, 600 );
		unset( $result['html'] );
		$this->assertSame( $with_author, $result );

		Settings::in()->update_option( Settings::DISABLE_OEMBED, true );
		$result = (array) $this->get_response( '/oembed/1.0/proxy', [
			'url' => get_the_permalink( $post ),
		], 'GET' )->get_data();
		unset( $result['html'] );

		$without_author = [
			'version'       => '1.0',
			'provider_name' => 'Limit Login Tests',
			'provider_url'  => 'http://limit-logins.loc',
			'author_name'   => get_bloginfo( 'name' ),
			'author_url'    => get_home_url(),
			'title'         => 'Test Post',
			'type'          => 'rich',
			'width'         => 600,
			'height'        => 338,
		];
		$this->assertSame( $without_author, $result );
		$result = (array) get_oembed_response_data( $post, 600 );
		unset( $result['html'] );
		$this->assertSame( $without_author, $result );
	}


	public function test_option_no_enabled(): void {
		$this->assertFalse( Settings::in()->get_option( Settings::DISABLE_OEMBED ) );

		$this->assertSame( [
			'application/json+oembed' => 'json',
			'text/xml+oembed'         => 'xml',
			'application/xml+oembed'  => 'xml',
		], apply_filters( 'oembed_linktypes', [
			'application/json+oembed' => 'json',
			'text/xml+oembed'         => 'xml',
			'application/xml+oembed'  => 'xml',
		] ) );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/oembed/1.0/embed', $routes );
		$this->assertArrayHasKey( '/oembed/1.0/proxy', $routes );

		$this->assertSame( 10, has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
	}
}

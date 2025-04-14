<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 */
class Oembed_EndpointTest extends \WP_UnitTestCase {

	public function test_remove_oembed_endpoint(): void {
		Settings::in()->update_option( Settings::DISABLE_OEMBED, true );
		$this->assertTrue( Settings::in()->get_option( Settings::DISABLE_OEMBED ) );
		do_action( 'cmb2_after_init' );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/oembed/1.0/embed', $routes );
		$this->assertArrayHasKey( '/oembed/1.0/proxy', $routes );

		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
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

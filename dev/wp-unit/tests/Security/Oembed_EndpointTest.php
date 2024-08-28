<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 */
class Oembed_EndpointTest extends \WP_UnitTestCase {

	public function test_remove_oembed_endpoint(): void {
		$this->assertEmpty( apply_filters( 'oembed_linktypes', [
			'application/json+oembed' => 'json',
			'text/xml+oembed'         => 'xml',
			'application/xml+oembed'  => 'xml',
		] ) );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/oembed/1.0/embed', $routes );

		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
	}
}

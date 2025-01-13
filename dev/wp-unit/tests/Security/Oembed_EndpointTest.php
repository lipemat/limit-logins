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
		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/oembed/1.0/embed', $routes );
		$this->assertArrayHasKey( '/oembed/1.0/proxy', $routes );

		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
	}
}

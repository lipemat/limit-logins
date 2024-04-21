<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Attempts;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class GatewayTest extends \WP_UnitTestCase {
	protected function tearDown(): void {
		unset( $GLOBALS['wp_xmlrpc_server'] );
		parent::tearDown();
	}


	public function test_detect_wp_login(): void {
		$gateway = Gateway::detect();
		$this->assertSame( Gateway::WP_LOGIN, $gateway );

		$user = self::factory()->user->create_and_get();
		wp_authenticate( $user->user_login, 'not valid password' );
		$this->assertSame( Gateway::WP_LOGIN, Attempts::in()->get_existing( $user->user_login )->gateway );
	}


	public function test_detect_xmlrpc(): void {
		$GLOBALS['wp_xmlrpc_server'] = new \wp_xmlrpc_server();
		$gateway = Gateway::detect();
		$this->assertSame( Gateway::XMLRPC, $gateway );

		$user = self::factory()->user->create_and_get();
		wp_authenticate( $user->user_login, 'not valid password' );
		$this->assertSame( Gateway::XMLRPC, Attempts::in()->get_existing( $user->user_login )->gateway );
	}


	public function test_detect_woo_login(): void {
		$_REQUEST['woocommerce-login-nonce'] = 'nonce';
		$gateway = Gateway::detect();
		$this->assertSame( Gateway::WOO_LOGIN, $gateway );

		$user = self::factory()->user->create_and_get();
		wp_authenticate( $user->user_login, 'not valid password' );
		$this->assertSame( Gateway::WOO_LOGIN, Attempts::in()->get_existing( $user->user_login )->gateway );
	}


	public function test_detect_rest_api(): void {
		add_filter( 'wp_is_rest_endpoint', '__return_true' );
		$gateway = Gateway::detect();
		$this->assertSame( Gateway::REST_API, $gateway );

		$user = self::factory()->user->create_and_get();
		wp_authenticate( $user->user_login, 'not valid password' );
		$this->assertSame( Gateway::REST_API, Attempts::in()->get_existing( $user->user_login )->gateway );
	}
}

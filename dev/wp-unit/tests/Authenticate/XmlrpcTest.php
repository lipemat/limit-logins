<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;
use Lipe\WP_Unit\Exceptions\TestHelperException;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class XmlrpcTest extends \WP_XMLRPC_UnitTestCase {
	protected function tearDown(): void {
		unset( $GLOBALS['wp_xmlrpc_server'] );
		parent::tearDown();
	}


	/**
	 * @throws TestHelperException
	 */
	public function test_blocked_skips_password_check(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require \dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		\WP_Application_Passwords::create_new_application_password( $fixture->user->ID, [ 'name' => __METHOD__ ] );
		// Check application passwords as a real XML-RPC request does.
		add_filter( 'application_password_is_api_request', '__return_true' );
		$GLOBALS['wp_xmlrpc_server'] = $this->myxmlrpcserver;
		$statuses = [];
		add_filter( 'status_header', function( string $header, int $code ) use ( &$statuses ): string {
			$statuses[] = $code;
			return $header;
		}, 10, 2 );
		$checks = did_filter( 'check_password' );
		$app_checks = did_action( 'application_password_failed_authentication' );

		$this->assertFalse( $this->myxmlrpcserver->login( $fixture->user->user_login, 'not valid password' ) );
		$this->assertSame( 'Too many failed login attempts.', $this->myxmlrpcserver->error->message );
		$this->assertSame( Authenticate::CODE_BLOCKED, $this->myxmlrpcserver->error->code );
		$this->assertSame( [ 403 ], $statuses );
		$this->assertSame( $checks, did_filter( 'check_password' ) );
		$this->assertSame( $app_checks, did_action( 'application_password_failed_authentication' ) );

		// Both passwords are checked once the block is removed.
		Attempts::in()->remove_block( $fixture->user->user_login );
		set_private_property( $this->myxmlrpcserver, 'auth_failed', false );
		$this->assertFalse( $this->myxmlrpcserver->login( $fixture->user->user_login, 'not valid password' ) );
		$this->assertSame( 'Incorrect username or password.', $this->myxmlrpcserver->error->message );
		$this->assertSame( $checks + 1, did_filter( 'check_password' ) );
		$this->assertSame( $app_checks + 1, did_action( 'application_password_failed_authentication' ) );
	}


	public function test_adjust_xmlrpc_error(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		$this->assertEquals( $user, $this->myxmlrpcserver->login( $user->user_login, $password ) );

		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			set_private_property( $this->myxmlrpcserver, 'auth_failed', false );
			$this->assertFalse( $this->myxmlrpcserver->login( $user->user_login, 'not valid password' ) );
			$this->assertSame( 'Incorrect username or password.', $this->myxmlrpcserver->error->message );
		}
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );

		set_private_property( $this->myxmlrpcserver, 'auth_failed', false );
		$this->assertFalse( $this->myxmlrpcserver->login( $user->user_login, 'not valid password' ) );
		$this->assertSame( 'Too many failed login attempts.', $this->myxmlrpcserver->error->message );
		$this->assertInstanceOf( \IXR_Error::class, $this->myxmlrpcserver->error );
		$this->assertSame( Authenticate::CODE_BLOCKED, $this->myxmlrpcserver->error->code );

		set_private_property( $this->myxmlrpcserver, 'auth_failed', false );
		$this->assertFalse( $this->myxmlrpcserver->login( $user->user_login, $password ) );
		$this->assertSame( 'Too many failed login attempts.', $this->myxmlrpcserver->error->message );

		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$this->assertCount( 1, Attempts::in()->get_all() );
	}
}

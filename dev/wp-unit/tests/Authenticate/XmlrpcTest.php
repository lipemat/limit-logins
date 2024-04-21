<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class XmlrpcTest extends \WP_XMLRPC_UnitTestCase {
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

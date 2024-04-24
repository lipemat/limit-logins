<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class Unlock_LinkTest extends \WP_UnitTestCase {
	public function test_invalid_attempt(): void {
		$key = get_private_property( Unlock_Link::class, 'KEY' );
		$this->invalidAttempt();

		$_GET[ $key ] = 'invalid';
		$this->invalidAttempt();
	}


	public function test_valid_attempt(): void {
	}


	public function test_get_unlock_url(): void {
	}


	public function test_get_unlock_key(): void {
	}


	public function test_send_blocked_email(): void {
	}


	public function test_get_matching_block(): void {
	}


	private function invalidAttempt(): void {
		remove_all_filters( 'wp_login_errors' );
		$action = get_private_property( Unlock_Link::class, 'ACTION' );
		$code = get_private_property( Unlock_Link::class, 'ERROR_CODE' );
		do_action( 'login_form_' . $action );
		/* @var \WP_Error $errors */
		$errors = \apply_filters( 'wp_login_errors', new \WP_Error() );
		$this->assertSame( '<strong>Error:</strong> Invalid unlock link. The failure has been recorded.', $errors->get_error_message( $code ) );
		$this->assertCount( 1, $errors->get_error_messages() );
	}
}

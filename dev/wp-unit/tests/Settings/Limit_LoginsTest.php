<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Settings;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class Limit_LoginsTest extends \WP_UnitTestCase {
	public function test_contact_field(): void {
		$user = self::factory()->user->create_and_get();
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			$result = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' );
			$this->assertSame( $this->default_error( $user->user_login ), $result->get_error_message() );
		}

		$error = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message();
		$this->assertSame( $this->too_many_error(), $error );
		$this->assertStringNotContainsString( 'contact form', $error );

		Limit_Logins::in()->update_option( Limit_Logins::CONTACT, 'https://contact.form' );

		$error = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message();
		$this->assertSame( $this->too_many_error(), $error );
		$this->assertStringContainsString( 'Use the <a href="https://contact.form">contact form</a> for help.', $error );
	}


	private function default_error( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function too_many_error(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;

/**
 * @author Mat Lipe
 * @since  September 2025
 *
 */
class Username_DetectionTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, true );
	}


	public function test_standardize_login_errors_invalid_username(): void {
		$error = wp_authenticate_username_password( null, 'invalid_username', 'invalid_password' );

		$this->assertSame( [ '<strong>Error:</strong> Your username or password is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>' ], $error->errors['invalid_username'] );
	}


	public function test_standardize_login_errors_incorrect_password(): void {
		$user = self::factory()->user->create_and_get();
		$error = wp_authenticate_username_password( null, $user->user_login, 'invalid_password' );

		$this->assertSame( [ '<strong>Error:</strong> Your username or password is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>' ], $error->errors['incorrect_password'] );
	}


	public function test_standardize_login_errors_disabled_setting(): void {
		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, false );

		$error = wp_authenticate_username_password( null, 'invalid_username', 'invalid_password' );

		$this->assertSame( 'invalid_username', $error->get_error_code() );
		$this->assertSame( '<strong>Error:</strong> The username <strong>invalid_username</strong> is not registered on this site. If you are unsure of your username, try your email address instead.', $error->get_error_message() );
	}


	public function test_use_dummy_user_for_lost_password_valid_user(): void {
		$user = self::factory()->user->create_and_get();
		$result = retrieve_password( $user->user_login );
		$this->assertTrue( $result );

		$this->assertSame( '[Limit Login Tests Network] Password Reset', tests_retrieve_phpmailer_instance()->get_sent()->subject );
		$this->assertSame( $user->user_email, tests_retrieve_phpmailer_instance()->get_sent()->to[0][0] );
	}


	public function test_use_dummy_user_for_lost_password_false_user(): void {
		$result = retrieve_password( 'not-valid-user' );

		$this->assertTrue( $result );
		$this->assertEmpty( tests_retrieve_phpmailer_instance()->get_sent() );
	}


	public function test_use_dummy_user_for_lost_password_disabled_setting(): void {
		Settings::in()->update_option( Settings::DISABLE_USER_ARCHIVE, false );

		$result = retrieve_password( 'not-valid-user' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalidcombo', $result->get_error_code() );
		$this->assertSame( '<strong>Error:</strong> There is no account with that username or email address.', $result->get_error_message() );
		$this->assertEmpty( tests_retrieve_phpmailer_instance()->get_sent() );
	}
}

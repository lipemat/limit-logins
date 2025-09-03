<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

/**
 * @author Mat Lipe
 * @since  September 2025
 *
 */
class Username_DetectionTest extends \WP_UnitTestCase {

	public function test_standardize_login_errors_invalid_username(): void {
		$error = wp_authenticate_username_password( null, 'invalid_username', 'invalid_password' );

		$this->assertSame( [ '<strong>Error:</strong> Your username or password is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>' ], $error->errors['invalid_username'] );
	}


	public function test_standardize_login_errors_incorrect_password(): void {
		$user = self::factory()->user->create_and_get();
		$error = wp_authenticate_username_password( null, $user->user_login, 'invalid_password' );

		$this->assertSame( [ '<strong>Error:</strong> Your username or password is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>' ], $error->errors['incorrect_password'] );
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
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class Reset_PasswordTest extends \WP_UnitTestCase {

	public function test_clear_blocks_on_password_reset(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$user = $fixture->user;

		$this->assertWPError( wp_authenticate( $user->user_login, $fixture->password ) );

		$new_password = wp_generate_password();
		reset_password( $fixture->user, $new_password );
		$result = wp_authenticate( $user->user_login, $new_password );
		$this->assertNotSame( $result->user_pass, $user->user_pass );
		$result->user_pass = $user->user_pass;
		$this->assertEquals( $fixture->user, $result );
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Settings;
use Lipe\Limit_Logins\Utils;

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


	public function test_password_reset_clears_all_attempts_for_current_ip(): void {
		$ip = Utils::in()->get_current_ip();
		$user = self::factory()->user->create_and_get( [ 'user_pass' => 'old-password' ] );
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [
			self::attempt( $user->user_login, '192.0.2.10', Attempts::ALLOWED_ATTEMPTS ),
			self::attempt( 'first-ip-user', $ip, Attempts::ALLOWED_ATTEMPTS ),
			self::attempt( 'second-ip-user', $ip, Attempts::ALLOWED_ATTEMPTS ),
			self::attempt( 'other-ip-user', '192.0.2.20', Attempts::ALLOWED_ATTEMPTS ),
			self::attempt( 'partial-ip-user', $ip, 2 ),
		] );
		$this->assertSame( 'blocked', wp_authenticate( $user->user_login, 'old-password' )->get_error_code() );

		reset_password( $user, 'new-password' );

		$this->assertInstanceOf( \WP_User::class, wp_authenticate( $user->user_login, 'new-password' ), 'Resetting the password should permit login from the formerly blocked IP.' );
		$this->assertSame( [ 'other-ip-user' ], \array_map( function( Attempt $attempt ): string {
			return $attempt->username;
		}, Attempts::in()->get_all() ), 'Username and current-IP attempts, including partial attempts, should be removed.' );
	}


	/**
	 * @return array{ip: string, username: string, gateway: string, count: int, expires: int, key: string}
	 */
	private static function attempt( string $username, string $ip, int $count ): array {
		return Attempt::factory( [
			Attempt::IP       => $ip,
			Attempt::USERNAME => $username,
			Attempt::GATEWAY  => Gateway::WP_LOGIN->value,
			Attempt::COUNT    => $count,
			Attempt::EXPIRES  => \time() + Attempts::DURATION,
		] )->jsonSerialize();
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings as Settings;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class AttemptsTest extends \WP_Test_REST_TestCase {

	public function test_get_existing(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$user_0 = ( require \dirname( __DIR__ ) . '/fixtures/blocked-user.php' )->user->user_login;
		$_SERVER['REMOTE_ADDR'] = '2.2.2.2';
		$user_1 = ( require \dirname( __DIR__ ) . '/fixtures/blocked-user.php' )->user->user_login;
		$data = Settings::in()->get_option( Settings::LOGGED_FAILURES, [] );
		$this->assertSame( $user_0, Attempts::in()->get_existing( $user_0 )->username );
		$this->assertSame( $user_1, Attempts::in()->get_existing( $user_1 )->username );

		// 1 available attempt, should receive the matching IP.
		$data[0]['expires'] = (int) gmdate( 'U' ) - 1;
		$data[0]['count'] = Attempts::ALLOWED_ATTEMPTS - 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $data );
		$this->assertSame( $user_1, Attempts::in()->get_existing( $user_0 )->username );
		$this->assertSame( $user_1, Attempts::in()->get_existing( $user_1 )->username );

		// 2 available attempts, should receive the blocked one.
		$data[0]['expires'] = (int) gmdate( 'U' ) + 30;
		$data[0]['ip'] = $data[1]['ip'];
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $data );
		$this->assertSame( $user_1, Attempts::in()->get_existing( $user_0 )->username );
		$this->assertSame( $user_1, Attempts::in()->get_existing( $user_1 )->username );
	}


	public function test_is_ip_blocked(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		$this->assertSame( Utils::in()->get_current_ip(), $fixture->attempt->ip );
		$this->assertTrue( Attempts::in()->is_ip_blocked() );

		$_SERVER['REMOTE_ADDR'] = '44.44.44.44';
		$this->assertFalse( Attempts::in()->is_ip_blocked() );
	}


	/**
	 * A block only the username matches leaves the IP free.
	 */
	public function test_is_ip_blocked_ignores_the_username(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		$_SERVER['REMOTE_ADDR'] = '45.45.45.45';

		$this->assertTrue( Attempts::in()->is_blocked( $fixture->user->user_login ) );
		$this->assertFalse( Attempts::in()->is_ip_blocked() );
	}


	public function test_is_ip_blocked_expired(): void {
		require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		$this->assertTrue( Attempts::in()->is_ip_blocked() );

		$data = Settings::in()->get_option( Settings::LOGGED_FAILURES, [] );
		$data[0][ Attempt::EXPIRES ] = (int) \gmdate( 'U' ) - 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $data );

		$this->assertFalse( Attempts::in()->is_ip_blocked() );
	}


	public function test_is_ip_blocked_below_allowed_attempts(): void {
		require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		$this->assertTrue( Attempts::in()->is_ip_blocked() );

		$data = Settings::in()->get_option( Settings::LOGGED_FAILURES, [] );
		$data[0][ Attempt::COUNT ] = Attempts::ALLOWED_ATTEMPTS - 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $data );

		$this->assertFalse( Attempts::in()->is_ip_blocked() );
	}


	public function test_username_failure(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertEmpty( Attempts::in()->get_all() );

		for ( $i = 0; $i <= Attempts::ALLOWED_ATTEMPTS + 3; $i ++ ) {
			$_SERVER['REMOTE_ADDR'] = "65.123.100.10{$i}";
			$result = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' );
			$existing = Attempts::in()->get_existing( $user->user_login );
			$this->assertCount( 1, Attempts::in()->get_all() );

			if ( $i < Attempts::ALLOWED_ATTEMPTS ) {
				$this->assertEquals( $i + 1, $existing->get_count() );
				$this->assertWPError( $result );
				$this->assertSame( $this->defaultError( $user->user_login ), $result->get_error_message() );
			} else {
				$this->assertSame( $this->tooManyError(), $result->get_error_message() );

				$this->assertSame( Attempts::ALLOWED_ATTEMPTS, $existing->get_count() );
			}
		}

		// Other users may still log in.
		$this->assertNotWPError( wp_authenticate( self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] )->user_login, $password ) );

		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, $password )->get_error_message() );
	}


	public function test_ip_failure(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertEmpty( Attempts::in()->get_all() );

		$_SERVER['REMOTE_ADDR'] = "99.123.100.10";
		for ( $i = 0; $i <= Attempts::ALLOWED_ATTEMPTS + 3; $i ++ ) {
			$loop_user = self::factory()->user->create_and_get();
			$result = wp_authenticate( $loop_user->user_login, 'NOT VALID PASSWORD' );
			$existing = Attempts::in()->get_existing( $loop_user->user_login );
			$this->assertCount( 1, Attempts::in()->get_all() );

			if ( $i < Attempts::ALLOWED_ATTEMPTS ) {
				$this->assertEquals( $i + 1, $existing->get_count() );
				$this->assertWPError( $result );
				$this->assertSame( $this->defaultError( $loop_user->user_login ), $result->get_error_message() );
			} else {
				$this->assertSame( $this->tooManyError(), $result->get_error_message() );
				$this->assertSame( Attempts::ALLOWED_ATTEMPTS, $existing->get_count() );
			}
		}

		// No more attempts from this IP.
		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, $password )->get_error_message() );

		// Other IP may still log in.
		$_SERVER['REMOTE_ADDR'] = '100.123.100.10';
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
	}


	public function test_limit_of_retries(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS - 1; $i ++ ) {
			$result = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' );
			$this->assertSame( $this->defaultError( $user->user_login ), $result->get_error_message() );
		}

		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertSame( $this->defaultError( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, $password )->get_error_message() );
	}


	public function test_retries_expiration(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			$result = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' );
			$this->assertSame( $this->defaultError( $user->user_login ), $result->get_error_message() );
		}

		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );

		$items = Attempts::in()->get_all();
		$this->assertCount( 1, $items );
		$data = $items[0]->jsonSerialize();

		// Set the expiration to 1 second from now.
		$data['expires'] = (int) gmdate( 'U' ) + 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [ $data ] );
		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertInstanceOf( Attempt::class, Attempts::in()->get_existing( $user->user_login ) );

		// Set the expiration to 1 second ago.
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$data['expires'] = (int) gmdate( 'U' ) - 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [ $data ] );
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );

		// Clears out old attempts during the next failure.
		$this->assertNull( Attempts::in()->get_existing( $user->user_login ) );
		$this->assertSame( $this->defaultError( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	public function test_remove_block(): void {
		/** @var \Fixture_Blocked_User $user_1 */
		$user_1 = require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';

		$_SERVER['REMOTE_ADDR'] = '32.32.32.32';
		/** @var \Fixture_Blocked_User $user_2 */
		$user_2 = require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';

		$this->assertSame( [ $user_1->user->user_login, $user_2->user->user_login ], self::attempt_usernames(), 'Both username and current-IP attempts should exist before removal.' );

		Attempts::in()->remove_block( $user_1->user->user_login );

		$this->assertSame( [], self::attempt_usernames(), 'Removing a username should also remove attempts from the current IP.' );
	}


	public function test_remove_block_by_ip_without_username_match(): void {
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [
			[ Attempt::USERNAME => 'blocked-user', Attempt::IP => Utils::in()->get_current_ip() ],
		] );
		$this->assertSame( [ 'blocked-user' ], self::attempt_usernames() );

		Attempts::in()->remove_block( 'different-username' );

		$this->assertSame( [], self::attempt_usernames(), 'A matching IP should remove the attempt even when the username differs.' );
	}


	public function test_remove_block_preserves_unrelated_ip(): void {
		$_SERVER['REMOTE_ADDR'] = '33.33.33.33';
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [
			[ Attempt::USERNAME => 'target', Attempt::IP => '192.0.2.10' ],
			[ Attempt::USERNAME => 'unrelated', Attempt::IP => '192.0.2.20' ],
		] );
		$this->assertSame( [ 'target', 'unrelated' ], self::attempt_usernames() );

		Attempts::in()->remove_block( 'target' );

		$this->assertSame( [ 'unrelated' ], self::attempt_usernames(), 'An attempt for another username and IP must remain.' );
	}


	public function test_remove_block_without_match_preserves_attempts(): void {
		$_SERVER['REMOTE_ADDR'] = '33.33.33.33';
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [
			[ Attempt::USERNAME => 'unrelated', Attempt::IP => '192.0.2.10' ],
		] );
		$this->assertSame( [ 'unrelated' ], self::attempt_usernames() );

		Attempts::in()->remove_block( 'missing-user' );

		$this->assertSame( [ 'unrelated' ], self::attempt_usernames(), 'An unrelated username and IP should not remove any attempt.' );
	}


	public function test_remove_block_removes_all_matching_attempts(): void {
		$ip = Utils::in()->get_current_ip();
		$attempts = [
			[ Attempt::USERNAME => 'target', Attempt::IP => '192.0.2.10', Attempt::COUNT => Attempts::ALLOWED_ATTEMPTS ],
			[ Attempt::USERNAME => 'first-ip-user', Attempt::IP => $ip, Attempt::COUNT => Attempts::ALLOWED_ATTEMPTS ],
			[ Attempt::USERNAME => 'partial-ip-user', Attempt::IP => $ip, Attempt::COUNT => 2 ],
			[ Attempt::USERNAME => 'target', Attempt::IP => '192.0.2.20', Attempt::COUNT => Attempts::ALLOWED_ATTEMPTS ],
			[ Attempt::USERNAME => 'unrelated', Attempt::IP => '192.0.2.30', Attempt::COUNT => Attempts::ALLOWED_ATTEMPTS ],
		];
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $attempts );
		$this->assertSame( [ 'target', 'first-ip-user', 'partial-ip-user', 'target', 'unrelated' ], self::attempt_usernames() );

		Attempts::in()->remove_block( 'target' );

		$this->assertSame( [ 'unrelated' ], self::attempt_usernames(), 'All username and IP matches, including partial attempts, should be removed.' );
	}


	public function test_remove_block_unknown_ip_does_not_clear_other_users(): void {
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [
			[ Attempt::USERNAME => 'target', Attempt::IP => '192.0.2.10' ],
			[ Attempt::USERNAME => 'unrelated', Attempt::IP => Utils::UNKNOWN_IP ],
		] );
		$this->assertSame( Utils::UNKNOWN_IP, Utils::in()->get_current_ip(), 'An invalid request IP should use the unknown-IP marker.' );
		$this->assertSame( [ 'target', 'unrelated' ], self::attempt_usernames(), 'Both records should exist before removal.' );

		Attempts::in()->remove_block( 'target' );

		$this->assertSame( [ 'unrelated' ], self::attempt_usernames(), 'The unknown-IP marker must not match another user.' );
	}


	/**
	 * @return list<string>
	 */
	private static function attempt_usernames(): array {
		return \array_map( function( Attempt $attempt ): string {
			return $attempt->username;
		}, Attempts::in()->get_all() );
	}


	private function defaultError( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function tooManyError(): string {
		return Authenticate::in()->get_blocked_message();
	}
}

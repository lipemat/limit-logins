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
		$user_1 = require dirname( __DIR__ ) . '/fixtures/blocked-user.php';

		$_SERVER['REMOTE_ADDR'] = '32.32.32.32';
		/** @var \Fixture_Blocked_User $user_2 */
		$user_2 = require dirname( __DIR__ ) . '/fixtures/blocked-user.php';

		$this->assertCount( 2, Attempts::in()->get_all() );

		Attempts::in()->remove_block( $user_1->user->user_login );
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( $user_2->user->user_login, Attempts::in()->get_existing( $user_2->user->user_login )->username );

		Attempts::in()->remove_block( $user_2->user->user_login );
		$this->assertEmpty( Attempts::in()->get_all() );

		/** @var \Fixture_Blocked_User $user_3 */
		$user_3 = require dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		$this->assertCount( 1, Attempts::in()->get_all() );
		Attempts::in()->remove_block( 'use IP to map to user' );
		$this->assertCount( 1, Attempts::in()->get_all() );
		Attempts::in()->remove_block( $user_3->user->user_login );
		$this->assertEmpty( Attempts::in()->get_all() );
	}


	private function defaultError( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function tooManyError(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}
}

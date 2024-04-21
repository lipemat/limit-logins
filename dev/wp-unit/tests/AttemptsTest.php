<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Util\Actions;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Settings\Limit_Logins as Settings;

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
		Settings::in()->update_option( Settings::LOGGED_FAILURES, wp_json_encode( [ $data ] ) );
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );

		// Clears out old attempts during the next failure.
		$this->assertNull( Attempts::in()->get_existing( $user->user_login ) );
		$this->assertSame( $this->defaultError( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	private function defaultError( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function tooManyError(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}
}

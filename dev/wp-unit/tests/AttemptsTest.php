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
				$this->assertSame( $this->default_error( $user->user_login ), $result->get_error_message() );
			} else {
				$this->assertSame( $this->too_many_error(), $result->get_error_message() );

				$this->assertSame( Attempts::ALLOWED_ATTEMPTS, $existing->get_count() );
			}
		}

		// Other users may still log in.
		$this->assertNotWPError( wp_authenticate( self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] )->user_login, $password ) );

		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, $password )->get_error_message() );
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
				$this->assertSame( $this->default_error( $loop_user->user_login ), $result->get_error_message() );
			} else {
				$this->assertSame( $this->too_many_error(), $result->get_error_message() );
				$this->assertSame( Attempts::ALLOWED_ATTEMPTS, $existing->get_count() );
			}
		}

		// No more attempts from this IP.
		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, $password )->get_error_message() );

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
			$this->assertSame( $this->default_error( $user->user_login ), $result->get_error_message() );
		}

		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertSame( $this->default_error( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, $password )->get_error_message() );
	}


	public function test_retries_expiration(): void {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => $password,
		] );
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			$result = wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' );
			$this->assertSame( $this->default_error( $user->user_login ), $result->get_error_message() );
		}

		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );

		$items = Attempts::in()->get_all();
		$this->assertCount( 1, $items );
		$data = $items[0]->jsonSerialize();

		// Set the expiration to 1 second from now.
		$data['expires'] = (int) gmdate( 'U' ) + 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [ $data ] );
		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertInstanceOf( Attempt::class, Attempts::in()->get_existing( $user->user_login ) );

		// Set the expiration to 1 second ago.
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$data['expires'] = (int) gmdate( 'U' ) - 1;
		Settings::in()->update_option( Settings::LOGGED_FAILURES, wp_json_encode( [ $data ] ) );
		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );

		// Clears out old attempts during the next failure.
		$this->assertNull( Attempts::in()->get_existing( $user->user_login ) );
		$this->assertSame( $this->default_error( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	public function test_rest_api_failures(): void {
		[ $user ] = $this->setup_rest_api();
		$this->assertSame( $this->default_error( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failed_rest_request();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 2, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	public function test_rest_api_valid(): void {
		[ $user, $pass ] = $this->setup_rest_api();
		$_SERVER['PHP_AUTH_PW'] = $pass;
		$this->assertNotErrorResponse( $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' ) );
		$this->assertCount( 0, Attempts::in()->get_all() );
		$this->assertNull( Attempts::in()->get_existing( $user->user_login ) );
	}


	public function test_rest_invalid_password(): void {
		[ $user ] = $this->setup_rest_api();
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failed_rest_request();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$this->assertSame( Gateway::REST_API, Attempts::in()->get_existing( $user->user_login )->gateway );
	}


	public function test_rest_application_passwords_disabled(): void {
		Actions::in()->add_single_filter( 'pre_site_option_using_application_passwords', '__return_null' );
		$this->failed_rest_request();
		$this->assertEmpty( Attempts::in()->get_all() );
	}


	public function test_rest_no_authentication(): void {
		$this->setup_rest_api();
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		$this->failed_rest_request();
		$this->assertEmpty( Attempts::in()->get_all() );
	}


	public function test_rest_rotating_user_accounts(): void {
		[ $user, $app_pass ] = $this->setup_rest_api();

		$_SERVER['REMOTE_ADDR'] = '41.41.41.41';
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failed_rest_request();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// Different user account.
		$_SERVER['PHP_AUTH_USER'] = self::factory()->user->create_and_get()->user_login;
		$this->failed_rest_request();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 2, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// Different IP address.
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['REMOTE_ADDR'] = '42.42.42.42';
		$this->failed_rest_request();
		$this->assertCount( 2, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// Valid password.
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW'] = $app_pass;
		$this->assertNotErrorResponse( $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' ) );
		$this->assertCount( 2, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	public function test_rest_lockouts(): void {
		[ $user, $app_pass ] = $this->setup_rest_api();
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			$this->failed_rest_request();
		}
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$this->assertSame( $this->too_many_error(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );

		$result = $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' );
		$this->assertSame( 'Too many failed login attempts.', $result->get_data()['message'] );
		$this->assertErrorResponse( Authenticate::CODE_BLOCKED, $result, 401 );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// valid password.
		$_SERVER['PHP_AUTH_PW'] = $app_pass;
		$result = $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' );
		$this->assertErrorResponse( Authenticate::CODE_BLOCKED, $result, 401 );
		$this->assertSame( 'Too many failed login attempts.', $result->get_data()['message'] );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	private function failed_rest_request(): void {
		$result = $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' );
		$this->assertErrorResponse( 'rest_forbidden_context', $result, 401 );
	}


	/**
	 * @return array{\WP_User,string}
	 */
	private function setup_rest_api(): array {
		$password = wp_generate_password();
		$user = self::factory()->user->create_and_get( [
			'role'      => 'administrator',
			'user_pass' => $password,
		] );
		$app_pass = \WP_Application_Passwords::chunk_password( \WP_Application_Passwords::create_new_application_password( $user->ID, [ 'name' => __METHOD__ ] )[0] );
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['PHP_AUTH_PW'] = $app_pass;

		$this->assertNotWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertEmpty( Attempts::in()->get_all() );

		return [ $user, $app_pass ];
	}


	private function default_error( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function too_many_error(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}
}

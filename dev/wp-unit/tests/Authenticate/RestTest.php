<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Lib\Util\Actions;
use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class RestTest extends \WP_Test_REST_TestCase {
	public function test_rest_api_failures(): void {
		[ $user ] = $this->setupRestApi();
		$this->assertSame( $this->defaultError( $user->user_login ), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failedRestRequest();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 2, Attempts::in()->get_existing( $user->user_login )->get_count() );
	}


	public function test_rest_api_valid(): void {
		[ $user, $pass ] = $this->setupRestApi();
		$_SERVER['PHP_AUTH_PW'] = $pass;
		$this->assertNotErrorResponse( $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' ) );
		$this->assertCount( 0, Attempts::in()->get_all() );
		$this->assertNull( Attempts::in()->get_existing( $user->user_login ) );
	}


	public function test_rest_invalid_password(): void {
		[ $user ] = $this->setupRestApi();
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failedRestRequest();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$this->assertSame( Gateway::REST_API, Attempts::in()->get_existing( $user->user_login )->gateway );
	}


	public function test_rest_application_passwords_disabled(): void {
		Actions::in()->add_single_filter( 'pre_site_option_using_application_passwords', '__return_null' );
		$this->failedRestRequest();
		$this->assertEmpty( Attempts::in()->get_all() );
	}


	public function test_rest_no_authentication(): void {
		$this->setupRestApi();
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		$this->failedRestRequest();
		$this->assertEmpty( Attempts::in()->get_all() );
	}


	public function test_rest_rotating_user_accounts(): void {
		[ $user, $app_pass ] = $this->setupRestApi();

		$_SERVER['REMOTE_ADDR'] = '41.41.41.41';
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		$this->failedRestRequest();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// Different user account.
		$_SERVER['PHP_AUTH_USER'] = self::factory()->user->create_and_get()->user_login;
		$this->failedRestRequest();
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( 2, Attempts::in()->get_existing( $user->user_login )->get_count() );

		// Different IP address.
		$_SERVER['PHP_AUTH_USER'] = $user->user_login;
		$_SERVER['REMOTE_ADDR'] = '42.42.42.42';
		$this->failedRestRequest();
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
		[ $user, $app_pass ] = $this->setupRestApi();
		$_SERVER['PHP_AUTH_PW'] = 'NOT VALID PASSWORD';
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
			$this->failedRestRequest();
		}
		$this->assertCount( 1, Attempts::in()->get_all() );
		$this->assertSame( Attempts::ALLOWED_ATTEMPTS, Attempts::in()->get_existing( $user->user_login )->get_count() );

		$this->assertSame( $this->tooManyError(), wp_authenticate( $user->user_login, 'NOT VALID PASSWORD' )->get_error_message() );

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


	private function failedRestRequest(): void {
		$result = $this->get_response( '/wp/v2/users', [ 'context' => 'edit' ], 'GET' );
		$this->assertErrorResponse( 'rest_forbidden_context', $result, 401 );
	}


	/**
	 * @return array{\WP_User,string}
	 */
	private function setupRestApi(): array {
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


	private function defaultError( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function tooManyError(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}
}

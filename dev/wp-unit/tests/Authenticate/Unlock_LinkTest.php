<?php
/** @noinspection PhpRedundantCatchClauseInspection, PhpUnhandledExceptionInspection */
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings;
use Lipe\Limit_Logins\Utils;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class Unlock_LinkTest extends \WP_UnitTestCase {
	public static string $unlock_key = '';

	public static string $rendered = '';


	public function setUp(): void {
		parent::setUp();
		self::$unlock_key = '';
		self::$rendered = '';

		change_container_object( Unlock_Link::class, new class() extends Unlock_Link {
			public function get_unlock_key(): array {
				$keys = parent::get_unlock_key();
				Unlock_LinkTest::$unlock_key = $keys['key'];
				return $keys;
			}


			public function render(): void {
				ob_start();
				try {
					parent::render();
				} catch ( \OutOfBoundsException ) {
				}
				Unlock_LinkTest::$rendered = ob_get_clean();
			}
		} );
	}


	/**
	 * These functions are part of wp-login.php and will never be in test context.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		function login_header( $title = '', $message = '', $errors = '' ) {
			echo $title . $message . $errors;
		}

		function login_footer() {
		}
	}


	public function test_invalid_attempt(): void {
		$key = get_private_property( Unlock_Link::class, 'KEY' );
		$this->invalidAttempt();

		$_GET[ $key ] = 'invalid';
		$this->invalidAttempt();
		$this->assertFalse( Utils::in()->did_exit );
	}


	public function test_valid_attempt(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$action = get_private_property( Unlock_Link::class, 'ACTION' );
		$key = get_private_property( Unlock_Link::class, 'KEY' );
		$attempt = $fixture->attempt;
		$this->assertTrue( $attempt->is_blocked() );

		$_GET[ $key ] = Unlock_LinkTest::$unlock_key;
		do_action( 'login_form_' . $action );

		$this->assertNull( Attempts::in()->get_existing( $attempt->username ) );
		$this->assertTrue( Utils::in()->did_exit );
		$this->assertSame( 'Account Unlocked<div class="notice notice-info message"><p>Your account has been unlocked. <a href="http://limit-logins.loc/wp-login.php">Log in</a></p></div>', Unlock_LinkTest::$rendered );
	}


	public function test_valid_attempt_clears_other_attempts_on_current_ip(): void {
		require \dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$attempts = Settings::in()->get_option( Settings::LOGGED_FAILURES, [] );
		$other_on_ip = $attempts[0];
		$other_on_ip[ Attempt::USERNAME ] = 'other-on-ip';
		$other_on_ip[ Attempt::KEY ] = '';
		$other_on_ip[ Attempt::COUNT ] = 2;
		$unrelated = $other_on_ip;
		$unrelated[ Attempt::USERNAME ] = 'unrelated';
		$unrelated[ Attempt::IP ] = '192.0.2.30';
		Settings::in()->update_option( Settings::LOGGED_FAILURES, [ $attempts[0], $other_on_ip, $unrelated ] );
		$this->assertCount( 3, Attempts::in()->get_all(), 'The unlock target, current-IP attempt, and unrelated attempt should exist.' );
		$query = wp_parse_url( Unlock_Link::in()->get_unlock_url( self::$unlock_key ), PHP_URL_QUERY );
		self::assertIsString( $query, 'The unlock URL should include an action and key.' );
		\parse_str( $query, $params );
		$_GET = \array_merge( $_GET, $params );

		do_action( 'login_form_' . $params['action'] );

		$this->assertSame( [ 'unrelated' ], \array_map( function( Attempt $attempt ): string {
			return $attempt->username;
		}, Attempts::in()->get_all() ), 'Unlocking should remove the target and every current-IP attempt while preserving another IP.' );
		$this->assertTrue( Utils::in()->did_exit, 'The valid unlock link should render the completion page.' );
	}


	public function test_get_unlock_url(): void {
		$action = get_private_property( Unlock_Link::class, 'ACTION' );
		$key = get_private_property( Unlock_Link::class, 'KEY' );
		$this->assertNotSame( 'key', $key, 'Using `key` conflicts with the reset password handler.' );
		$url = Unlock_Link::in()->get_unlock_url( 'test' );
		$this->assertSame( 'http://limit-logins.loc/wp-login.php?action=' . $action . '&' . $key . '=test', $url );
	}


	public function test_get_reset_password_url(): void {
		$user = self::factory()->user->create_and_get();
		$url = Unlock_Link::in()->get_reset_password_url( $user->user_login );
		$this->assertMatchesRegularExpression( '/http:\/\/limit-logins\.loc\/wp-login\.php\?action=rp&key=\w+&login=' . rawurlencode( $user->user_login ) . '/', $url );

		preg_match( '/http:\/\/limit-logins\.loc\/wp-login\.php\?action=rp&key=(?P<key>\w+)&login=(?P<login>.+)/', $url, $values );
		$this->assertSame( $user->user_login, check_password_reset_key( $values['key'], urldecode( $values['login'] ) )->user_login );
	}


	public function test_get_unlock_key(): void {
		/** @var \PasswordHash $hasher */
		$hasher = call_private_method( Unlock_Link::in(), 'get_hasher' );
		$keys = call_private_method( Unlock_Link::in(), 'get_unlock_key' );
		$this->assertTrue( $hasher->CheckPassword( $keys['key'], $keys['hash'] ) );
	}


	public function test_send_blocked_email(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 0, $mailer->mock_sent );

		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$this->assertCount( 1, $mailer->mock_sent );
		$this->assertSame( $fixture->user->user_email, $mailer->get_sent()->to[0][0] );
	}


	public function test_get_matching_attempt(): void {
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$this->assertNull( call_private_method( Unlock_Link::in(), 'get_matching_attempt', [ 'invalid' ] ) );
		$this->assertEquals( $fixture->attempt, call_private_method( Unlock_Link::in(), 'get_matching_attempt', [ Unlock_LinkTest::$unlock_key ] ) );
	}


	private function invalidAttempt(): void {
		remove_all_filters( 'wp_login_errors' );
		$action = get_private_property( Unlock_Link::class, 'ACTION' );
		$code = get_private_property( Unlock_Link::class, 'ERROR_CODE' );
		do_action( 'login_form_' . $action );
		/* @var \WP_Error $errors */
		$errors = \apply_filters( 'wp_login_errors', new \WP_Error() );
		$this->assertSame( '<strong>Error:</strong> Invalid unlock link. The failure has been recorded.', $errors->get_error_message( $code ) );
		$this->assertCount( 1, $errors->get_error_messages() );
	}

}

<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Settings;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class BlockedTest extends \WP_UnitTestCase {
	public function test_get_email_addresses(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$fixture = $this->triggerEmail();
		$this->assertSame( $fixture->user->user_email, $mailer->get_sent()->to[0][0] );
	}


	public function test_get_message(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->triggerEmail();
		$this->assertStringContainsString( 'Your Limit Login Tests account has been locked due to too many failed login attempts.', $mailer->get_sent()->body );
	}


	public function test_get_subject(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->triggerEmail();
		$this->assertSame( 'Your Limit Login Tests account has been locked.', $mailer->get_sent()->subject );
	}


	public function test_sent_at_appropriate_time(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 0, $mailer->mock_sent );
		$loop_user = self::factory()->user->create_and_get();
		for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS - 1; $i ++ ) {
			wp_authenticate( $loop_user->user_login, 'NOT VALID PASSWORD' );
			$this->assertCount( 0, $mailer->mock_sent );
		}
		wp_authenticate( $loop_user->user_login, 'NOT VALID PASSWORD' );
		$this->assertCount( 1, $mailer->mock_sent );
		$this->assertSame( 'Your Limit Login Tests account has been locked.', $mailer->get_sent()->subject );

		wp_authenticate( $loop_user->user_login, 'NOT VALID PASSWORD' );
		$this->assertCount( 1, $mailer->mock_sent );
	}


	public function test_includes_currect_unlock_link(): void {
		$action = get_private_property( Unlock_Link::class, 'ACTION' );
		$key = get_private_property( Unlock_Link::class, 'KEY' );
		$mailer = tests_retrieve_phpmailer_instance();
		$fixture = $this->triggerEmail();

		preg_match_all( '/http:\/\/limit-logins\.loc\/wp-login\.php\?action=' . $action . '&' . $key . '=(?P<key>\w+)"/', $mailer->get_sent()->body, $values );
		$this->assertCount( 2, $values['key'] );
		$this->assertSame( $values['key'][0], $values['key'][1] );
		foreach ( $values['key'] as $key ) {
			$this->assertEquals( $fixture->attempt->username, call_private_method( Unlock_Link::in(), 'get_matching_attempt', [ $key ] )->username );
		}
	}


	public function test_includes_current_reset_password_link(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$fixture = $this->triggerEmail();
		preg_match( '/http:\/\/limit-logins\.loc\/wp-login\.php\?action=rp&key=(?P<key>\w+)&login=(?P<login>.+)"/', $mailer->get_sent()->body, $values );
		$this->assertNotEmpty( $values['key'] );
		$this->assertSame( $fixture->user->user_login, urldecode( $values['login'] ) );

		$this->assertSame( $fixture->user->user_login, check_password_reset_key( $values['key'], urldecode( $values['login'] ) )->user_login );
	}


	public function test_contact_form_link(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->triggerEmail();
		$this->assertStringNotContainsString( 'contact form', $mailer->get_sent()->body );
		$this->assertStringNotContainsString( 'reply to this email', $mailer->get_sent()->body );

		Settings::in()->update_option( Settings::CONTACT, 'https://example.com/contact' );
		$this->triggerEmail();
		$this->assertStringNotContainsString( 'reply to this email', $mailer->get_sent( 1 )->body );
		$this->assertStringContainsString( 'You may use our <a href="https://example.com/contact">contact form</a> to recieve help.', $mailer->get_sent( 1 )->body );

		Settings::in()->update_option( Settings::EMAIL, 'support@test.com' );
		$this->triggerEmail();
		$this->assertStringContainsString( 'You may reply to this email or use our <a href="https://example.com/contact">contact form</a> to receive help.', $mailer->get_sent( 2 )->body );

		unset( Settings::in()[ Settings::CONTACT ] );
		$this->triggerEmail();
		$this->assertStringNotContainsString( 'contact form', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'You may reply to this email to receive help.', $mailer->get_sent( 3 )->body );
	}


	public function test_sender_email(): void {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->triggerEmail();
		$this->assertStringContainsString( 'From: WordPress <wordpress@limit-logins.loc>', $mailer->get_sent()->header );

		Settings::in()->update_option( Settings::EMAIL, 'support@test.com' );
		$this->triggerEmail();
		$this->assertStringContainsString( 'From: WordPress <support@test.com>', $mailer->get_sent( 1 )->header );
	}


	private function triggerEmail(): \Fixture_Blocked_User {
		static $count = 0;
		$_SERVER['REMOTE_ADDR'] = '88.88.88.' . $count;
		++ $count;
		/** @var \Fixture_Blocked_User $fixture */
		$fixture = require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		return $fixture;
	}

}

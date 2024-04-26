<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings;
use Lipe\Limit_Logins\Utils;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class PreviewTest extends \WP_UnitTestCase {

	public function test_get_url(): void {
		$nonce = get_private_property( Preview::in(), 'NONCE' );
		$user = self::factory()->user->create_and_get( [
			'role' => 'administrator',
		] );
		$this->assertSame( '', Preview::in()->get_url() );

		// current_user_can( 'manage_options' )
		wp_set_current_user( $user->ID );
		$this->assertSame( '', Preview::in()->get_url() );

		// is_admin
		$GLOBALS['current_screen'] = convert_to_screen( 'options-general' );
		$this->assertSame( '', Preview::in()->get_url() );

		// Blocks exist
		require \dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$pattern = '/http:\/\/limit-logins\.loc\/api\/lipe__limit_logins__email__preview\/\?_wpnonce=(\w+)/';
		$this->assertMatchesRegularExpression( $pattern, Preview::in()->get_url() );
		preg_match( $pattern, Preview::in()->get_url(), $matches );
		$this->assertSame( 1, wp_verify_nonce( $matches[1], $nonce ) );

		// Invalid username
		$attempts = \array_map( fn( Attempt $attempt ) => $attempt->jsonSerialize(), Attempts::in()->get_all() );
		$attempts[0]['username'] = 'invalid';
		Settings::in()->update_option( Settings::LOGGED_FAILURES, $attempts );
		$this->assertSame( '', Preview::in()->get_url() );
	}


	public function test_permission(): void {
		$nonce = get_private_property( Preview::in(), 'NONCE' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce );
		$this->assertEmpty( get_echo( fn() => call_private_method( Preview::in(), 'preview' ) ) );

		// Blocks exist
		require \dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
		$this->assertEmpty( get_echo( fn() => call_private_method( Preview::in(), 'preview' ) ) );

		wp_set_current_user( self::factory()->user->create( [
			'role' => 'administrator',
		] ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce );

		$contents = $this->getContents();
		$this->assertStringContainsString( 'Your Limit Login Tests account is locked.', $contents );
	}


	public function test_is_preview(): void {
		$email = new class() implements Email {
			public function get_email_addresses(): array {
				return [ new EmailAddress( 'fake@email.com' ) ];
			}


			public function get_subject(): string {
				return 'Test';
			}


			public function get_message(): string {
				return '';
			}
		};

		$this->assertFalse( Preview::in()->is_preview() );

		Util::in()->send( $email );
		$this->assertFalse( Preview::in()->is_preview() );

		ob_start();
		try {
			call_private_method( Preview::in(), 'render', [ $email ] );
		} catch ( \OutOfBoundsException ) {
		} finally {
			$this->assertTrue( Preview::in()->is_preview() );
		}
		ob_end_clean();
	}


	public function test_not_expose_reset_links(): void {
		$nonce = get_private_property( Preview::in(), 'NONCE' );
		wp_set_current_user( self::factory()->user->create( [
			'role' => 'administrator',
		] ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce );
		require \dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';

		$contents = $this->getContents();
		$this->assertTrue( Preview::in()->is_preview() );
		preg_match( '/"http:\/\/limit-logins\.loc\/wp-login\.php\?action=rp&key=(?P<key>[\w-]+)&login=(?P<login>.+)"/', $contents, $values );
		$this->assertNotEmpty( $values['key'] );
		$this->assertSame( 'preview-key', $values['key'] );
	}


	public static function tearDownAfterClass(): void {
		self::assertFalse( Preview::in()->is_preview() );
		self::assertFalse( Utils::in()->did_exit );
		parent::tearDownAfterClass();
	}


	private function getContents(): string {
		ob_start();
		try {
			call_private_method( Preview::in(), 'preview' );
		} catch ( \OutOfBoundsException $e ) {
			$caught = true;
			$this->assertSame( 'Exit called in test context.', $e->getMessage() );
		} finally {
			$this->assertTrue( Utils::in()->did_exit );
			$this->assertTrue( isset( $caught ) && $caught );
		}
		return ob_get_clean();
	}
}

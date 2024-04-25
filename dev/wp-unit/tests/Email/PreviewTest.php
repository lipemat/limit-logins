<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings;

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
		require dirname( __DIR__, 2 ) . '/fixtures/blocked-user.php';
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
}

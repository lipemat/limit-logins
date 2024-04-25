<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Meta\Repo;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
class SettingsTest extends \WP_UnitTestCase {

	public function test_clear_limit_login_attempts(): void {
		global $wpdb;
		foreach ( $this->limitLoginAttemptsOptions() as $option ) {
			update_option( $option['option_name'], $option['option_value'], $option['autoload'] );
			$this->assertNotFalse( get_option( $option['option_name'] ) );
		}
		$global_count = $wpdb->get_var( 'SELECT COUNT(*) from ' . $wpdb->options );
		$this->assertSame( 'checkbox', call_private_method( Repo::in(), 'get_field', [ Settings::CLEAR ] )->get_type() );

		$find_query = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", 'limit_login_%' );
		$this->assertSame( 24, (int) $wpdb->get_var( $find_query ) );

		Settings::in()->update_option( Settings::CLEAR, true );
		wp_cache_flush();
		// One option was added when CLEAR is set.
		$this->assertSame( ( $global_count + 1 ) - 24, (int) $wpdb->get_var( 'SELECT COUNT(*) from ' . $wpdb->options ) );
		$this->assertSame( '0', $wpdb->get_var( $find_query ) );
		foreach ( $this->limitLoginAttemptsOptions() as $option ) {
			$this->assertFalse( get_option( $option['option_name'] ) );
		}

		do_action( 'cmb2_init' );
		$this->assertSame( 'title', call_private_method( Repo::in(), 'get_field', [ Settings::CLEAR ] )->get_type() );
	}


	private function defaultError( string $username ): string {
		return '<strong>Error:</strong> The password you entered for the username <strong>' . $username . '</strong> is incorrect. <a href="http://limit-logins.loc/wp-login.php?action=lostpassword">Lost your password?</a>';
	}


	private function tooManyError(): string {
		return call_private_method( Authenticate::in(), 'get_error' );
	}


	/**
	 * @return list<array{
	 *     option_id: string,
	 *     option_name: string,
	 *     option_value: string,
	 *     autoload: 'yes'|'no',
	 * }>
	 */
	private function limitLoginAttemptsOptions(): array {
		return [
			[
				'option_id' => '41245', 'option_name' => 'limit_login_retries', 'option_value' => 'a:66:{s:12:"170.39.76.22";i:2;s:14:"138.197.44.248";i:2;s:14:"14.241.133.220";i:2;s:14:"173.219.72.221";i:2;s:13:"198.71.226.38";i:1;s:13:"34.123.24.122";i:1;s:12:"35.236.46.71";i:1;s:12:"50.62.176.58";i:1;s:14:"35.221.245.153";i:1;s:14:"138.68.248.154";i:2;}', 'autoload' => 'no',
			],
			[
				'option_id' => '41246', 'option_name' => 'limit_login_retries_valid', 'option_value' => 'a:66:{s:12:"170.39.76.22";i:1707235743;s:14:"138.197.44.248";i:1707240295;s:14:"14.241.133.220";i:1707240464;s:14:"173.219.72.221";i:1707240713;s:13:"198.71.226.38";i:1707235348;s:13:"34.123.24.122";i:1707235350;s:12:"35.236.46.71";i:1707235398;s:12:"50.62.176.58";i:1707235438;s:14:"35.221.245.153";i:1707235438;s:14:"138.68.248.154"}', 'autoload' => 'no',
			],
			[ 'option_id' => '59349', 'option_name' => 'limit_login_lockouts_total', 'option_value' => '43', 'autoload' => 'no' ],
			[ 'option_id' => '61413', 'option_name' => 'limit_login_client_type', 'option_value' => 'REMOTE_ADDR', 'autoload' => 'yes' ],
			[ 'option_id' => '61414', 'option_name' => 'limit_login_allowed_retries', 'option_value' => '5', 'autoload' => 'yes' ],
			[ 'option_id' => '61415', 'option_name' => 'limit_login_lockout_duration', 'option_value' => '1800', 'autoload' => 'yes' ],
			[ 'option_id' => '61416', 'option_name' => 'limit_login_allowed_lockouts', 'option_value' => '2', 'autoload' => 'yes' ],
			[ 'option_id' => '61417', 'option_name' => 'limit_login_long_duration', 'option_value' => '172800', 'autoload' => 'yes' ],
			[ 'option_id' => '61418', 'option_name' => 'limit_login_valid_duration', 'option_value' => '43200', 'autoload' => 'yes' ],
			[ 'option_id' => '61419', 'option_name' => 'limit_login_lockout_notify', 'option_value' => 'email', 'autoload' => 'yes' ],
			[ 'option_id' => '61420', 'option_name' => 'limit_login_notify_email_after', 'option_value' => '2', 'autoload' => 'yes' ],
			[ 'option_id' => '61421', 'option_name' => 'limit_login_cookies', 'option_value' => '1', 'autoload' => 'yes' ],
			[ 'option_id' => '61701', 'option_name' => 'limit_login_lockouts', 'option_value' => 'a:0:{}', 'autoload' => 'yes' ],
			[
				'option_id' => '62299', 'option_name' => 'limit_login_logged', 'option_value' => 'a:30:{s:12:"68.105.59.46";a:1:{s:5:"admin";a:4:{s:7:"counter";i:1;s:4:"date";i:1697221008;s:7:"gateway";s:11:"WooCommerce";s:8:"unlocked";b:1;}}s:13:"20.106.94.205";a:1:{s:5:"admin";a:4:{s:7:"counter";i:2;s:4:"date";i:1697368197;s:7:"gateway";s:8:"WP Login";s:8:"unlocked";b:1;}}s:12:"58.212.43.54";}}}', 'autoload' => 'no',
			],
			[ 'option_id' => '62327', 'option_name' => 'limit_login_gdpr', 'option_value' => '0', 'autoload' => 'yes' ],
			[ 'option_id' => '62328', 'option_name' => 'limit_login_admin_notify_email', 'option_value' => '', 'autoload' => 'yes' ],
			[ 'option_id' => '62329', 'option_name' => 'limit_login_whitelist', 'option_value' => 'a:0:{}', 'autoload' => 'yes' ],
			[ 'option_id' => '62330', 'option_name' => 'limit_login_whitelist_usernames', 'option_value' => 'a:0:{}', 'autoload' => 'yes' ],
			[ 'option_id' => '62331', 'option_name' => 'limit_login_blacklist', 'option_value' => 'a:0:{}', 'autoload' => 'yes' ],
			[ 'option_id' => '62332', 'option_name' => 'limit_login_blacklist_usernames', 'option_value' => 'a:0:{}', 'autoload' => 'yes' ],
			[ 'option_id' => '62333', 'option_name' => 'limit_login_trusted_ip_origins', 'option_value' => 'a:1:{i:0;s:11:"REMOTE_ADDR";}', 'autoload' => 'yes' ],
			[ 'option_id' => '63169', 'option_name' => 'limit_login_activation_timestamp', 'option_value' => '1568251430', 'autoload' => 'yes' ],
			[ 'option_id' => '63170', 'option_name' => 'limit_login_review_notice_shown', 'option_value' => '1', 'autoload' => 'yes' ],
			[ 'option_id' => '64780', 'option_name' => 'limit_login_notice_enable_notify_timestamp', 'option_value' => '1705857372', 'autoload' => 'yes' ],
		];
	}
}

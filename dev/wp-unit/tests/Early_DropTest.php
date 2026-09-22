<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Attempts\Gateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Mat Lipe
 * @since  September 2026
 *
 */
#[CoversClass( Early_Drop::class )]
final class Early_DropTest extends \WP_UnitTestCase {
	private const string BLOCKED_IP   = '5.5.5.5';
	private const string BLOCKED_USER = 'blocked-user';
	private const string OTHER_IP     = '6.6.6.6';

	/**
	 * Codes sent through `status_header()`.
	 *
	 * @var list<int>
	 */
	private array $statuses = [];

	/**
	 * Write queries sent to the database.
	 *
	 * @var list<string>
	 */
	private array $writes = [];

	/**
	 * Request globals restored after each test.
	 *
	 * @var array{server: array<string, mixed>, request: array<string, mixed>, pagenow: mixed}
	 */
	private array $request = [];


	protected function setUp(): void {
		parent::setUp();
		$this->request = [
			'server'  => $_SERVER,
			'request' => $_REQUEST,
			'pagenow' => $GLOBALS['pagenow'] ?? null,
		];
		$_SERVER['REMOTE_ADDR'] = self::BLOCKED_IP;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['PHP_SELF'] = '/index.php';

		add_filter( 'status_header', function( string $header, int $code ): string {
			$this->statuses[] = $code;
			return $header;
		}, 10, 2 );
		add_filter( 'query', function( string $query ): string {
			if ( 1 === \preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\s/i', $query ) ) {
				$this->writes[] = $query;
			}
			return $query;
		} );
	}


	protected function tearDown(): void {
		$_SERVER = $this->request['server'];
		$_REQUEST = $this->request['request'];
		$GLOBALS['pagenow'] = $this->request['pagenow'];
		parent::tearDown();
	}


	public function test_wp_login_blocked_ip(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = 'someone-else';
		$_POST['pwd'] = 'password';

		$this->assertDropped();
	}


	public function test_wp_login_blocked_username_other_ip(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;
		$_POST['pwd'] = 'password';

		$this->assertDropped();
	}


	public function test_wp_login_explicit_login_action(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = 'login';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertDropped();
	}


	/**
	 * Core falls back to the login screen for any action it does not recognize.
	 */
	public function test_wp_login_unrecognized_action(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = 'not-a-real-action';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertDropped();
	}


	public function test_wp_login_unblocked(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = 'someone-else';
		$_POST['pwd'] = 'password';

		$this->assertNotDropped();
	}


	public function test_wp_login_without_log_field(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['pwd'] = 'password';

		$this->assertNotDropped();
	}


	public function test_wp_login_get_form(): void {
		$this->block();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['pagenow'] = 'wp-login.php';

		$this->assertNotDropped();
	}


	/**
	 * A Composer autoloader can include the plugin before `vars.php` sets `$pagenow`.
	 */
	public function test_wp_login_before_pagenow_is_set(): void {
		$this->block();
		unset( $GLOBALS['pagenow'] );
		$_SERVER['PHP_SELF'] = '/wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertDropped();
	}


	public function test_non_login_script_before_pagenow_is_set(): void {
		$this->block();
		unset( $GLOBALS['pagenow'] );
		$_SERVER['PHP_SELF'] = '/index.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	public function test_request_uri_fallback(): void {
		$this->block();
		unset( $GLOBALS['pagenow'] );
		$_SERVER['PHP_SELF'] = '';
		$_SERVER['REQUEST_URI'] = '/wp-login.php?redirect_to=%2Fwp-admin%2F';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertDropped();
	}


	/**
	 * A query string may name `wp-login.php` on a request going somewhere else.
	 */
	public function test_request_uri_fallback_query_string(): void {
		$this->block();
		unset( $GLOBALS['pagenow'] );
		$_SERVER['PHP_SELF'] = '';
		$_SERVER['REQUEST_URI'] = '/contact/?redirect_to=/wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	/**
	 * The credentials are only a submission when they arrive on a POST.
	 */
	public function test_wp_login_non_post_method(): void {
		$this->block();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	public function test_woo_login_blocked_ip(): void {
		$this->block();
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['username'] = 'someone-else';
		$_POST['password'] = 'password';

		$this->assertDropped();
	}


	public function test_woo_login_blocked_username_other_ip(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['username'] = self::BLOCKED_USER;
		$_POST['password'] = 'password';

		$this->assertDropped();
	}


	public function test_woo_login_unblocked(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['username'] = 'someone-else';
		$_POST['password'] = 'password';

		$this->assertNotDropped();
	}


	public function test_woo_login_without_username(): void {
		$this->block();
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['password'] = 'password';

		$this->assertNotDropped();
	}


	/**
	 * A front end form may use a `log` field of its own without being a login.
	 */
	public function test_non_login_request(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'index.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	public function test_expired_block(): void {
		$this->block( [
			Attempt::EXPIRES => \time() - 1,
		] );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	public function test_count_below_allowed_attempts(): void {
		$this->block( [
			Attempt::COUNT => Attempts::ALLOWED_ATTEMPTS - 1,
		] );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	public function test_partial_failure_record(): void {
		update_option( Settings::NAME, [
			Settings::LOGGED_FAILURES => [
				[
					Attempt::IP       => self::BLOCKED_IP,
					Attempt::USERNAME => self::BLOCKED_USER,
				],
			],
		] );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped();
	}


	/**
	 * The dropped page carries the same message the `authenticate` fallback returns.
	 */
	public function test_blocked_message_matches_authenticate(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$rendered = $this->drop();

		$this->assertStringContainsString( Authenticate::in()->get_blocked_message(), $rendered, 'The drop should render the gateway blocked message.' );
	}


	/**
	 * A `log` field alongside one of core's own actions is still not a login.
	 */
	#[DataProvider( 'providerNonLoginActions' )]
	public function test_core_action( string $action, string $message ): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = $action;
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertNotDropped( $message );
	}


	public function test_unlock_link_action(): void {
		$this->block();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = 'unlock-account';
		$_GET['unlock-key'] = 'a-key';

		$this->assertNotDropped();
	}


	/**
	 * Assert the request exited with a `403` blocked page and no database writes.
	 */
	private function assertDropped(): void {
		$rendered = $this->drop();

		$this->assertTrue( Utils::in()->did_exit, 'The request should have exited.' );
		$this->assertSame( [ 403 ], $this->statuses, 'The response should be a 403.' );
		$this->assertSame( [], $this->writes, 'A blocked attempt should not write to the database.' );
		$this->assertStringContainsString( 'Too many failed login attempts.', $rendered, 'The blocked message should be rendered.' );
		$this->assertStringContainsString( esc_url( wp_lostpassword_url() ), $rendered, 'A lost password link should be rendered.' );
	}


	/**
	 * Assert the request was left alone for the rest of WordPress to handle.
	 */
	private function assertNotDropped( string $message = 'The request should not have been dropped.' ): void {
		$rendered = $this->drop();

		$this->assertFalse( Utils::in()->did_exit, $message );
		$this->assertSame( '', $rendered, $message );
		$this->assertSame( [], $this->statuses, $message );
	}


	/**
	 * Run the early check and return anything it rendered.
	 */
	private function drop(): string {
		$this->statuses = [];
		$this->writes = [];

		\ob_start();
		try {
			call_private_method( Early_Drop::in(), 'maybe_drop' );
		} catch ( \OutOfBoundsException ) {
		}

		return (string) \ob_get_clean();
	}


	/**
	 * Store a single blocked failure for `self::BLOCKED_IP` and `self::BLOCKED_USER`.
	 *
	 * @param array<string, int|string> $overrides
	 */
	private function block( array $overrides = [] ): void {
		$attempt = Attempt::factory( \array_merge( [
			Attempt::IP       => self::BLOCKED_IP,
			Attempt::USERNAME => self::BLOCKED_USER,
			Attempt::GATEWAY  => Gateway::WP_LOGIN->value,
			Attempt::COUNT    => Attempts::ALLOWED_ATTEMPTS,
			Attempt::EXPIRES  => \time() + Attempts::DURATION,
		], $overrides ) );

		update_option( Settings::NAME, [
			Settings::LOGGED_FAILURES => [ $attempt->jsonSerialize() ],
		] );
	}


	/**
	 * Every action core handles itself, from `$default_actions` in `wp-login.php`.
	 *
	 * @return array<string, array{action: string, message: string}>
	 */
	public static function providerNonLoginActions(): array {
		$actions = [
			'checkemail',
			'confirm_admin_email',
			'confirmaction',
			'entered_recovery_mode',
			'logout',
			'lostpassword',
			'postpass',
			'register',
			'resetpass',
			'retrievepassword',
			'rp',
		];

		$rows = [];
		foreach ( $actions as $action ) {
			$rows[ $action ] = [
				'action'  => $action,
				'message' => "The {$action} action is handled by core and is not a login submission.",
			];
		}

		return $rows;
	}
}

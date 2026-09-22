<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

/**
 * @author Mat Lipe
 * @since  September 2026
 *
 */
class AuthenticateTest extends \WP_UnitTestCase {
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


	protected function setUp(): void {
		parent::setUp();
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


	public function test_wp_login_blocked_ip(): void {
		$this->blockedUser();
		$user = self::factory()->user->create_and_get( [
			'user_pass' => 'other-password',
		] );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'other-password';

		$this->assertSignonBlocked( [] );
	}


	public function test_wp_login_blocked_username(): void {
		$fixture = $this->blockedUser();
		$_SERVER['REMOTE_ADDR'] = '3.3.3.3';
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = $fixture->user->user_login;
		$_POST['pwd'] = $fixture->password;

		$this->assertSignonBlocked( [] );
	}


	public function test_wp_login_empty_password(): void {
		$fixture = $this->blockedUser();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = $fixture->user->user_login;

		$this->assertSignonBlocked( [] );
	}


	public function test_wp_login_form(): void {
		$this->blockedUser();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$this->statuses = [];

		$result = wp_signon();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( Authenticate::CODE_BLOCKED, $result->get_error_code() );
		$this->assertSame( [], $this->statuses );
	}


	public function test_woo_login(): void {
		$fixture = $this->blockedUser();
		$_REQUEST['woocommerce-login-nonce'] = 'nonce';

		$this->assertSignonBlocked( [
			'user_login'    => $fixture->user->user_login,
			'user_password' => $fixture->password,
		] );
	}


	public function test_custom_wp_signon(): void {
		$fixture = $this->blockedUser();

		$this->assertSignonBlocked( [
			'user_login'    => $fixture->user->user_login,
			'user_password' => $fixture->password,
		] );
		$this->assertFalse( Utils::in()->did_exit );
	}


	public function test_unblocked(): void {
		$user = self::factory()->user->create_and_get( [
			'user_pass' => 'password',
		] );
		$this->writes = [];
		$checks = did_filter( 'check_password' );

		$result = wp_authenticate( $user->user_login, 'not valid password' );
		$this->assertSame( 'incorrect_password', $result->get_error_code() );
		$this->assertSame( $checks + 1, did_filter( 'check_password' ) );
		$this->assertNotEmpty( $this->writes );
		$this->assertSame( 1, Attempts::in()->get_existing( $user->user_login )->get_count() );
		$this->assertInstanceOf( \WP_User::class, wp_authenticate( $user->user_login, 'password' ) );
	}


	public function test_block_removed(): void {
		$fixture = $this->blockedUser();
		Attempts::in()->remove_block( $fixture->user->user_login );

		$this->assertInstanceOf( \WP_User::class, wp_authenticate( $fixture->user->user_login, $fixture->password ) );
	}


	/**
	 * Sign on and assert it was blocked without a password check or a DB write.
	 *
	 * @param array{user_login?: string, user_password?: string} $credentials
	 */
	private function assertSignonBlocked( array $credentials ): void {
		$this->statuses = [];
		$this->writes = [];
		$checks = did_filter( 'check_password' );

		$result = wp_signon( $credentials );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( Authenticate::CODE_BLOCKED, $result->get_error_code() );
		$this->assertSame( call_private_method( Authenticate::in(), 'get_error' ), $result->get_error_message() );
		$this->assertSame( $checks, did_filter( 'check_password' ), 'The password was checked.' );
		$this->assertSame( [ 403 ], $this->statuses );
		$this->assertSame( [], $this->writes );

		foreach ( Authenticate::AUTH_CALLBACKS as $callback ) {
			$this->assertSame( 20, has_filter( 'authenticate', $callback ) );
		}
	}


	private function blockedUser(): \Fixture_Blocked_User {
		return require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
	}
}

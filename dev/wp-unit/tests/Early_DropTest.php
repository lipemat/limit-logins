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
	private const string REST_PATH    = '/wp-json/wp/v2/users/me';

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
	 * @var array{server: array<string, mixed>, request: array<string, mixed>, cookie: array<string, mixed>, pagenow: mixed}
	 */
	private array $request = [];


	protected function setUp(): void {
		parent::setUp();
		$this->request = [
			'server'  => $_SERVER,
			'request' => $_REQUEST,
			'cookie'  => $_COOKIE,
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
		unset( $GLOBALS['wp_xmlrpc_server'] );
		$_SERVER = $this->request['server'];
		$_REQUEST = $this->request['request'];
		$_COOKIE = $this->request['cookie'];
		$GLOBALS['pagenow'] = $this->request['pagenow'];
		parent::tearDown();
	}


	public function test_wp_login_blocked_ip(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = 'someone-else';
		$_POST['pwd'] = 'password';

		$this->assertFormDropped();
	}


	public function test_wp_login_blocked_username_other_ip(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;
		$_POST['pwd'] = 'password';

		$this->assertFormDropped();
	}


	public function test_wp_login_explicit_login_action(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = 'login';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertFormDropped();
	}


	/**
	 * Core falls back to the login screen for any action it does not recognize.
	 */
	public function test_wp_login_unrecognized_action(): void {
		$this->block();
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_REQUEST['action'] = 'not-a-real-action';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertFormDropped();
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

		$this->assertFormDropped();
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

		$this->assertFormDropped();
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

		$this->assertFormDropped();
	}


	public function test_woo_login_blocked_username_other_ip(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['username'] = self::BLOCKED_USER;
		$_POST['password'] = 'password';

		$this->assertFormDropped();
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


	public function test_xmlrpc_blocked_ip(): void {
		$this->block();
		$this->xmlrpc( 'wp.getUsersBlogs' );

		$this->assertXmlrpcDropped();
	}


	/**
	 * The calls `system.multicall` wraps carry credentials of their own.
	 */
	public function test_xmlrpc_multicall(): void {
		$this->block();
		$this->xmlrpc( 'system.multicall' );

		$this->assertXmlrpcDropped();
	}


	/**
	 * The fault is built by hand because `class-IXR.php` is not loaded this early.
	 */
	public function test_xmlrpc_fault_matches_ixr_error(): void {
		$this->block();
		$this->xmlrpc( 'wp.getUsersBlogs' );

		$rendered = $this->assertDropped();

		$expected = new \IXR_Error( Authenticate::CODE_BLOCKED, Authenticate::MESSAGE_BLOCKED );
		$this->assertXmlStringEqualsXmlString( $expected->getXml(), $rendered, 'The fault should match the one `IXR_Error` renders.' );
	}


	#[DataProvider( 'providerUnauthenticatedMethods' )]
	public function test_xmlrpc_unauthenticated_method( string $method, string $message ): void {
		$this->block();
		$this->xmlrpc( $method );

		$this->assertNotDropped( $message );
	}


	/**
	 * The username is inside the method parameters, which the `authenticate`
	 * fallback reads once core has parsed the request.
	 */
	public function test_xmlrpc_username_only_block(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$this->xmlrpc( 'wp.getUsersBlogs' );

		$this->assertTrue( Attempts::in()->is_blocked( self::BLOCKED_USER ), 'The username should still be blocked.' );
		$this->assertNotDropped( 'A username only block is left to the `authenticate` fallback.' );
	}


	public function test_xmlrpc_empty_body(): void {
		$this->block();
		$this->xmlrpc( 'wp.getUsersBlogs' );
		$this->requestBody( '' );

		$this->assertNotDropped();
	}


	public function test_xmlrpc_body_without_a_method_name(): void {
		$this->block();
		$this->xmlrpc( 'wp.getUsersBlogs' );
		$this->requestBody( '<?xml version="1.0"?><methodCall><params /></methodCall>' );

		$this->assertNotDropped();
	}


	/**
	 * Core's parser dispatches a CDATA wrapped method name like any other.
	 */
	public function test_xmlrpc_cdata_method_name(): void {
		$this->block();
		$this->xmlrpc( 'wp.getUsersBlogs' );
		$this->requestBody( '<?xml version="1.0"?><methodCall><methodName><![CDATA[wp.getUsersBlogs]]></methodName><params /></methodCall>' );

		$this->assertXmlrpcDropped();
	}


	/**
	 * A method call posted to anything but `xmlrpc.php` is just a form post.
	 */
	public function test_method_call_outside_an_xmlrpc_request(): void {
		$this->block();
		$this->requestBody( self::methodCall( 'wp.getUsersBlogs' ) );

		$this->assertNotDropped();
	}


	public function test_rest_blocked_ip(): void {
		$this->block();
		$this->rest( self::REST_PATH, 'someone-else' );

		$this->assertRestDropped();
	}


	public function test_rest_blocked_username_other_ip(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$this->rest( self::REST_PATH, self::BLOCKED_USER );

		$this->assertRestDropped();
	}


	/**
	 * Sites without pretty permalinks reach the API through `rest_route`.
	 */
	public function test_rest_route_query_var(): void {
		$this->block();
		$this->rest( '/index.php?rest_route=/wp/v2/users/me', self::BLOCKED_USER );
		$_REQUEST['rest_route'] = '/wp/v2/users/me';

		$this->assertRestDropped();
	}


	/**
	 * Core ignores an empty `rest_route`, so it is not a REST request.
	 */
	public function test_empty_rest_route_query_var(): void {
		$this->block();
		$this->rest( '/index.php?rest_route=', self::BLOCKED_USER );
		$_REQUEST['rest_route'] = '';

		$this->assertNotDropped();
	}


	/**
	 * An unvalidated login cookie is not proof of a login, so it never stops
	 * the drop. Only reachable behind server Basic auth.
	 */
	public function test_rest_with_a_login_cookie(): void {
		$this->block();
		$this->rest( self::REST_PATH, self::BLOCKED_USER );
		$_COOKIE[ LOGGED_IN_COOKIE ] = 'a-cookie';

		$this->assertRestDropped();
	}


	/**
	 * The API index carries no path after the prefix.
	 */
	public function test_rest_index(): void {
		$this->block();
		$this->rest( '/wp-json', self::BLOCKED_USER );

		$this->assertRestDropped();
	}


	public function test_rest_in_a_subdirectory_install(): void {
		$this->block();
		$this->rest( '/blog/wp-json/wp/v2/users/me', self::BLOCKED_USER );

		$this->assertRestDropped();
	}


	/**
	 * Cookie authenticated REST requests send no `PHP_AUTH_USER`.
	 */
	public function test_rest_without_credentials(): void {
		$this->block();
		$this->rest( self::REST_PATH, self::BLOCKED_USER );
		unset( $_SERVER['PHP_AUTH_USER'] );

		$this->assertNotDropped( 'A REST request without credentials of its own should never be dropped.' );
	}


	public function test_rest_unblocked(): void {
		$this->block();
		$_SERVER['REMOTE_ADDR'] = self::OTHER_IP;
		$this->rest( self::REST_PATH, 'someone-else' );

		$this->assertNotDropped();
	}


	/**
	 * A site behind HTTP Basic auth sends `PHP_AUTH_USER` on every request.
	 */
	public function test_basic_auth_outside_the_rest_api(): void {
		$this->block();
		$this->rest( '/wp-admin/edit.php', self::BLOCKED_USER );

		$this->assertNotDropped();
	}


	/**
	 * A query string may name the REST prefix on a request going somewhere else.
	 */
	public function test_rest_prefix_in_the_query_string(): void {
		$this->block();
		$this->rest( '/contact/?redirect=/wp-json/wp/v2/users/me', self::BLOCKED_USER );

		$this->assertNotDropped();
	}


	public function test_disabled_setting_on_the_login_form(): void {
		$this->block();
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, true );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;
		$_POST['pwd'] = 'password';

		$this->assertNotDropped( 'The disabled setting should leave the login form alone.' );
	}


	public function test_disabled_setting_on_a_woo_login(): void {
		$this->block();
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, true );
		$_POST[ Gateway::WOO_NONCE_FIELD ] = 'nonce';
		$_POST['username'] = self::BLOCKED_USER;

		$this->assertNotDropped( 'The disabled setting should leave the WooCommerce login alone.' );
	}


	public function test_disabled_setting_on_xmlrpc(): void {
		$this->block();
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, true );
		$this->xmlrpc( 'wp.getUsersBlogs' );

		$this->assertNotDropped( 'The disabled setting should leave the XML-RPC call alone.' );
	}


	public function test_disabled_setting_on_rest(): void {
		$this->block();
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, true );
		$this->rest( self::REST_PATH, self::BLOCKED_USER );

		$this->assertNotDropped( 'The disabled setting should leave the REST request alone.' );
	}


	/**
	 * Only the checked value disables the drop, whatever else is stored.
	 */
	public function test_unchecked_setting(): void {
		$this->block();
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, false );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertFormDropped();
	}


	public function test_setting_defaults_to_enabled(): void {
		$this->block();
		$this->assertArrayNotHasKey( Settings::DISABLE_EARLY_DROP, get_option( Settings::NAME ), 'The setting should be unset.' );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = self::BLOCKED_USER;

		$this->assertFormDropped();
	}


	/**
	 * The submission the drop let through is still rejected by `authenticate`.
	 */
	public function test_disabled_setting_still_rejects(): void {
		$fixture = require \dirname( __DIR__ ) . '/fixtures/blocked-user.php';
		Settings::in()->update_option( Settings::DISABLE_EARLY_DROP, true );
		$GLOBALS['pagenow'] = 'wp-login.php';
		$_POST['log'] = $fixture->user->user_login;
		$_POST['pwd'] = $fixture->password;
		$this->assertNotDropped();

		$result = wp_signon();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( Authenticate::CODE_BLOCKED, $result->get_error_code() );
		$this->assertSame( Authenticate::in()->get_blocked_message(), $result->get_error_message() );
	}


	/**
	 * Assert the request exited with a `403` and no database writes.
	 *
	 * @return string The rendered response body.
	 */
	private function assertDropped(): string {
		$rendered = $this->drop();

		$this->assertTrue( Utils::in()->did_exit, 'The request should have exited.' );
		$this->assertSame( [ 403 ], $this->statuses, 'The response should be a 403.' );
		$this->assertSame( [], $this->writes, 'A blocked attempt should not write to the database.' );
		$this->assertStringContainsString( Authenticate::MESSAGE_BLOCKED, $rendered, 'The blocked message should be rendered.' );

		return $rendered;
	}


	/**
	 * Assert the login form request exited with the `403` blocked page.
	 */
	private function assertFormDropped(): void {
		$rendered = $this->assertDropped();

		$this->assertEqualHTML( '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Login Blocked</title></head><body><p>' . Authenticate::in()->get_blocked_message() . '</p><p><a href="' . esc_url( wp_lostpassword_url() ) . '">Lost your password?</a></p></body></html>', $rendered );

		$this->assertStringContainsString( '<a href="' . esc_url( wp_lostpassword_url() ) . '">Lost your password?</a>', $rendered, 'A lost password link should be rendered.' );
	}


	/**
	 * Assert the XML-RPC request exited with a `403` and a blocked fault.
	 */
	private function assertXmlrpcDropped(): void {
		$rendered = $this->assertDropped();

		$this->assertStringContainsString( '<name>faultCode</name><value><int>' . Authenticate::CODE_BLOCKED . '</int></value>', $rendered, 'An XML-RPC fault should be rendered.' );
	}


	/**
	 * Assert the REST request exited with a `403` and the blocked JSON error,
	 * byte for byte as a client receives it.
	 */
	private function assertRestDropped(): void {
		$rendered = $this->assertDropped();

		$this->assertSame( '{"code":"blocked","message":"Too many failed login attempts.","data":{"status":403}}', $rendered, 'The blocked JSON error should be rendered.' );
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
	 * Make the request an XML-RPC call to `$method`.
	 */
	private function xmlrpc( string $method ): void {
		$GLOBALS['wp_xmlrpc_server'] = new \wp_xmlrpc_server();
		$this->requestBody( self::methodCall( $method ) );
	}


	/**
	 * Make the request a REST request to `$path` with Basic auth for `$username`.
	 */
	private function rest( string $path, string $username ): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = $path;
		$_SERVER['PHP_AUTH_USER'] = $username;
	}


	/**
	 * Serve `$body` as the raw request body, which `php://input` cannot provide here.
	 */
	private function requestBody( string $body ): void {
		change_container_object( Utils::class, new class( $body ) extends Utils {
			public function __construct( private readonly string $body ) {
			}


			public function get_request_body(): string {
				return $this->body;
			}
		} );
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
	 * The body of an XML-RPC call to `$method`.
	 */
	private static function methodCall( string $method ): string {
		return '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName><params><param><value><string>' . self::BLOCKED_USER . '</string></value></param></params></methodCall>';
	}


	/**
	 * Every action core handles itself, from `$default_actions` in `wp-login.php`.
	 * Keep these expectations independent of the early-drop deny list.
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


	/**
	 * XML-RPC methods core serves to anonymous callers.
	 * Keep these expectations independent of the early-drop allow list.
	 *
	 * @return array<string, array{method: string, message: string}>
	 */
	public static function providerUnauthenticatedMethods(): array {
		$methods = [
			'demo.addTwoNumbers',
			'demo.sayHello',
			'pingback.extensions.getPingbacks',
			'pingback.ping',
			'system.getCapabilities',
			'system.listMethods',
		];

		$rows = [];
		foreach ( $methods as $method ) {
			$rows[ $method ] = [
				'method'  => $method,
				'message' => "The {$method} method is served without credentials.",
			];
		}

		return $rows;
	}
}

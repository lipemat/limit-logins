<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Api\Api;
use Lipe\Limit_Logins\Email\Preview;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Mat Lipe
 * @since  November 2026
 *
 */
#[CoversFunction( 'Lipe\Limit_Logins\register_providers' )]
final class Register_ProvidersTest extends \WP_UnitTestCase {
	protected function tearDown(): void {
		wp_installing( false );
		parent::tearDown();
	}


	/**
	 * @param \Closure(): string $hook
	 */
	#[DataProvider( 'providerProviderHooks' )]
	public function test_register_providers_adds_hooks( \Closure $hook, string $message ): void {
		remove_all_filters( $hook() );

		register_providers();

		$this->assertTrue( has_filter( $hook() ), $message );
	}


	public function test_register_providers_skips_hooks_while_installing(): void {
		remove_all_filters( 'illegal_user_logins' );
		wp_installing( true );
		$before = self::count_callbacks();

		register_providers();

		$this->assertFalse( has_filter( 'illegal_user_logins' ), 'An `admin` user must be allowed on a fresh install.' );
		$this->assertSame( $before, self::count_callbacks(), 'No provider should add callbacks while installing.' );
	}


	private static function count_callbacks(): int {
		$count = 0;
		foreach ( $GLOBALS['wp_filter'] as $hook ) {
			foreach ( $hook->callbacks as $callbacks ) {
				$count += \count( $callbacks );
			}
		}
		return $count;
	}


	/**
	 * @return array<string, array{hook: \Closure(): string, message: string}>
	 */
	public static function providerProviderHooks(): array {
		return [
			'Attempts'     => [
				'hook'    => fn(): string => 'wp_login_failed',
				'message' => 'Attempts provider should log failed logins.',
			],
			'Authenticate' => [
				'hook'    => fn(): string => 'xmlrpc_login_error',
				'message' => 'Authenticate provider should adjust XML-RPC errors.',
			],
			'Email'        => [
				'hook'    => fn(): string => Api::in()->get_action( Preview::ENDPOINT ),
				'message' => 'Email provider should serve the preview endpoint.',
			],
			'Security'     => [
				'hook'    => fn(): string => 'illegal_user_logins',
				'message' => 'Security provider should filter illegal user logins.',
			],
			'Settings'     => [
				'hook'    => fn(): string => 'cmb2_init',
				'message' => 'Settings provider should register its CMB2 box.',
			],
		];
	}
}

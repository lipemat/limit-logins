<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Settings;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @author Mat Lipe
 * @since  September 2026
 *
 */
#[CoversClass( Storage::class )]
final class StorageTest extends \WP_UnitTestCase {

	public function test_save_not_autoloaded(): void {
		global $wpdb;

		Storage::in()->save( [ self::attempt( '10.0.0.1', 1, HOUR_IN_SECONDS ) ] );

		$this->assertArrayNotHasKey( Storage::OPTION, wp_load_alloptions( true ), 'Failures must not load with every request.' );
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Storage::OPTION ) );
		self::assertIsString( $autoload, 'The option should be stored.' );
		$this->assertNotContains( $autoload, wp_autoload_values_to_autoload(), 'The stored row must be flagged as not autoloaded.' );
	}


	public function test_log_failure_leaves_settings_option_alone(): void {
		Settings::in()->update_option( Settings::CONTACT, 'https://example.test/contact' );
		$updated = 0;
		add_action( 'update_option_' . Settings::NAME, function() use ( &$updated ) {
			++ $updated;
		} );

		Attempts::in()->log_failure( 'some-user' );

		$this->assertSame( 0, $updated, 'Recording a failure must not rewrite the autoloaded settings.' );
		$this->assertSame( [ 'some-user' ], self::usernames( Storage::in()->get() ), 'The failure should be stored.' );
	}


	public function test_init_without_legacy_skips_read(): void {
		$reads = 0;
		add_filter( 'pre_option_' . Storage::OPTION, function( $value ) use ( &$reads ) {
			++ $reads;
			return $value;
		} );

		Storage::init();

		$this->assertSame( 0, $reads );
	}


	public function test_get_missing_option(): void {
		$this->assertFalse( get_option( Storage::OPTION ), 'Nothing should be stored yet.' );

		$this->assertSame( [], Storage::in()->get() );
	}


	public function test_save_drops_expired(): void {
		$active = self::attempt( '10.0.0.1', 1, HOUR_IN_SECONDS );

		Storage::in()->save( [ self::attempt( '10.0.0.2', Attempts::ALLOWED_ATTEMPTS, - 1 ), $active ] );

		$this->assertSame( [ '10.0.0.1' ], self::ips( Storage::in()->get() ) );
	}


	public function test_save_at_capacity_keeps_every_row(): void {
		$attempts = self::unblocked( Storage::MAX_ROWS, 60 );

		Storage::in()->save( $attempts );

		$this->assertCount( Storage::MAX_ROWS, Storage::in()->get() );
	}


	public function test_save_over_capacity_is_bounded(): void {
		$attempts = self::unblocked( Storage::MAX_ROWS * 3, 60 );

		Storage::in()->save( $attempts );

		$this->assertCount( Storage::MAX_ROWS, Storage::in()->get() );
	}


	public function test_save_over_capacity_drops_soonest_expiring(): void {
		$attempts = self::unblocked( Storage::MAX_ROWS, 60 );
		\array_unshift( $attempts, self::attempt( '10.1.0.1', 2, 30 ) );

		Storage::in()->save( $attempts );

		$this->assertNotContains( '10.1.0.1', self::ips( Storage::in()->get() ) );
	}


	public function test_save_over_capacity_keeps_blocks(): void {
		$attempts = self::unblocked( Storage::MAX_ROWS, 120 );
		$attempts[] = self::attempt( '10.1.0.1', Attempts::ALLOWED_ATTEMPTS, 30 );

		Storage::in()->save( $attempts );

		$ips = self::ips( Storage::in()->get() );
		$this->assertContains( '10.1.0.1', $ips, 'A block should outrank newer partial counts.' );
		$this->assertCount( Storage::MAX_ROWS, $ips, 'A partial count should make room for it.' );
	}


	public function test_save_over_capacity_keeps_current(): void {
		$attempts = self::full_of_blocks();
		$current = self::attempt( '10.1.0.1', 3, 30 );
		$attempts[] = $current;

		Storage::in()->save( $attempts, $current );

		$this->assertContains( '10.1.0.1', self::ips( Storage::in()->get() ), 'The failure just recorded must never be pruned.' );
	}


	public function test_save_over_capacity_keeps_order(): void {
		$attempts = self::unblocked( Storage::MAX_ROWS, 60 );
		$attempts[] = self::attempt( '10.1.0.1', 1, 30 );
		\array_unshift( $attempts, self::attempt( '10.1.0.2', Attempts::ALLOWED_ATTEMPTS, 30 ) );

		Storage::in()->save( $attempts );

		$ips = self::ips( Storage::in()->get() );
		$this->assertSame( '10.1.0.2', $ips[0], 'Kept rows should stay in the order they were logged.' );
		$this->assertSame( '10.0.0.2', $ips[1], 'Kept rows should stay in the order they were logged.' );
	}


	public function test_log_failure_at_capacity_increments_existing(): void {
		$_SERVER['REMOTE_ADDR'] = '10.1.0.1';
		$attempts = self::unblocked( Storage::MAX_ROWS - 1, 120 );
		\array_unshift( $attempts, self::attempt( '10.1.0.1', 2, 30 ) );
		Storage::in()->save( $attempts );
		$this->assertCount( Storage::MAX_ROWS, Storage::in()->get(), 'The list should start full.' );

		Attempts::in()->log_failure( 'user-10.1.0.1' );

		$this->assertSame( 3, Attempts::in()->get_existing( 'user-10.1.0.1' )?->get_count(), 'The soonest-expiring partial count should be incremented, not pruned.' );
	}


	public function test_log_failure_over_capacity_keeps_new_row(): void {
		$_SERVER['REMOTE_ADDR'] = '10.1.0.1';
		Storage::in()->save( self::full_of_blocks() );

		Attempts::in()->log_failure( 'new-user' );

		$this->assertSame( 1, Attempts::in()->get_existing( 'new-user' )?->get_count(), 'The new failure should be stored.' );
		$this->assertCount( Storage::MAX_ROWS, Storage::in()->get(), 'The list should stay bounded.' );
	}


	public function test_save_rows_defaults_partial_rows(): void {
		Storage::in()->save_rows( [
			[ Attempt::IP => '10.0.0.1', Attempt::USERNAME => 'partial' ],
		] );

		$attempt = Storage::in()->get()[0];
		$this->assertSame( 1, $attempt->get_count(), 'A missing count should default to one.' );
		$this->assertSame( Gateway::WP_LOGIN, $attempt->gateway, 'A missing gateway should default to wp-login.' );
		$this->assertFalse( $attempt->is_expired(), 'A missing expiration should default to a fresh duration.' );
	}


	public function test_save_rows_drops_invalid_rows(): void {
		Storage::in()->save_rows( [
			'not a row',
			[ Attempt::IP => '10.0.0.1' ],
			[ Attempt::USERNAME => 'no-ip' ],
			[ Attempt::IP => '', Attempt::USERNAME => '' ],
			[ Attempt::IP => '10.0.0.2', Attempt::USERNAME => 'valid' ],
		] );

		$this->assertSame( [ 'valid' ], self::usernames( Storage::in()->get() ) );
	}


	/**
	 * Migration tests write the raw settings option to seed the legacy layout.
	 */
	public function test_migrate_moves_legacy_rows(): void {
		$legacy = self::attempt( '10.0.0.1', Attempts::ALLOWED_ATTEMPTS, HOUR_IN_SECONDS );
		update_option( Settings::NAME, [
			Settings::CONTACT         => 'https://example.test/contact',
			Settings::LOGGED_FAILURES => [ $legacy->jsonSerialize() ],
		] );

		Storage::init();

		$this->assertSame( [ Settings::CONTACT => 'https://example.test/contact' ], get_option( Settings::NAME ), 'Only the failures should leave the settings.' );
		$this->assertSame( [ $legacy->jsonSerialize() ], Storage::in()->get_rows(), 'The block should survive the move.' );
		$this->assertTrue( Storage::in()->get()[0]->is_blocked(), 'The block should still block.' );
	}


	public function test_get_migrates_legacy(): void {
		$legacy = self::attempt( '10.0.0.1', Attempts::ALLOWED_ATTEMPTS, HOUR_IN_SECONDS );
		update_option( Settings::NAME, [
			Settings::LOGGED_FAILURES => [ $legacy->jsonSerialize() ],
		] );
		delete_option( Storage::OPTION );

		$ips = self::ips( Storage::in()->get() );

		$this->assertSame( [ '10.0.0.1' ], $ips, 'Early drop reads before the hook and must see legacy blocks.' );
		$this->assertArrayNotHasKey( Settings::LOGGED_FAILURES, get_option( Settings::NAME ), 'The legacy rows should be removed.' );
	}


	public function test_migrate_keeps_stored_rows(): void {
		Storage::in()->save( [ self::attempt( '10.0.0.1', 1, HOUR_IN_SECONDS ) ] );
		update_option( Settings::NAME, [
			Settings::LOGGED_FAILURES => [ self::attempt( '10.0.0.2', 1, HOUR_IN_SECONDS )->jsonSerialize() ],
		] );

		Storage::in()->migrate();

		$this->assertSame( [ '10.0.0.1', '10.0.0.2' ], self::ips( Storage::in()->get() ) );
	}


	public function test_migrate_without_legacy_rows(): void {
		Storage::in()->save( [ self::attempt( '10.0.0.1', 1, HOUR_IN_SECONDS ) ] );
		update_option( Settings::NAME, [ Settings::CONTACT => 'https://example.test/contact' ] );

		$rows = Storage::in()->migrate();

		$this->assertSame( [], $rows, 'Nothing should be migrated.' );
		$this->assertSame( [ '10.0.0.1' ], self::ips( Storage::in()->get() ), 'Stored rows should be untouched.' );
		$this->assertSame( [ Settings::CONTACT => 'https://example.test/contact' ], get_option( Settings::NAME ), 'The settings should be untouched.' );
	}


	public function test_migrate_non_array_legacy_value(): void {
		update_option( Settings::NAME, [ Settings::LOGGED_FAILURES => '' ] );

		Storage::in()->migrate();

		$this->assertSame( [], Storage::in()->get(), 'Nothing should be stored.' );
		$this->assertSame( [], get_option( Settings::NAME ), 'The empty legacy key should be removed.' );
	}


	/**
	 * @param list<Attempt> $attempts
	 *
	 * @return list<string>
	 */
	private static function ips( array $attempts ): array {
		return \array_map( function( Attempt $attempt ): string {
			return $attempt->ip;
		}, $attempts );
	}


	/**
	 * @param list<Attempt> $attempts
	 *
	 * @return list<string>
	 */
	private static function usernames( array $attempts ): array {
		return \array_map( function( Attempt $attempt ): string {
			return $attempt->username;
		}, $attempts );
	}


	/**
	 * Partial counts from `10.0.0.1` onward, each expiring later than the last.
	 *
	 * @return list<Attempt>
	 */
	private static function unblocked( int $count, int $expires_in ): array {
		return \array_map( function( int $i ) use ( $expires_in ): Attempt {
			return self::attempt( self::ip( '10.0.', $i ), 1, $expires_in + $i );
		}, \range( 0, $count - 1 ) );
	}


	/**
	 * `MAX_ROWS` blocks from `10.2.0.1` onward, each expiring later than the last.
	 *
	 * @return list<Attempt>
	 */
	private static function full_of_blocks(): array {
		return \array_map( function( int $i ): Attempt {
			return self::attempt( self::ip( '10.2.', $i ), Attempts::ALLOWED_ATTEMPTS, 120 + $i );
		}, \range( 0, Storage::MAX_ROWS - 1 ) );
	}


	/**
	 * The `$i`th address under a `/16` prefix such as `10.0.`.
	 */
	private static function ip( string $prefix, int $i ): string {
		return $prefix . \intdiv( $i, 250 ) . '.' . ( $i % 250 + 1 );
	}


	private static function attempt( string $ip, int $count, int $expires_in ): Attempt {
		return Attempt::factory( [
			Attempt::IP       => $ip,
			Attempt::USERNAME => 'user-' . $ip,
			Attempt::GATEWAY  => Gateway::WP_LOGIN->value,
			Attempt::COUNT    => $count,
			Attempt::EXPIRES  => (int) \gmdate( 'U' ) + $expires_in,
		] );
	}
}

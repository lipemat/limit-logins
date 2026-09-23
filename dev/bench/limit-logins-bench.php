<?php
declare( strict_types=1 );

/**
 * Plugin Name: Limit Logins Bench
 * Description: Records server-side time, peak memory and query count of benchmark requests. Installed and removed by `dev/bench/bench.sh`.
 * Author: Mat Lipe
 */

namespace Lipe\Limit_Logins\Bench;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings;

/**
 * Request header carrying the scenario name.
 */
const HEADER = 'HTTP_X_LIMIT_LOGINS_BENCH';

/**
 * One tab-separated line per request: server ms, peak memory bytes, query count, IP, Xdebug mode.
 */
const LOG_FILE = __DIR__ . '/limit-logins-bench.log';

/**
 * Scenario which must stay unblocked for the whole run.
 */
const REFERENCE = 'reference';

if ( ! \is_string( $_SERVER[ HEADER ] ?? null ) ) {
	return;
}

// Registered after WP's own shutdown handler, so it runs last and still runs after `exit`.
\register_shutdown_function( function(): void {
	global $wpdb;
	// An unquoted `off` in php.ini reads back as ''.
	$xdebug = \ini_get( 'xdebug.mode' );
	$line = [
		\number_format( ( \microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 3, '.', '' ),
		\memory_get_peak_usage(),
		$wpdb instanceof \wpdb ? $wpdb->num_queries : 0,
		$_SERVER['REMOTE_ADDR'] ?? '',
		\is_string( $xdebug ) && '' !== $xdebug ? $xdebug : 'off',
	];
	\file_put_contents( LOG_FILE, \implode( "\t", $line ) . "\n", FILE_APPEND | LOCK_EX );
} );

if ( REFERENCE === $_SERVER[ HEADER ] ) {
	// Plugin classes autoload only once plugins load.
	add_action( 'plugins_loaded', function(): void {
		/**
		 * Cycle the stored count back to 1 before it can reach a block, while still writing on every request.
		 */
		add_filter( 'pre_update_option_' . Settings::NAME, function( array $value ): array {
			foreach ( $value[ Settings::LOGGED_FAILURES ] ?? [] as $i => $attempt ) {
				if ( Attempts::ALLOWED_ATTEMPTS - 1 === (int) ( $attempt[ Attempt::COUNT ] ?? 0 ) ) {
					$value[ Settings::LOGGED_FAILURES ][ $i ][ Attempt::COUNT ] = 1;
				}
			}
			return $value;
		} );
	}, 0 );
}

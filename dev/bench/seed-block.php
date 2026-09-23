<?php
declare( strict_types=1 );

/**
 * Replace the logged failures with a single active block.
 *
 * Usage: `wp eval-file dev/bench/seed-block.php <ip> <username> <gateway>`
 *
 * @var list<string> $args
 */

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Settings;

if ( 3 !== \count( $args ) ) {
	\WP_CLI::error( 'Usage: wp eval-file seed-block.php <ip> <username> <gateway>' );
}

$lipe_limit_logins_block = Attempt::factory( [
	Attempt::IP       => $args[0],
	Attempt::USERNAME => $args[1],
	Attempt::GATEWAY  => $args[2],
	Attempt::COUNT    => Attempts::ALLOWED_ATTEMPTS,
	Attempt::EXPIRES  => \time() + Attempts::DURATION,
] );
Settings::in()->update_option( Settings::LOGGED_FAILURES, [ $lipe_limit_logins_block->jsonSerialize() ] );

\WP_CLI::success( "Blocked {$args[0]} / {$args[1]} on {$args[2]}." );

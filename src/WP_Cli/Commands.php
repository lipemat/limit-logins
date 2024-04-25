<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\WP_Cli;

use Lipe\Limit_Logins\Settings;

/**
 * Mange the limit-logins plugin.
 *
 * @author  Mat Lipe
 * @since   April 2024
 *
 * @command limit-logins
 */
final class Commands {
	public const COMMAND = 'limit-logins';


	/**
	 * Clear blocks from the database while preserving other settings.
	 *
	 * @subcommand clear-blocks
	 */
	public function clear_blocks(): void {
		$count = \count( Settings::in()->get_option( Settings::LOGGED_FAILURES, [] ) );
		unset( Settings::in()[ Settings::LOGGED_FAILURES ] );

		\WP_CLI::success( "Cleared {$count} blocks." );
	}
}

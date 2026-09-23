<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\WP_Cli;

use Lipe\Limit_Logins\Attempts\Storage;

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
		$count = \count( Storage::in()->get() );
		Storage::in()->save( [] );

		\WP_CLI::success( "Cleared {$count} blocks." );
	}
}

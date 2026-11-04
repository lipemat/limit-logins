<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

/**
 * Plugin Name: Limit Logins
 * Description: Limit rate of login attempts and block IP or username temporarily.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 2.1.1
 * Text Domain: lipe
 * License: MIT
 * Network: false
 * Requires at least: 6.4.0
 * Requires PHP: 8.3.0
 * Update URI: false
 */

use Lipe\Limit_Logins\Service_Providers\Attempts_Provider;
use Lipe\Limit_Logins\Service_Providers\Authenticate_Provider;
use Lipe\Limit_Logins\Service_Providers\Email_Provider;
use Lipe\Limit_Logins\Service_Providers\Security_Provider;
use Lipe\Limit_Logins\Service_Providers\Settings_Provider;
use Lipe\Limit_Logins\WP_Cli\Commands;

const LIMIT_LOGINS_PATH = __DIR__;

// Blocked login submissions exit here, before the providers run at `plugins_loaded`.
Early_Drop::in()->maybe_drop();

add_action( 'plugins_loaded', register_providers( ... ) );

add_action( 'cli_init', function() {
	if ( ! class_exists( '\WP_CLI' ) ) {
		return;
	}
	\WP_CLI::add_command( 'limit-logins', Commands::class );
} );

/**
 * Register the hooks of every service provider.
 *
 * Skipped while installing, when the tables do not exist yet and an
 * `admin` user must be allowed.
 */
function register_providers(): void {
	if ( true === wp_installing() ) {
		return;
	}
	( new Attempts_Provider() )->register();
	( new Authenticate_Provider() )->register();
	( new Email_Provider() )->register();
	( new Security_Provider() )->register();
	( new Settings_Provider() )->register();
}

/**
 * A namespaced function to use within this plugin.
 *
 * @phpstan-return ($value is null? null : string)
 */
function sn( float|int|string|null $value ): ?string {
	if ( null === $value ) {
		return null;
	}
	if ( \is_string( $value ) ) {
		$value = \trim( sanitize_text_field( wp_unslash( $value ) ) );
	}
	return (string) $value;
}

/**
 * A namespaced function to use within this plugin.
 */
function es( \BackedEnum|float|\Stringable|int|string|null $value ): string {
	if ( $value instanceof \BackedEnum ) {
		return (string) $value->value;
	}
	return (string) $value;
}

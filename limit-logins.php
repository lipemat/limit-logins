<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

/**
 * Plugin Name: Limit Logins
 * Description: Limit rate of login attempts and block IP or username temporarily.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 0.17.0
 * Text Domain: lipe
 * License: MIT
 * Network: false
 * Requires at least: 6.4.0
 * Requires PHP: 8.2.0
 * Update URI: false
 */

use Lipe\Limit_Logins\Authenticate\Reset_Password;
use Lipe\Limit_Logins\Authenticate\Rest;
use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Authenticate\Xmlrpc;
use Lipe\Limit_Logins\Email\Preview;
use Lipe\Limit_Logins\WP_Cli\Commands;

const PATH = __DIR__;

add_action( 'plugins_loaded', function() {
	Attempts::init();
	Authenticate::init();
	Preview::init();
	Settings::init();
	Reset_Password::init();
	Rest::init();
	Unlock_Link::init();
	Xmlrpc::init();

	if ( class_exists( '\WP_CLI' ) ) {
		\WP_CLI::add_command( 'limit-logins', Commands::class );
	}
} );

/**
 * A namespaced function to use within this plugin.
 *
 * @param string $value
 *
 * @return string
 */
function sn( string $value ): string {
	return trim( sanitize_text_field( wp_unslash( $value ) ) );
}

/**
 * A namespaced function to use within this plugin.
 *
 * @param string|int|float|\Stringable|\BackedEnum $value
 *
 * @return string
 */
function es( $value ): string {
	if ( $value instanceof \BackedEnum ) {
		return (string) $value->value;
	}
	return (string) $value;
}

/**
 * Return the container.
 *
 * @return Container
 */
function container(): Container {
	return Container::instance();
}

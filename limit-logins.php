<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

/**
 * Plugin Name: Limit Logins
 * Description: Limit rate of login attempts and block IP or username temporarily.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 0.14.2
 * Text Domain: lipe
 * License: MIT
 * Network: false
 * Requires at least: 6.4.0
 * Requires PHP: 8.2.0
 * Update URI: false
 */

use Lipe\Limit_Logins\Authenticate\Reset_Password;
use Lipe\Limit_Logins\Authenticate\Rest;
use Lipe\Limit_Logins\Authenticate\Xmlrpc;
use Lipe\Limit_Logins\Settings\Limit_Logins;

add_action( 'plugins_loaded', function() {
	Attempts::init();
	Authenticate::init();
	Limit_Logins::init();
	Reset_Password::init();
	Rest::init();
	Xmlrpc::init();
} );

/**
 * A namespaced function to use within this plugin.
 *
 * @param string $value
 *
 * @return string
 */
function sn( string $value ): string {
	if ( function_exists( '\sn' ) ) {
		return \sn( $value );
	}
	return trim( sanitize_text_field( wp_unslash( $value ) ) );
}

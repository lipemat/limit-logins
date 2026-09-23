<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Lib\Container\Instance;
use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;
use Lipe\Limit_Logins\Utils;

/**
 * Prevent blocked users or IP from authenticating during REST requests.
 *
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Rest {
	use Instance;

	/**
	 * `wp_authenticate` is not called during REST requests.
	 *
	 * Using the actions called during `determine_current_user`.
	 *
	 * @internal
	 */
	public function rest_authenticate( \WP_Error $error, ?\WP_User $user = null ): void {
		if ( ! Utils::in()->is_rest_request() ) {
			return;
		}
		$username = $user->user_login ?? Utils::in()->get_rest_username();
		if ( Attempts::in()->is_blocked( $username ) ) {
			add_filter( 'rest_request_after_callbacks', $this->get_rest_blocked_error( ... ), 1_000 );
		}
	}


	/**
	 * Converts all REST requests with authentication that are blocked to a WP_Error.
	 *
	 * The default REST handlers are likely already returning an error, but it is not
	 * our "too many failed login attempts" error. Sending our custom error prevents
	 * the attacker from getting any more information.
	 *
	 * @note Always use code `403`, even if the authentication passed.
	 *
	 * @internal
	 */
	public function get_rest_blocked_error(): \WP_Error {
		return new \WP_Error( Authenticate::CODE_BLOCKED, Authenticate::MESSAGE_BLOCKED, [
			'status' => 403,
		] );
	}
}

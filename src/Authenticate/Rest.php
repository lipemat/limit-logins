<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;
use Lipe\Limit_Logins\Traits\Singleton;
use Lipe\Limit_Logins\Utils;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Rest {
	use Singleton;

	private function hook(): void {
		add_action( 'wp_authenticate_application_password_errors', [ $this, 'rest_authenticate' ], 9, 2 );
		add_action( 'application_password_failed_authentication', [ $this, 'rest_authenticate' ], 9 );
	}


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
		$existing = Attempts::in()->get_existing( $username );
		if ( null !== $existing && $existing->is_blocked() ) {
			add_filter( 'rest_request_after_callbacks', [ $this, 'get_rest_blocked_error' ], 100 );
		}
	}


	/**
	 * Converts all REST requests with authentication that are blocked to a WP_Error.
	 *
	 * The default REST handlers are likely already returning an error, but it is not
	 * our "too many failed login attempts" error. Sending our custom error prevents
	 * the attacker from getting any more information.
	 *
	 * @note Always use code `401` to say the authentication failed even if it passed.
	 * @see  rest_authorization_required_code
	 *
	 * @internal
	 */
	public function get_rest_blocked_error(): \WP_Error {
		return new \WP_Error( Authenticate::CODE_BLOCKED, 'Too many failed login attempts.', [
			'status' => 401,
		] );
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Settings\Limit_Logins as Settings;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Authenticate {
	use Singleton;

	public const CODE_BLOCKED = 'blocked';


	private function hook(): void {
		add_filter( 'authenticate', [ $this, 'authenticate' ], 50, 2 );
		add_filter( 'xmlrpc_login_error', [ $this, 'adjust_xmlrpc_error' ], 10, 2 );
		add_action( 'wp_authenticate_application_password_errors', [ $this, 'rest_authenticate' ], 9, 2 );
		add_action( 'application_password_failed_authentication', [ $this, 'rest_authenticate' ], 9 );
	}


	/**
	 * @internal
	 */
	public function authenticate( null|\WP_User|\WP_Error $user, string $username ): null|\WP_User|\WP_Error {
		$existing = Attempts::in()->get_existing( $username );
		if ( null !== $existing && $existing->is_blocked() ) {
			return new \WP_Error( self::CODE_BLOCKED, $this->get_error() );
		}

		return $user;
	}


	/**
	 * `wp_authenticate_user` is not called during REST requests.
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
		return new \WP_Error( self::CODE_BLOCKED, 'Too many failed login attempts.', [
			'status' => 401,
		] );
	}


	public function adjust_xmlrpc_error( \IXR_Error $ixr, \WP_Error $error ): \IXR_Error {
		if ( self::CODE_BLOCKED === $error->get_error_code() ) {
			$ixr->message = 'Too many failed login attempts.';
			$ixr->code = $error->get_error_code();
		}

		return $ixr;
	}


	private function get_error(): string {
		$contact = Settings::in()->get_option( Settings::CONTACT, '' );
		if ( ! \is_string( $contact ) || '' === $contact ) {
			return '<strong>ERROR:</strong> Too many failed login attempts.';
		}

		return "<strong>ERROR:</strong> Too many failed login attempts.<br />Use the <a href=\"{$contact}\">contact form</a> for help.";
	}
}

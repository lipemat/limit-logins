<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Container\Instance;

/**
 * Prevent blocked users or IP from authenticating.
 *
 * @author Mat Lipe
 * @since  April, 2024
 *
 */
final class Authenticate {
	use Instance;

	public const string CODE_BLOCKED = 'blocked';

	/**
	 * Plain text shown for a blocked attempt on the machine gateways.
	 */
	public const string MESSAGE_BLOCKED = 'Too many failed login attempts.';

	/**
	 * Core `authenticate` callbacks which check a password hash.
	 */
	public const array AUTH_CALLBACKS = [
		'wp_authenticate_username_password',
		'wp_authenticate_email_password',
		'wp_authenticate_application_password',
	];

	/**
	 * Priorities of the password callbacks removed for a blocked attempt.
	 *
	 * @var array<value-of<self::AUTH_CALLBACKS>, int>
	 */
	private array $callbacks_to_restore = [];


	/**
	 * Reject a blocked attempt without a password check by removing core's
	 * password callbacks until `self::restore_password_callbacks()` restores them.
	 *
	 * Removes 77% of the CPU time spent on a blocked attempt.
	 */
	public function maybe_block_before_checks( null|\WP_User|\WP_Error $user, string $username, string $password ): null|\WP_User|\WP_Error {
		if ( '' === $username || '' === $password || ! Attempts::in()->is_blocked( $username ) ) {
			return $user;
		}

		foreach ( self::AUTH_CALLBACKS as $callback ) {
			$priority = has_filter( 'authenticate', $callback );
			if ( \is_int( $priority ) ) {
				remove_filter( 'authenticate', $callback, $priority );
				$this->callbacks_to_restore[ $callback ] = $priority;
			}
		}
		return new \WP_Error( self::CODE_BLOCKED, $this->get_blocked_message() );
	}


	/**
	 * Restore skipped password callbacks and reject blocked attempts with a `403`.
	 */
	public function authenticate( null|\WP_User|\WP_Error $user, string $username ): null|\WP_User|\WP_Error {
		$this->restore_password_callbacks();

		if ( Attempts::in()->is_blocked( $username ) ) {
			// An empty username is the login form loading, not an attempt.
			if ( '' !== $username ) {
				status_header( 403 );
			}
			return new \WP_Error( self::CODE_BLOCKED, $this->get_blocked_message() );
		}

		return $user;
	}


	/**
	 * Restore the core password callbacks removed for a blocked attempt.
	 *
	 * Allows subsequent `authenticate()` calls to check the password hash
	 * for non-blocked attempts.
	 *
	 * @example Two auths in one request: XML-RPC system.multicall
	 * @example In Tests: block an attempt, remove a block, login in again.
	 */
	private function restore_password_callbacks(): void {
		foreach ( $this->callbacks_to_restore as $callback => $priority ) {
			add_filter( 'authenticate', $callback, $priority, 3 );
		}
		$this->callbacks_to_restore = [];
	}


	/**
	 * Message shown for a blocked attempt on every gateway.
	 */
	public function get_blocked_message(): string {
		return '<strong>ERROR:</strong> ' . self::MESSAGE_BLOCKED . '<br />An email has been sent to the email on file with more information.';
	}
}

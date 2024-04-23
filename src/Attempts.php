<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Util\Arrays;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Attempts {
	use Singleton;

	public const  ALLOWED_ATTEMPTS = 5;
	public const  DURATION         = HOUR_IN_SECONDS * 12;


	private function hook(): void {
		add_action( 'wp_login_failed', [ $this, 'log_failure' ] );
		add_action( 'application_password_failed_authentication', [ $this, 'maybe_log_application_password_failure' ] );
	}


	/**
	 * During a rest request there is no call to `wp_authenticate`. Application
	 * passwords are checked during `determine_current_user`.
	 *
	 * We use a random string to as the username because it is not available on
	 * this action. If we used the same user such as "unknown", then ALL REST users
	 * would be blocked. Using a random string allows us to block only the IP.
	 *
	 * @return void
	 */
	public function maybe_log_application_password_failure(): void {
		if ( Utils::in()->is_rest_request() ) {
			$this->log_failure( Utils::in()->get_rest_username() );
		}
	}


	public function log_failure( string $username ): void {
		$attempts = $this->clear_expired( $this->get_all() );
		$existing = $this->get_existing_index( $attempts, $username );
		if ( null !== $existing ) {
			if ( $attempts[ $existing ]->get_count() >= self::ALLOWED_ATTEMPTS ) {
				return;
			}
			$attempts[ $existing ]->add_failure();
		} else {
			$attempts[] = Attempt::new_attempt( $username );
		}

		Settings::in()->update_option( Settings::LOGGED_FAILURES, \array_map( fn( $attempt ) => $attempt->jsonSerialize(), $attempts ) );
	}


	public function get_existing( string $username ): ?Attempt {
		$attempts = $this->clear_expired( $this->get_all() );
		$existing = $this->get_existing_index( $attempts, $username );

		return null === $existing ? null : $this->get_all()[ $existing ];
	}


	/**
	 * Remove a block for a given username.
	 *
	 * Does not match the IP, just the username.
	 */
	public function remove_block( string $username ): void {
		$attempts = $this->get_all();
		$existing = Arrays::in()->find_index( $attempts, fn( $attempt ) => $attempt->username === $username );
		if ( null !== $existing ) {
			unset( $attempts[ $existing ] );
			Settings::in()->update_option( Settings::LOGGED_FAILURES, \array_map( fn( $attempt ) => $attempt->jsonSerialize(), \array_values( $attempts ) ) );
		}
	}


	/**
	 * @return list<Attempt>
	 */
	public function get_all(): array {
		$attempts = Settings::in()->get_option( Settings::LOGGED_FAILURES, [] );
		return \array_map( [ Attempt::class, 'factory' ], $attempts );
	}


	/**
	 * @phpstan-param list<Attempt> $attempts
	 * @return list<Attempt>
	 */
	private function clear_expired( array $attempts ): array {
		return \array_values( \array_filter( $attempts, fn( $attempt ) => ! $attempt->is_expired() ) );
	}


	/**
	 * Get the index of existing attempt which matches the username or ip.
	 *
	 * @phpstan-param list<Attempt> $attempts
	 */
	private function get_existing_index( array $attempts, string $username ): ?int {
		$ip = Utils::in()->get_current_ip();
		$found = Arrays::in()->find_index( $attempts, fn( $attempt ) => $attempt->username === $username || $attempt->ip === $ip );

		return null === $found ? null : (int) $found;
	}
}

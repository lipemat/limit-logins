<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Attempts\Storage;
use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Attempts {
	use Singleton;

	public const int ALLOWED_ATTEMPTS = 5;
	public const int DURATION         = HOUR_IN_SECONDS * 12;


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
			$current = $attempts[ $existing ];
			if ( $current->get_count() >= self::ALLOWED_ATTEMPTS ) {
				return;
			}
			$current->add_failure();
			if ( $current->is_blocked() ) {
				Unlock_Link::in()->send_blocked_email( $current );
			}
		} else {
			$current = Attempt::new_attempt( $username );
			$attempts[] = $current;
		}

		Storage::in()->save( $attempts, $current );
	}


	public function get_existing( string $username ): ?Attempt {
		$attempts = $this->clear_expired( $this->get_all() );
		$existing = $this->get_existing_index( $attempts, $username );
		if ( null === $existing ) {
			return null;
		}

		return $attempts[ $existing ] ?? null;
	}


	/**
	 * Is the username or the current IP blocked?
	 */
	public function is_blocked( string $username ): bool {
		$existing = $this->get_existing( $username );
		return $existing instanceof Attempt && $existing->is_blocked();
	}


	/**
	 * Is the current IP blocked, ignoring the username entirely?
	 *
	 * For gateways such as XML-RPC, which submit no username we can read.
	 */
	public function is_ip_blocked(): bool {
		$ip = Utils::in()->get_current_ip();
		foreach ( $this->get_all() as $attempt ) {
			if ( $attempt->ip === $ip && $attempt->is_blocked() ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Remove a block for a given username.
	 *
	 * Matches the username only. Matching the current IP would let a second
	 * account clear a block recorded against someone else.
	 */
	public function remove_block( string $username ): void {
		$attempts = $this->get_all();
		$found = \array_filter( $attempts, function( Attempt $attempt ) use ( $username ): bool {
			return $username === $attempt->username;
		} );

		Storage::in()->save( \array_values( \array_diff_key( $attempts, $found ) ) );
	}


	/**
	 * Get all stored attempts.
	 *
	 * @return list<Attempt>
	 */
	public function get_all(): array {
		return Storage::in()->get();
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
	 * If more than one attempt is found, the first blocked attempt is returned.
	 *
	 * @phpstan-param list<Attempt> $attempts
	 */
	private function get_existing_index( array $attempts, string $username ): ?int {
		$ip = Utils::in()->get_current_ip();
		$found = \array_filter( $attempts, function( Attempt $attempt ) use ( $username, $ip ): bool {
			return $username === $attempt->username || $ip === $attempt->ip;
		} );
		foreach ( $found as $i => $attempt ) {
			/** @var Attempt $attempt */
			if ( $attempt->is_blocked() ) {
				return $i;
			}
		}
		return \count( $found ) > 0 ? \array_key_first( $found ) : null;
	}
}

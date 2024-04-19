<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Util\Arrays;
use Lipe\Limit_Logins\Log\Attempt;
use Lipe\Limit_Logins\Settings\Limit_Logins as Settings;
use Lipe\Limit_Logins\Traits\Singleton;
use Lipe\Limit_Logins\Utils\Ip;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-import-type DATA from Log\Attempt
 */
final class Attempts {
	use Singleton;

	public const  ALLOWED_ATTEMPTS = 5;
	public const  DURATION         = HOUR_IN_SECONDS * 12;


	private function hook(): void {
		add_action( 'wp_login_failed', [ $this, 'log_failure' ] );
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

		Settings::in()->update_option( Settings::LOG, wp_json_encode( $attempts ) );
	}


	public function get_existing( string $username ): ?Attempt {
		$attempts = $this->clear_expired( $this->get_all() );
		$existing = $this->get_existing_index( $attempts, $username );

		return null === $existing ? null : $this->get_all()[ $existing ];
	}


	/**
	 * @return list<Attempt>
	 */
	public function get_all(): array {
		return \array_map( [ Attempt::class, 'factory' ], Settings::in()->get_logs() );
	}


	/**
	 * @phpstan-param list<Attempt> $attempts
	 * @return list<Attempt>
	 */
	private function clear_expired( array $attempts ): array {
		return \array_filter( $attempts, fn( $attempt ) => ! $attempt->is_expired() );
	}


	/**
	 * Get the index of existing attempt which matches the username or ip.
	 *
	 * @phpstan-param list<Attempt> $attempts
	 */
	private function get_existing_index( array $attempts, string $username ): ?int {
		$ip = Ip::in()->get_current_ip();
		$found = Arrays::in()->find_index( $attempts, fn( $attempt ) => $attempt->username === $username || $attempt->ip === $ip );

		return null === $found ? null : (int) $found;
	}
}

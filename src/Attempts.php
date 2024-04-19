<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Util\Arrays;
use Lipe\Limit_Logins\Log\Attempt;
use Lipe\Limit_Logins\Log\Gateway;
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
	private const DURATION         = HOUR_IN_SECONDS * 12;


	private function hook(): void {
		add_action( 'wp_login_failed', [ $this, 'log' ] );
	}


	public function log( string $username ): void {
		$attempts = $this->get_all();
		$existing = $this->get_existing( $username );
		if ( null !== $existing ) {
			if ( $attempts[ $existing ]->get_count() >= self::ALLOWED_ATTEMPTS ) {
				return;
			}
			$attempts[ $existing ]->add_failure();
		} else {
			$attempts[] = Attempt::factory( [
				'ip'       => Ip::in()->get_current_ip(),
				'username' => $username,
				'gateway'  => Gateway::detect()->value,
				'count'    => 1,
				'expires'  => (int) gmdate( 'U' ) + self::DURATION,
			] );
		}

		Settings::in()->update_option( Settings::LOG, wp_json_encode( $attempts ) );
	}


	private function get_existing( string $username ): ?int {
		$ip = Ip::in()->get_current_ip();
		$found = Arrays::in()->find_index( $this->get_all(), fn( $attempt ) => $attempt->username === $username || $attempt->ip === $ip );
		return null === $found ? null : (int) $found;
	}


	/**
	 * @return list<Attempt>
	 */
	public function get_all(): array {
		return \array_map( [ Attempt::class, 'factory' ], Settings::in()->get_logs() );
	}
}

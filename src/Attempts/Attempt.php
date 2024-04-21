<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Utils;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-type DATA array{
 *     ip: string,
 *     username: string,
 *     gateway: string,
 *     count: int|string,
 *     expires: int|string
 * }
 */
final class Attempt implements \JsonSerializable {
	public const IP       = 'ip';
	public const USERNAME = 'username';
	public const GATEWAY  = 'gateway';
	public const COUNT    = 'count';
	public const EXPIRES  = 'expires';


	private function __construct(
		public readonly string $ip,
		public readonly string $username,
		public readonly Gateway $gateway,
		private int $count,
		public readonly int $expires
	) {
	}


	public function get_count(): int {
		return $this->count;
	}


	public function add_failure(): void {
		++ $this->count;
	}


	public function is_expired(): bool {
		return $this->expires < (int) gmdate( 'U' );
	}


	public function is_blocked(): bool {
		return $this->count >= Attempts::ALLOWED_ATTEMPTS && ! $this->is_expired();
	}


	/**
	 * @phpstan-return DATA
	 */
	public function jsonSerialize(): array {
		return [
			self::IP       => $this->ip,
			self::USERNAME => $this->username,
			self::GATEWAY  => $this->gateway->value,
			self::COUNT    => $this->count,
			self::EXPIRES  => $this->expires,
		];
	}


	/**
	 * Create a new attempt using the information from the current request.
	 *
	 */
	public static function new_attempt( string $username ): self {
		return new self(
			Utils::in()->get_current_ip(),
			$username,
			Gateway::detect(),
			1,
			(int) gmdate( 'U' ) + Attempts::DURATION
		);
	}


	/**
	 * @phpstan-param DATA $data
	 */
	public static function factory( array $data ): self {
		return new self(
			$data[ self::IP ],
			$data[ self::USERNAME ],
			Gateway::from( $data[ self::GATEWAY ] ),
			(int) $data[ self::COUNT ],
			(int) $data[ self::EXPIRES ]
		);
	}
}

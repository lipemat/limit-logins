<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Log;

use Lipe\Limit_Logins\Attempts;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-type DATA array{
 *     ip: string,
 *     username: string,
 *     gateway: string,
 *     count: int,
 *     expires: int
 * }
 *
 */
final class Attempt implements \JsonSerializable {
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


	/**
	 * @phpstan-return DATA
	 */
	public function jsonSerialize(): array {
		return [
			'ip'       => $this->ip,
			'username' => $this->username,
			'gateway'  => $this->gateway->value,
			'count'    => $this->count,
			'expires'  => $this->expires,
		];
	}


	/**
	 * @phpstan-param DATA $data
	 */
	public static function factory( array $data ): self {
		return new self(
			$data['ip'],
			$data['username'],
			Gateway::from( $data['gateway'] ),
			$data['count'],
			$data['expires']
		);
	}
}

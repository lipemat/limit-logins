<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Limit_Logins\Attempts\Attempt;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Blocked implements Email {
	private static Blocked $current;


	private function __construct(
		public readonly Attempt $attempt,
		public readonly string $key
	) {
		self::$current = $this;
	}


	public function get_email_addresses(): array {
		$user = get_user_by( 'login', $this->attempt->username );
		if ( false === $user ) {
			return [];
		}
		try {
			$email = new EmailAddress( $user->user_email );
		} catch ( \UnexpectedValueException ) {
			return [];
		}
		return [ $email ];
	}


	public function get_subject(): string {
		return \sprintf( 'Your %s account has been locked.', get_bloginfo( 'name' ) );
	}


	public function get_message(): string {
		return Util::in()->get_template( 'blocked' );
	}


	public static function get_current(): ?Blocked {
		return self::$current ?? null;
	}


	public static function factory( Attempt $attempt, string $key ): Blocked {
		return new self( $attempt, $key );
	}
}

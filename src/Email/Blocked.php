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
	private function __construct(
		private readonly Attempt $attempt,
		private readonly string $key
	) {
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
		return \sprintf( 'Your %s account has been blocked.', get_bloginfo( 'name' ) );
	}


	public function get_message(): string {
		return Util::in()->get_template( 'blocked' );
	}


	public static function factory( Attempt $attempt, string $key ): Blocked {
		return new self( $attempt, $key );
	}
}

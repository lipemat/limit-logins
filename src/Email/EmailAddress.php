<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

/**
 * Value object for email address data.
 *
 * @author  Mat Lipe
 * @since   April 2024
 */
final readonly class EmailAddress {
	/**
	 * @throws \UnexpectedValueException
	 */
	public function __construct(
		private string $email
	) {
		if ( false === is_email( $email ) ) {
			throw new \UnexpectedValueException( 'Invalid email address' );
		}
	}


	public function get_email(): string {
		return sanitize_email( $this->email );
	}


	public function __toString(): string {
		return $this->get_email();
	}
}

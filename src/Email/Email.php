<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
interface Email {
	/**
	 * @return EmailAddress[]
	 */
	public function get_email_addresses(): array;


	public function get_subject(): string;


	public function get_message(): string;
}

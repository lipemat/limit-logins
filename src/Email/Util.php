<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Limit_Logins\Traits\Singleton;
use const Lipe\Limit_Logins\PATH;

/**
 * @author  Mat Lipe
 * @since   April 2024
 */
final class Util {
	use Singleton;

	/**
	 * Send an email.
	 *
	 * @param Email $email
	 *
	 * @return bool
	 */
	public function send( Email $email ): bool {
		$emails = $email->get_email_addresses();
		if ( \count( $emails ) > 0 ) {
			$headers = [
				'Content-type: text/html; charset=' . get_bloginfo( 'charset' ),
			];

			$addresses = \array_map( fn( EmailAddress $email_address ) => $email_address->get_email(), $emails );
			return wp_mail( $addresses, htmlspecialchars_decode( $email->get_subject() ), $email->get_message(), $headers );
		}

		return false;
	}


	/**
	 * Get the rendered contents of an email template.
	 *
	 * @param string $slug - Template slug.
	 *
	 * @return string
	 */
	public function get_template( string $slug ): string {
		ob_start();
		require PATH . '/templates/email/' . $slug . '.php';

		return (string) ob_get_clean();
	}
}

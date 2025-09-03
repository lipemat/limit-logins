<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use function Lipe\Limit_Logins\container;

/**
 * Handle username enumeration prevention.
 *
 * @author Mat Lipe
 * @since  1.2.0
 *
 */
final class Username_Detection {
	private const FAKE_USER_ID = 999_999_9999;


	/**
	 * Standardize login error messages to prevent username enumeration.
	 *
	 * @action wp_error_added 10 4
	 */
	public function standardize_login_errors( string $code, \WP_Error $error ): void {
		if ( 'invalid_username' === $code || 'incorrect_password' === $code ) {
			// Prevent username enumeration by not revealing if the username exists.
			$error->errors[ $code ] = [
				'<strong>Error:</strong> Your username or password is incorrect. <a href="' . wp_lostpassword_url() . '">' .
				// phpcs:ignore WordPress.WP.I18n
				__( 'Lost your password?' ) .
				'</a>',
			];
		}
	}


	/**
	 * Use a fake user for lost password requests when the user doesn't exist.
	 *
	 * @filter lostpassword_user_data 10 2
	 */
	public function use_dummy_user_for_lost_password( \WP_User|false $user_data, \WP_Error $error ): \WP_User {
		if ( $user_data instanceof \WP_User && ! $error->has_errors() ) {
			return $user_data;
		}

		$dummy_user = new \WP_User();
		$dummy_user->ID = self::FAKE_USER_ID;
		return $dummy_user;
	}


	/**
	 * Prevent sending password reset emails for fake users.
	 *
	 * @filter send_retrieve_password_email 10 3
	 */
	public function prevent_dummy_user_email( bool $send, \WP_User $user ): bool {
		if ( self::FAKE_USER_ID === $user->ID ) {
			return false;
		}
		return $send;
	}


	public static function in(): Username_Detection {
		return container()->get( __CLASS__ );
	}
}

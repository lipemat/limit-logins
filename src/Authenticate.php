<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Traits\Singleton;
use Lipe\Limit_Logins\Settings\Limit_Logins as Settings;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Authenticate {
	use Singleton;

	private const CODE_BLOCKED = 'blocked';


	private function hook(): void {
		add_filter( 'wp_authenticate_user', [ $this, 'authenticate' ], 9 );
	}


	public function authenticate( \WP_User|\WP_Error $user ): \WP_User|\WP_Error {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$existing = Attempts::in()->get_existing( $user->user_login );
		if ( null !== $existing && $existing->is_blocked() ) {
			return new \WP_Error( self::CODE_BLOCKED, $this->get_error() );
		}

		return $user;
	}


	private function get_error(): string {
		$contact = Settings::in()->get_option( Settings::CONTACT, '' );
		if ( ! \is_string( $contact ) || '' === $contact ) {
			return '<strong>ERROR:</strong> Too many failed login attempts.';
		}

		return "<strong>ERROR:</strong> Too many failed login attempts.<br />Use the <a href=\"{$contact}\">contact form</a> for help.";
	}
}

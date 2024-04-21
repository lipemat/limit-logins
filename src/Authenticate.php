<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Settings\Limit_Logins as Settings;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Authenticate {
	use Singleton;

	public const CODE_BLOCKED = 'blocked';


	private function hook(): void {
		add_filter( 'authenticate', [ $this, 'authenticate' ], 1_000, 2 );
	}


	/**
	 * @internal
	 */
	public function authenticate( null|\WP_User|\WP_Error $user, string $username ): null|\WP_User|\WP_Error {
		$existing = Attempts::in()->get_existing( $username );
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

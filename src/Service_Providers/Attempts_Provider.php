<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Attempts;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Attempts_Provider implements Provider {
	public function register(): void {
		$this->Attempts();
	}


	private function Attempts(): void {
		add_action( 'wp_login_failed', fn( $a ) => Attempts::in()->log_failure( $a ) );
		add_action( 'application_password_failed_authentication', fn() => Attempts::in()->maybe_log_application_password_failure() );
	}
}

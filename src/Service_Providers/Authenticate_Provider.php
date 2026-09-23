<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Authenticate\Unlock_Link;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Authenticate_Provider implements Provider {
	public function register(): void {
		$this->Unlock_Link();
	}


	private function Unlock_Link(): void {
		add_action( 'login_form_' . Unlock_Link::ACTION, function() {
			Unlock_Link::in()->maybe_unlock();
		} );
	}
}

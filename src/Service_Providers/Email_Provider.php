<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Lib\Api\Api;
use Lipe\Limit_Logins\Email\Preview;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Email_Provider implements Provider {
	public function register(): void {
		$this->Preview();
	}


	private function Preview(): void {
		add_action( Api::in()->get_action( Preview::ENDPOINT ), function() {
			Preview::in()->preview();
		} );
		Api::init_once();
	}
}

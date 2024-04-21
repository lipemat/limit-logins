<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Authenticate;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Xmlrpc {
	use Singleton;

	private function hook(): void {
		add_filter( 'xmlrpc_login_error', [ $this, 'adjust_xmlrpc_error' ], 10, 2 );
	}


	public function adjust_xmlrpc_error( \IXR_Error $ixr, \WP_Error $error ): \IXR_Error {
		if ( Authenticate::CODE_BLOCKED === $error->get_error_code() ) {
			$ixr->message = 'Too many failed login attempts.';
			$ixr->code = $error->get_error_code();
		}

		return $ixr;
	}
}

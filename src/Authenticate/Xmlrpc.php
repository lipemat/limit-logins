<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Lib\Container\Instance;
use Lipe\Limit_Logins\Authenticate;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Xmlrpc {
	use Instance;

	public function adjust_xmlrpc_error( \IXR_Error $ixr, \WP_Error $error ): \IXR_Error {
		if ( Authenticate::CODE_BLOCKED === $error->get_error_code() ) {
			$ixr->message = Authenticate::MESSAGE_BLOCKED;
			$ixr->code = $error->get_error_code();
		}

		return $ixr;
	}
}

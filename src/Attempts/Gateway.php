<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Utils;

enum Gateway: string {
	case REST_API = 'rest_api';
	case WP_LOGIN = 'wp_login';
	case WOO_LOGIN = 'woo_login';
	case XMLRPC = 'xmlrpc';


	public static function detect(): self {
		if ( isset( $_POST['woocommerce-login-nonce'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification
			return self::WOO_LOGIN;
		}

		if ( \defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return self::XMLRPC;
		}

		if ( isset( $GLOBALS['wp_xmlrpc_server'] ) && $GLOBALS['wp_xmlrpc_server'] instanceof \IXR_Server ) {
			return self::XMLRPC;
		}

		if ( Utils::in()->is_rest_request() ) {
			return self::REST_API;
		}

		return self::WP_LOGIN;
	}
}

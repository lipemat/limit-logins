<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Log;

enum Gateway: string {
	case WP_LOGIN = 'wp_login';
	case WOO_LOGIN = 'woo_login';
	case XMLRPC = 'xmlrpc';


	public static function detect(): self {
		if ( isset( $_POST['woocommerce-login-nonce'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification
			return self::WOO_LOGIN;
		} elseif ( isset( $GLOBALS['wp_xmlrpc_server'] ) && is_object( $GLOBALS['wp_xmlrpc_server'] ) ) {
			return self::XMLRPC;
		}

		return self::WP_LOGIN;
	}
}

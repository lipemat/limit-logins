<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Utils;

enum Gateway: string {
	/**
	 * Nonce field WooCommerce puts on its login form.
	 */
	public const string WOO_NONCE_FIELD = 'woocommerce-login-nonce';

	case REST_API  = 'rest-api';
	case WP_LOGIN  = 'wp-login';
	case WOO_LOGIN = 'woo-login';
	case XMLRPC    = 'xmlrpc';


	public static function detect(): self {
		//phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_REQUEST[ self::WOO_NONCE_FIELD ] ) ) {
			return self::WOO_LOGIN;
		}

		if ( Utils::in()->is_xmlrpc_request() ) {
			return self::XMLRPC;
		}

		if ( Utils::in()->is_rest_request() ) {
			return self::REST_API;
		}

		return self::WP_LOGIN;
	}
}

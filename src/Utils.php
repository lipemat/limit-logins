<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Utils {
	public bool $did_exit = false;


	public function get_current_ip(): string {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && false !== \WP_Http::is_ip_address( sn( $_SERVER['REMOTE_ADDR'] ) ) ) {
			return sn( $_SERVER['REMOTE_ADDR'] );
		}
		return '0.0.0.0';
	}


	/**
	 * Errors from the application passwords do not provide a username.
	 * We use the IP with a 'rest-' designator to make easier to see in the admin.
	 *
	 * If we used the same user such as 'unknown', then ALL REST users
	 * would be blocked. Using the IP string allows us to block only the IP.
	 *
	 * @return string
	 */
	public function get_rest_username(): string {
		return 'rest-' . $this->get_current_ip();
	}


	public function is_rest_request(): bool {
		if ( \function_exists( 'wp_is_rest_endpoint' ) ) {
			return wp_is_rest_endpoint();
		}

		return \defined( 'REST_REQUEST' ) && REST_REQUEST;
	}


	public function is_xmlrpc_request(): bool {
		if ( \defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		return isset( $GLOBALS['wp_xmlrpc_server'] ) && $GLOBALS['wp_xmlrpc_server'] instanceof \IXR_Server;
	}


	/**
	 * @todo Switch to \Lipe\Lib\Util\Testing::exit() when we can require 5.0.0
	 * @phpstan-return  never
	 */
	public function exit(): void {
		if ( \defined( 'WP_UNIT_DIR' ) ) {
			$this->did_exit = true;
			throw new \OutOfBoundsException( 'Exit called in test context.' );
		}
		exit;
	}


	public static function in(): Utils {
		return container()->get( __CLASS__ );
	}
}

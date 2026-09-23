<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Authenticate;
use Lipe\Limit_Logins\Authenticate\Reset_Password;
use Lipe\Limit_Logins\Authenticate\Rest;
use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Authenticate\Xmlrpc;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Authenticate_Provider implements Provider {
	public function register(): void {
		$this->Authenticate();
		$this->Reset_Password();
		$this->Rest();
		$this->Unlock_Link();
		$this->Xmlrpc();
	}


	private function Authenticate(): void {
		add_filter( 'authenticate', fn( $a, $b, $c ) => Authenticate::in()->maybe_block_before_checks( $a, $b, $c ), 1, 3 );
		add_filter( 'authenticate', fn( $a, $b ) => Authenticate::in()->authenticate( $a, $b ), 1_000, 2 );
	}


	private function Reset_Password(): void {
		add_action( 'after_password_reset', fn( $a ) => Reset_Password::in()->clear_blocks_on_password_reset( $a ) );
	}


	private function Rest(): void {
		add_action( 'wp_authenticate_application_password_errors', fn( $a, $b ) => Rest::in()->rest_authenticate( $a, $b ), 9, 2 );
		add_action( 'application_password_failed_authentication', fn( $a ) => Rest::in()->rest_authenticate( $a ), 9 );
	}


	private function Unlock_Link(): void {
		add_action( 'login_form_' . Unlock_Link::ACTION, function() {
			Unlock_Link::in()->maybe_unlock();
		} );
	}


	private function Xmlrpc(): void {
		add_filter( 'xmlrpc_login_error', fn( $a, $b ) => Xmlrpc::in()->adjust_xmlrpc_error( $a, $b ), 10, 2 );
	}
}

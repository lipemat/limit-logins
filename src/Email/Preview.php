<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Lib\Api\Api;
use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Preview {
	use Singleton;

	private const ENDPOINT = 'lipe__limit_logins__email__preview';
	private const NONCE    = 'lipe/limit-logins/email/preview/nonce';


	private function hook(): void {
		add_action( Api::in()->get_action( self::ENDPOINT ), function() {
			$this->preview();
		} );
		Api::init_once();
	}


	public function get_url(): string {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return wp_nonce_url( Api::in()->get_url( self::ENDPOINT ), self::NONCE );
	}


	private function preview(): void {
		check_ajax_referer( self::NONCE, '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$attempts = Attempts::in()->get_all();
		if ( 0 === \count( $attempts ) ) {
			return;
		}
		$email = Blocked::factory( \reset( $attempts ), 'preview-key' );
		Util::in()->preview( $email );
	}
}

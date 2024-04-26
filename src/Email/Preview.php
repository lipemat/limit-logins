<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Lib\Api\Api;
use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Traits\Singleton;
use Lipe\Limit_Logins\Utils;
use function Lipe\Limit_Logins\container;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Preview {
	use Singleton;

	private const ENDPOINT = 'lipe__limit_logins__email__preview';
	private const NONCE    = 'lipe/limit-logins/email/preview/nonce';

	private bool $is_preview = false;

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
		$attempts = $this->get_valid_attempts();
		if ( 0 === \count( $attempts ) ) {
			return '';
		}
		return wp_nonce_url( Api::in()->get_url( self::ENDPOINT ), self::NONCE );
	}


	public function is_preview(): bool {
		return $this->is_preview;
	}


	private function preview(): void {
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$attempts = $this->get_valid_attempts();
		if ( 0 === \count( $attempts ) ) {
			return;
		}
		$email = Blocked::factory( \reset( $attempts ), 'preview-key' );
		$this->render( $email );
	}


	/**
	 * @phpstan-return never
	 */
	private function render( Email $email ): void {
		$this->is_preview = true;
		echo $email->get_message(); //phpcs:ignore
		Utils::in()->exit();
	}


	/**
	 * @return Attempt[]
	 */
	private function get_valid_attempts(): array {
		return \array_filter( Attempts::in()->get_all(), function(
			Attempt $attempt
		) {
			return false !== username_exists( $attempt->username );
		} );
	}


	public static function in(): Preview {
		return container()->get( __CLASS__ );
	}
}

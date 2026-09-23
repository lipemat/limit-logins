<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Email;

use Lipe\Lib\Api\Api;
use Lipe\Lib\Container\Instance;
use Lipe\Lib\Util\Testing;
use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Preview {
	use Instance;

	public const string ENDPOINT = 'lipe__limit_logins__email__preview';

	private const string NONCE = 'lipe/limit-logins/email/preview/nonce';

	private bool $is_preview = false;


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


	public function preview(): void {
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
		Testing::in()->exit();
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
}

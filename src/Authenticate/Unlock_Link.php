<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Email\Blocked;
use Lipe\Limit_Logins\Email\Util;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Unlock_Link {
	use Singleton;

	private const ACTION     = 'unlock-account';
	private const KEY        = 'key';
	private const ERROR_CODE = 'invalid-unlock';


	private function hook(): void {
		add_action( 'login_form_' . self::ACTION, [ $this, 'maybe_unlock' ] );
	}


	public function get_unlock_url( string $key ): string {
		return add_query_arg( [
			'action'  => self::ACTION,
			self::KEY => $key,
		], wp_login_url() );
	}


	/**
	 * @return array{key: string, hash: string}
	 */
	public function get_unlock_key(): array {
		$hasher = $this->get_hasher();
		$key = wp_generate_password( 20, false );
		$hashed = $hasher->HashPassword( $key );

		return [
			'key'  => $key,
			'hash' => $hashed,
		];
	}


	public function send_blocked_email( Attempt $attempt ): void {
		$key = $this->get_unlock_key();
		$attempt->set_key( $key['hash'] );
		$email = Blocked::factory( $attempt, $key['key'] );
		Util::in()->send( $email );
	}


	/**
	 * @internal
	 */
	public function maybe_unlock(): void {
		$block = null;
		if ( isset( $_GET[ self::KEY ] ) ) {
			$block = $this->get_matching_block( sn( $_GET[ self::KEY ] ) );
		}

		if ( ! $block instanceof Attempt ) {
			add_filter( 'wp_login_errors', function( \WP_Error $errors ) {
				$errors->add( self::ERROR_CODE, '<strong>Error:</strong> Invalid unlock link. The failure has been recorded.' );
				return $errors;
			} );
			return;
		}

		Attempts::in()->remove_block( $block->username );
		$this->render();
	}


	private function get_matching_block( string $key ): ?Attempt {
		$hasher = $this->get_hasher();
		foreach ( Attempts::in()->get_all() as $attempt ) {
			if ( '' === $attempt->get_key() || ! $hasher->CheckPassword( $key, $attempt->get_key() ) ) {
				continue;
			}
			return $attempt;
		}

		return null;
	}


	private function get_hasher(): \PasswordHash {
		global $wp_hasher;
		if ( ! $wp_hasher instanceof \PasswordHash ) {
			require_once ABSPATH . '/wp-includes/class-phpass.php';
			$wp_hasher = new \PasswordHash( 8, true );
		}
		return $wp_hasher;
	}


	private function render(): void {
		login_header(
			__( 'Account Unlocked' ),
			wp_get_admin_notice( 'Your account has been unlocked. <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a>',
				[
					'type'               => 'info',
					'additional_classes' => [ 'message' ],
				]
			)
		);
		login_footer();
		die();
	}
}

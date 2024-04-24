<?php
//phpcs:disable WordPress.Security.NonceVerification.Recommended
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Container;
use Lipe\Limit_Logins\Email\Blocked;
use Lipe\Limit_Logins\Email\Util;
use Lipe\Limit_Logins\Traits\Singleton;
use Lipe\Limit_Logins\Utils;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Unlock_Link {
	use Singleton;

	private const ACTION     = 'unlock-account';
	private const KEY        = 'unlock-key';
	private const ERROR_CODE = 'invalid-unlock';


	private function hook(): void {
		add_action( 'login_form_' . self::ACTION, function() {
			self::in()->maybe_unlock();
		} );
	}


	/**
	 * Get the URL to be included in the blocked emails.
	 *
	 * @param string $key - Unlock key before being hashed.
	 */
	public function get_unlock_url( string $key ): string {
		return add_query_arg( [
			'action'  => self::ACTION,
			self::KEY => $key,
		], wp_login_url() );
	}


	/**
	 * Mimic the password reset URL used by WP core.
	 *
	 * @see retrieve_password()
	 */
	public function get_reset_password_url( string $username ): string {
		$user = get_user_by( 'login', $username );
		if ( false === $user ) {
			return '';
		}
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return '';
		}
		return add_query_arg( [
			'action' => 'rp',
			'key'    => $key,
			'login'  => rawurlencode( $username ),
		], network_site_url( 'wp-login.php', 'login' ) );
	}


	/**
	 * Set the key on the attempt then send the email containing
	 * the link to unlock the account.
	 */
	public function send_blocked_email( Attempt $attempt ): void {
		$key = $this->get_unlock_key();
		$attempt->set_key( $key['hash'] );
		$email = Blocked::factory( $attempt, $key['key'] );
		Util::in()->send( $email );
	}


	/**
	 * Get the unlock key, and the hashed version to store in the database.
	 *
	 * We use one way hashing to store the key in the database so even
	 * if the attempts data is exposed the key is not.
	 *
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


	/**
	 * @phpstan-return never
	 */
	public function render(): void {
		login_header(
			'Account Unlocked',
			wp_get_admin_notice( 'Your account has been unlocked. <a href="' . esc_url( wp_login_url() ) . '">Log in</a>',
				[
					'type'               => 'info',
					'additional_classes' => [ 'message' ],
				]
			)
		);
		login_footer();
		Utils::in()->exit();
	}


	/**
	 * Called using a custom login action sent to `wp-login.php`.
	 *
	 * A key passed to the URL much be key set to the block before
	 * it is hashed and stored.
	 */
	private function maybe_unlock(): void {
		$block = null;
		if ( isset( $_GET[ self::KEY ] ) ) {
			$block = $this->get_matching_attempt( sn( $_GET[ self::KEY ] ) );
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


	private function get_matching_attempt( string $key ): ?Attempt {
		$hasher = $this->get_hasher();
		foreach ( Attempts::in()->get_all() as $attempt ) {
			if ( '' === $attempt->get_key() || ! $hasher->CheckPassword( $key, $attempt->get_key() ) ) {
				continue;
			}
			return $attempt;
		}

		return null;
	}


	/**
	 * Get a reusable password hasher.
	 *
	 * @return \PasswordHash
	 */
	private function get_hasher(): \PasswordHash {
		static $wp_hasher;
		if ( ! $wp_hasher instanceof \PasswordHash ) {
			require_once ABSPATH . '/wp-includes/class-phpass.php';
			$wp_hasher = new \PasswordHash( 8, true );
		}
		return $wp_hasher;
	}


	public static function in(): Unlock_Link {
		return Container::instance()->get( __CLASS__ );
	}
}

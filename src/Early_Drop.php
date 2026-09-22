<?php
//phpcs:disable WordPress.Security.NonceVerification
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * Drop blocked login submissions when `limit-logins.php` is included.
 *
 * The earliest point a self-contained plugin gets. CMB2 and the rest of the
 * WordPress bootstrap are skipped entirely for a blocked submission.
 *
 * A Composer autoloader may pull this file in as early as must-use plugin
 * load, so nothing here may assume `vars.php` or the database has run.
 *
 * @author Mat Lipe
 * @since  September 2026
 *
 */
final class Early_Drop {
	use Singleton;

	private const string LOGIN_SCRIPT = 'wp-login.php';

	/**
	 * `wp-login.php` actions which are not a credentials submission.
	 *
	 * A deny list, because core falls back to `login` for anything it does
	 * not recognize, including junk an attacker appends to skip this check.
	 *
	 * @see wp-login.php `$default_actions`
	 */
	private const array NON_LOGIN_ACTIONS = [
		'checkemail',
		'confirm_admin_email',
		'confirmaction',
		'entered_recovery_mode',
		'logout',
		'lostpassword',
		'postpass',
		'register',
		'resetpass',
		'retrievepassword',
		'rp',
	];


	private function hook(): void {
		$this->maybe_drop();
	}


	/**
	 * Exit with a `403` when a detectable login submission is already blocked.
	 *
	 * Everything else, including the GET login form, lost password, reset
	 * password and the unlock link, returns without touching the database.
	 */
	private function maybe_drop(): void {
		if ( ! $this->is_database_ready() ) {
			// Included before the database exists, retry at the first hook where it does.
			if ( 0 === did_action( 'muplugins_loaded' ) ) {
				add_action( 'muplugins_loaded', $this->maybe_drop( ... ), 0 );
			}
			return;
		}

		$username = $this->get_submitted_username();
		if ( null === $username ) {
			return;
		}

		if ( ! Attempts::in()->is_blocked( $username ) ) {
			return;
		}

		$this->render();
	}


	/**
	 * The submitted username when the request is a login submission we can detect.
	 *
	 * `null` for every other request, including the GET login form and the
	 * lost password, reset password and unlock actions.
	 */
	private function get_submitted_username(): ?string {
		if ( 'POST' !== $this->get_server_value( 'REQUEST_METHOD' ) ) {
			return null;
		}

		if ( null !== $this->get_posted_value( Gateway::WOO_NONCE_FIELD ) ) {
			return $this->get_posted_value( 'username' );
		}

		if ( ! $this->is_wp_login() ) {
			return null;
		}
		if ( \in_array( $this->get_requested_action(), self::NON_LOGIN_ACTIONS, true ) ) {
			return null;
		}

		return $this->get_posted_value( 'log' );
	}


	/**
	 * Is the request going to `wp-login.php`?
	 *
	 * `$pagenow` is not set until `vars.php` runs, which is after must-use
	 * plugins. Fall back to the detection `vars.php` performs.
	 */
	private function is_wp_login(): bool {
		$pagenow = $GLOBALS['pagenow'] ?? null;
		if ( \is_string( $pagenow ) ) {
			return self::LOGIN_SCRIPT === $pagenow;
		}

		$path = $this->get_server_value( 'PHP_SELF' );
		if ( '' === $path ) {
			// A query string would let `?x=/wp-login.php` match, so drop it first.
			$path = \strtok( $this->get_server_value( 'REQUEST_URI' ), '?' );
		}
		if ( ! \is_string( $path ) || 1 !== \preg_match( '#([^/]+\.php)(/.*?)?$#i', $path, $matches ) ) {
			return false;
		}

		return self::LOGIN_SCRIPT === $matches[1];
	}


	/**
	 * The `wp-login.php` action, which core reads from either the query or the form.
	 */
	private function get_requested_action(): string {
		if ( ! isset( $_REQUEST['action'] ) || ! \is_string( $_REQUEST['action'] ) ) {
			return '';
		}

		return sn( $_REQUEST['action'] );
	}


	/**
	 * Get a sanitized string from the posted form, or `null` when it is missing.
	 */
	private function get_posted_value( string $key ): ?string {
		if ( ! isset( $_POST[ $key ] ) || ! \is_string( $_POST[ $key ] ) ) {
			return null;
		}

		return sn( $_POST[ $key ] );
	}


	/**
	 * Get a sanitized string from the server variables.
	 */
	private function get_server_value( string $key ): string {
		if ( ! isset( $_SERVER[ $key ] ) || ! \is_string( $_SERVER[ $key ] ) ) {
			return '';
		}

		return sn( $_SERVER[ $key ] );
	}


	/**
	 * `$wpdb` and the object cache are both in place before any plugin file loads,
	 * unless the plugin was included from `wp-config.php` or an `auto_prepend_file`.
	 */
	private function is_database_ready(): bool {
		global $wpdb;
		return $wpdb instanceof \wpdb && \function_exists( 'wp_cache_get' );
	}


	/**
	 * Send the blocked page.
	 *
	 * `wp_die()` is not used because its handler loads translations and robots
	 * filters, which is the bootstrap work this drop exists to skip.
	 *
	 * @phpstan-return never
	 */
	private function render(): void {
		if ( ! \headers_sent() ) {
			\header( 'Content-Type: text/html; charset=utf-8' );
		}
		status_header( 403 );
		nocache_headers();

		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Login Blocked</title></head><body><p>';
		echo wp_kses_post( Authenticate::in()->get_blocked_message() );
		echo '</p><p><a href="' . esc_url( wp_lostpassword_url() ) . '">Lost your password?</a></p></body></html>';

		Utils::in()->exit();
	}
}

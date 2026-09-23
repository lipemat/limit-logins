<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Util\Testing;
use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Authenticate\Rest;
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
	 * Default REST prefix, because `rest_url_prefix` may not be hooked yet.
	 */
	private const string REST_PREFIX = 'wp-json';

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

	/**
	 * XML-RPC methods core serves without credentials.
	 *
	 * `system.multicall` is missing on purpose: the calls it wraps carry
	 * credentials of their own.
	 */
	private const array UNAUTHENTICATED_METHODS = [
		'system.getCapabilities',
		'system.listMethods',
	];

	/**
	 * Prefixes of the XML-RPC method families core serves without credentials.
	 */
	private const array UNAUTHENTICATED_PREFIXES = [
		'demo.',
		'pingback.',
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

		// Ahead of gateway detection, so the setting covers every gateway.
		if ( true === Settings::in()->get_option( Settings::DISABLE_EARLY_DROP, false ) ) {
			return;
		}

		if ( Utils::in()->is_xmlrpc_request() ) {
			$this->maybe_drop_xmlrpc();
		} elseif ( $this->is_rest_with_credentials() ) {
			$this->maybe_drop_rest();
		} else {
			$this->maybe_drop_form();
		}
	}


	/**
	 * Drop a blocked submission from the wp-login or WooCommerce login form.
	 */
	private function maybe_drop_form(): void {
		$username = $this->get_submitted_username();
		if ( null === $username ) {
			return;
		}

		if ( ! Attempts::in()->is_blocked( $username ) ) {
			return;
		}

		$this->render_form();
	}


	/**
	 * Drop an XML-RPC call which authenticates when the IP is blocked.
	 *
	 * The username lives in the method parameters, which the `authenticate`
	 * fallback reads once core has parsed the request.
	 */
	private function maybe_drop_xmlrpc(): void {
		// Checked first so an unblocked call never reads the request body.
		if ( ! Attempts::in()->is_ip_blocked() ) {
			return;
		}

		if ( ! $this->requires_credentials() ) {
			return;
		}

		$this->render_xmlrpc();
	}


	/**
	 * Drop a REST request whose Basic auth user or IP is blocked.
	 */
	private function maybe_drop_rest(): void {
		if ( ! Attempts::in()->is_blocked( $this->get_server_value( 'PHP_AUTH_USER' ) ) ) {
			return;
		}

		$this->render_rest();
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
		if ( \in_array( $this->get_requested_value( 'action' ), self::NON_LOGIN_ACTIONS, true ) ) {
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
			$path = $this->get_request_path();
		}
		if ( 1 !== \preg_match( '#([^/]+\.php)(/.*?)?$#i', $path, $matches ) ) {
			return false;
		}

		return self::LOGIN_SCRIPT === $matches[1];
	}


	/**
	 * Is the request going to the REST API carrying credentials of its own?
	 *
	 * Cookie authenticated requests send no `PHP_AUTH_USER`, except behind
	 * server Basic auth, where a blocked IP is dropped either way.
	 */
	private function is_rest_with_credentials(): bool {
		if ( '' === $this->get_server_value( 'PHP_AUTH_USER' ) ) {
			return false;
		}
		if ( '' !== $this->get_requested_value( 'rest_route' ) ) {
			return true;
		}

		return 1 === \preg_match( '#/' . self::REST_PREFIX . '(/|$)#', $this->get_request_path() );
	}


	/**
	 * Does the called XML-RPC method require credentials?
	 */
	private function requires_credentials(): bool {
		$method = $this->get_called_method();
		if ( '' === $method || \in_array( $method, self::UNAUTHENTICATED_METHODS, true ) ) {
			return false;
		}

		foreach ( self::UNAUTHENTICATED_PREFIXES as $prefix ) {
			if ( \str_starts_with( $method, $prefix ) ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * The method name from the XML-RPC request body.
	 *
	 * CDATA is accepted because core's parser dispatches it the same way.
	 */
	private function get_called_method(): string {
		if ( 1 !== \preg_match( '#<methodName>\s*(?:<!\[CDATA\[\s*)?([\w.]+)#', Utils::in()->get_request_body(), $matches ) ) {
			return '';
		}

		return $matches[1];
	}


	/**
	 * Get a sanitized string from the query or the form, which is where core
	 * reads the `wp-login.php` action and the REST route from.
	 */
	//phpcs:disable WordPress.Security.NonceVerification -- Core login has no nonce; these form fields are read-only.
	private function get_requested_value( string $key ): string {
		if ( ! isset( $_REQUEST[ $key ] ) || ! \is_string( $_REQUEST[ $key ] ) ) {
			return '';
		}

		return sn( $_REQUEST[ $key ] );
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
	//phpcs:enable WordPress.Security.NonceVerification


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
	 * The requested path with any query string removed.
	 *
	 * A query string would let `?x=/wp-login.php` match the path checks.
	 */
	private function get_request_path(): string {
		$path = \strtok( $this->get_server_value( 'REQUEST_URI' ), '?' );

		return \is_string( $path ) ? $path : '';
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
	 * Send the blocked login page.
	 *
	 * `wp_die()` is not used because its handler loads translations and robots
	 * filters, which is the bootstrap work this drop exists to skip.
	 *
	 * @phpstan-return never
	 */
	private function render_form(): void {
		$this->send_headers( 'text/html; charset=utf-8' );

		echo '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Login Blocked</title></head><body><p>';
		echo wp_kses_post( Authenticate::in()->get_blocked_message() );
		echo '</p><p><a href="' . esc_url( wp_lostpassword_url() ) . '">Lost your password?</a></p></body></html>';

		Testing::in()->exit();
	}


	/**
	 * Send the fault `IXR_Error` would render, which `class-IXR.php` is too
	 * early to provide.
	 *
	 * @phpstan-return never
	 */
	private function render_xmlrpc(): void {
		$charset = get_bloginfo( 'charset' );
		$this->send_headers( 'text/xml; charset=' . $charset );

		echo '<?xml version="1.0" encoding="' . esc_attr( $charset ) . '"?>';
		echo '<methodResponse><fault><value><struct>';
		echo '<member><name>faultCode</name><value><int>' . esc_html( Authenticate::CODE_BLOCKED ) . '</int></value></member>';
		echo '<member><name>faultString</name><value><string>' . esc_html( Authenticate::MESSAGE_BLOCKED ) . '</string></value></member>';
		echo '</struct></value></fault></methodResponse>';

		Testing::in()->exit();
	}


	/**
	 * Send the body the REST API renders for the same error once it has loaded.
	 *
	 * @phpstan-return never
	 */
	private function render_rest(): void {
		$this->send_headers( 'application/json; charset=' . get_bloginfo( 'charset' ) );

		$error = Rest::in()->get_rest_blocked_error();
		echo wp_json_encode( [
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'data'    => $error->get_error_data(),
		] );

		Testing::in()->exit();
	}


	/**
	 * Send the `403` headers shared by every dropped gateway.
	 *
	 * Only the content type needs a guard; `status_header()` and
	 * `nocache_headers()` check `headers_sent()` themselves.
	 */
	private function send_headers( string $content_type ): void {
		if ( ! \headers_sent() ) {
			\header( 'Content-Type: ' . $content_type );
		}
		status_header( 403 );
		nocache_headers();
	}
}

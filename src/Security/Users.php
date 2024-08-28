<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\container;

/**
 * @author Mat Lipe
 * @since  0.17.0
 *
 */
final class Users {
	/**
	 * Disable the users' endpoints for any user who does not have
	 * edit_users capabilities.
	 *
	 * @param array<string, mixed> $endpoints
	 *
	 * @filter   rest_endpoints 10 1
	 *
	 * @return array<string, mixed>
	 */
	public function disable_users_endpoint( array $endpoints ): array {
		if ( ! Settings::in()->get_option( Settings::DISABLE_USER_REST, false ) ) {
			return $endpoints;
		}
		if ( ! current_user_can( 'edit_users' ) ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'], $endpoints['/wp/v2/users/me'] );
		}

		return $endpoints;
	}


	/**
	 * Prevent the admin username from being used as a login.
	 *
	 * @filter  illegal_user_logins 10 1
	 *
	 * @param array<string> $illegal
	 *
	 * @return array<string>
	 */
	public function prevent_admin_username( array $illegal ): array {
		return \array_merge( $illegal, [
			'admin',
			'administrator',
			'root',
			'superadmin',
			'support',
			'sysadmin',
			'webmaster',
		] );
	}


	/**
	 * Disable the `author` query var so `?author=mat` resolves as either
	 * a 404 or simple loads the homepage.
	 *
	 * @filter query_vars 1_000 1
	 *
	 * @param array<int, string> $query_vars
	 *
	 * @return array<int, string>
	 */
	public function disable_author_query_var( array $query_vars ): array {
		if ( ! Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE, false ) ) {
			return $query_vars;
		}

		unset(
			$query_vars[ (int) \array_search( 'author', $query_vars, true ) ],
			$query_vars[ (int) \array_search( 'author_name', $query_vars, true ) ]
		);
		return $query_vars;
	}


	/**
	 * Remove the rewrite rules for author archives to prevent
	 * and `/author` requests from resolving.
	 *
	 * @filter author_rewrite_rules 10 1
	 *
	 * @param string[] $rewrites
	 *
	 * @return string[]
	 */
	public function disable_author_archives( array $rewrites ): array {
		if ( ! Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE, false ) ) {
			return $rewrites;
		}
		return [];
	}


	public static function in(): Users {
		return container()->get( __CLASS__ );
	}
}

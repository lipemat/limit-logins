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
	 * Remove the author link from any REST responses.
	 *
	 * Will be broken if the endpoints are disabled.
	 *
	 * @filter rest_request_after_callbacks 10 1
	 */
	public function remove_author_links_from_rest_responses( mixed $result ): mixed {
		if (
			! $result instanceof \WP_REST_Response ||
			! Settings::in()->get_option( Settings::DISABLE_USER_REST, false ) ||
			current_user_can( 'edit_users' )
		) {
			return $result;
		}

		$result->remove_link( 'author' );
		return $result;
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
			'dev',
			'root',
			'superadmin',
			'support',
			'sysadmin',
			'webmaster',
		] );
	}


	/**
	 * Disable the `author` query var so `?author=mat` resolves as either
	 * a 404 or simply loads the homepage.
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


	/**
	 * Remove the users from the sitemap entirely.
	 *
	 * @filter wp_sitemaps_add_provider 10 2
	 *
	 * @param \WP_Sitemaps_Provider $provider
	 * @param string                $name
	 *
	 * @return false|\WP_Sitemaps_Provider
	 */
	public function disable_user_sitemap( \WP_Sitemaps_Provider $provider, string $name ): false|\WP_Sitemaps_Provider {
		// CMB2 is not yet availble in the `wp_sitemaps_add_provider` filter.
		$disabled = ( get_option( Settings::NAME, [] )[ Settings::DISABLE_USER_ARCHIVE ] ?? false ) === 'on';

		if ( 'users' === $name && $disabled ) {
			return false;
		}
		return $provider;
	}


	/**
	 * Disable any links on the site to author archives which
	 * are broken when the author archive is disabled.
	 *
	 * @filter author_link 10 1
	 * @filter get_the_author_{$field} 10 1
	 *
	 * @param string $url - The URL to the author archive.
	 *
	 * @return string
	 */
	public function disable_author_links( string $url ): string {
		if ( ! Settings::in()->get_option( Settings::DISABLE_USER_ARCHIVE, false ) ) {
			return $url;
		}
		return '';
	}


	/**
	 * Remove any exposed author information from the body classes.
	 *
	 * @filter body_class 10 1
	 *
	 * @param array<string> $classes - The body classes.
	 *
	 * @return array<string>
	 */
	public function remove_author_body_classes( array $classes ): array {
		return \array_filter( $classes, fn( $css_class ) => ! \str_contains( $css_class, 'author-' ) );
	}


	public static function in(): Users {
		return container()->get( __CLASS__ );
	}
}

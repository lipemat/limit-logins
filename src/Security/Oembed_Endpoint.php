<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\container;

/**
 * @author  Mat Lipe
 * @since   0.17.1
 *
 * @phpstan-type DATA array{
 *  version: string,
 *  provider_name: string,
 *  provider_url: string,
 *  author_name: string,
 *  author_url: string,
 *  title: string,
 *  type: string,
 *  width: int,
 *  height: int,
 *  html: string
 * }
 */
final class Oembed_Endpoint {

	/**
	 * The oEmbed endpoint exposes the main user's username to the public.
	 * oEmbeds won't work because we have `X-Frame-Options` to `sameorigin`
	 * in `.htaccess` so no need for the oembed endpoint anyway.
	 *
	 * We leave the `/proxy` endpoint intact, so we can still embed
	 * external links in this site.
	 *
	 * @action cmb2_after_init 100 1
	 */
	public function remove_oembed_endpoint(): void {
		if ( ! Settings::in()->get_option( Settings::DISABLE_OEMBED, false ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		add_filter( 'rest_endpoints', function( $endpoints ) {
			unset( $endpoints['/oembed/1.0/embed'] );
			return $endpoints;
		} );
	}


	/**
	 * If the proxy endpoint is still enabled, a local post could be used
	 * to get the oEmbed data for a remote post.
	 *
	 * We always use the site as the author name and URL to prevent
	 * exposing the local author's name and URL.
	 *
	 * @filter oembed_response_data 100 1
	 *
	 * @phpstan-param DATA $data
	 * @phpstan-return DATA
	 */
	public function remove_author_data( array $data ): array {
		if ( ! Settings::in()->get_option( Settings::DISABLE_OEMBED, false ) ) {
			return $data;
		}

		$data['author_name'] = get_bloginfo( 'name' );
		$data['author_url'] = get_home_url();
		return $data;
	}


	public static function in(): Oembed_Endpoint {
		return container()->get( __CLASS__ );
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Security;

use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\container;

/**
 * @author  Mat Lipe
 * @since   0.17.1
 */
final class Oembed_Endpoint {

	/**
	 * The oEmbed endpoint exposes the main user's username to the public.
	 * oEmbeds won't work because we have `X-Frame-Options` to `sameorigin`
	 * in `.htaccess` so no need for the oembed endpoint anyway.
	 *
	 * We leave the `/proxy` endpoint intact, so we can still embed
	 * external links in this site.
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


	public static function in(): Oembed_Endpoint {
		return container()->get( __CLASS__ );
	}
}

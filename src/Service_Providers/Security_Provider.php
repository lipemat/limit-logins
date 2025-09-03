<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Security\Oembed_Endpoint;
use Lipe\Limit_Logins\Security\Username_Detection;
use Lipe\Limit_Logins\Security\Users;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 * @phpstan-type PROVIDER array{
 *     "Lipe\Limit_Logins\Security\Oembed_Endpoint": Oembed_Endpoint,
 *     "Lipe\Limit_Logins\Security\Username_Detection": Username_Detection,
 *     "Lipe\Limit_Logins\Security\Users": Users,
 * }
 */
final class Security_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple[ Oembed_Endpoint::class ] = fn() => new Oembed_Endpoint();
		$pimple[ Username_Detection::class ] = fn() => new Username_Detection();
		$pimple[ Users::class ] = fn() => new Users();

		$this->Oembed_Endpoint();
		$this->Username_Detection();
		$this->Users();
	}


	private function Oembed_Endpoint(): void {
		add_action( 'cmb2_after_init', function() {
			Oembed_Endpoint::in()->remove_oembed_endpoint();
		}, 100 );
		add_filter( 'oembed_response_data', function( $data ) {
			return Oembed_Endpoint::in()->remove_author_data( $data );
		}, 100 );
	}


	private function Users(): void {
		add_filter( 'illegal_user_logins', fn( $a ) => Users::in()->prevent_admin_username( $a ) );
		add_filter( 'rest_endpoints', fn( $a ) => Users::in()->disable_users_endpoint( $a ) );
		add_filter( 'rest_request_after_callbacks', fn( $a ) => Users::in()->remove_author_links_from_rest_responses( $a ) );
		add_filter( 'query_vars', fn( $a ) => Users::in()->disable_author_query_var( $a ), 1_000 );
		add_filter( 'author_rewrite_rules', fn( $a ) => Users::in()->disable_author_archives( $a ) );
		add_filter( 'wp_sitemaps_add_provider', fn( $a, $b ) => Users::in()->disable_user_sitemap( $a, $b ), 10, 2 );
		add_filter( 'author_link', fn( $a ) => Users::in()->disable_author_links( $a ) );
		add_filter( 'get_the_author_user_url', fn( $a ) => Users::in()->disable_author_links( $a ) );
		add_filter( 'body_class', fn( $a ) => Users::in()->remove_author_body_classes( $a ) );
	}


	private function Username_Detection(): void {
		add_action( 'wp_error_added', fn( $a, $b, $c, $d ) => Username_Detection::in()->standardize_login_errors( $a, $d ), 10, 4 );
		add_filter( 'lostpassword_user_data', fn( $a, $b ) => Username_Detection::in()->use_dummy_user_for_lost_password( $a, $b ), 10, 2 );
		add_filter( 'send_retrieve_password_email', fn( $a, $b, $c ) => Username_Detection::in()->prevent_dummy_user_email( $a, $c ), 10, 3 );
	}
}

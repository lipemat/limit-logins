<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Security\Oembed_Endpoint;
use Lipe\Limit_Logins\Security\Users;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 * @phpstan-type PROVIDER array{
 *     "Lipe\Limit_Logins\Security\Oembed_Endpoint": Oembed_Endpoint,
 *     "Lipe\Limit_Logins\Security\Users": Users,
 * }
 */
final class Security_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple[ Oembed_Endpoint::class ] = fn() => new Oembed_Endpoint();
		$pimple[ Users::class ] = fn() => new Users();

		$this->Oembed_Endpoint();
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
		add_filter( 'query_vars', fn( $a ) => Users::in()->disable_author_query_var( $a ), 1_000 );
		add_filter( 'author_rewrite_rules', fn( $a ) => Users::in()->disable_author_archives( $a ) );
	}
}

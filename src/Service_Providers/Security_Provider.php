<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Settings;
use Lipe\Limit_Logins\Security\Users;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * @author Mat Lipe
 * @since  August 2024
 *
 */
final class Security_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple[ Users::class ] = fn() => new Users();

		$this->Users();
	}


	private function Users(): void {
		add_filter( 'illegal_user_logins', fn( $a ) => Users::in()->prevent_admin_username( $a ) );
		add_filter( 'rest_endpoints', fn( $a ) => Users::in()->disable_users_endpoint( $a ) );
		add_filter( 'query_vars', fn( $a ) => Users::in()->disable_author_query_var( $a ), 1_000 );
		add_filter( 'author_rewrite_rules', fn( $a ) => Users::in()->disable_author_archives( $a ) );
	}
}

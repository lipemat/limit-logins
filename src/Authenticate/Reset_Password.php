<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Reset_Password {
	use Singleton;

	private function hook(): void {
		add_action( 'after_password_reset', [ $this, 'clear_blocks_on_password_reset' ] );
	}


	public function clear_blocks_on_password_reset( \WP_User $user ): void {
		Attempts::in()->remove_block( $user->user_login );
	}
}

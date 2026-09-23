<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Authenticate;

use Lipe\Lib\Container\Instance;
use Lipe\Limit_Logins\Attempts;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Reset_Password {
	use Instance;

	public function clear_blocks_on_password_reset( \WP_User $user ): void {
		Attempts::in()->remove_block( $user->user_login );
	}
}

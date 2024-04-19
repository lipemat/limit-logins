<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Utils;

use Lipe\Lib\Traits\Singleton;
use function Lipe\Limit_Logins\sn;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Ip {
	use Singleton;

	public function get_current_ip(): string {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && false !== \WP_Http::is_ip_address( sn( $_SERVER['REMOTE_ADDR'] ) ) ) {
			return sn( $_SERVER['REMOTE_ADDR'] );
		}
		return '0.0.0.0';
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Utils;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Core_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple['email.util'] = fn() => new Utils();
	}
}

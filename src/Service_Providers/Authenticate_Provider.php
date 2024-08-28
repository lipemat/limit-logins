<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-type PROVIDER array{
 *        "Lipe\Limit_Logins\Authenticate\Unlock_Link": Unlock_Link,
 * }
 */
final class Authenticate_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple[ Unlock_Link::class ] = fn() => new Unlock_Link();
	}
}

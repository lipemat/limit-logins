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
 * @phpstan-type PROVIDER array{
 *     "Lipe\Limit_Logins\Utils": Utils,
 * }
 */
final class Core_Provider implements ServiceProviderInterface {
	public function register( Container $pimple ): void {
		$pimple[ Utils::class ] = fn() => new Utils();
	}
}

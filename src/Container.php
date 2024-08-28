<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Email\Preview;
use Lipe\Limit_Logins\Security\Users;
use Lipe\Limit_Logins\Service_Providers\Authenticate_Provider;
use Lipe\Limit_Logins\Service_Providers\Core_Provider;
use Lipe\Limit_Logins\Service_Providers\Email_Provider;
use Lipe\Limit_Logins\Service_Providers\Security_Provider;

/**
 * @author Mat Lipe
 * @since  0.15.0
 *
 *
 * @phpstan-type SERVICES array{
 *     "Lipe\Limit_Logins\Security\Users": Users,
 *     "Lipe\Limit_Logins\Utils": Utils,
 *     "Lipe\Limit_Logins\Email\Preview": Preview,
 *     "Lipe\Limit_Logins\Authenticate\Unlock_Link": Unlock_Link,
 * }
 */
final class Container {
	/**
	 * Instance of this Class
	 *
	 * @var ?self
	 */
	protected static ?self $core_instance = null;

	/**
	 * @var \Pimple\Container
	 */
	protected \Pimple\Container $container;


	private function __construct( \Pimple\Container $container ) {
		$this->container = $container;
		$this->container->register( new Authenticate_Provider() );
		$this->container->register( new Core_Provider() );
		$this->container->register( new Email_Provider() );
		$this->container->register( new Security_Provider() );
	}


	/**
	 * Get key from container
	 *
	 * @template TKey of key-of<SERVICES>
	 * @phpstan-param TKey $key
	 *
	 * @phpstan-return SERVICES[TKey]
	 */
	public function get( string $key ): mixed {
		return $this->container()->offsetGet( $key );
	}


	/**
	 * Set key in container
	 *
	 * @template TKey of key-of<SERVICES>
	 *
	 * @phpstan-param TKey $key
	 * @phpstan-param SERVICES[TKey] $value
	 */
	public function set( string $key, mixed $value ): void {
		$this->container()->offsetSet( $key, $value );
	}


	public function container(): \Pimple\Container {
		return $this->container;
	}


	public static function instance(): self {
		if ( null === self::$core_instance ) {
			self::$core_instance = new self( new \Pimple\Container( [] ) );
		}

		return self::$core_instance;
	}
}

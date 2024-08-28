<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Service_Providers\Authenticate_Provider;
use Lipe\Limit_Logins\Service_Providers\Core_Provider;
use Lipe\Limit_Logins\Service_Providers\Email_Provider;
use Lipe\Limit_Logins\Service_Providers\Security_Provider;

/**
 * @author Mat Lipe
 * @since  0.15.0
 *
 * @phpstan-import-type PROVIDER from Authenticate_Provider as AUTHENTICATE_P
 * @phpstan-import-type PROVIDER from Core_Provider as CORE
 * @phpstan-import-type PROVIDER from Email_Provider as EMAIL
 * @phpstan-import-type PROVIDER from Security_Provider as SECURITY
 *
 * @phpstan-type SERVICES \Union<
 *    AUTHENTICATE_P,
 *    CORE,
 *    EMAIL,
 *    SECURITY,
 * >
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
			do_action( 'lipe/limit-logins/container-loaded', self::$core_instance->container() );
		}

		return self::$core_instance;
	}
}

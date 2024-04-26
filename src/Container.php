<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Limit_Logins\Service_Providers\Authenticate_Provider;
use Lipe\Limit_Logins\Service_Providers\Core_Provider;
use Lipe\Limit_Logins\Service_Providers\Email_Provider;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
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
	}


	/**
	 * Get key from container
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get( string $key ): mixed {
		return $this->container()->offsetGet( $key );
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

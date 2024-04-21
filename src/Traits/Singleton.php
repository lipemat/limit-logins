<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Traits;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Authenticate;
use Lipe\Limit_Logins\Authenticate\Reset_Password;
use Lipe\Limit_Logins\Authenticate\Rest;
use Lipe\Limit_Logins\Authenticate\Xmlrpc;
use Lipe\Limit_Logins\Settings\Limit_Logins;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
trait Singleton {

	/**
	 * Instance of this class for use as singleton
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Whether the class has been initialized.
	 *
	 * @var bool
	 */
	protected static $inited = false;


	/**
	 * Create the instance of the class.
	 *
	 * @return void
	 */
	public static function init(): void {
		static::$instance = static::in();
		static::$instance->hook();
		static::$inited = true;
	}


	/**
	 * Call this method as many times as needed, and the
	 * class will only init() one time.
	 *
	 * @static
	 *
	 * @return void
	 */
	public static function init_once(): void {
		if ( ! static::$inited ) {
			static::init();
		}
	}


	/**
	 * Return the instance of this class.
	 *
	 * @return static
	 */
	public static function in(): static {
		if ( ! is_a( static::$instance, __CLASS__ ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}
}

<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Settings;

use Lipe\Lib\CMB2\Options_Page;
use Lipe\Lib\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 */
final class Limit_Logins {
	use Singleton;

	public const NAME = 'lipe/limit-logins/settings/limit-logins';


	private function hook(): void {
		add_action( 'cmb2_init', function() {
			$this->register();
		} );
	}


	private function register(): void {
		$box = new Options_Page( self::NAME, 'Limit Logins' );
	}
}

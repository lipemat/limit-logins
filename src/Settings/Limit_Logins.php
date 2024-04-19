<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Settings;

use Lipe\Lib\CMB2\Options_Page;
use Lipe\Lib\Settings\Settings_Trait;
use Lipe\Limit_Logins\Log\Attempt;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-import-type DATA from Attempt
 */
final class Limit_Logins {
	use Singleton;
	use Settings_Trait;

	public const NAME = 'lipe/limit-logins/settings/limit-logins';

	public const CONTACT = 'lipe/limit-logins/settings/limit-logins/contact';
	public const LOG     = 'lipe/limit-logins/settings/limit-logins/log';


	private function hook(): void {
		add_action( 'cmb2_init', function() {
			$this->register();
		} );
	}


	private function register(): void {
		$box = new Options_Page( self::NAME, 'Limit Logins' );
		$box->parent_slug = 'options-general.php';

		$box->field( self::CONTACT, 'Contact Page' )
		    ->text_url()
		    ->description( 'Link will display in error message if set.' );

		$box->field( self::LOG, 'Log' )
		    ->textarea( 20 );
	}


	/**
	 * @phpstan-return list<DATA>
	 */
	public function get_logs(): array {
		$logs = $this->get_option( self::LOG, [] );
		if ( ! is_array( $logs ) ) {
			return [];
		}
		return $logs;
	}
}

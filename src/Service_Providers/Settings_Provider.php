<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

use Lipe\Limit_Logins\Attempts\Storage;
use Lipe\Limit_Logins\Settings;

/**
 * @author Mat Lipe
 * @since  September 2026
 *
 */
final class Settings_Provider implements Provider {
	public function register(): void {
		$this->Settings();
	}


	private function Settings(): void {
		add_action( 'cmb2_init', function() {
			Settings::in()->register();
		} );
		add_filter( 'cmb2_override_' . Settings::MIGRATE_FAILURES . '_meta_save', function(): bool {
			Storage::in()->migrate();
			return true;
		} );
		add_filter( 'cmb2_override_' . Settings::LOGGED_FAILURES . '_meta_value', fn() => Storage::in()->get_rows() );
		add_filter( 'cmb2_override_' . Settings::LOGGED_FAILURES . '_meta_save', function( $override, array $args ): bool {
			Storage::in()->save_rows( \is_array( $args['value'] ) ? $args['value'] : [] );
			return true;
		}, 10, 2 );
		add_filter( 'cmb2_override_' . Settings::LOGGED_FAILURES . '_meta_remove', function(): bool {
			Storage::in()->save( [] );
			return true;
		} );
	}
}

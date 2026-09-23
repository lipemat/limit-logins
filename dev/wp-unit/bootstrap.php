<?php

use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Utils;

$GLOBALS['wp_tests_options']['permalink_structure'] = '%postname%/';

require __DIR__ . '/helpers.php';
require __DIR__ . '/wp-tests-config.php';

// Prevent side effects from the current install's plugins.
require_once WP_UNIT_DIR . '/includes/functions.php';
tests_add_filter( 'option_active_plugins', '__return_empty_array', 99 );
tests_add_filter( 'site_option_active_sitewide_plugins', '__return_empty_array', 99 );

tests_add_filter( 'plugins_loaded', function() {
	// Allow extending final classes. Must run before the autoloader reaches them.
	tests_allow_extending_final( Unlock_Link::class );
	tests_allow_extending_final( Utils::class );

	// Add composer's autoloader.
	if ( \is_readable( \dirname( __DIR__, 4 ) . '/autoload.php' ) ) {
		require_once \dirname( __DIR__, 4 ) . '/autoload.php';
	}
}, 1 );

// Load the WP-Unit environment.
require BOOTSTRAP;

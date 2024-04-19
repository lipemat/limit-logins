<?php
declare(strict_types=1);
/**
 * Plugin Name: Limit Logins
 * Description: Limit rate of login attempts and block IP or username temporarily.
 * Author: Mat Lipe
 * Author URI: https://onpointplugins.com
 * Version: 0.0.1
 * License: MIT
 * Text Domain: lipe
 * Update URI: false
 */

use Lipe\Limit_Logins\Settings\Limit_Logins;

add_action( 'plugins_loaded', function () {
	Limit_Logins::init();
});

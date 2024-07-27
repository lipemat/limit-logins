<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins;

use Lipe\Lib\Api\Api;
use Lipe\Lib\CMB2\Options_Page;
use Lipe\Lib\Settings\Settings_Trait;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Attempts\Gateway;
use Lipe\Limit_Logins\Email\Preview;
use Lipe\Limit_Logins\Traits\Singleton;

/**
 * @author Mat Lipe
 * @since  April 2024
 *
 * @phpstan-import-type DATA from Attempt
 *
 * @phpstan-type KEYS array{
 *     "lipe/limit-logins/settings/limit-logins/clear": bool,
 *     "lipe/limit-logins/settings/limit-logins/contact": string,
 *     "lipe/limit-logins/settings/limit-logins/email": string,
 *     "lipe/limit-logins/settings/limit-logins/logged-failures": list<DATA>
 * }
 *
 * @implements \ArrayAccess<self::*, value-of<KEYS>>
 */
final class Settings implements \ArrayAccess {
	/**
	 * @use Settings_Trait<KEYS>
	 */
	use Settings_Trait;
	use Singleton;

	public const NAME = 'lipe/limit-logins/settings/limit-logins';

	public const CLEAR           = 'lipe/limit-logins/settings/limit-logins/clear';
	public const CONTACT         = 'lipe/limit-logins/settings/limit-logins/contact';
	public const EMAIL           = 'lipe/limit-logins/settings/limit-logins/email';
	public const LOGGED_FAILURES = 'lipe/limit-logins/settings/limit-logins/logged-failures';


	private function hook(): void {
		add_action( 'cmb2_init', function() {
			$this->register();
		} );

		Api::init_once();
	}


	private function register(): void {
		$box = new Options_Page( self::NAME, 'Limit Logins' );
		$box->parent_slug( 'options-general.php' );

		$box->field( self::CONTACT, 'Contact Page' )
		    ->text_url()
		    ->description( 'Link in include in blocked emails.' );
		$box->field( self::EMAIL, 'Sender Email' )
		    ->text_email()
		    ->description( $this->email_description() );

		$group = $box->group( self::LOGGED_FAILURES, 'Logged Failures' );
		// Hide the up and down buttons to keep rows short.
		$group->before_group = '<style>.cmb-group-table .cmb-group-table-control a.move-up{ display: none !important; }.cmb-group-table .cmb-group-table-control a.move-down{ display: none !important; }</style>';
		$group->layout( 'table' )->repeatable();

		$group->field( Attempt::IP, 'IP' )
		      ->text_small()
		      ->attributes( [
			      'maxlength' => 15,
			      'pattern'   => '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$',
		      ] );
		$group->field( Attempt::USERNAME, 'Username' )
		      ->text_small()
		      ->attributes( [
			      'maxlength' => 60,
		      ] );
		$group->field( Attempt::GATEWAY, 'Gateway' )
		      ->select( [ $this, 'get_gateway_options' ] );
		$group->field( Attempt::COUNT, 'Count' )
		      ->text_number( 1, 0, Attempts::ALLOWED_ATTEMPTS );
		$group->field( Attempt::EXPIRES, 'Expires' )
		      ->text_datetime_timestamp();
		$group->field( Attempt::KEY, 'Unlock Key' )
		      ->hidden();

		if ( $this->get_option( self::CLEAR, false ) ) {
			$box->field( self::CLEAR, 'Legacy Data' )
			    ->title()
			    ->description( 'Limit Login Attempts Reloaded data has been cleaned up.' );
		} else {
			$box->field( self::CLEAR, 'Limit Login Attempts Data' )
			    ->checkbox()
			    ->description( 'Check and save to clear dangling settings from the Limit Login Attempts Reloaded plugin.' )
			    ->change_cb( function() {
				    $this->clear_limit_login_attempts_options();
			    } );
		}
	}


	/**
	 * @return array<string, string>
	 */
	public function get_gateway_options(): array {
		$gateways = \array_map( fn( $gateway ) => $gateway->value, Gateway::cases() );
		return \array_combine( $gateways, $gateways );
	}


	private function email_description(): string {
		$description = 'Email to send blocked notifications from which must be able to recieve replies.';
		$url = Preview::in()->get_url();
		if ( '' !== $url ) {
			$description .= '<br /><a href="' . $url . '" target="_blank">Preview email</a>';
		} else {
			$description .= '<br /><em>A block with a valid username is required to preview the email.</em>';
		}
		return $description;
	}


	/**
	 * Clear out the options left over from the Limit Login Atttempt Reloaded plugin.
	 *
	 * May only be run once by checking the box then saving the fields.
	 * After that, the field will display as a message that the data has been cleaned up.
	 *
	 * @throws \ErrorException
	 */
	private function clear_limit_login_attempts_options(): void {
		global $wpdb;
		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'limit_login_%' ) ) ) {
			throw new \ErrorException( 'Failed to clear limit login attempt options.' );
		}
	}
}

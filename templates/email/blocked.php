<?php
declare( strict_types=1 );

use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Email\Blocked;
use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\es;

defined( 'ABSPATH' ) || exit;

$current = Blocked::get_current();

?>
	<p>
		Your <?= esc_html( get_bloginfo( 'name' ) ) ?> account has been locked due to too many failed login attempts. You will not be able
		to log in into your account for
		<?= esc_html( human_time_diff( $current->attempt->expires, gmdate( 'U' ) ) ) ?>.
	</p>

	<h2>If this was not you</h2>
	<p>
		An attacker may be trying to gain access to your account. It is recommended to let our system continue to block the attacker and
		prevent
		unauthorized access. Our system is quite secure and no action is required on your part.
	</p>

	If you need to access the site before the block has expired, you may use
	<a href="<?= es( Unlock_Link::in()->get_unlock_url( $current->key ) ) ?>">this link</a> to unlock your account.

	<h2>If this was you</h2>
	<p>
		You may use the below options to remove the block and restore account access.
	</p>

<?php
$reset = Unlock_Link::in()->get_reset_password_url( $current->attempt->username );
if ( '' !== $reset ) {
	?>
	<h3>Reset your password</h3>
	<p>
		If you have forgotten your password, you may reset it using
		<a href="<?= es( $reset ) ?>">this link</a>
		.
	</p>
	<?php
}
?>

	<h3>Unlock your account</h3>
	<p>
		Following
		<a href="<?= es( Unlock_Link::in()->get_unlock_url( $current->key ) ) ?>">this link</a>
		will unlock your account and
		allow you to log in again without making any changes to your account.
	</p>

<?php
$email = Settings::in()->get_option( Settings::EMAIL, '' );
$contact = Settings::in()->get_option( Settings::CONTACT, '' );
if ( '' !== $email || '' !== $contact ) {
	?>
	<h2>Need help?</h2>
	<?php
	echo match ( true ) {
		'' !== $email && '' !== $contact => '<p>You may reply to this email or use our <a href="' . esc_url( $contact ) . '">contact form</a> to receive help.</p>',
		'' !== $email                    => '<p>You may reply to this email to receive help.</p>',
		'' !== $contact                  => '<p>You may use our <a href="' . esc_url( $contact ) . '">contact form</a> to recieve help.</p>',
		default                          => '',
	};
}

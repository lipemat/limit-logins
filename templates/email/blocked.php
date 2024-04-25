<?php
declare( strict_types=1 );

use Lipe\Limit_Logins\Authenticate\Unlock_Link;
use Lipe\Limit_Logins\Email\Blocked;
use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\es;

defined( 'ABSPATH' ) || exit;

$current = Blocked::get_current();

?>
<table border="0" cellpadding="10" cellspacing="0" width="100%">
	<tbody>
		<tr>
			<td>
				<h1
					style='font-weight: 300; line-height: 150%; margin: 0 0 14px; text-shadow: 2px 2px #282828; display: block; font-size: 32px; text-align: center; color: #fff; background-color: inherit;'
					bgcolor='inherit'
				>
					Your <?= esc_html( get_bloginfo( 'name', 'display' ) ); ?> account is locked.
				</h1>
			</td>
		</tr>
		<tr></tr>
		<td
			valign='top'
			id='body_content'
			style='background-color: #eaeaea; border: 1px solid #008a8a; box-shadow: 0 1px 4px rgba(0,0,0,.1); border-radius: 3px; padding: 25px; font-size: 15px; line-height: 130%;'
			bgcolor='#eaeaea'
		>
			<p style="margin: 0 0 16px;">
				Hi <?= esc_html( get_user_by( 'login', $current->attempt->username )->display_name ) ?>,
			</p>
			<p style="margin: 0 0 16px;">
				Your <?= esc_html( get_bloginfo( 'name' ) ) ?> account has been locked due to too many failed login attempts. You will not
				be able
				to log in into your account for
				<?= esc_html( human_time_diff( $current->attempt->expires, gmdate( 'U' ) ) ) ?>.
			</p>

			<h2>If this was not you</h2>
			<p style="margin: 0 0 16px;">
				An attacker may be trying to gain access to your account. It is recommended to let our system continue to block the attacker
				and
				prevent
				unauthorized access. Our system is quite secure and no action is required on your part.
			</p>

			<p style='margin: 0 0 16px;'>
				If you need to access the site before the block has expired, you may use the Unlock Account button to unlock your account.
			</p>

			<p style='margin: 0 0 16px;'>
				<a
					class='button'
					href="<?= es( Unlock_Link::in()->get_unlock_url( $current->key ) ) ?>"
					target='_blank'
					style='text-decoration: none; background-size: 0 100%,100%; color: #fff; padding: 5px 10px 6px; position: relative; border-radius: 5px; cursor: pointer; display: inline-block; font-weight: 500; line-height: 1.5; text-align: center; transition: all .15s ease-in-out; margin-bottom: 10px; outline: none; background: #043c51; border: 1px solid #282828;'
					bgcolor='#043c51'
				>
					Unlock Account
				</a>
			</p>

			<h2>If this was you</h2>
			<p style="margin: 0 0 16px;">
				You may use the below options to remove the block and restore account access.
			</p>

			<?php
			$reset = Unlock_Link::in()->get_reset_password_url( $current->attempt->username );
			if ( '' !== $reset ) {
				?>
				<h3>Reset your password</h3>
				<p style="margin: 0 0 16px;">
					If you have forgotten your password, you may reset it using
					this button.
				</p>
				<p style='margin: 0 0 16px;'>
					<a
						class='button'
						href="<?= es( $reset ) ?>"
						target='_blank'
						style='text-decoration: none; background-size: 0 100%,100%; color: #fff; padding: 5px 10px 6px; position: relative; border-radius: 5px; cursor: pointer; display: inline-block; font-weight: 500; line-height: 1.5; text-align: center; transition: all .15s ease-in-out; margin-bottom: 10px; outline: none; background: #043c51; border: 1px solid #282828;'
						bgcolor='#043c51'
					>
						Reset Password
					</a>
				</p>
				<?php
			}
			?>

			<h3>Unlock your account</h3>
			<p style="margin: 0 0 16px;">
				The Unlock button will unlock your account and
				allow you to log in again without making any changes to your account.

			</p>
			<p style='margin: 0 0 16px;'>
				<a
					class='button'
					href="<?= es( Unlock_Link::in()->get_unlock_url( $current->key ) ) ?>"
					target='_blank'
					style='text-decoration: none; background-size: 0 100%,100%; color: #fff; padding: 5px 10px 6px; position: relative; border-radius: 5px; cursor: pointer; display: inline-block; font-weight: 500; line-height: 1.5; text-align: center; transition: all .15s ease-in-out; margin-bottom: 10px; outline: none; background: #043c51; border: 1px solid #282828;'
					bgcolor='#043c51'
				>
					Unlock Account
				</a>
			</p>

			<?php
			$email = Settings::in()->get_option( Settings::EMAIL, '' );
			$contact = Settings::in()->get_option( Settings::CONTACT, '' );
			if ( '' !== $email || '' !== $contact ) {
				?>
				<h2>Need help?</h2>
				<?php
				echo match ( true ) {
					'' !== $email && '' !== $contact => '<p style="margin: 0 0 16px;">You may reply to this email or use our <a href="' . esc_url( $contact ) . '">contact form</a> to receive help.</p>',
					'' !== $email                    => '<p style="margin: 0 0 16px;">You may reply to this email to receive help.</p>',
					'' !== $contact                  => '<p style="margin: 0 0 16px;">You may use our <a href="' . esc_url( $contact ) . '">contact form</a> to recieve help.</p>',
					default                          => '',
				};
			}
			?>
		</td>
		</tr>
	</tbody>
</table>

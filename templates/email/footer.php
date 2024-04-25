<?php
defined( 'ABSPATH' ) || exit;

?>
</td>
<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
</tr>
<tr>
	<td
		align="center"
		valign="middle"
		colspan="3"
		style="border-radius: 6px; border: 0; color: #969696; font-size: 12px; line-height: 150%; text-align: center; padding: 24px 0;"
	>
		<p style="margin: 0 0 16px;">
			<a
				style="color: #fff; text-shadow: 2px 2px #282828; font-size: 17px; text-decoration: none; font-weight: 300; outline: none;"
				href="<?php echo esc_url( home_url( '/' ) ); ?>"
			><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
		</p>
	</td>
</tr>
</table>
</body>
</html>

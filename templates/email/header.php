<?php
defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<title><?= esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
<table
	width='100%'
	id='outer_wrapper'
	style='background-color: transparent; background: linear-gradient(290deg,#099,#043a61 90%); font-family: Helvetica,Roboto,Arial,sans-serif;'
	bgcolor='linear-gradient(290deg,#099,#043a61'
>
	<tbody>
		<tr>
			<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
			<td width='600' style="padding: 25px 10px;">

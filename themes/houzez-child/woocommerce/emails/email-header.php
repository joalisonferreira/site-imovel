<?php
/**
 * Override do cabeçalho de e-mail WooCommerce — visual premium Imovel Parceiro.
 *
 * Adiciona a faixa de marca (logo + cor do tema Houzez) acima do cartão.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$store_name = isset( $store_name ) ? $store_name : get_bloginfo( 'name', 'display' );

$ipc_logo = '';
$ipc_primary = '#06192c';

if ( function_exists( 'fave_option' ) ) {
	$ipc_head = fave_option( 'email_head_logo' );
	if ( ! empty( $ipc_head['url'] ) ) {
		$ipc_logo = $ipc_head['url'];
	}

	$ipc_head_bg = fave_option( 'email_head_bg_color' );
	if ( $ipc_head_bg ) {
		$ipc_primary = $ipc_head_bg;
	}
}

if ( '' === $ipc_logo ) {
	$ipc_logo = get_option( 'woocommerce_email_header_image' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<title><?php echo esc_html( $store_name ); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
	<table width="100%" id="outer_wrapper" role="presentation">
		<tr>
			<td></td>
			<td width="600">
				<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
					<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="inner_wrapper" role="presentation">
						<tr>
							<td align="center" valign="top">
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header_image" role="presentation">
									<tr>
										<td align="center" valign="middle">
											<?php if ( $ipc_logo ) : ?>
												<img src="<?php echo esc_url( $ipc_logo ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" />
											<?php else : ?>
												<p class="email-logo-text"><?php echo esc_html( $store_name ); ?></p>
											<?php endif; ?>
										</td>
									</tr>
								</table>
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation">
									<tr>
										<td align="center" valign="top">
											<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
												<tr>
													<td id="header_wrapper">
														<h1><?php echo esc_html( $email_heading ); ?></h1>
													</td>
												</tr>
											</table>
										</td>
									</tr>
									<tr>
										<td align="center" valign="top">
											<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
												<tr>
													<td valign="top" id="body_content">
														<table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
															<tr>
																<td valign="top" id="body_content_inner_cell">
																	<div id="body_content_inner">

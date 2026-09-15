<?php
/**
 * Override de estilos de e-mail WooCommerce — visual premium Imovel Parceiro.
 *
 * Paleta: navy #06192c, acento #00aeef, fundo #f2f4f7, texto #3f4752.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bg          = '#f2f4f7';
$body        = '#ffffff';
$base        = '#06192c';
$text        = '#3f4752';
$footer_text = '#8a94a6';
$link_color  = '#00aeef';
$border      = '#e9edf2';

$text_lighter_20 = '#5b6470';
$base_lighter_40 = '#4a5c74';

$font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

if ( function_exists( 'fave_option' ) ) {
	$head_bg = fave_option( 'email_head_bg_color' );
	if ( $head_bg ) {
		$base = $head_bg;
	}
}
?>
body {
	background-color: <?php echo esc_attr( $bg ); ?>;
	padding: 0;
	text-align: center;
}

#outer_wrapper {
	background-color: <?php echo esc_attr( $bg ); ?>;
}

#wrapper {
	margin: 0 auto;
	padding: 28px 0;
	-webkit-text-size-adjust: none !important;
	width: 100%;
	max-width: 600px;
}

#template_container {
	box-shadow: 0 4px 18px rgba(6, 25, 44, 0.08);
	background-color: <?php echo esc_attr( $body ); ?>;
	border: 1px solid <?php echo esc_attr( $border ); ?>;
	border-radius: 0 0 12px 12px;
}

#template_header {
	background-color: <?php echo esc_attr( $body ); ?>;
	border-radius: 0;
	color: <?php echo esc_attr( $base ); ?>;
	border-bottom: 0;
	font-weight: bold;
	line-height: 100%;
	vertical-align: middle;
}

#template_header h1 {
	color: <?php echo esc_attr( $base ); ?>;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-size: 26px;
	font-weight: 700;
	letter-spacing: -0.5px;
	margin: 0;
	padding: 0;
	line-height: 1.3;
}

#template_header_image {
	background-color: <?php echo esc_attr( $base ); ?>;
	border-radius: 12px 12px 0 0;
	padding: 30px 20px;
}

#template_header_image img {
	max-width: 200px;
	height: auto;
	border: 0;
}

.email-logo-text {
	color: #ffffff;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-size: 22px;
	font-weight: 700;
	letter-spacing: 0.02em;
}

#header_wrapper {
	padding: 32px 44px 0;
}

#template_body {
	background-color: <?php echo esc_attr( $body ); ?>;
}

#template_body table {
	border-collapse: collapse;
}

#body_content {
	background-color: <?php echo esc_attr( $body ); ?>;
}

#body_content table td {
	padding: 12px 44px;
}

#body_content table td td {
	padding: 12px 0;
}

#body_content table td th {
	padding: 12px;
}

#body_content td ul.wc-item-meta {
	font-size: small;
	margin: 1em 0 0;
	padding: 0;
	list-style: none;
}

#body_content td ul.wc-item-meta li {
	margin: 0.5em 0 0;
	padding: 0;
}

#body_content td ul.wc-item-meta li p {
	margin: 0;
}

#body_content_inner {
	color: <?php echo esc_attr( $text ); ?>;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-size: 15px;
	line-height: 1.7;
	text-align: left;
}

#template_footer #credit {
	border: 0;
	color: <?php echo esc_attr( $footer_text ); ?>;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-size: 12px;
	line-height: 1.6;
	text-align: center;
	padding: 24px 32px 20px;
}

#template_footer #credit a {
	color: <?php echo esc_attr( $link_color ); ?>;
	text-decoration: none;
}

.td {
	color: <?php echo esc_attr( $text ); ?>;
	border: 1px solid <?php echo esc_attr( $border ); ?>;
	vertical-align: middle;
}

.address {
	padding: 12px;
	color: <?php echo esc_attr( $text ); ?>;
	border: 1px solid <?php echo esc_attr( $border ); ?>;
}

.additional-fields {
	padding: 12px 12px 0;
	color: <?php echo esc_attr( $text ); ?>;
	border: 1px solid <?php echo esc_attr( $border ); ?>;
	list-style: none outside;
}

.additional-fields li {
	margin: 0 0 12px 0;
}

.text {
	color: <?php echo esc_attr( $text ); ?>;
	font-family: <?php echo esc_attr( $font ); ?>;
}

.address-title {
	color: <?php echo esc_attr( $base ); ?>;
	font-weight: 600;
}

.order-item-data {
	font-size: 13px;
}

.link {
	color: <?php echo esc_attr( $link_color ); ?>;
}

h1, h2, h3 {
	color: <?php echo esc_attr( $base ); ?>;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-weight: 700;
}

a {
	color: <?php echo esc_attr( $link_color ); ?>;
	text-decoration: none;
}

img {
	border: none;
	display: inline-block;
	font-size: 14px;
	font-weight: bold;
	height: auto;
	outline: none;
	text-decoration: none;
	text-transform: capitalize;
	vertical-align: middle;
}

.woocommerce-email .btn {
	display: inline-block;
	padding: 14px 30px;
	background-color: <?php echo esc_attr( $base ); ?>;
	color: #ffffff;
	border-radius: 8px;
	font-family: <?php echo esc_attr( $font ); ?>;
	font-size: 15px;
	font-weight: 600;
	text-decoration: none;
}

@media screen and (max-width: 600px) {
	#template_container {
		border-radius: 0;
	}

	#header_wrapper {
		padding: 24px 24px 0;
	}

	#body_content table td {
		padding: 12px 24px;
	}
}

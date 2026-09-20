<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template HTML central de e-mails do Imovel Parceiro.
 *
 * Redesenho premium (estilo Zillow / QuintoAndar) aplicado a todos os e-mails
 * transacionais: plugin, tema Houzez e WooCommerce. As cores e o logo são lidos
 * das opções já configuradas no tema (houzez_options), com fallbacks premium.
 */
class Imovel_Parceiro_Email_Template {

	const PRIMARY_FALLBACK = '#06192c';
	const ACCENT           = '#d72218';
	const BG               = '#f1f5f9';
	const CARD             = '#ffffff';
	const TEXT             = '#475569';
	const TITLE            = '#0f172a';
	const MUTED            = '#94a3b8';
	const BORDER           = '#e9edf2';

	/**
	 * Cor de acento (CTA / links): usa a cor primária do site.
	 *
	 * @return string
	 */
	public static function accent() {
		if ( function_exists( 'houzez_option' ) ) {
			$brand = houzez_option( 'houzez_primary_color', '' );
			if ( ! empty( $brand ) ) {
				return $brand;
			}
		}

		return self::ACCENT;
	}

	/**
	 * Tokens de marca lidos do tema.
	 *
	 * @return array
	 */
	public static function settings() {
		$logo = '';
		if ( function_exists( 'fave_option' ) ) {
			$head_logo = fave_option( 'email_head_logo' );
			if ( ! empty( $head_logo['url'] ) ) {
				$logo = $head_logo['url'];
			}
		}

		$primary = self::PRIMARY_FALLBACK;
		if ( function_exists( 'fave_option' ) ) {
			$head_bg = fave_option( 'email_head_bg_color' );
			if ( $head_bg ) {
				$primary = $head_bg;
			}
		}

		$footer = '';
		if ( function_exists( 'fave_option' ) ) {
			$footer = (string) fave_option( 'email_footer_content' );
		}

		// Mantém o rodapé na paleta da marca (o conteúdo salvo costuma trazer
		// o antigo azul #00aeef embutido no link).
		if ( '' !== $footer ) {
			$footer = str_ireplace( '#00aeef', self::accent(), $footer );
		}

		return array(
			'logo'    => $logo,
			'primary' => $primary,
			'accent'  => self::accent(),
			'brand'   => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
			'footer'  => $footer,
		);
	}

	/**
	 * Converte texto puro em HTML de corpo (parágrafos + links clicáveis).
	 *
	 * @param string $text Texto puro.
	 * @return string
	 */
	public static function text_to_html( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		$text = esc_html( $text );

		// Quebras de linha -> <br/> (preserva listas manuais feitas com nova linha).
		$text = str_replace( "\r\n", "\n", $text );
		$text = str_replace( "\r", "\n", $text );
		$text = nl2br( $text );

		// Transforma URLs "nuas" em links clicáveis.
		$text = preg_replace( '~(\bhttps?://[^\s<]+)~i', '<a href="$1" style="color:' . esc_attr( self::accent() ) . ';text-decoration:none;font-weight:600;">$1</a>', $text );

		return $text;
	}

	/**
	 * Botão (tabela) compatível com Outlook.
	 *
	 * @param string $url   URL de destino.
	 * @param string $label Texto do botão.
	 * @return string
	 */
	public static function button( $url, $label ) {
		$settings = self::settings();
		$bg       = $settings['accent'];

		return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:26px 0 6px;"><tr><td style="border-radius:10px;background-color:' . esc_attr( $bg ) . ';">'
			. '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:15px 34px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;letter-spacing:.01em;color:#ffffff;text-decoration:none;border-radius:10px;">' . esc_html( $label ) . '</a>'
			. '</td></tr></table>';
	}

	/**
	 * Monta o HTML completo do e-mail.
	 *
	 * @param string $content Conteúdo do corpo (HTML).
	 * @param array  $args    Opções: title, preheader, cta_url, cta_text.
	 * @return string
	 */
	public static function render( $content, $args = array() ) {
		$settings = self::settings();

		$title     = isset( $args['title'] ) ? $args['title'] : '';
		$preheader = isset( $args['preheader'] ) ? $args['preheader'] : '';
		$cta_url   = isset( $args['cta_url'] ) ? $args['cta_url'] : '';
		$cta_text  = isset( $args['cta_text'] ) ? $args['cta_text'] : '';

		$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

		$title_html = '';
		if ( '' !== $title ) {
			$title_html = '<h1 style="margin:0 0 14px;font-family:' . $font . ';font-size:23px;line-height:1.3;font-weight:800;color:' . esc_attr( self::TITLE ) . ';">' . esc_html( $title ) . '</h1>';
		}

		$cta_html = '';
		if ( '' !== $cta_url && '' !== $cta_text ) {
			$cta_html = self::button( $cta_url, $cta_text );
		}

		$logo_html = '';
		if ( ! empty( $settings['logo'] ) ) {
			$logo_html = '<img src="' . esc_url( $settings['logo'] ) . '" alt="' . esc_attr( $settings['brand'] ) . '" style="display:block;max-width:200px;width:100%;height:auto;border:0;outline:none;text-decoration:none;" />';
		}

		$footer_html = $settings['footer'];
		if ( '' === $footer_html ) {
			$footer_html = '<p style="margin:0;">&copy; ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( $settings['brand'] ) . '.</p>';
		}

		$html  = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$html .= '<meta name="x-apple-disable-message-reformatting"><meta name="color-scheme" content="light only">';
		$html .= '<title>' . esc_html( $title ) . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background-color:' . esc_attr( self::BG ) . ';word-spacing:normal;">';

		// Preheader oculto.
		if ( '' !== $preheader ) {
			$html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">' . esc_html( $preheader ) . '</div>';
		}

		$html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="' . esc_attr( self::BG ) . '"><tr><td align="center" style="padding:36px 12px;">';

		// Cartão principal.
		$html .= '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" bgcolor="' . esc_attr( self::CARD ) . '" style="max-width:600px;width:100%;border-radius:16px;overflow:hidden;box-shadow:0 14px 38px -22px rgba(6,25,44,0.4);">';

		// Faixa de marca com o logo.
		if ( '' !== $logo_html ) {
			$html .= '<tr><td align="center" bgcolor="' . esc_attr( $settings['primary'] ) . '" style="padding:30px 24px;">' . $logo_html . '</td></tr>';
		}

		// Barra de acento (cor da marca).
		$html .= '<tr><td height="4" bgcolor="' . esc_attr( $settings['accent'] ) . '" style="font-size:0;line-height:0;height:4px;">&nbsp;</td></tr>';

		// Conteúdo.
		$html .= '<tr><td style="padding:40px 40px 8px;">' . $title_html . '<div style="font-family:' . $font . ';font-size:15px;line-height:1.75;color:' . esc_attr( self::TEXT ) . ';">' . $content . '</div>' . $cta_html . '</td></tr>';
		$html .= '<tr><td style="padding:0 40px 40px;font-size:0;line-height:0;">&nbsp;</td></tr>';

		$html .= '</table>';

		// Rodapé.
		$html .= '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;"><tr><td align="center" style="padding:22px 32px 6px;font-family:' . $font . ';font-size:12px;line-height:1.6;color:' . esc_attr( self::MUTED ) . ';">' . $footer_html . '</td></tr></table>';

		$html .= '</td></tr></table>';
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Renderiza e envia o e-mail.
	 *
	 * @param string|array $to      Destinatário(s).
	 * @param string       $subject Assunto.
	 * @param string       $content Conteúdo (HTML).
	 * @param array        $args    Opções de render + headers/from.
	 * @return bool
	 */
	public static function send( $to, $subject, $content, $args = array() ) {
		$from_name = isset( $args['from_name'] ) ? $args['from_name'] : '';
		$from_addr = isset( $args['from_addr'] ) ? $args['from_addr'] : '';
		$reply_to  = isset( $args['reply_to'] ) ? $args['reply_to'] : '';
		$cc        = isset( $args['cc'] ) ? $args['cc'] : '';
		$bcc       = isset( $args['bcc'] ) ? $args['bcc'] : '';

		unset( $args['from_name'], $args['from_addr'], $args['reply_to'], $args['cc'], $args['bcc'] );

		$html = self::render( $content, $args );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( '' !== $from_name && '' !== $from_addr ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_addr . '>';
		} else {
			$domain = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : wp_parse_url( home_url(), PHP_URL_HOST );
			$headers[] = 'From: No Reply <noreply@' . $domain . '>';
		}

		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( '' !== $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}
		if ( '' !== $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		return wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Compatibilidade com houzez_send_emails($email, $subject, $message):
	 * converte texto puro em HTML e aplica o template premium.
	 *
	 * @param string $user_email Destinatário.
	 * @param string $subject    Assunto.
	 * @param string $message    Conteúdo (texto puro ou HTML).
	 * @return bool
	 */
	public static function send_houzez( $user_email, $subject, $message ) {
		$message = stripslashes( (string) $message );

		if ( false === strpos( $message, '<' ) ) {
			$message = self::text_to_html( $message );
		} else {
			$message = wp_kses_post( $message );
		}

		return self::send( $user_email, $subject, $message, array( 'title' => $subject ) );
	}
}

if ( ! function_exists( 'houzez_send_emails' ) ) {
	function houzez_send_emails( $user_email, $subject, $message ) {
		if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			return Imovel_Parceiro_Email_Template::send_houzez( $user_email, $subject, $message );
		}
		return false;
	}
}

if ( ! function_exists( 'houzez_send_emails_match_submission' ) ) {
	function houzez_send_emails_match_submission( $user_email, $subject, $message ) {
		if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			return Imovel_Parceiro_Email_Template::send_houzez( $user_email, $subject, $message );
		}
		return false;
	}
}

if ( ! function_exists( 'houzez_send_messages_emails' ) ) {
	function houzez_send_messages_emails( $user_email, $subject, $message ) {
		if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			return Imovel_Parceiro_Email_Template::send_houzez( $user_email, $subject, $message );
		}
		return false;
	}
}

/**
 * Reaplica o template premium aos e-mails HTML gerados pelo plugin
 * "houzez-login-register" (cadastro, aprovação, etc.), que monta o próprio
 * layout e não passa por esta classe.
 *
 * Detecta a assinatura do template legado (faixa cinza #F6F6F6 + card 620px),
 * extrai o corpo interno e o re-renderiza com o layout premium.
 *
 * @param array $args Argumentos do wp_mail.
 * @return array
 */
function imovel_parceiro_wrap_legacy_emails( $args ) {
	if ( empty( $args['message'] ) || ! class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
		return $args;
	}

	$message = (string) $args['message'];

	// Somente o template legado do Houzez.
	if ( false === strpos( $message, '#F6F6F6' ) || false === strpos( $message, 'width: 620px' ) ) {
		return $args;
	}

	$open  = '<div style="font-family:\'Helvetica Neue\',\'Helvetica\',Helvetica,Arial,sans-serif;font-size:100%;line-height:1.6em;display:block;max-width:600px;margin:0 auto;padding:0">';
	$start = strpos( $message, $open );
	if ( false === $start ) {
		return $args;
	}

	$start += strlen( $open );
	$end    = strpos( $message, '</div>', $start );
	if ( false === $end ) {
		return $args;
	}

	$inner = trim( substr( $message, $start, $end - $start ) );
	if ( '' === $inner ) {
		return $args;
	}

	$subject         = isset( $args['subject'] ) ? $args['subject'] : '';
	$args['message'] = Imovel_Parceiro_Email_Template::render( $inner, array( 'title' => $subject ) );

	return $args;
}
add_filter( 'wp_mail', 'imovel_parceiro_wrap_legacy_emails', 20 );

/**
 * Padroniza os e-mails de moderação de contas do plugin houzez-login-register
 * (aprovação, recusa, suspensão): hoje saem em inglês e texto puro via wp_mail
 * direto. Reescreve para PT-BR com o template premium, sem editar o plugin
 * de terceiros.
 *
 * @param array $args Argumentos do wp_mail.
 * @return array
 */
function imovel_parceiro_standardize_account_emails( $args ) {
	if ( empty( $args['subject'] ) || empty( $args['to'] ) || ! class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
		return $args;
	}

	$to = $args['to'];
	$to_mail = is_array( $to ) ? (string) reset( $to ) : (string) $to;
	if ( ! is_email( $to_mail ) ) {
		return $args;
	}

	$subject = (string) $args['subject'];
	$kind = '';

	if ( 'Your account has been approved' === $subject ) {
		$kind = 'approved';
	} elseif ( 'Your account registration has been declined' === $subject ) {
		$kind = 'declined';
	} elseif ( 'Your account has been suspended' === $subject ) {
		$kind = 'suspended';
	} elseif ( preg_match( '/^Your account on .+ has been approved$/', $subject ) ) {
		$kind = 'approved';
	} elseif ( preg_match( '/^\[.*\] User Auto-Approved: (.+)$/', $subject, $matches ) ) {
		$kind = 'admin_auto';
		$auto_login = trim( $matches[1] );
	}

	if ( '' === $kind ) {
		return $args;
	}

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$user = get_user_by( 'email', $to_mail );
	$name = $user ? ( $user->first_name ? $user->first_name : $user->user_login ) : '';

	if ( 'admin_auto' === $kind ) {
		$target = isset( $auto_login ) && '' !== $auto_login ? get_user_by( 'login', $auto_login ) : false;
		$roles = array();
		if ( $target && ! empty( $target->roles ) ) {
			foreach ( (array) $target->roles as $role ) {
				$roles[] = ucfirst( trim( str_replace( 'houzez_', '', $role ) ) );
			}
		}
		$new_subject = sprintf( __( '[%s] Usuário aprovado automaticamente', 'imovel-parceiro-core' ), $site_name );
		$lines = array(
			sprintf( __( 'Um novo usuário foi aprovado automaticamente em %s.', 'imovel-parceiro-core' ), $site_name ),
			'',
			sprintf( __( 'Nome de usuário: %s', 'imovel-parceiro-core' ), $target ? $target->user_login : $auto_login ),
			sprintf( __( 'E-mail: %s', 'imovel-parceiro-core' ), $target ? $target->user_email : '' ),
			sprintf( __( 'Papéis: %s', 'imovel-parceiro-core' ), $roles ? implode( ', ', $roles ) : __( 'Nenhum', 'imovel-parceiro-core' ) ),
			'',
			__( 'O usuário foi aprovado porque seu papel está na lista de aprovação automática.', 'imovel-parceiro-core' ),
		);
		$render_args = array( 'title' => __( 'Usuário aprovado automaticamente', 'imovel-parceiro-core' ) );
	} else {
		$greeting = '' !== $name ? sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $name ) : __( 'Olá!', 'imovel-parceiro-core' );

		if ( 'approved' === $kind ) {
			$new_subject = sprintf( __( '[%s] Sua conta foi aprovada', 'imovel-parceiro-core' ), $site_name );
			$lines = array(
				$greeting,
				'',
				sprintf( __( 'Boas notícias! Sua conta em %s foi aprovada.', 'imovel-parceiro-core' ), $site_name ),
				'',
				__( 'Você já pode acessar a plataforma.', 'imovel-parceiro-core' ),
			);
			$render_args = array(
				'title'    => __( 'Conta aprovada', 'imovel-parceiro-core' ),
				'cta_url'  => home_url( '/' ),
				'cta_text' => __( 'Acessar plataforma', 'imovel-parceiro-core' ),
			);
		} elseif ( 'declined' === $kind ) {
			$new_subject = sprintf( __( '[%s] Seu cadastro foi recusado', 'imovel-parceiro-core' ), $site_name );
			$lines = array(
				$greeting,
				'',
				sprintf( __( 'Seu cadastro em %s não foi aprovado neste momento.', 'imovel-parceiro-core' ), $site_name ),
				'',
				__( 'Se você acredita que isso é um erro, entre em contato conosco.', 'imovel-parceiro-core' ),
				'',
				__( 'Atenciosamente,', 'imovel-parceiro-core' ),
				__( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
			);
			$render_args = array( 'title' => __( 'Cadastro recusado', 'imovel-parceiro-core' ) );
		} else {
			$new_subject = sprintf( __( '[%s] Sua conta foi suspensa', 'imovel-parceiro-core' ), $site_name );
			$lines = array(
				$greeting,
				'',
				sprintf( __( 'Sua conta em %s foi suspensa.', 'imovel-parceiro-core' ), $site_name ),
				'',
				__( 'Se você acredita que isso é um erro, entre em contato conosco.', 'imovel-parceiro-core' ),
				'',
				__( 'Atenciosamente,', 'imovel-parceiro-core' ),
				__( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
			);
			$render_args = array( 'title' => __( 'Conta suspensa', 'imovel-parceiro-core' ) );
		}
	}

	$args['subject'] = $new_subject;
	$args['message'] = Imovel_Parceiro_Email_Template::render(
		Imovel_Parceiro_Email_Template::text_to_html( implode( "\n", $lines ) ),
		$render_args
	);
	$args['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );

	return $args;
}
add_filter( 'wp_mail', 'imovel_parceiro_standardize_account_emails', 5 );

/**
 * Remove a senha em texto puro do e-mail de boas-vindas e a substitui por um
 * link de definição (uso único, expira em ~24h — mesmo mecanismo do "esqueci
 * a senha"). Vale para todos os caminhos de cadastro (formulário, social e
 * corretores criados pela imobiliária). Roda antes da re-aplicação do
 * template premium (prioridade 20) e insere o bloco dentro do cartão.
 *
 * @param array $args Argumentos do wp_mail.
 * @return array
 */
function imovel_parceiro_welcome_setup_link( $args ) {
	if ( empty( $args['subject'] ) || empty( $args['to'] ) ) {
		return $args;
	}

	// Assunto do template houzez_new_user_register (após troca de tokens).
	if ( 0 !== strpos( (string) $args['subject'], 'Bem-vindo(a) ao ' ) ) {
		return $args;
	}

	$to = $args['to'];
	$to_mail = is_array( $to ) ? (string) reset( $to ) : (string) $to;
	$user = is_email( $to_mail ) ? get_user_by( 'email', $to_mail ) : false;
	if ( ! $user ) {
		return $args;
	}

	$message = (string) $args['message'];

	// 1) Remove a linha "Senha: xxx" — senha nunca trafega por e-mail.
	$stripped = preg_replace( '/<strong[^>]*>\s*Senha:\s*<\/strong>.*?<br[^>]*>/su', '', $message );
	if ( null !== $stripped ) {
		$message = $stripped;
	}

	// 2) Gera a chave de definição (não envia nada por si só).
	$key = get_password_reset_key( $user );
	if ( ! is_wp_error( $key ) ) {
		$link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
		$block = '<p>'
			. '<strong>' . esc_html__( 'Acesso à sua conta:', 'imovel-parceiro-core' ) . '</strong> '
			. esc_html__( 'Por segurança, não enviamos senhas por e-mail. Defina sua senha clicando no link abaixo (uso único, expira em 24 horas):', 'imovel-parceiro-core' )
			. '<br><a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></p>';

		// Insere dentro do cartão de conteúdo do template legado.
		$marker = '<div style="font-family:\'Helvetica Neue\',\'Helvetica\',Helvetica,Arial,sans-serif;font-size:100%;line-height:1.6em;display:block;max-width:600px;margin:0 auto;padding:0">';
		$pos = strpos( $message, $marker );
		if ( false !== $pos ) {
			$close = strpos( $message, '</div>', $pos + strlen( $marker ) );
			if ( false !== $close ) {
				$message = substr( $message, 0, $close ) . $block . substr( $message, $close );
			} else {
				$message .= $block;
			}
		} else {
			$message .= $block;
		}
	}

	$args['message'] = $message;

	return $args;
}
add_filter( 'wp_mail', 'imovel_parceiro_welcome_setup_link', 6 );

if ( ! function_exists( 'houzez_send_emails_with_reply' ) ) {
	function houzez_send_emails_with_reply( $user_email, $subject, $message, $sender_name = '', $sender_email = '', $cc_email = '', $bcc_email = '' ) {
		if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			$message = stripslashes( (string) $message );
			if ( false === strpos( $message, '<' ) ) {
				$message = Imovel_Parceiro_Email_Template::text_to_html( $message );
			} else {
				$message = wp_kses_post( $message );
			}

			$args = array( 'title' => $subject );

			if ( '' !== $sender_name && '' !== $sender_email ) {
				$args['from_name'] = $sender_name;
				$args['from_addr'] = $sender_email;
			}
			if ( '' !== $sender_email ) {
				$args['reply_to'] = $sender_email;
			}
			if ( '' !== $cc_email ) {
				$args['cc'] = $cc_email;
			}
			if ( '' !== $bcc_email ) {
				$args['bcc'] = $bcc_email;
			}

			return Imovel_Parceiro_Email_Template::send( $user_email, $subject, $message, $args );
		}
		return false;
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verificação de e-mail no cadastro.
 *
 * O login do tema já bloqueia o acesso quando o meta `houzez_email_verified`
 * existe e é falso (login_register.php), e a página de login do tema já
 * valida o link `?verrify-email={id}&token={token}` (template-login.php).
 * Faltava apenas gerar o token e enviar o e-mail — é o que esta classe faz
 * para cadastros via formulário (houzez_after_register). Cadastros sociais
 * (e-mail já validado pelo provedor) e contas criadas pelo admin ficam fora.
 */
class Imovel_Parceiro_Email_Verification {

	const TOKEN_META    = 'houzez_email_verification_token';
	const VERIFIED_META = 'houzez_email_verified';

	public function __construct() {
		add_action( 'houzez_after_register', array( $this, 'send_verification_on_register' ), 20, 1 );
		add_action( 'wp_ajax_nopriv_ipc_resend_verification', array( $this, 'ajax_resend' ) );
		add_action( 'wp_ajax_ipc_resend_verification', array( $this, 'ajax_resend' ) );
	}

	/**
	 * Monta o link de confirmação no formato esperado pelo template-login.php.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $token   Token.
	 * @return string
	 */
	public static function build_link( $user_id, $token ) {
		$template = function_exists( 'houzez_get_template_link' )
			? houzez_get_template_link( 'template/template-login.php' )
			: '';

		if ( empty( $template ) || untrailingslashit( $template ) === untrailingslashit( home_url( '/' ) ) ) {
			$template = home_url( '/' );
		}

		return add_query_arg(
			array(
				'verrify-email' => absint( $user_id ),
				'token'         => $token,
			),
			$template
		);
	}

	/**
	 * Gera token, marca como não verificado e envia o e-mail de confirmação.
	 *
	 * @param int $user_id Usuário recém-cadastrado.
	 */
	public function send_verification_on_register( $user_id ) {
		$user_id = absint( $user_id );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		// Conta já verificada (ex.: ativada manualmente pelo admin).
		if ( metadata_exists( 'user', $user_id, self::VERIFIED_META )
			&& get_user_meta( $user_id, self::VERIFIED_META, true ) ) {
			return;
		}

		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, self::TOKEN_META, $token );
		update_user_meta( $user_id, self::VERIFIED_META, false );

		$this->send_email( $user, $token );
	}

	/**
	 * Reenvia o e-mail de confirmação (anti-abuso: máx. 3/hora por e-mail).
	 */
	public function ajax_resend() {
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Informe um e-mail válido.', 'imovel-parceiro-core' ) ), 400 );
		}

		$bucket = 'ipc_resend_verification_' . md5( strtolower( $email ) );
		$tries  = absint( get_transient( $bucket ) );
		if ( $tries >= 3 ) {
			wp_send_json_error( array( 'message' => __( 'Muitas tentativas. Tente novamente em uma hora.', 'imovel-parceiro-core' ) ), 429 );
		}
		set_transient( $bucket, $tries + 1, HOUR_IN_SECONDS );

		$user = get_user_by( 'email', $email );
		if ( $user
			&& metadata_exists( 'user', $user->ID, self::VERIFIED_META )
			&& ! get_user_meta( $user->ID, self::VERIFIED_META, true ) ) {
			$token = get_user_meta( $user->ID, self::TOKEN_META, true );
			if ( '' === $token ) {
				$token = wp_generate_password( 32, false );
				update_user_meta( $user->ID, self::TOKEN_META, $token );
			}
			$this->send_email( $user, $token );
		}

		wp_send_json_success( array( 'message' => __( 'Se houver uma conta pendente de confirmação, reenviamos o e-mail.', 'imovel-parceiro-core' ) ) );
	}

	/**
	 * Envia o e-mail de confirmação em PT-BR com o template premium.
	 *
	 * @param WP_User $user  Destinatário.
	 * @param string  $token Token de confirmação.
	 * @return bool
	 */
	private function send_email( $user, $token ) {
		if ( ! class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
			return false;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$name      = $user->first_name ? $user->first_name : $user->user_login;
		$link      = self::build_link( $user->ID, $token );

		$lines = array(
			sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $name ),
			'',
			sprintf( __( 'Falta só um passo para ativar sua conta em %s: confirme seu endereço de e-mail.', 'imovel-parceiro-core' ), $site_name ),
			'',
			strtoupper( __( 'Confirmar e-mail', 'imovel-parceiro-core' ) ) . ': ' . $link,
			'',
			__( 'Se você não criou esta conta, ignore esta mensagem.', 'imovel-parceiro-core' ),
			'',
			__( 'Atenciosamente,', 'imovel-parceiro-core' ),
			__( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
		);

		return Imovel_Parceiro_Email_Template::send(
			$user->user_email,
			sprintf( __( '[%s] Confirme seu e-mail para ativar a conta', 'imovel-parceiro-core' ), $site_name ),
			Imovel_Parceiro_Email_Template::text_to_html( implode( "\n", $lines ) ),
			array(
				'title'    => __( 'Confirme seu e-mail', 'imovel-parceiro-core' ),
				'cta_url'  => $link,
				'cta_text' => __( 'Confirmar e-mail', 'imovel-parceiro-core' ),
			)
		);
	}
}

new Imovel_Parceiro_Email_Verification();

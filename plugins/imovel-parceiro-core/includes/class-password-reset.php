<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página alternativa de redefinição de senha.
 *
 * - Shortcode [ipc_password_reset]: formulário de solicitação (e-mail/usuário)
 *   + formulário de nova senha (com chave válida).
 * - Redireciona todos os resets para a página: filtro lostpassword_url,
 *   reescrita do link no e-mail (retrieve_password_message) e redirect dos
 *   actions perdidos do wp-login.php.
 * - Mensagens genéricas (sem enumeração de usuários) e limite de tentativas.
 */
class Imovel_Parceiro_Password_Reset {

	const PAGE_SLUG   = 'redefinir-senha';
	const PAGE_OPTION = 'ipc_password_reset_page_id';
	const NONCE       = 'ipc_password_reset_nonce';

	public function __construct() {
		add_shortcode( 'ipc_password_reset', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'ensure_page' ), 20 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 20, 2 );
		add_filter( 'woocommerce_lostpassword_url', array( $this, 'filter_wc_lostpassword_url' ), 20, 1 );
		add_filter( 'retrieve_password_message', array( $this, 'rewrite_email_link' ), 20, 4 );
		add_action( 'login_init', array( $this, 'redirect_wp_login_actions' ), 5 );
	}

	/**
	 * URL da página de redefinição ('' se ainda não existir).
	 *
	 * @return string
	 */
	public static function url() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) {
			$link = get_permalink( $page_id );
			if ( $link ) {
				return $link;
			}
		}

		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page ) {
			update_option( self::PAGE_OPTION, (int) $page->ID, false );
			return get_permalink( $page->ID );
		}

		return '';
	}

	/**
	 * Garante a existência da página (cria uma única vez).
	 */
	public function ensure_page() {
		if ( self::url() ) {
			return;
		}

		if ( get_page_by_path( self::PAGE_SLUG ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Redefinir senha', 'imovel-parceiro-core' ),
				'post_name'    => self::PAGE_SLUG,
				'post_content' => '[ipc_password_reset]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'page_template' => 'template/template-password-reset.php',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( self::PAGE_OPTION, (int) $page_id, false );
		}
	}

	public function filter_lostpassword_url( $url, $redirect ) {
		$custom = self::url();
		if ( ! $custom ) {
			return $url;
		}

		if ( ! empty( $redirect ) ) {
			$custom = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom );
		}

		return $custom;
	}

	public function filter_wc_lostpassword_url( $url ) {
		$custom = self::url();

		return $custom ? $custom : $url;
	}

	/**
	 * Troca o link de redefinição do e-mail pelo link da página.
	 * Tolera qualquer ordem de parâmetros (o formato mudou no WP 6.8+).
	 */
	public function rewrite_email_link( $message, $key, $user_login, $user_data ) {
		$custom = self::url();
		if ( ! $custom ) {
			return $message;
		}

		if ( ! preg_match( '#https?://[^\s<"\']*wp-login\.php\?[^\s<"\']*#i', (string) $message, $m ) ) {
			return $message;
		}

		$found = html_entity_decode( $m[0], ENT_QUOTES, 'UTF-8' );
		$query = array();
		$parts = wp_parse_url( $found );
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$reset_link = add_query_arg(
			array(
				'key'   => isset( $query['key'] ) && '' !== $query['key'] ? $query['key'] : $key,
				'login' => rawurlencode( isset( $query['login'] ) && '' !== $query['login'] ? $query['login'] : $user_login ),
			),
			$custom
		);

		return str_replace( $m[0], $reset_link, (string) $message );
	}

	/**
	 * Redireciona os actions de reset do wp-login.php para a página.
	 */
	public function redirect_wp_login_actions() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( ! in_array( $action, array( 'lostpassword', 'rp', 'resetpass' ), true ) ) {
			return;
		}

		$custom = self::url();
		if ( ! $custom ) {
			return;
		}

		if ( in_array( $action, array( 'rp', 'resetpass' ), true )
			&& ! empty( $_GET['key'] ) && ! empty( $_GET['login'] ) ) {
			$custom = add_query_arg(
				array(
					'key'   => sanitize_text_field( wp_unslash( $_GET['key'] ) ),
					'login' => sanitize_text_field( wp_unslash( $_GET['login'] ) ),
				),
				$custom
			);
		}

		wp_safe_redirect( $custom );
		exit;
	}

	/**
	 * Limite simples anti-abuso (5 tentativas/hora por IP).
	 *
	 * @return bool True se excedeu.
	 */
	private function rate_limited() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'ipc_pwreset_' . md5( $ip );
		$tries = absint( get_transient( $key ) );
		if ( $tries >= 5 ) {
			return true;
		}
		set_transient( $key, $tries + 1, HOUR_IN_SECONDS );

		return false;
	}

	/**
	 * Política de senha (igual à do cadastro): mín. 8, 1 maiúscula, 1 símbolo.
	 *
	 * @param string $password Senha.
	 * @return string Erro ou ''.
	 */
	private function password_error( $password ) {
		if ( strlen( $password ) < 8
			|| ! preg_match( '/[A-Z]/', $password )
			|| ! preg_match( '/[^A-Za-z0-9]/', $password ) ) {
			return __( 'Mínimo de 8 caracteres, incluindo 1 letra maiúscula e 1 símbolo.', 'imovel-parceiro-core' );
		}

		return '';
	}

	/**
	 * Shortcode: solicitação ou definição conforme presença de chave válida.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

		if ( '' !== $key && '' !== $login ) {
			return $this->render_reset_step( $key, $login );
		}

		return $this->render_request_step();
	}

	/**
	 * Etapa 1: pedir o link por e-mail/usuário.
	 *
	 * @return string
	 */
	private function render_request_step() {
		$notice = '';
		$class  = '';

		if ( isset( $_POST['ipc_pwreset_request'] ) ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
				$notice = __( 'Sessão expirada. Recarregue a página e tente de novo.', 'imovel-parceiro-core' );
				$class  = 'is-error';
			} elseif ( $this->rate_limited() ) {
				$notice = __( 'Muitas tentativas. Aguarde uma hora e tente novamente.', 'imovel-parceiro-core' );
				$class  = 'is-error';
			} else {
				$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
				if ( '' === $login ) {
					$notice = __( 'Informe seu usuário ou e-mail.', 'imovel-parceiro-core' );
					$class  = 'is-error';
				} else {
					$user = strpos( $login, '@' ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
					if ( $user ) {
						retrieve_password( $user->user_login );
					}
					// Mensagem idêntica exista ou não a conta (sem enumeração).
					$notice = __( 'Se os dados estiverem corretos, você receberá um link para criar uma nova senha por e-mail.', 'imovel-parceiro-core' );
					$class  = 'is-success';
				}
			}
		}

		ob_start();
		?>
		<div class="ipc-reset-card">
			<h3 class="ipc-reset-card__title"><?php esc_html_e( 'Esqueci minha senha', 'imovel-parceiro-core' ); ?></h3>
			<p class="ipc-reset-card__text"><?php esc_html_e( 'Informe seu usuário ou e-mail abaixo. Você receberá um link para criar uma nova senha.', 'imovel-parceiro-core' ); ?></p>
			<?php if ( '' !== $notice ) : ?>
				<p class="ipc-reset-notice <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $notice ); ?></p>
			<?php endif; ?>
			<form method="post" class="ipc-reset-form">
				<div class="form-group">
					<input type="text" class="form-control" name="user_login" autocomplete="username" placeholder="<?php esc_attr_e( 'Usuário ou e-mail', 'imovel-parceiro-core' ); ?>" required />
				</div>
				<?php wp_nonce_field( self::NONCE ); ?>
				<button type="submit" name="ipc_pwreset_request" value="1" class="btn btn-primary w-100">
					<?php esc_html_e( 'Enviar link de redefinição', 'imovel-parceiro-core' ); ?>
				</button>
			</form>
			<p class="ipc-reset-card__back"><a href="#" class="ipc-open-houzez-login" data-bs-toggle="modal" data-bs-target="#login-register-form"><?php esc_html_e( 'Voltar ao login', 'imovel-parceiro-core' ); ?></a></p>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function(){
		  document.querySelectorAll('.ipc-open-houzez-login').forEach(function(el){
		    el.addEventListener('click', function(e){
		      e.preventDefault();
		      var modal=document.getElementById('login-register-form');
		      if(modal){
		        if(window.bootstrap&&bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modal).show();
		        else if(window.jQuery&&jQuery.fn.modal) jQuery(modal).modal('show');
		        else window.location.href='/';
		      }
		    });
		  });
		});
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Etapa 2: definir a nova senha (chave válida).
	 *
	 * @param string $key   Chave.
	 * @param string $login Login.
	 * @return string
	 */
	private function render_reset_step( $key, $login ) {
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			ob_start();
			?>
			<div class="ipc-reset-card">
				<h3 class="ipc-reset-card__title"><?php esc_html_e( 'Link inválido ou expirado', 'imovel-parceiro-core' ); ?></h3>
				<p class="ipc-reset-card__text"><?php esc_html_e( 'Este link de redefinição não é mais válido. Solicite um novo link abaixo.', 'imovel-parceiro-core' ); ?></p>
				<p><a class="btn btn-primary w-100" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Solicitar novo link', 'imovel-parceiro-core' ); ?></a></p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		$notice = '';
		$class  = '';
		$done   = false;

		if ( isset( $_POST['ipc_pwreset_save'] ) ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE . $user->ID ) ) {
				$notice = __( 'Sessão expirada. Recarregue a página e tente de novo.', 'imovel-parceiro-core' );
				$class  = 'is-error';
			} else {
				// Revalida a chave no POST (uso único).
				$recheck = check_password_reset_key( $key, $login );
				if ( is_wp_error( $recheck ) ) {
					$notice = __( 'Este link de redefinição não é mais válido. Solicite um novo link.', 'imovel-parceiro-core' );
					$class  = 'is-error';
				} else {
					$pass1 = isset( $_POST['pass1'] ) ? wp_unslash( $_POST['pass1'] ) : '';
					$pass2 = isset( $_POST['pass2'] ) ? wp_unslash( $_POST['pass2'] ) : '';
					if ( $pass1 !== $pass2 ) {
						$notice = __( 'As senhas não conferem.', 'imovel-parceiro-core' );
						$class  = 'is-error';
					} elseif ( '' !== ( $policy = $this->password_error( $pass1 ) ) ) {
						$notice = $policy;
						$class  = 'is-error';
					} else {
						reset_password( $user, $pass1 );
						$done = true;
					}
				}
			}
		}

		if ( $done ) {
			ob_start();
			?>
			<div class="ipc-reset-card">
				<h3 class="ipc-reset-card__title"><?php esc_html_e( 'Senha alterada!', 'imovel-parceiro-core' ); ?></h3>
				<p class="ipc-reset-card__text"><?php esc_html_e( 'Sua nova senha foi salva. Use-a no próximo acesso.', 'imovel-parceiro-core' ); ?></p>
				<p><a class="btn btn-primary w-100 ipc-open-houzez-login" href="#" data-bs-toggle="modal" data-bs-target="#login-register-form"><?php esc_html_e( 'Fazer login', 'imovel-parceiro-core' ); ?></a></p>
			</div>
			<script>
			document.addEventListener('DOMContentLoaded', function(){
			  document.querySelectorAll('.ipc-open-houzez-login').forEach(function(el){
			    el.addEventListener('click', function(e){
			      e.preventDefault();
			      var modal = document.getElementById('login-register-form');
			      if(modal){
			        if(window.bootstrap && bootstrap.Modal){ bootstrap.Modal.getOrCreateInstance(modal).show(); }
			        else if(window.jQuery && jQuery.fn.modal){ jQuery(modal).modal('show'); }
			        else { window.location.href='/'; }
			      }
			    });
			  });
			});
			</script>
			<?php
			return (string) ob_get_clean();
		}

		ob_start();
		?>
		<div class="ipc-reset-card">
			<h3 class="ipc-reset-card__title"><?php esc_html_e( 'Criar nova senha', 'imovel-parceiro-core' ); ?></h3>
			<p class="ipc-reset-card__text"><?php printf( esc_html__( 'Olá, %s! Defina abaixo sua nova senha de acesso.', 'imovel-parceiro-core' ), esc_html( $user->user_login ) ); ?></p>
			<?php if ( '' !== $notice ) : ?>
				<p class="ipc-reset-notice <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $notice ); ?></p>
			<?php endif; ?>
			<form method="post" class="ipc-reset-form">
				<div class="form-group">
					<input type="password" class="form-control" name="pass1" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Nova senha', 'imovel-parceiro-core' ); ?>" required />
				</div>
				<div class="form-group">
					<input type="password" class="form-control" name="pass2" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Repetir nova senha', 'imovel-parceiro-core' ); ?>" required />
				</div>
				<p class="ipc-reset-hint"><?php esc_html_e( 'Mínimo de 8 caracteres, incluindo 1 letra maiúscula e 1 símbolo.', 'imovel-parceiro-core' ); ?></p>
				<?php wp_nonce_field( self::NONCE . $user->ID ); ?>
				<button type="submit" name="ipc_pwreset_save" value="1" class="btn btn-primary w-100">
					<?php esc_html_e( 'Salvar nova senha', 'imovel-parceiro-core' ); ?>
				</button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

new Imovel_Parceiro_Password_Reset();

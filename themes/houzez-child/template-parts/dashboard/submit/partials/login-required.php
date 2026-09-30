<?php
/**
 * Override do tema filho: tela de "login necessário" da página Cadastrar Imóvel.
 *
 * Arquivo pai: houzez/template-parts/dashboard/submit/partials/login-required.php
 * Incluído via get_template_part() em houzez/template/user_dashboard_submit.php:553,
 * que prioriza o tema filho — nenhum ajuste no tema pai é necessário.
 *
 * Mantidas as travas de exibição (houzez_option header_login/header_register),
 * os gatilhos do modal (data-bs-toggle/data-bs-target) e as classes
 * .login-link/.register-link (o custom.js do Houzez usa essas classes para
 * abrir o modal já na aba correta: entrar x criar conta).
 */
$show_login    = function_exists( 'houzez_option' ) ? (int) houzez_option( 'header_login' ) !== 0 : true;
$show_register = function_exists( 'houzez_option' ) ? (int) houzez_option( 'header_register' ) !== 0 : true;
?>
<div class="block-wrap mb-4 ipc-submit-login-wrap">
	<div class="block-content-wrap">
		<div class="ipc-submit-login" role="region" aria-label="<?php esc_attr_e( 'Entre para cadastrar seu imóvel', 'houzez' ); ?>">
			<div class="ipc-submit-login__card">
				<span class="ipc-submit-login__icon" aria-hidden="true">
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				</span>
				<p class="ipc-submit-login__eyebrow"><?php esc_html_e( 'Área do anunciante', 'houzez' ); ?></p>
				<h2 class="ipc-submit-login__title"><?php esc_html_e( 'Entre para cadastrar seu imóvel', 'houzez' ); ?></h2>
				<p class="ipc-submit-login__text">
					<?php esc_html_e( 'Para publicar e gerenciar seus imóveis você precisa estar conectado. É rápido e gratuito.', 'houzez' ); ?>
				</p>
				<ul class="ipc-submit-login__benefits">
					<li><?php esc_html_e( 'Publique fotos, preço e detalhes do imóvel', 'houzez' ); ?></li>
					<li><?php esc_html_e( 'Receba propostas de corretores parceiros', 'houzez' ); ?></li>
					<li><?php esc_html_e( 'Acompanhe visitas e negociações em um só lugar', 'houzez' ); ?></li>
				</ul>
				<div class="ipc-submit-login__actions">
					<?php if ( $show_login ) { ?>
						<span class="login-link ipc-submit-login__btn">
							<a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#login-register-form">
								<?php esc_html_e( 'Entrar na minha conta', 'houzez' ); ?>
							</a>
						</span>
					<?php } ?>
					<?php if ( $show_register ) { ?>
						<span class="register-link ipc-submit-login__btn">
							<a href="#" class="btn btn-primary-outlined" data-bs-toggle="modal" data-bs-target="#login-register-form">
								<?php esc_html_e( 'Criar conta grátis', 'houzez' ); ?>
							</a>
						</span>
					<?php } ?>
				</div>
				<p class="ipc-submit-login__note">
					<?php esc_html_e( 'Seus dados estão protegidos e nunca são compartilhados sem a sua permissão.', 'houzez' ); ?>
				</p>
			</div>
		</div>
	</div><!-- block-content-wrap -->
</div><!-- block-wrap -->

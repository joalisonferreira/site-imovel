<?php
/**
 * Wizard de cadastro multi-step (padrão stitch "Modern Real Estate").
 *
 * Passo 1: Perfil + dados básicos (nome, e-mail, celular/WhatsApp).
 * Passo 2: Documento (CPF/CNPJ + nascimento; CRECI se corretor) + preferências (comprador).
 * Passo 3: Senha + termos (submit real via AJAX próprio em auth.js).
 * Passo 4: Sucesso (resumo + próximos passos por perfil).
 *
 * Nomes de campos do backend preservados: ipc_full_name, useremail,
 * phone_number, phone_is_whatsapp, person_type, person_document, creci,
 * role, term_condition, username (auto), register_pass(_retype), nonce/action.
 */
$GLOBALS['ipc_register_wizard'] = true;
$user_show_roles = houzez_option('user_show_roles');
$show_hide_roles = houzez_option('show_hide_roles');
$terms_url = get_permalink(houzez_option('login_terms_condition'));
$privacy_url = get_permalink(houzez_option('login_privacy_policy'));
if ( ! $privacy_url ) {
    $privacy_url = $terms_url;
}
?>
<div id="hz-register-messages" class="hz-social-messages"></div>
<?php if( get_option('users_can_register') ) { ?>
<form id="houzez_register_form" method="post" class="ipc-register-form ipc-wizard" novalidate>
<div class="ipc-wz-card">

    <div class="ipc-wz-progress"><span class="ipc-wz-progress__bar" data-ipc-wz-bar="1"></span></div>

    <nav class="ipc-wz-stepper" aria-label="<?php esc_attr_e('Progresso do cadastro', 'houzez'); ?>">
        <ol>
            <li data-ipc-wz-dot="1"><span class="ipc-wz-dot"><span class="ipc-wz-dot__num">1</span><span class="ipc-wz-dot__check" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span></span><span class="ipc-wz-stepper__txt"><small><?php esc_html_e('Etapa 1', 'houzez'); ?></small><strong><?php esc_html_e('Perfil e dados', 'houzez'); ?></strong></span></li>
            <li data-ipc-wz-dot="2"><span class="ipc-wz-dot"><span class="ipc-wz-dot__num">2</span><span class="ipc-wz-dot__check" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span></span><span class="ipc-wz-stepper__txt"><small><?php esc_html_e('Etapa 2', 'houzez'); ?></small><strong><?php esc_html_e('Documento', 'houzez'); ?></strong></span></li>
            <li data-ipc-wz-dot="3"><span class="ipc-wz-dot"><span class="ipc-wz-dot__num">3</span><span class="ipc-wz-dot__check" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span></span><span class="ipc-wz-stepper__txt"><small><?php esc_html_e('Etapa 3', 'houzez'); ?></small><strong><?php esc_html_e('Segurança', 'houzez'); ?></strong></span></li>
        </ol>
    </nav>

    <!-- ================= PASSO 1 ================= -->
    <section class="ipc-wz-panel" data-ipc-wz-panel="1">
        <p class="ipc-wz-kicker"><?php esc_html_e('Criação de conta', 'houzez'); ?></p>
        <h3 class="ipc-wz-title"><?php esc_html_e('Como você deseja utilizar a plataforma?', 'houzez'); ?></h3>
        <p class="ipc-wz-sub"><?php esc_html_e('Personalizamos sua experiência com ferramentas e alertas para o seu perfil.', 'houzez'); ?></p>

        <?php if($user_show_roles != 0) { ?>
        <p class="ipc-wz-label"><?php esc_html_e('Selecione seu perfil principal', 'houzez'); ?></p>
        <div class="ipc-wz-profiles" role="radiogroup" aria-label="<?php esc_attr_e('Perfil', 'houzez'); ?>">
            <?php if( isset($show_hide_roles['buyer']) && $show_hide_roles['buyer'] != 1 ) { ?>
            <div class="ipc-wz-profile">
                <input class="ipc-wz-profile__radio" type="radio" id="ipc_prof_buyer" name="role" value="houzez_buyer" checked />
                <label class="ipc-wz-profile__card" for="ipc_prof_buyer">
                    <span class="ipc-wz-profile__top"><span class="ipc-wz-profile__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><span class="ipc-wz-profile__check" aria-hidden="true"></span></span>
                    <strong><?php esc_html_e('Quero comprar ou alugar', 'houzez'); ?></strong>
                    <small><?php esc_html_e('Busque imóveis, salve favoritos e agende visitas.', 'houzez'); ?></small>
                </label>
            </div>
            <?php } ?>
            <?php if( isset($show_hide_roles['owner']) && $show_hide_roles['owner'] != 1 ) { ?>
            <div class="ipc-wz-profile">
                <input class="ipc-wz-profile__radio" type="radio" id="ipc_prof_owner" name="role" value="houzez_owner" />
                <label class="ipc-wz-profile__card" for="ipc_prof_owner">
                    <span class="ipc-wz-profile__top"><span class="ipc-wz-profile__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg></span><span class="ipc-wz-profile__check" aria-hidden="true"></span></span>
                    <strong><?php esc_html_e('Tenho um imóvel', 'houzez'); ?></strong>
                    <small><?php esc_html_e('Anuncie com visibilidade e receba propostas.', 'houzez'); ?></small>
                </label>
            </div>
            <?php } ?>
            <?php if( isset($show_hide_roles['agent']) && $show_hide_roles['agent'] != 1 ) { ?>
            <div class="ipc-wz-profile">
                <input class="ipc-wz-profile__radio" type="radio" id="ipc_prof_agent" name="role" value="houzez_agent" />
                <label class="ipc-wz-profile__card" for="ipc_prof_agent">
                    <span class="ipc-wz-profile__top"><span class="ipc-wz-profile__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span><span class="ipc-wz-profile__check" aria-hidden="true"></span></span>
                    <strong><?php esc_html_e('Sou corretor', 'houzez'); ?></strong>
                    <small><?php esc_html_e('Acesse parcerias e feche negócios via CRECI.', 'houzez'); ?></small>
                </label>
            </div>
            <?php } ?>
        </div>
        <?php if( isset($show_hide_roles['agency']) && $show_hide_roles['agency'] != 1 ) { ?>
        <button type="button" class="ipc-wz-pj" data-ipc-wz-pj="1">
            <span><?php esc_html_e('É imobiliária ou construtora?', 'houzez'); ?> <strong><?php esc_html_e('Cadastre como Pessoa Jurídica (PJ)', 'houzez'); ?></strong></span>
            <span aria-hidden="true">→</span>
        </button>
        <input class="ipc-wz-profile__radio" type="radio" name="role" value="houzez_agency" data-ipc-wz-agency="1" hidden />
        <?php } ?>
        <?php } ?>

        <div class="ipc-wz-field">
            <label for="ipc_wz_name"><?php esc_html_e('Nome completo', 'houzez'); ?></label>
            <input id="ipc_wz_name" type="text" name="ipc_full_name" autocomplete="name" placeholder="<?php esc_attr_e('Ex: Carlos Eduardo de Oliveira', 'houzez'); ?>" />
        </div>
        <div class="ipc-wz-grid">
            <div class="ipc-wz-field">
                <label for="ipc_wz_email"><?php esc_html_e('E-mail de contato', 'houzez'); ?></label>
                <input id="ipc_wz_email" type="email" name="useremail" autocomplete="username" placeholder="<?php esc_attr_e('seu.email@exemplo.com.br', 'houzez'); ?>" />
                <p class="ipc-wz-help"><?php esc_html_e('Usamos seu e-mail para acesso seguro e avisos.', 'houzez'); ?></p>
            </div>
            <div class="ipc-wz-field">
                <label for="ipc_wz_phone"><?php esc_html_e('Celular com DDD', 'houzez'); ?></label>
                <input id="ipc_wz_phone" type="tel" name="phone_number" inputmode="tel" autocomplete="tel" placeholder="<?php esc_attr_e('(11) 98765-4321', 'houzez'); ?>" />
                <label class="ipc-wz-check"><input type="checkbox" name="phone_is_whatsapp" value="1" checked /> <span><strong><?php esc_html_e('WhatsApp:', 'houzez'); ?></strong> <?php esc_html_e('usar este número para contato', 'houzez'); ?></span></label>
            </div>
        </div>
        <p class="ipc-wz-error" data-ipc-wz-error="1" hidden></p>

        <input type="hidden" name="username" class="ipc-username-auto" value="" />
        <input type="hidden" name="first_name" value="" />
        <input type="hidden" name="last_name" value="" />

        <div class="ipc-wz-actions">
            <span></span>
            <button type="button" class="ipc-wz-btn ipc-wz-btn--primary" data-ipc-wz-next="2"><?php esc_html_e('Avançar', 'houzez'); ?> <span aria-hidden="true">→</span></button>
        </div>
        <p class="ipc-wz-seal"><?php esc_html_e('Seus dados estão protegidos pela LGPD.', 'houzez'); ?></p>
    </section>

    <!-- ================= PASSO 2 ================= -->
    <section class="ipc-wz-panel" data-ipc-wz-panel="2" hidden>
        <p class="ipc-wz-kicker"><?php esc_html_e('Dados e preferências', 'houzez'); ?></p>
        <h3 class="ipc-wz-title"><?php esc_html_e('Complete seus dados', 'houzez'); ?></h3>
        <p class="ipc-wz-sub"><?php esc_html_e('Usamos essas informações para validar seu cadastro e personalizar oportunidades.', 'houzez'); ?></p>

        <input type="hidden" name="person_type" data-ipc-wz-person-type="1" value="cpf" />
        <div class="ipc-wz-grid">
            <div class="ipc-wz-field">
                <label for="ipc_wz_doc"><span data-ipc-wz-doc-label="1"><?php esc_html_e('CPF', 'houzez'); ?></span> <span class="ipc-wz-req">*</span></label>
                <input id="ipc_wz_doc" type="text" name="person_document" inputmode="numeric" placeholder="000.000.000-00" />
            </div>
            <div class="ipc-wz-field">
                <label for="ipc_wz_birth"><?php esc_html_e('Data de nascimento', 'houzez'); ?> <span class="ipc-wz-req">*</span></label>
                <input id="ipc_wz_birth" type="text" name="birthdate" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" />
            </div>
        </div>
        <div class="ipc-wz-field" data-ipc-wz-creci-wrap="1" hidden>
            <label for="ipc_wz_creci"><?php esc_html_e('CRECI', 'houzez'); ?> <span class="ipc-wz-req">*</span></label>
            <input id="ipc_wz_creci" type="text" name="creci" autocomplete="off" placeholder="<?php esc_attr_e('Ex: 123456-F', 'houzez'); ?>" />
        </div>

        <div data-ipc-wz-prefs="1">
            <p class="ipc-wz-label"><?php esc_html_e('O que você está buscando agora?', 'houzez'); ?></p>
            <div class="ipc-wz-goals" role="radiogroup" aria-label="<?php esc_attr_e('Objetivo', 'houzez'); ?>">
                <label class="ipc-wz-goal"><input type="radio" name="ipc_goal" value="comprar" checked /><span><?php esc_html_e('Comprar', 'houzez'); ?></span></label>
                <label class="ipc-wz-goal"><input type="radio" name="ipc_goal" value="alugar" /><span><?php esc_html_e('Alugar', 'houzez'); ?></span></label>
                <label class="ipc-wz-goal"><input type="radio" name="ipc_goal" value="investir" /><span><?php esc_html_e('Investir', 'houzez'); ?></span></label>
            </div>
            <p class="ipc-wz-label"><?php esc_html_e('Tipo de imóvel preferido', 'houzez'); ?></p>
            <div class="ipc-wz-chips" data-ipc-wz-chips="1">
                <button type="button" class="ipc-wz-chip is-on" data-value="Apartamento"><?php esc_html_e('Apartamento', 'houzez'); ?></button>
                <button type="button" class="ipc-wz-chip" data-value="Casa em condomínio"><?php esc_html_e('Casa em condomínio', 'houzez'); ?></button>
                <button type="button" class="ipc-wz-chip" data-value="Cobertura"><?php esc_html_e('Cobertura', 'houzez'); ?></button>
                <button type="button" class="ipc-wz-chip" data-value="Comercial / Sala"><?php esc_html_e('Comercial / Sala', 'houzez'); ?></button>
            </div>
            <input type="hidden" name="ipc_property_types" data-ipc-wz-types="1" value="Apartamento" />
            <div class="ipc-wz-grid">
                <div class="ipc-wz-field">
                    <label for="ipc_wz_price"><?php esc_html_e('Faixa de preço', 'houzez'); ?></label>
                    <select id="ipc_wz_price" name="ipc_price_range">
                        <option value="ate-300"><?php esc_html_e('Até R$ 300 mil', 'houzez'); ?></option>
                        <option value="300-500"><?php esc_html_e('R$ 300 a R$ 500 mil', 'houzez'); ?></option>
                        <option value="500-800" selected><?php esc_html_e('R$ 500 a R$ 800 mil', 'houzez'); ?></option>
                        <option value="800-1200"><?php esc_html_e('R$ 800 mil a R$ 1,2 mi', 'houzez'); ?></option>
                        <option value="1200-2000"><?php esc_html_e('R$ 1,2 a R$ 2 mi', 'houzez'); ?></option>
                        <option value="acima-2000"><?php esc_html_e('Acima de R$ 2 mi', 'houzez'); ?></option>
                    </select>
                </div>
                <div class="ipc-wz-field">
                    <label for="ipc_wz_loc"><?php esc_html_e('Cidade / bairro de interesse', 'houzez'); ?></label>
                    <input id="ipc_wz_loc" type="text" name="ipc_location" placeholder="<?php esc_attr_e('Ex: Jardins, São Paulo - SP', 'houzez'); ?>" />
                </div>
            </div>
            <label class="ipc-wz-check ipc-wz-check--box"><input type="checkbox" name="ipc_consultoria" value="1" checked /> <span><?php esc_html_e('Quero consultoria gratuita de corretores da minha região.', 'houzez'); ?></span></label>
        </div>
        <p class="ipc-wz-error" data-ipc-wz-error="2" hidden></p>

        <div class="ipc-wz-actions">
            <button type="button" class="ipc-wz-btn ipc-wz-btn--ghost" data-ipc-wz-prev="1">← <?php esc_html_e('Voltar', 'houzez'); ?></button>
            <button type="button" class="ipc-wz-btn ipc-wz-btn--primary" data-ipc-wz-next="3"><?php esc_html_e('Avançar para segurança', 'houzez'); ?> <span aria-hidden="true">→</span></button>
        </div>
        <p class="ipc-wz-seal"><?php esc_html_e('Seus dados estão protegidos pela LGPD.', 'houzez'); ?></p>
    </section>

    <!-- ================= PASSO 3 ================= -->
    <section class="ipc-wz-panel" data-ipc-wz-panel="3" hidden>
        <p class="ipc-wz-kicker"><?php esc_html_e('Segurança e acesso', 'houzez'); ?></p>
        <h3 class="ipc-wz-title"><?php esc_html_e('Crie sua senha de acesso', 'houzez'); ?></h3>
        <p class="ipc-wz-sub"><?php esc_html_e('Ela protege sua conta em qualquer dispositivo.', 'houzez'); ?></p>

        <div class="ipc-wz-field">
            <label for="ipc_wz_pass"><?php esc_html_e('Senha', 'houzez'); ?></label>
            <div class="ipc-password-field">
                <input id="ipc_wz_pass" type="password" name="register_pass" autocomplete="new-password" placeholder="<?php esc_attr_e('Digite sua senha', 'houzez'); ?>" />
                <?php get_template_part('template-parts/login-register/password-toggle'); ?>
            </div>
            <div class="ipc-wz-strength" data-ipc-wz-strength="1">
                <div class="ipc-wz-strength__bars"><span></span><span></span><span></span><span></span></div>
                <p class="ipc-wz-strength__label" data-ipc-wz-strength-label="1"></p>
            </div>
            <ul class="ipc-wz-criteria" data-ipc-wz-criteria="1">
                <li data-criterion="len"><?php esc_html_e('Mínimo de 8 caracteres', 'houzez'); ?></li>
                <li data-criterion="upper"><?php esc_html_e('1 letra maiúscula', 'houzez'); ?></li>
                <li data-criterion="symbol"><?php esc_html_e('1 símbolo (@, #, $…)', 'houzez'); ?></li>
            </ul>
            <input type="hidden" name="register_pass_retype" class="ipc-pass-retype" value="" />
        </div>
        <div class="ipc-wz-field">
            <label for="ipc_wz_pass2"><?php esc_html_e('Confirmar senha', 'houzez'); ?></label>
            <input id="ipc_wz_pass2" type="password" autocomplete="new-password" placeholder="<?php esc_attr_e('Repita a senha', 'houzez'); ?>" />
        </div>

        <label class="ipc-wz-check ipc-wz-check--box"><input type="checkbox" name="term_condition" value="on" /> <span><?php printf( __( 'Concordo com os %s e a %s.', 'houzez' ), '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Termos de Uso', 'houzez' ) . '</a>', '<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Política de Privacidade', 'houzez' ) . '</a>' ); ?></span></label>

        <?php get_template_part('template-parts/captcha'); ?>
        <?php do_action('houzez_after_register_form_fields'); ?>
        <?php wp_nonce_field( 'houzez_register_nonce', 'houzez_register_security' ); ?>
        <input type="hidden" name="action" value="houzez_register" id="register_action" />
        <p class="ipc-wz-error" data-ipc-wz-error="3" hidden></p>

        <div class="ipc-wz-actions">
            <button type="button" class="ipc-wz-btn ipc-wz-btn--ghost" data-ipc-wz-prev="2">← <?php esc_html_e('Voltar', 'houzez'); ?></button>
            <button type="submit" id="houzez-register-btn" class="ipc-wz-btn ipc-wz-btn--primary btn-register">
                <?php get_template_part('template-parts/loader'); ?>
                <?php esc_html_e('Criar minha conta', 'houzez'); ?>
            </button>
        </div>
        <p class="ipc-wz-seal"><?php esc_html_e('Ambiente seguro • LGPD', 'houzez'); ?></p>
    </section>

    <!-- ================= PASSO 4 (sucesso) ================= -->
    <section class="ipc-wz-panel ipc-wz-success" data-ipc-wz-panel="4" hidden>
        <div class="ipc-wz-success__icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <p class="ipc-wz-kicker ipc-wz-kicker--center"><?php esc_html_e('Conta criada com sucesso', 'houzez'); ?></p>
        <h3 class="ipc-wz-title ipc-wz-title--center"><?php esc_html_e('Bem-vindo à Imóvel Parceiro!', 'houzez'); ?></h3>
        <p class="ipc-wz-sub ipc-wz-sub--center"><?php esc_html_e('Seu perfil foi configurado. Enviamos os dados de acesso para o seu e-mail.', 'houzez'); ?></p>
        <div class="ipc-wz-summary" data-ipc-wz-summary="1"></div>
        <a class="ipc-wz-btn ipc-wz-btn--primary ipc-wz-btn--block" data-ipc-wz-cta="1" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e('Ir para o painel', 'houzez'); ?> <span aria-hidden="true">→</span></a>
        <p class="ipc-wz-seal"><?php esc_html_e('Ambiente seguro • LGPD • Suporte todos os dias', 'houzez'); ?></p>
    </section>

    <?php do_action('houzez_register_form_fields'); ?>
</div>
</form>

<?php if( houzez_option('facebook_login') == 'yes' || houzez_option('google_login') == 'yes' ) { ?>
<div class="social-login-wrap">
    <?php if( houzez_option('facebook_login') == 'yes' ) { ?>
    <button type="button" class="hz-facebook-login btn btn-facebook-login w-100">
        <?php get_template_part('template-parts/loader'); ?>
        <?php esc_html_e( 'Continue with Facebook', 'houzez' ); ?>
    </button>
    <?php } ?>
    <?php if( houzez_option('google_login') == 'yes' ) { ?>
    <button type="button" class="hz-google-login btn btn-google-plus-lined w-100">
        <?php get_template_part('template-parts/loader'); ?>
        <img class="google-icon" src="<?php echo HOUZEZ_IMAGE; ?>Google__G__Logo.svg" alt="<?php esc_html_e( 'Sign in with google', 'houzez' ); ?>"/> <?php esc_html_e( 'Sign in with google', 'houzez' ); ?>
    </button>
    <?php } ?>
</div>
<?php } ?>

<?php } else {
    esc_html_e('User registration is disabled for demo purpose.', 'houzez');
} ?>

<?php
/**
 * Premium override of the Houzez registration form.
 *
 * Organises the fields into labelled sections with a responsive two-column
 * grid. All field names, IDs, hooks, conditionals and nonces required by the
 * theme and by the "imovel-parceiro-core" plugin are preserved.
 */
$allowed_html_array = array(
    'a' => array(
        'href' => array(),
        'target' => array(),
        'title' => array()
    )
);
$user_show_roles = houzez_option('user_show_roles');
$show_hide_roles = houzez_option('show_hide_roles');
?>
<div id="hz-register-messages" class="hz-social-messages"></div>
<?php if( get_option('users_can_register') ) { ?>
<form id="houzez_register_form" method="post" class="ipc-register-form">
<div class="register-form-wrap ipc-auth-body">

    <div class="ipc-auth-intro">
        <h3 class="ipc-auth-intro__title"><?php esc_html_e( 'Crie sua conta', 'houzez' ); ?></h3>
        <p class="ipc-auth-intro__text"><?php esc_html_e( 'Cadastre-se em poucos passos e comece a fechar negócios.', 'houzez' ); ?></p>
    </div>

    <section class="ipc-auth-section">
        <h4 class="ipc-auth-section__title"><?php esc_html_e( 'Dados pessoais', 'houzez' ); ?></h4>
        <div class="ipc-grid ipc-grid--2">

            <div class="ipc-field ipc-field--full">
                <input type="text" class="form-control ipc-full-name" name="ipc_full_name" placeholder="<?php esc_html_e('Nome completo','houzez'); ?>" autocomplete="name" required />
                <?php $GLOBALS['ipc_register_full_name_rendered'] = true; ?>
            </div>
            <input type="hidden" name="first_name" value="" />
            <input type="hidden" name="last_name" value="" />

            <input type="hidden" name="username" class="ipc-username-auto" value="" />

            <div class="ipc-field">
                <input type="email" class="form-control" name="useremail" autocomplete="username" placeholder="<?php esc_html_e('Email','houzez'); ?>" />
                <p class="ipc-field-note"><?php esc_html_e('Usamos seu e-mail como identificação — sem nome de usuário para decorar.','houzez'); ?></p>
            </div>

            <?php if( houzez_option('register_mobile', 0) == 1 ) { ?>
            <div class="ipc-field ipc-field--full">
                <input type="tel" inputmode="tel" pattern="[0-9()\s-]*" maxlength="15" class="form-control" name="phone_number" placeholder="<?php esc_html_e('Phone','houzez'); ?>" />
                <label class="ipc-whatsapp-check">
                    <input type="checkbox" name="phone_is_whatsapp" value="1" />
                    <span><?php esc_html_e( 'Este número é WhatsApp', 'houzez' ); ?></span>
                </label>
            </div>
            <?php } ?>

        </div>
    </section>

    <?php if( houzez_option('enable_password') == 'yes' ) { ?>
    <section class="ipc-auth-section">
        <h4 class="ipc-auth-section__title"><?php esc_html_e( 'Segurança', 'houzez' ); ?></h4>
        <div class="ipc-grid ipc-grid--1">
            <div class="ipc-field">
                <div class="ipc-password-field">
                    <input type="password" class="form-control" name="register_pass" autocomplete="new-password" minlength="8" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}" title="<?php esc_attr_e('Minimo de 8 caracteres, com pelo menos 1 letra maiuscula e 1 simbolo.','houzez'); ?>" placeholder="<?php esc_html_e('Password','houzez'); ?>" />
                    <?php get_template_part('template-parts/login-register/password-toggle'); ?>
                </div>
                <p class="ipc-field-hint"><?php esc_html_e('Minimo de 8 caracteres, incluindo 1 letra maiuscula e 1 simbolo.','houzez'); ?></p>
                <input type="hidden" name="register_pass_retype" class="ipc-pass-retype" value="" />
            </div>
        </div>
    </section>
    <?php } ?>

    <section class="ipc-auth-section">
        <h4 class="ipc-auth-section__title"><?php esc_html_e( 'Perfil e documento', 'houzez' ); ?></h4>

        <?php do_action('houzez_register_form_fields'); ?>

        <?php if($user_show_roles != 0) { ?>
        <div class="ipc-profile-pick" data-ipc-profile-pick="1" role="radiogroup" aria-label="<?php esc_attr_e('Qual é o seu perfil?', 'houzez'); ?>">
            <p class="ipc-profile-pick__label"><?php esc_html_e('Qual é o seu perfil?', 'houzez'); ?></p>
            <?php if( isset($show_hide_roles['agent']) && $show_hide_roles['agent'] != 1 ) { ?>
            <label class="ipc-profile-card">
                <input type="radio" name="role" value="houzez_agent" class="ipc-profile-card__input" />
                <span class="ipc-profile-card__icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span>
                <span class="ipc-profile-card__text"><strong><?php esc_html_e('Sou corretor', 'houzez'); ?></strong><small><?php esc_html_e('Anuncie imóveis e faça parcerias', 'houzez'); ?></small></span>
            </label>
            <?php } ?>
            <?php if( isset($show_hide_roles['owner']) && $show_hide_roles['owner'] != 1 ) { ?>
            <label class="ipc-profile-card">
                <input type="radio" name="role" value="houzez_owner" class="ipc-profile-card__input" />
                <span class="ipc-profile-card__icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg></span>
                <span class="ipc-profile-card__text"><strong><?php esc_html_e('Tenho um imóvel', 'houzez'); ?></strong><small><?php esc_html_e('Anuncie e receba propostas', 'houzez'); ?></small></span>
            </label>
            <?php } ?>
            <?php if( isset($show_hide_roles['buyer']) && $show_hide_roles['buyer'] != 1 ) { ?>
            <label class="ipc-profile-card">
                <input type="radio" name="role" value="houzez_buyer" class="ipc-profile-card__input" />
                <span class="ipc-profile-card__icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                <span class="ipc-profile-card__text"><strong><?php esc_html_e('Quero comprar ou alugar', 'houzez'); ?></strong><small><?php esc_html_e('Busque imóveis e salve favoritos', 'houzez'); ?></small></span>
            </label>
            <?php } ?>
        </div>
        <?php if( isset($show_hide_roles['agency']) && $show_hide_roles['agency'] != 1 ) { ?>
        <p class="ipc-profile-agency">
            <label class="ipc-profile-agency__label">
                <input type="radio" name="role" value="houzez_agency" class="ipc-profile-card__input" />
                <span><?php esc_html_e('Sou imobiliária — cadastrar como pessoa jurídica', 'houzez'); ?></span>
            </label>
        </p>
        <?php } ?>
        <?php } ?>

    </section>

    <div class="form-tools ipc-terms">
        <label class="control control--checkbox">
            <input type="checkbox" name="term_condition" required>
            <span>
            <?php echo sprintf( __( 'I agree with your <a target="_blank" href="%s">Terms & Conditions</a>', 'houzez' ), 
                get_permalink(houzez_option('login_terms_condition') )); ?>
            </span>
            <span class="control__indicator"></span>
        </label>
    </div>

    <?php get_template_part('template-parts/captcha'); ?>

    <?php do_action('houzez_after_register_form_fields'); ?>

    <?php wp_nonce_field( 'houzez_register_nonce', 'houzez_register_security' ); ?>
    <input type="hidden" name="action" value="houzez_register" id="register_action">
    <button type="submit" id="houzez-register-btn" class="btn-register btn btn-primary w-100 ipc-auth-submit">
        <?php get_template_part('template-parts/loader'); ?>
        <?php esc_html_e('Register','houzez');?>
    </button>
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

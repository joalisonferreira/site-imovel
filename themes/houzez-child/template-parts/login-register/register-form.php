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

            <div class="ipc-field">
                <input type="text" class="form-control" name="username" placeholder="<?php esc_html_e('Username','houzez'); ?>" />
            </div>

            <div class="ipc-field">
                <input type="email" class="form-control" name="useremail" autocomplete="username" placeholder="<?php esc_html_e('Email','houzez'); ?>" />
            </div>

            <?php if( houzez_option('register_mobile', 0) == 1 ) { ?>
            <div class="ipc-field ipc-field--full">
                <input type="tel" inputmode="tel" class="form-control" name="phone_number" placeholder="<?php esc_html_e('Phone','houzez'); ?>" />
            </div>
            <?php } ?>

        </div>
    </section>

    <?php if( houzez_option('enable_password') == 'yes' ) { ?>
    <section class="ipc-auth-section">
        <h4 class="ipc-auth-section__title"><?php esc_html_e( 'Segurança', 'houzez' ); ?></h4>
        <div class="ipc-grid ipc-grid--2">
            <div class="ipc-field">
                <div class="ipc-password-field">
                    <input type="password" class="form-control" name="register_pass" autocomplete="new-password" minlength="8" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}" title="<?php esc_attr_e('Minimo de 8 caracteres, com pelo menos 1 letra maiuscula e 1 simbolo.','houzez'); ?>" placeholder="<?php esc_html_e('Password','houzez'); ?>" />
                    <?php get_template_part('template-parts/login-register/password-toggle'); ?>
                </div>
                <p class="ipc-field-hint"><?php esc_html_e('Minimo de 8 caracteres, incluindo 1 letra maiuscula e 1 simbolo.','houzez'); ?></p>
            </div>
            <div class="ipc-field">
                <div class="ipc-password-field">
                    <input type="password" class="form-control" name="register_pass_retype" autocomplete="new-password" placeholder="<?php esc_html_e('Retype Password','houzez'); ?>" />
                    <?php get_template_part('template-parts/login-register/password-toggle'); ?>
                </div>
            </div>
        </div>
    </section>
    <?php } ?>

    <section class="ipc-auth-section">
        <h4 class="ipc-auth-section__title"><?php esc_html_e( 'Perfil e documento', 'houzez' ); ?></h4>

        <?php do_action('houzez_register_form_fields'); ?>

        <?php if($user_show_roles != 0) { ?>
        <div class="ipc-field ipc-field--full">
            <select name="role" class="form-control ipc-select" title="<?php esc_html_e('Select your account type', 'houzez'); ?>">
                <option value=""><?php esc_html_e('Select your account type', 'houzez'); ?></option>
                <?php
                if( isset($show_hide_roles['agent']) && $show_hide_roles['agent'] != 1 ) {
                    echo '<option value="houzez_agent">'.houzez_option('agent_role').'</option>';
                }
                if( isset($show_hide_roles['agency']) && $show_hide_roles['agency'] != 1 ) {
                    echo '<option value="houzez_agency">'.houzez_option('agency_role').'</option>';
                }
                if( isset($show_hide_roles['owner']) && $show_hide_roles['owner'] != 1 ) {
                    echo '<option value="houzez_owner">'.houzez_option('owner_role').'</option>';
                }
                if( isset($show_hide_roles['buyer']) && $show_hide_roles['buyer'] != 1 ) {
                    echo '<option value="houzez_buyer">'.houzez_option('buyer_role').'</option>';
                }
                if( isset($show_hide_roles['seller']) && $show_hide_roles['seller'] != 1 ) {
                    echo '<option value="houzez_seller">'.houzez_option('seller_role').'</option>';
                }
                ?>
            </select>
        </div>
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

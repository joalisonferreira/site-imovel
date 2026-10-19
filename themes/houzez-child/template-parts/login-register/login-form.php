<?php
/**
 * Premium override of the Houzez login form.
 *
 * Mirrors the layout, spacing and colours of the registration form (intro
 * heading, single-column grid, terms row and primary CTA). All field names,
 * IDs, hooks, classes and nonces required by the theme and by the
 * "houzez-login-register" plugin are preserved.
 */
?>
<div id="hz-login-messages" class="hz-social-messages"></div>
<form id="houzez_login_form" method="post" class="ipc-login-form">
<div class="login-form-wrap ipc-auth-body">

    <div class="ipc-auth-intro">
        <h3 class="ipc-auth-intro__title"><?php esc_html_e( 'Bem-vindo de volta', 'houzez' ); ?></h3>
        <p class="ipc-auth-intro__text"><?php esc_html_e( 'Acesse sua conta para continuar.', 'houzez' ); ?></p>
    </div>

    <div class="ipc-grid">
        <div class="ipc-field">
            <input type="text" class="form-control" name="username" autocomplete="username" placeholder="<?php esc_attr_e('Usuário ou e-mail','imovel-parceiro-core'); ?>" aria-label="<?php esc_attr_e('Usuário ou e-mail','imovel-parceiro-core'); ?>">
        </div>
        <div class="ipc-field">
            <div class="ipc-password-field">
                <input type="password" class="form-control" name="password" autocomplete="current-password" placeholder="<?php esc_attr_e('Senha','imovel-parceiro-core'); ?>" aria-label="<?php esc_attr_e('Senha','imovel-parceiro-core'); ?>">
                <?php get_template_part('template-parts/login-register/password-toggle'); ?>
            </div>
        </div>
    </div>

</div><!-- login-form-wrap -->

<?php do_action('houzez_login_form_fields'); ?>

<div class="form-tools ipc-terms ipc-login-tools">
    <div class="d-flex">
        <label class="control control--checkbox flex-grow-1">
            <input type="checkbox" name="remember"><?php esc_html_e( 'Lembrar de mim', 'imovel-parceiro-core' ); ?>
            <span class="control__indicator"></span>
        </label>
        <?php $ipc_reset_url = class_exists( 'Imovel_Parceiro_Password_Reset' ) ? Imovel_Parceiro_Password_Reset::url() : ''; ?>
        <?php if ( $ipc_reset_url ) : ?>
        <a href="<?php echo esc_url( $ipc_reset_url ); ?>"><?php esc_html_e( 'Esqueci minha senha', 'imovel-parceiro-core' ); ?></a>
        <?php else : ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#reset-password-form" data-bs-dismiss="modal"><?php esc_html_e( 'Esqueci minha senha', 'imovel-parceiro-core' ); ?></a>
        <?php endif; ?>
    </div><!-- d-flex -->
</div><!-- form-tools -->

<?php get_template_part('template-parts/captcha'); ?>

<?php do_action('houzez_after_login_form_fields'); ?>

<?php wp_nonce_field( 'houzez_login_nonce', 'houzez_login_security' ); ?>
<input type="hidden" name="action" id="login_action" value="houzez_login">
<input type="hidden" name="redirect_to" value="<?php echo esc_url(houzez_after_login_redirect()); ?>">
<button id="houzez-login-btn" type="submit" class="btn btn-success btn-login w-100 ipc-auth-submit">
    <?php get_template_part('template-parts/loader'); ?>
    <?php esc_html_e('Entrar', 'imovel-parceiro-core'); ?>
</button>
</form>

<?php if( houzez_option('facebook_login') == 'yes' || houzez_option('google_login') == 'yes' ) { ?>
<div class="social-login-wrap">
    <?php if( houzez_option('facebook_login') == 'yes' ) { ?>
    <button type="button" class="hz-facebook-login btn btn-facebook-login w-100">
        <?php get_template_part('template-parts/loader'); ?>
        <?php esc_html_e( 'Continuar com Facebook', 'imovel-parceiro-core' ); ?>
    </button>
    <?php } ?>

    <?php if( houzez_option('google_login') == 'yes' ) { ?>
    <button type="button" class="hz-google-login btn btn-google-plus-lined w-100">
        <?php get_template_part('template-parts/loader'); ?>
        <img class="google-icon" src="<?php echo HOUZEZ_IMAGE; ?>Google__G__Logo.svg" alt="<?php esc_attr_e( 'Entrar com Google', 'imovel-parceiro-core' ); ?>"/> <?php esc_html_e( 'Entrar com Google', 'imovel-parceiro-core' ); ?>
    </button>
    <?php } ?>
</div>
<?php } ?>

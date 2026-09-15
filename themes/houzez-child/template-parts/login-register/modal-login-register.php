<?php
/**
 * Premium override of the Houzez login/register modal.
 *
 * Adds the "ipc-auth-modal" scope and exposes the theme primary color as a
 * CSS custom property. All IDs, classes, data attributes and tab hooks used by
 * the theme JS are preserved untouched.
 */
$ipc_auth_primary = houzez_option( 'houzez_primary_color', '#d72218' );
?>
<div class="modal fade login-register-form ipc-auth-modal" id="login-register-form" tabindex="-1" aria-labelledby="loginRegisterModal" aria-hidden="true" style="--ipc-auth-primary: <?php echo esc_attr( $ipc_auth_primary ); ?>;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="login-register-tabs">
                    <ul class="nav nav-tabs" id="loginRegisterTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="modal-toggle-1 nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-form-tab" type="button" role="tab" aria-controls="login-form-tab" aria-selected="true"><?php esc_html_e('Login', 'houzez'); ?></button>
                        </li>

                        <?php if( houzez_option('header_register') ) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="modal-toggle-2 nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-form-tab" type="button" role="tab" aria-controls="register-form-tab" aria-selected="false"><?php esc_html_e('Register', 'houzez'); ?></button>
                        </li>
                        <?php } ?>
                    </ul>    
                </div>
                <button type="button" class="btn close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="tab-content" id="loginRegisterTabContent">
                    <div class="tab-pane fade show active login-form-tab" id="login-form-tab" role="tabpanel" aria-labelledby="login-tab">
                        <?php get_template_part('template-parts/login-register/login-form'); ?>
                    </div><!-- login-form-tab -->
                    <div class="tab-pane fade register-form-tab" id="register-form-tab" role="tabpanel" aria-labelledby="register-tab">
                         <?php get_template_part('template-parts/login-register/register-form'); ?>
                    </div><!-- register-form-tab -->
                </div><!-- tab-content -->
            </div><!-- modal-body -->
        </div><!-- modal-content -->
    </div><!-- modal-dialog -->
</div><!-- login-register-form -->

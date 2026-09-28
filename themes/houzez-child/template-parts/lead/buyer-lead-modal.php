<?php
/**
 * Modal "Quero comprar" — lead express (o que procura + onde + contato).
 *
 * Impresso no wp_footer. Salva no CRM nativo via AJAX (ipc_save_buyer_lead).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$prefill_name  = '';
$prefill_phone = '';
if ( is_user_logged_in() ) {
    $current       = wp_get_current_user();
    $prefill_name  = $current->display_name;
    $prefill_phone = (string) get_user_meta( $current->ID, 'fave_author_mobile', true );
}
?>
<div class="modal fade" id="ipc-lead-modal" tabindex="-1" aria-labelledby="ipc-lead-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ipc-lead-card">
            <button type="button" class="ipc-lead-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'houzez' ); ?>">×</button>
            <div data-ipc-lead-form-wrap="1">
                <p class="ipc-wz-kicker"><?php esc_html_e( 'Quero comprar', 'houzez' ); ?></p>
                <h3 class="ipc-wz-title" id="ipc-lead-modal-label"><?php esc_html_e( 'Diga o que procura e onde', 'houzez' ); ?></h3>
                <p class="ipc-wz-sub"><?php esc_html_e( 'Em 30 segundos avisamos corretores da região para te chamar.', 'houzez' ); ?></p>
                <form id="ipc-lead-form" novalidate>
                    <p class="ipc-wz-label"><?php esc_html_e( 'Quero', 'houzez' ); ?></p>
                    <div class="ipc-wz-goals" role="radiogroup" aria-label="<?php esc_attr_e( 'Objetivo', 'houzez' ); ?>">
                        <label class="ipc-wz-goal"><input type="radio" name="lead_goal" value="comprar" checked /><span><?php esc_html_e( 'Comprar', 'houzez' ); ?></span></label>
                        <label class="ipc-wz-goal"><input type="radio" name="lead_goal" value="alugar" /><span><?php esc_html_e( 'Alugar', 'houzez'); ?></span></label>
                    </div>
                    <p class="ipc-wz-label"><?php esc_html_e( 'Tipo de imóvel (opcional)', 'houzez'); ?></p>
                    <div class="ipc-wz-chips" data-ipc-lead-chips="1">
                        <button type="button" class="ipc-wz-chip" data-value="Apartamento"><?php esc_html_e( 'Apartamento', 'houzez' ); ?></button>
                        <button type="button" class="ipc-wz-chip" data-value="Casa"><?php esc_html_e( 'Casa', 'houzez' ); ?></button>
                        <button type="button" class="ipc-wz-chip" data-value="Cobertura"><?php esc_html_e( 'Cobertura', 'houzez' ); ?></button>
                        <button type="button" class="ipc-wz-chip" data-value="Comercial / Sala"><?php esc_html_e( 'Comercial / Sala', 'houzez' ); ?></button>
                    </div>
                    <div class="ipc-wz-field">
                        <label for="ipc_lead_where"><?php esc_html_e( 'Onde? (cidade ou bairro)', 'houzez'); ?></label>
                        <input id="ipc_lead_where" type="text" name="lead_where" autocomplete="off" placeholder="<?php esc_attr_e( 'Ex: Jardins, São Paulo - SP', 'houzez'); ?>" />
                    </div>
                    <div class="ipc-wz-grid">
                        <div class="ipc-wz-field">
                            <label for="ipc_lead_name"><?php esc_html_e( 'Seu nome', 'houzez'); ?></label>
                            <input id="ipc_lead_name" type="text" name="lead_name" autocomplete="name" placeholder="<?php esc_attr_e( 'Como podemos te chamar?', 'houzez'); ?>" value="<?php echo esc_attr( $prefill_name ); ?>" />
                        </div>
                        <div class="ipc-wz-field">
                            <label for="ipc_lead_phone"><?php esc_html_e( 'WhatsApp', 'houzez'); ?></label>
                            <input id="ipc_lead_phone" type="tel" name="lead_phone" inputmode="tel" autocomplete="tel" placeholder="<?php esc_attr_e( '(11) 98765-4321', 'houzez'); ?>" value="<?php echo esc_attr( $prefill_phone ); ?>" />
                        </div>
                    </div>
                    <div class="ipc-wz-field">
                        <label for="ipc_lead_email"><?php esc_html_e( 'E-mail (opcional)', 'houzez'); ?></label>
                        <input id="ipc_lead_email" type="email" name="lead_email" autocomplete="email" placeholder="<?php esc_attr_e( 'Para receber novidades', 'houzez'); ?>" />
                    </div>
                    <label class="ipc-wz-check ipc-wz-check--box"><input type="checkbox" name="lead_consent" value="1" /> <span><?php esc_html_e( 'Autorizo ser contatado sobre imóveis do meu interesse.', 'houzez'); ?></span></label>
                    <p class="ipc-wz-error" data-ipc-lead-error="1" hidden></p>
                    <button type="submit" class="ipc-wz-btn ipc-wz-btn--primary ipc-wz-btn--block" data-ipc-lead-submit="1"><?php esc_html_e( 'Avise-me quando achar', 'houzez'); ?></button>
                    <p class="ipc-wz-seal"><?php esc_html_e( 'Sem compromisso • LGPD', 'houzez'); ?></p>
                </form>
            </div>
            <div data-ipc-lead-success="1" hidden>
                <div class="ipc-wz-success__icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
                <h3 class="ipc-wz-title ipc-wz-title--center"><?php esc_html_e( 'Pedido recebido!', 'houzez'); ?></h3>
                <p class="ipc-wz-sub ipc-wz-sub--center" data-ipc-lead-success-msg="1"></p>
                <?php if ( ! is_user_logged_in() ) : ?>
                <button type="button" class="ipc-wz-btn ipc-wz-btn--primary ipc-wz-btn--block" data-ipc-lead-to-register="1"><?php esc_html_e( 'Criar conta para acompanhar', 'houzez'); ?></button>
                <?php endif; ?>
                <button type="button" class="ipc-wz-btn ipc-wz-btn--ghost ipc-wz-btn--block" data-bs-dismiss="modal"><?php esc_html_e( 'Fechar', 'houzez'); ?></button>
            </div>
        </div>
    </div>
</div>

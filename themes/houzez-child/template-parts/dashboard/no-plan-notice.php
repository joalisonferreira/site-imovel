<?php
/**
 * Aviso exibido após redirect por falta de plano ativo (?ipc_no_plan=1).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $_GET['ipc_no_plan'] ) ) {
    return;
}

$plans_url = function_exists( 'houzez_child_ipc_page_url' )
    ? houzez_child_ipc_page_url( 'pacotes' )
    : home_url( '/pacotes/' );
?>
<div class="ipc-profile-nudge" data-ipc-no-plan="1" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;" role="alert">
    <div>
        <strong><?php esc_html_e( 'Plano necessário', 'houzez' ); ?></strong>
        <span><?php esc_html_e( 'Você precisa de um plano ativo para criar anúncios.', 'houzez' ); ?></span>
    </div>
    <a class="ipc-profile-nudge__btn" href="<?php echo esc_url( $plans_url ); ?>"><?php esc_html_e( 'Ver planos', 'houzez' ); ?></a>
</div>

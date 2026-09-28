<?php
/**
 * Banner "complete seu cadastro" (perfil progressivo).
 *
 * Exibido no dashboard quando faltam dados adiados no cadastro mínimo
 * (WhatsApp e, para corretor/imobiliária, CPF/CNPJ). Autossuficiente:
 * não imprime nada quando o perfil está completo, o usuário é admin
 * ou o aviso foi dispensado nos últimos 30 dias.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nudge = function_exists( 'houzez_child_profile_nudge_data' ) ? houzez_child_profile_nudge_data() : false;
if ( ! $nudge ) {
    return;
}
?>
<div class="ipc-profile-nudge" data-ipc-profile-nudge="1">
    <div>
        <strong><?php esc_html_e( 'Complete seu cadastro', 'houzez' ); ?></strong>
        <span><?php echo esc_html( $nudge['message'] ); ?></span>
    </div>
    <a class="ipc-profile-nudge__btn" href="<?php echo esc_url( $nudge['profile_url'] ); ?>"><?php esc_html_e( 'Completar agora', 'houzez' ); ?></a>
    <button type="button" class="ipc-profile-nudge__dismiss" aria-label="<?php esc_attr_e( 'Dispensar aviso', 'houzez' ); ?>" data-ipc-nudge-dismiss="1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ipc_profile_nudge' ) ); ?>">&times;</button>
</div>
<script>
(function () {
    var nudge = document.querySelector('[data-ipc-profile-nudge]');
    if (!nudge) {
        return;
    }
    var btn = nudge.querySelector('[data-ipc-nudge-dismiss]');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', function () {
        nudge.style.display = 'none';
        var body = 'action=ipc_dismiss_profile_nudge&nonce=' + encodeURIComponent(btn.getAttribute('data-nonce'));
        if (window.fetch && window.ipcAuth && window.ipcAuth.ajaxurl) {
            window.fetch(window.ipcAuth.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body,
                credentials: 'same-origin'
            });
        }
    });
})();
</script>

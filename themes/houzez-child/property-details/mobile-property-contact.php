<?php
$agent_display = houzez_get_listing_data('agent_display_option');

if ($agent_display != 'none') {

$agent_array = houzez20_get_property_agent();
$agent_array = $agent_array['agent_info'][0] ?? '';

$agent_name = isset($agent_array['agent_name']) ? $agent_array['agent_name'] : '';
$agent_mobile_call = isset($agent_array['agent_mobile_call']) ? $agent_array['agent_mobile_call'] : '';
$agent_whatsapp_call = isset($agent_array['agent_whatsapp_call']) ? $agent_array['agent_whatsapp_call'] : '';
$agent_number_call = isset($agent_array['agent_mobile_call']) ? $agent_array['agent_mobile_call'] : '';
$agent_picture = $agent_array['picture'] ?? '';
if ( empty($agent_number_call) ) {
    $agent_number_call = isset($agent_array['agent_phone_call']) ? $agent_array['agent_phone_call'] : '';
}

$show_partnership_btn = false;
if ( is_user_logged_in() && is_singular('property') ) {
    $property_id = (int) get_queried_object_id();
    if ( ! $property_id ) {
        $property_id = (int) get_the_ID();
    }

    if ( $property_id > 0 ) {
        $viewer_id = get_current_user_id();
        // Cliente (houzez_buyer ou qualquer não-corretor) nunca vê botão de parceria
        $is_client = false;
        if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $viewer_id ) ) {
            $is_client = true;
        } else {
            $u = get_userdata( $viewer_id );
            if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'administrator' ), (array) $u->roles ) ) {
                // houzez_owner e papéis sem cap comercial são tratados como cliente para parceria
                if ( ! current_user_can( 'imovel_parceiro_manage_commercial' ) ) {
                    $is_client = true;
                }
            }
        }
        if ( ! $is_client ) {
            $owner_id  = class_exists( 'Imovel_Parceiro_Partnerships' ) ? (int) Imovel_Parceiro_Partnerships::property_owner_id( $property_id ) : 0;
            $broker_id = class_exists( 'Imovel_Parceiro_Contact_Widget' ) ? (int) Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id ) : 0;

            // The owner (and the responsible broker) cannot request a partnership for themselves.
            $show_partnership_btn = ! ( ( $owner_id && $owner_id === $viewer_id ) || ( $broker_id && $broker_id === $viewer_id ) );
        }
    }
}

// Contact buttons (message/call) only for logged-in corretor/imobiliária
// with an active plan and a verified Houzez profile.
$ipc_can_see_contact = class_exists( 'Imovel_Parceiro_Contact_Visibility' ) && Imovel_Parceiro_Contact_Visibility::viewer_can_see_contact();

// Cliente e proprietário bloqueado: veem só formulários (modal do envelope).
// WhatsApp direto e ligação ficam ocultos, como nas demais superfícies.
$ipc_hide_direct = false;
if ( class_exists( 'Imovel_Parceiro_Contact_Visibility' ) ) {
    if ( Imovel_Parceiro_Contact_Visibility::is_client() ) {
        $ipc_hide_direct = true;
    } elseif ( is_user_logged_in() && is_singular( 'property' ) ) {
        $ipc_pid = (int) get_queried_object_id();
        if ( ! $ipc_pid ) {
            $ipc_pid = (int) get_the_ID();
        }
        if ( $ipc_pid > 0 && Imovel_Parceiro_Contact_Visibility::viewer_is_owner_blocked( get_current_user_id(), $ipc_pid ) ) {
            $ipc_hide_direct = true;
        }
    }
    // Padroniza WhatsApp para wa.me com DDI 55 (evita link quebrado).
    if ( ! empty( $agent_whatsapp_call ) ) {
        $agent_whatsapp_call = Imovel_Parceiro_Contact_Visibility::normalize_whatsapp_number( $agent_whatsapp_call );
    }
}
?>
<div class="mobile-property-contact w-100 d-block d-lg-none" role="complementary">
    <div class="d-flex justify-content-between">
        <div class="agent-details flex-grow-1">
            <div class="d-flex align-items-center gap-3">
                <div class="agent-image">
                <img class="rounded" src="<?php echo esc_url($agent_picture); ?>" width="50" height="50" alt="<?php echo esc_attr($agent_name); ?>">
                </div>
                <ul class="agent-information list-unstyled m-0" role="list">
                    <li class="agent-name" role="listitem">
                        <?php echo esc_attr($agent_name); ?>
                    </li>
                </ul>
            </div><!-- d-flex -->
        </div><!-- agent-details -->
        <?php if ( $ipc_can_see_contact ) : ?>
        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#mobile-property-form" aria-label="<?php esc_attr_e('Enviar mensagem para a equipe', 'imovel-parceiro-core'); ?>" style="min-width:44px;min-height:44px;">
            <i class="houzez-icon icon-envelope" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
        <?php if ( $ipc_hide_direct && $ipc_can_see_contact ) : ?>
        <span class="ipc-mobile-hint text-muted small ms-1"><?php esc_html_e('Fale com nossa equipe pelo formulário', 'imovel-parceiro-core'); ?></span>
        <?php endif; ?>
        <?php if ( $ipc_can_see_contact && ! $ipc_hide_direct && !empty( $agent_whatsapp_call ) && houzez_option('agent_whatsapp_num', 1) ) { ?>
        <a href="https://wa.me/<?php echo esc_attr( $agent_whatsapp_call ); ?>?text=<?php echo rawurlencode( houzez_option('spl_con_interested', 'Olá, tenho interesse em').' ['.get_the_title().'] '.get_permalink() ); ?>" class="btn btn-secondary-outlined" aria-label="<?php esc_attr_e('Conversar no WhatsApp', 'imovel-parceiro-core'); ?>" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">
            <i class="houzez-icon icon-messaging-whatsapp" aria-hidden="true"></i>
        </a>
        <?php } ?>
        <?php if ( $ipc_can_see_contact && ! $ipc_hide_direct && ! empty($agent_number_call) && houzez_option('agent_mobile_num', 1) ) { ?>
        <a href="tel:<?php echo esc_attr($agent_number_call); ?>" class="btn btn-secondary-outlined" aria-label="<?php esc_attr_e('Ligar para o anunciante', 'imovel-parceiro-core'); ?>" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">
            <i class="houzez-icon icon-phone" aria-hidden="true"></i>
        </a>
        <?php } ?>
        <?php if ( $show_partnership_btn ) { ?>
        <button type="button" class="btn btn-secondary-outlined imovel-parceiro-request-partnership" data-bs-toggle="modal" data-bs-target="#imovel-parceiro-partnership-modal" aria-label="<?php esc_attr_e('Solicitar parceria', 'imovel-parceiro-core'); ?>">
            <i class="houzez-icon icon-business-contract-handshake-sign" aria-hidden="true"></i>
        </button>
        <?php } ?>
    </div><!-- d-flex -->
</div><!-- mobile-property-contact -->

<div class="modal fade mobile-property-form" id="mobile-property-form" role="dialog" aria-labelledby="mobilePropertyFormTitle" aria-modal="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mobilePropertyFormTitle"><?php esc_html_e('Falar com a equipe', 'imovel-parceiro-core'); ?></h5>
                <button type="button" class="btn-close btn-danger" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Fechar', 'imovel-parceiro-core'); ?>" style="min-width:44px;min-height:44px;"></button>
            </div><!-- modal-header -->
            <div class="modal-body">
                <?php get_template_part('property-details/agent-form'); ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>

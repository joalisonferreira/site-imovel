<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Security {
    private $protected_ajax_actions = array(
        'imovel_parceiro_accept_terms',
        'imovel_parceiro_owner_upload_document',
        'imovel_parceiro_owner_request_broker_change',
        'imovel_parceiro_owner_process_broker_change',
        'imovel_parceiro_owner_review_document',
        'imovel_parceiro_owner_request_property_delete',
        'imovel_parceiro_owner_process_property_delete',
        'imovel_parceiro_owner_download_document',
        'imovel_parceiro_owner_document_preview',
        'imovel_parceiro_owner_stream_document',
        'imovel_parceiro_owner_property_context',
        'imovel_parceiro_request_partnership',
        'imovel_parceiro_handle_partnership',
        'imovel_parceiro_cancel_partnership',
        'imovel_parceiro_transition_partnership',
        'imovel_parceiro_create_opportunity',
        'imovel_parceiro_update_opportunity',
        'imovel_parceiro_close_deal',
        'imovel_parceiro_calculate_commission',
        'houzez_direct_pay_package',
        'houzez_direct_pay_per_listing',
        'houzez_wire_transfer_per_listing',
        'houzez_stripe_package_payment',
        'houzez_paypal_package_payment',
        'houzez_recuring_paypal_package_payment',
        'houzez_recuring_paypal_package_payment_deprecated',
        'houzez_free_membership_package',
        'houzez_mollie_package_payment',
        'houzez_make_prop_featured',
        'houzez_remove_prop_featured',
        'houzez_property_on_hold_package',
        'houzez_delete_property',
        'houzez_delete_properties',
    );

    private $owner_blocked_ajax_actions = array(
        'imovel_parceiro_request_partnership',
        'imovel_parceiro_create_opportunity',
        'imovel_parceiro_update_opportunity',
        'imovel_parceiro_close_deal',
        'imovel_parceiro_calculate_commission',
        'houzez_direct_pay_package',
        'houzez_direct_pay_per_listing',
        'houzez_wire_transfer_per_listing',
        'houzez_stripe_package_payment',
        'houzez_paypal_package_payment',
        'houzez_recuring_paypal_package_payment',
        'houzez_recuring_paypal_package_payment_deprecated',
        'houzez_free_membership_package',
        'houzez_mollie_package_payment',
        'houzez_make_prop_featured',
        'houzez_remove_prop_featured',
        'houzez_property_on_hold_package',
        'houzez_resend_for_approval',
        'houzez_resend_for_approval_perlisting',
        'houzez_delete_property',
        'houzez_delete_properties',
    );

    private $capability_map = array(
        'imovel_parceiro_owner_upload_document' => 'imovel_parceiro_owner_manage_docs',
        'imovel_parceiro_owner_request_broker_change' => 'imovel_parceiro_owner_request_broker_change',
        'imovel_parceiro_owner_request_property_delete' => 'imovel_parceiro_owner_request_deletion',
        'imovel_parceiro_owner_property_context' => 'imovel_parceiro_owner_manage_docs',
        'imovel_parceiro_owner_process_broker_change' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_owner_review_document' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_owner_process_property_delete' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_owner_download_document' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_owner_document_preview' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_owner_stream_document' => 'imovel_parceiro_manage_owner_workflow',
        'imovel_parceiro_request_partnership' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_handle_partnership' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_cancel_partnership' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_transition_partnership' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_create_opportunity' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_update_opportunity' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_close_deal' => 'imovel_parceiro_manage_commercial',
        'imovel_parceiro_calculate_commission' => 'imovel_parceiro_manage_commercial',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'protect_endpoints' ) );
    }

    public function protect_endpoints() {
        if ( ! wp_doing_ajax() ) {
            return;
        }

        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

        if ( empty( $action ) ) {
            return;
        }

        if ( ! is_user_logged_in() && in_array( $action, $this->protected_ajax_actions, true ) ) {
            wp_die( esc_html__( 'Acesso restrito.', 'imovel-parceiro-core' ) );
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( $this->is_proprietario_user( get_current_user_id() ) && in_array( $action, $this->owner_blocked_ajax_actions, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Seu perfil de proprietario nao possui acesso a esta funcionalidade comercial.', 'imovel-parceiro-core' ) ) );
        }

        if ( isset( $this->capability_map[ $action ] ) && ! current_user_can( $this->capability_map[ $action ] ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissao insuficiente para executar esta acao.', 'imovel-parceiro-core' ) ) );
        }
    }

    private function is_proprietario_user( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return false;
        }

        return in_array( 'houzez_owner', (array) $user->roles, true );
    }
}

new Imovel_Parceiro_Security();

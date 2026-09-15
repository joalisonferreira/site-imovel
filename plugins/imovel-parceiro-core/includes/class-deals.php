<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Deals {
    public function __construct() {
        add_action( 'wp_ajax_imovel_parceiro_close_deal', array( $this, 'close_deal' ) );
    }

    public function close_deal() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $opportunity_id = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
        $user_id = get_current_user_id();
        if ( ! $opportunity_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        global $wpdb;
        $opportunities_table = $wpdb->prefix . 'imovel_parceiro_opportunities';
        $deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
        $opportunity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$opportunities_table} WHERE id = %d", $opportunity_id ) );
        if ( ! $opportunity ) {
            wp_send_json_error( array( 'message' => __( 'Oportunidade não encontrada.', 'imovel-parceiro-core' ) ) );
        }
        $wpdb->insert(
            $deals_table,
            array(
                'opportunity_id' => $opportunity_id,
                'partnership_id' => $opportunity->partnership_id,
                'property_id' => $opportunity->property_id,
                'lead_id' => $opportunity->lead_id,
                'owner_id' => $opportunity->owner_id,
                'partner_id' => $opportunity->partner_id,
                'status' => 'won',
                'origin' => $opportunity->origin,
                'closed_at' => current_time( 'mysql' ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        wp_send_json_success( array( 'message' => __( 'Negócio fechado.', 'imovel-parceiro-core' ) ) );
    }
}

new Imovel_Parceiro_Deals();

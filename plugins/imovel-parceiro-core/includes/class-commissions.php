<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Commissions {
    public function __construct() {
        add_action( 'wp_ajax_imovel_parceiro_calculate_commission', array( $this, 'calculate_commission' ) );
    }

    public function calculate_commission() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $deal_id = isset( $_POST['deal_id'] ) ? absint( $_POST['deal_id'] ) : 0;
        if ( ! $deal_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        global $wpdb;
        $deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
        $commissions_table = $wpdb->prefix . 'imovel_parceiro_commissions';
        $deal = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$deals_table} WHERE id = %d", $deal_id ) );
        if ( ! $deal ) {
            wp_send_json_error( array( 'message' => __( 'Negócio não encontrado.', 'imovel-parceiro-core' ) ) );
        }
        $amount = (float) $deal->total_commission;
        $owner_amount = $amount * 0.5;
        $partner_amount = $amount * 0.5;
        $wpdb->insert(
            $commissions_table,
            array(
                'deal_id' => $deal_id,
                'partnership_id' => $deal->partnership_id,
                'beneficiary_id' => $deal->owner_id,
                'beneficiary_role' => 'owner',
                'percentage' => 50.00,
                'amount' => $owner_amount,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s' )
        );
        $wpdb->insert(
            $commissions_table,
            array(
                'deal_id' => $deal_id,
                'partnership_id' => $deal->partnership_id,
                'beneficiary_id' => $deal->partner_id,
                'beneficiary_role' => 'partner',
                'percentage' => 50.00,
                'amount' => $partner_amount,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s' )
        );
        wp_send_json_success( array( 'message' => __( 'Comissões geradas.', 'imovel-parceiro-core' ) ) );
    }
}

new Imovel_Parceiro_Commissions();

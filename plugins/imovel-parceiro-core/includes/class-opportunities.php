<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Opportunities {
    public function __construct() {
        add_action( 'wp_ajax_imovel_parceiro_create_opportunity', array( $this, 'create_opportunity' ) );
        add_action( 'wp_ajax_imovel_parceiro_update_opportunity', array( $this, 'update_opportunity' ) );
        add_filter( 'houzez_record_activities', array( $this, 'record_activity' ), 10, 1 );
    }

    public function create_opportunity() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $user_id = get_current_user_id();
        if ( ! $property_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_opportunities';
        $wpdb->insert(
            $table,
            array(
                'property_id' => $property_id,
                'owner_id' => $user_id,
                'partner_id' => 0,
                'funnel_stage' => 'new_lead',
                'origin' => 'direct',
                'status' => 'open',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        wp_send_json_success( array( 'message' => __( 'Oportunidade criada.', 'imovel-parceiro-core' ) ) );
    }

    public function update_opportunity() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $id = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
        $stage = isset( $_POST['stage'] ) ? sanitize_text_field( wp_unslash( $_POST['stage'] ) ) : 'new_lead';
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_opportunities';
        $wpdb->update(
            $table,
            array( 'funnel_stage' => $stage, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        wp_send_json_success( array( 'message' => __( 'Oportunidade atualizada.', 'imovel-parceiro-core' ) ) );
    }

    public function record_activity( $activity_meta ) {
        if ( ! is_array( $activity_meta ) ) {
            $activity_meta = array();
        }
        $activity_meta['plugin'] = 'imovel-parceiro-core';
        return $activity_meta;
    }
}

new Imovel_Parceiro_Opportunities();

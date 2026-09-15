<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Acceptances {
    const OPTION_TERMS_VERSION = 'imovel_parceiro_terms_version';

    private static $accepted_payload = array();

    private $required_keys = array( 'authorization', 'partnership', 'commission', 'terms' );

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_ensure_table' ) );
        add_action( 'wp_ajax_imovel_parceiro_accept_terms', array( $this, 'save_acceptance' ) );
        add_filter( 'houzez_before_submit_property', array( $this, 'validate_before_submit' ) );
        add_filter( 'houzez_before_update_property', array( $this, 'validate_before_submit' ) );
        add_action( 'houzez_after_property_submit', array( $this, 'record_acceptance' ), 10, 1 );
        add_action( 'houzez_after_property_update', array( $this, 'record_acceptance' ), 10, 1 );
    }

    public function maybe_ensure_table() {
        if ( get_option( 'imovel_parceiro_acceptances_db_version' ) === '1.0.0' ) {
            return;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_acceptances';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists === $table ) {
            update_option( 'imovel_parceiro_acceptances_db_version', '1.0.0', false );
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            property_id bigint(20) unsigned NOT NULL,
            terms_version varchar(50) NOT NULL,
            accepted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'accepted',
            acceptance_data longtext DEFAULT '',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY property_id (property_id),
            KEY terms_version (terms_version)
        ) {$charset_collate};";
        dbDelta( $sql );
        update_option( 'imovel_parceiro_acceptances_db_version', '1.0.0', false );
    }

    private function get_terms_version() {
        return (string) get_option( self::OPTION_TERMS_VERSION, '1.0' );
    }

    private function get_request_property_id() {
        if ( isset( $_POST['property_id'] ) ) {
            return absint( $_POST['property_id'] );
        }

        if ( isset( $_POST['prop_id'] ) ) {
            return absint( $_POST['prop_id'] );
        }

        if ( isset( $_POST['draft_property_id'] ) ) {
            return absint( $_POST['draft_property_id'] );
        }

        return 0;
    }

    private function get_sanitized_acceptance_payload() {
        $raw = isset( $_POST['imovel_parceiro_acceptance'] ) ? (array) wp_unslash( $_POST['imovel_parceiro_acceptance'] ) : array();
        $accepted = array();

        foreach ( $this->required_keys as $key ) {
            $accepted[ $key ] = ! empty( $raw[ $key ] );
        }

        return $accepted;
    }

    private function has_all_required_acceptances( $acceptance_data ) {
        foreach ( $this->required_keys as $key ) {
            if ( empty( $acceptance_data[ $key ] ) ) {
                return false;
            }
        }

        return true;
    }

    public function validate_before_submit( $property ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( esc_html__( 'Voce precisa estar autenticado para publicar ou editar este imovel.', 'imovel-parceiro-core' ) );
        }

        $property_id = $this->get_request_property_id();
        $acceptance_data = $this->get_sanitized_acceptance_payload();

        if ( $this->has_all_required_acceptances( $acceptance_data ) ) {
            self::$accepted_payload[ $user_id ] = $acceptance_data;
            return $property;
        }

        if ( $property_id && $this->has_accepted_terms( $user_id, $property_id ) ) {
            return $property;
        }

        wp_die( esc_html__( 'E obrigatorio aceitar todos os termos para publicar ou editar este imovel.', 'imovel-parceiro-core' ) );

        return $property;
    }

    public function save_acceptance() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $user_id = get_current_user_id();
        if ( ! $property_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }

        $raw_acceptance = isset( $_POST['acceptance'] ) ? (array) wp_unslash( $_POST['acceptance'] ) : array();
        $acceptance_data = array();
        foreach ( $this->required_keys as $key ) {
            $acceptance_data[ $key ] = ! empty( $raw_acceptance[ $key ] );
        }

        if ( ! $this->has_all_required_acceptances( $acceptance_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Todos os aceites sao obrigatorios.', 'imovel-parceiro-core' ) ) );
        }

        $this->store_acceptance( $user_id, $property_id, $acceptance_data );
        wp_send_json_success( array( 'message' => __( 'Aceites registrados.', 'imovel-parceiro-core' ) ) );
    }

    public function record_acceptance( $property_id, $user_id = 0 ) {
        if ( ! $property_id ) {
            return;
        }
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return;
        }

        if ( isset( self::$accepted_payload[ $user_id ] ) && $this->has_all_required_acceptances( self::$accepted_payload[ $user_id ] ) ) {
            $this->store_acceptance( $user_id, $property_id, self::$accepted_payload[ $user_id ] );
            return;
        }

        if ( $this->has_accepted_terms( $user_id, $property_id ) ) {
            return;
        }

        $acceptance_data = $this->get_sanitized_acceptance_payload();
        if ( $this->has_all_required_acceptances( $acceptance_data ) ) {
            $this->store_acceptance( $user_id, $property_id, $acceptance_data );
        }
    }

    public function store_acceptance( $user_id, $property_id, $acceptance_data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_acceptances';
        $accepted = array(
            'authorization' => ! empty( $acceptance_data['authorization'] ),
            'partnership' => ! empty( $acceptance_data['partnership'] ),
            'commission' => ! empty( $acceptance_data['commission'] ),
            'terms' => ! empty( $acceptance_data['terms'] ),
        );
        $wpdb->insert(
            $table,
            array(
                'user_id' => absint( $user_id ),
                'property_id' => absint( $property_id ),
                'terms_version' => $this->get_terms_version(),
                'accepted_at' => current_time( 'mysql' ),
                'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'status' => 'accepted',
                'acceptance_data' => wp_json_encode( $accepted ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public function has_accepted_terms( $user_id, $property_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_acceptances';
        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND property_id = %d AND terms_version = %s AND status = 'accepted' ORDER BY id DESC LIMIT 1",
            absint( $user_id ),
            absint( $property_id ),
            $this->get_terms_version()
        );
        return (bool) $wpdb->get_var( $sql );
    }
}

new Imovel_Parceiro_Acceptances();

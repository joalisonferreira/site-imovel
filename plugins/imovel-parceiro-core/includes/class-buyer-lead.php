<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lead express do comprador ("Diga o que procura e onde").
 *
 * Salva na tabela NATIVA do Houzez CRM (houzez_crm_leads) — nenhum schema
 * novo, nenhuma migration. Notifica o admin por e-mail (FluentSMTP).
 */
class Imovel_Parceiro_Buyer_Lead {

    const NONCE_ACTION = 'ipc_buyer_lead';
    const SOURCE       = 'Lead express - menu Quero comprar';

    /**
     * Dono do lead no CRM (recebe no CRM > Leads e o e-mail de aviso).
     */
    const RECIPIENT_EMAIL = 'contato@imovelparceiro.com.br';

    /**
     * Papéis considerados "comprador": o lead gerado por eles vai para o
     * RECIPIENT_EMAIL em vez da própria conta.
     */
    const BUYER_ROLES = array( 'houzez_buyer', 'subscriber' );

    public function __construct() {
        add_action( 'wp_ajax_ipc_save_buyer_lead', array( $this, 'save_lead' ) );
        add_action( 'wp_ajax_nopriv_ipc_save_buyer_lead', array( $this, 'save_lead' ) );
    }

    public static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'houzez_crm_leads';
    }

    public static function table_exists() {
        global $wpdb;

        $table = self::table_name();

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    public function save_lead() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! self::table_exists() ) {
            wp_send_json_error( array( 'message' => __( 'Captura de leads indisponível no momento. Tente novamente mais tarde.', 'imovel-parceiro-core' ) ) );
        }

        $goal = isset( $_POST['goal'] ) ? sanitize_key( wp_unslash( $_POST['goal'] ) ) : '';
        if ( ! in_array( $goal, array( 'comprar', 'alugar' ), true ) ) {
            $goal = 'comprar';
        }

        $types_raw = isset( $_POST['types'] ) ? sanitize_text_field( wp_unslash( $_POST['types'] ) ) : '';
        $types     = array_values(
            array_filter(
                array_map( 'trim', explode( ',', $types_raw ) ),
                function ( $type ) {
                    return '' !== $type && mb_strlen( $type ) <= 60;
                }
            )
        );
        $types = array_slice( $types, 0, 8 );

        $where = isset( $_POST['where'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['where'] ) ) ) : '';
        if ( mb_strlen( $where ) < 2 ) {
            wp_send_json_error( array( 'message' => __( 'Informe a cidade ou bairro de interesse.', 'imovel-parceiro-core' ) ) );
        }

        $name = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
        if ( mb_strlen( $name ) < 3 ) {
            wp_send_json_error( array( 'message' => __( 'Informe seu nome.', 'imovel-parceiro-core' ) ) );
        }

        $phone_digits = isset( $_POST['phone'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
        if ( strlen( $phone_digits ) < 10 ) {
            wp_send_json_error( array( 'message' => __( 'Informe um WhatsApp válido com DDD.', 'imovel-parceiro-core' ) ) );
        }

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if ( '' !== $email && ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe um e-mail válido ou deixe em branco.', 'imovel-parceiro-core' ) ) );
        }

        if ( empty( $_POST['consent'] ) ) {
            wp_send_json_error( array( 'message' => __( 'É preciso autorizar o contato para continuar.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;

        $parts      = preg_split( '/\s+/', $name );
        $first_name = array_shift( $parts );
        $last_name  = implode( ' ', (array) $parts );

        $goal_label = 'alugar' === $goal ? __( 'Alugar', 'imovel-parceiro-core' ) : __( 'Comprar', 'imovel-parceiro-core' );
        $message    = sprintf(
            __( 'Objetivo: %1$s | Tipos: %2$s | Região: %3$s | Origem: %4$s', 'imovel-parceiro-core' ),
            $goal_label,
            $types ? implode( ', ', $types ) : __( 'Qualquer', 'imovel-parceiro-core' ),
            mb_substr( $where, 0, 120 ),
            self::SOURCE
        );

        $user_id = $this->resolve_recipient_id();

        $table = self::table_name();

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT lead_id FROM {$table} WHERE mobile = %s LIMIT 1", $phone_digits )
        );

        $consent_note = sprintf(
            __( 'Aceitou contato via WhatsApp em %s (origem: %s).', 'imovel-parceiro-core' ),
            current_time( 'd/m/Y H:i' ),
            self::SOURCE
        );

        if ( $existing_id ) {
            $wpdb->update(
                $table,
                array(
                    'display_name' => $name,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'email'        => $email,
                    'city'         => mb_substr( $where, 0, 120 ),
                    'message'      => $message,
                    'private_note' => $consent_note,
                    'time'         => gmdate( 'Y-m-d H:i:s' ),
                ),
                array( 'lead_id' => $existing_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            $lead_id = $existing_id;
        } else {
            $type_label = function_exists( 'houzez_crm_get_form_user_type' ) ? houzez_crm_get_form_user_type( 'buyer' ) : 'buyer';
            $wpdb->insert(
                $table,
                array(
                    'user_id'      => $user_id,
                    'prefix'       => '',
                    'display_name' => $name,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'email'        => $email,
                    'mobile'       => $phone_digits,
                    'home_phone'   => '',
                    'work_phone'   => '',
                    'address'      => '',
                    'city'         => mb_substr( $where, 0, 120 ),
                    'state'        => '',
                    'country'      => '',
                    'zipcode'      => '',
                    'type'         => $type_label,
                    'status'       => '',
                    'source'       => self::SOURCE,
                    'source_link'  => esc_url_raw( home_url( '/' ) ),
                    'enquiry_to'   => 0,
                    'enquiry_user_type' => '',
                    'twitter_url'  => '',
                    'linkedin_url' => '',
                    'facebook_url' => '',
                    'private_note' => $consent_note,
                    'message'      => $message,
                    'time'         => gmdate( 'Y-m-d H:i:s' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );
            $lead_id = (int) $wpdb->insert_id;
        }

        if ( ! $lead_id ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível salvar. Tente novamente.', 'imovel-parceiro-core' ) ) );
        }

        $this->notify_admin( $lead_id, $name, $phone_digits, $email, $message );

        wp_send_json_success(
            array(
                'message' => __( 'Recebido! Um especialista vai te chamar no WhatsApp.', 'imovel-parceiro-core' ),
                'lead_id' => $lead_id,
            )
        );
    }

    /**
     * Define o dono do lead: contato@ para visitantes e compradores;
     * demais logados mantêm na própria conta. Fallback: admin.
     *
     * @return int
     */
    private function resolve_recipient_id() {
        $recipient = get_user_by( 'email', self::RECIPIENT_EMAIL );
        $recipient_id = $recipient ? (int) $recipient->ID : 0;

        if ( is_user_logged_in() ) {
            $me = wp_get_current_user();
            if ( array_intersect( self::BUYER_ROLES, (array) $me->roles ) ) {
                return $recipient_id ? $recipient_id : (int) $me->ID;
            }

            return (int) $me->ID;
        }

        if ( $recipient_id ) {
            return $recipient_id;
        }

        $admin = get_user_by( 'email', get_option( 'admin_email' ) );

        return $admin ? (int) $admin->ID : 0;
    }

    private function notify_admin( $lead_id, $name, $phone, $email, $message ) {
        $to = self::RECIPIENT_EMAIL;
        if ( ! get_user_by( 'email', $to ) ) {
            $to = get_option( 'admin_email' );
        }
        if ( ! $to || ! is_email( $to ) ) {
            return;
        }

        $subject = sprintf( __( '[Lead express] %s quer %s', 'imovel-parceiro-core' ), $name, $message );
        $body    = sprintf(
            __( "Novo interesse de comprador (lead #%d).\n\nNome: %s\nWhatsApp: %s\nE-mail: %s\n\n%s", 'imovel-parceiro-core' ),
            $lead_id,
            $name,
            $phone,
            '' !== $email ? $email : __( 'não informado', 'imovel-parceiro-core' ),
            $message
        );

        wp_mail( $to, $subject, $body );
    }
}

new Imovel_Parceiro_Buyer_Lead();

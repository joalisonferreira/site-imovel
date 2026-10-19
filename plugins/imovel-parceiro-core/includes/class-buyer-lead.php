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
                    // Reatribui o dono: sem isso, um telefone já cadastrado
                    // mantém o user_id antigo e o lead NÃO aparece na página
                    // de Leads do contato@ (que filtra por user_id).
                    'user_id'      => $user_id,
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
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
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

        $this->notify_admin( $lead_id, $name, $phone_digits, $email, $goal_label, $types, $where );

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

    private function notify_admin( $lead_id, $name, $phone, $email, $goal_label, $types, $where ) {
        $to = self::RECIPIENT_EMAIL;
        if ( ! get_user_by( 'email', $to ) ) {
            $to = get_option( 'admin_email' );
        }
        if ( ! $to || ! is_email( $to ) ) {
            return;
        }

        $where_short = mb_substr( trim( (string) $where ), 0, 60 );
        $subject     = sprintf(
            __( '[Lead express] %1$s quer %2$s em %3$s', 'imovel-parceiro-core' ),
            $name,
            function_exists( 'mb_strtolower' ) ? mb_strtolower( $goal_label ) : strtolower( $goal_label ),
            $where_short
        );

        // Normaliza para wa.me: só dígitos + DDI 55 quando for número BR sem país.
        $digits = preg_replace( '/\D/', '', (string) $phone );
        if ( preg_match( '/^[1-9]{2}[89]?\d{8}$/', $digits ) ) {
            $digits = '55' . $digits;
        }
        $wa_url = 'https://wa.me/' . $digits;

        $first_name = trim( (string) strtok( $name, ' ' ) );
        if ( '' === $first_name ) {
            $first_name = $name;
        }
        $brand      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $goal_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $goal_label ) : strtolower( $goal_label );
        $wa_text    = sprintf(
            __( 'Olá %1$s! Aqui é da %2$s. Recebemos seu interesse em %3$s em %4$s. Podemos conversar?', 'imovel-parceiro-core' ),
            $first_name,
            $brand,
            $goal_lower,
            $where_short
        );
        $wa_url .= '?text=' . rawurlencode( $wa_text );

        // Exibição amigável: (11) 99999-9999.
        $phone_display = $phone;
        $local         = preg_replace( '/^55/', '', $digits );
        if ( 11 === strlen( $local ) ) {
            $phone_display = sprintf( '(%s) %s-%s', substr( $local, 0, 2 ), substr( $local, 2, 5 ), substr( $local, 7 ) );
        } elseif ( 10 === strlen( $local ) ) {
            $phone_display = sprintf( '(%s) %s-%s', substr( $local, 0, 2 ), substr( $local, 2, 4 ), substr( $local, 6 ) );
        }

        $types_label = $types ? implode( ', ', $types ) : __( 'Qualquer', 'imovel-parceiro-core' );
        $email_label = '' !== $email ? $email : __( 'não informado', 'imovel-parceiro-core' );

        $link_color = class_exists( 'Imovel_Parceiro_Email_Template' ) ? Imovel_Parceiro_Email_Template::accent() : '#d72218';

        $content  = '<p>' . sprintf( esc_html__( 'Novo interesse de comprador (lead #%d).', 'imovel-parceiro-core' ), $lead_id ) . '</p>';
        $content .= '<p>'
            . '<strong>' . esc_html__( 'Nome:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( $name ) . '<br>'
            . '<strong>' . esc_html__( 'WhatsApp:', 'imovel-parceiro-core' ) . '</strong> '
            . '<a href="' . esc_url( $wa_url ) . '" target="_blank" style="color:' . esc_attr( $link_color ) . ';text-decoration:none;font-weight:600;">' . esc_html( $phone_display ) . '</a><br>'
            . '<strong>' . esc_html__( 'E-mail:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( $email_label ) . '</p>';
        $content .= '<p>'
            . '<strong>' . esc_html__( 'Objetivo:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( $goal_label ) . '<br>'
            . '<strong>' . esc_html__( 'Tipos:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( $types_label ) . '<br>'
            . '<strong>' . esc_html__( 'Região:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( $where_short ) . '<br>'
            . '<strong>' . esc_html__( 'Origem:', 'imovel-parceiro-core' ) . '</strong> ' . esc_html( self::SOURCE ) . '</p>';

        if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
            Imovel_Parceiro_Email_Template::send(
                $to,
                $subject,
                $content,
                array(
                    'title'     => sprintf( __( 'Novo lead express: %s', 'imovel-parceiro-core' ), $name ),
                    'preheader' => sprintf( __( 'Lead #%1$d — %2$s quer %3$s em %4$s', 'imovel-parceiro-core' ), $lead_id, $name, $goal_lower, $where_short ),
                    'cta_url'   => $wa_url,
                    'cta_text'  => __( 'Conversar no WhatsApp', 'imovel-parceiro-core' ),
                )
            );
        } else {
            wp_mail( $to, $subject, wp_strip_all_tags( str_replace( '<br>', "\n", $content ) ) );
        }
    }
}

new Imovel_Parceiro_Buyer_Lead();

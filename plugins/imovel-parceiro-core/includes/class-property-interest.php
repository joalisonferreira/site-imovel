<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imovel_Parceiro_Property_Interest
 *
 * Proteção de lead comprador (cliente x corretor):
 *
 * O cliente NÃO recebe o WhatsApp direto do corretor responsável. Em vez
 * disso, clica em "Tenho interesse neste imóvel", o que registra a lead no
 * **CRM nativo do Houzez** (tabelas houzez_crm_leads + houzez_crm_enquiries,
 * exatamente como as leads dos formulários nativos) com cliente, imóvel,
 * corretor responsável, data/hora e IP — prova de origem para a comissão da
 * plataforma — e notifica o corretor (painel + e-mail) com os dados do
 * cliente para que ELE faça o primeiro contato (intermediação).
 *
 * Reaproveita: CRM Houzez, notificações in-app de IPC_Notifications e o
 * widget flutuante de contato.
 */
class Imovel_Parceiro_Property_Interest {

    const AJAX_ACTION = 'imovel_parceiro_property_interest';

    /**
     * Marcador dentro de enquiry_meta identificando a origem do fluxo.
     */
    const KIND_INTEREST = 'property_interest';

    /**
     * Papéis que NUNCA operam como cliente interessado.
     */
    const NON_CLIENT_ROLES = array( 'houzez_agent', 'houzez_agency', 'houzez_owner', 'administrator' );

    public function __construct() {
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_submit' ) );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    /* ---------------------------------------------------------------------
     * Qualificação
     * ------------------------------------------------------------------ */

    /**
     * Se o usuário navega como cliente comprador (qualquer papel fora de
     * corretor/imobiliária/proprietário/admin).
     */
    public static function is_client( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) ) {
            return (bool) Imovel_Parceiro_First_Login_Redirect::is_client( $user_id );
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        return empty( array_intersect( self::NON_CLIENT_ROLES, (array) $user->roles ) );
    }

    /**
     * Interesse já registrado no CRM (lead + enquiry do corretor para o
     * imóvel com o e-mail do cliente). Idempotência do botão.
     *
     * @return int ID da enquiry ou 0.
     */
    public static function open_lead_for( $user_id, $property_id ) {
        global $wpdb;

        $user_id     = absint( $user_id );
        $property_id = absint( $property_id );
        if ( ! $user_id || ! $property_id ) {
            return 0;
        }

        $tables = self::crm_tables();
        if ( ! $tables ) {
            return 0;
        }

        $contact = self::client_contact( $user_id );
        if ( '' === $contact['email'] ) {
            return 0;
        }

        $broker_id = 0;
        if ( class_exists( 'Imovel_Parceiro_Contact_Widget' ) ) {
            $broker_id = Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id );
        }
        if ( ! $broker_id ) {
            return 0;
        }

        $enquiry_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e.enquiry_id FROM {$tables['enquiries']} e
                 INNER JOIN {$tables['leads']} l ON l.lead_id = e.lead_id
                 WHERE e.user_id = %d AND e.listing_id = %d AND l.email = %s AND e.enquiry_meta LIKE %s
                 ORDER BY e.enquiry_id DESC LIMIT 1",
                $broker_id,
                $property_id,
                $contact['email'],
                '%' . $wpdb->esc_like( self::KIND_INTEREST ) . '%'
            )
        );

        return $enquiry_id ? absint( $enquiry_id ) : 0;
    }

    /**
     * Tabelas do CRM Houzez (false quando o plugin CRM está ausente).
     *
     * @return array|false Com chaves leads e enquiries.
     */
    private static function crm_tables() {
        global $wpdb;

        $leads     = $wpdb->prefix . 'houzez_crm_leads';
        $enquiries = $wpdb->prefix . 'houzez_crm_enquiries';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $has_leads = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) );
        if ( $has_leads !== $leads ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $has_enquiries = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $enquiries ) );
        if ( $has_enquiries !== $enquiries ) {
            return false;
        }

        return array( 'leads' => $leads, 'enquiries' => $enquiries );
    }

    /**
     * Contato do cliente a partir do perfil (para a intermediação: é o
     * corretor quem recebe estes dados e faz o primeiro contato).
     */
    public static function client_contact( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return array( 'name' => '', 'email' => '', 'phone' => '' );
        }
        $phone = '';
        foreach ( array( 'fave_author_mobile', 'fave_author_whatsapp', 'fave_author_phone', 'billing_phone' ) as $key ) {
            $value = trim( (string) get_user_meta( $user->ID, $key, true ) );
            if ( '' !== $value ) {
                $phone = $value;
                break;
            }
        }
        return array(
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
        );
    }

    /* ---------------------------------------------------------------------
     * AJAX: registra interesse
     * ------------------------------------------------------------------ */

    public function ajax_submit() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Faça login como cliente para demonstrar interesse.', 'imovel-parceiro-core' ), 'code' => 'login_required' ), 401 );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) || 'publish' !== get_post_status( $property_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Imóvel inválido.', 'imovel-parceiro-core' ) ), 400 );
        }

        // Apenas cliente comprador usa este fluxo (corretores usam parceria).
        if ( ! self::is_client( $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Este recurso é exclusivo para clientes.', 'imovel-parceiro-core' ), 'code' => 'not_client' ), 403 );
        }

        if ( ! class_exists( 'Imovel_Parceiro_Contact_Widget' ) ) {
            wp_send_json_error( array( 'message' => __( 'Recurso indisponível no momento.', 'imovel-parceiro-core' ) ), 500 );
        }

        $broker_id = Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id );
        if ( ! $broker_id ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível identificar o corretor responsável.', 'imovel-parceiro-core' ) ), 500 );
        }

        // Sem autocontato.
        if ( (int) $broker_id === (int) $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Você é o responsável por este imóvel.', 'imovel-parceiro-core' ) ), 400 );
        }

        // Idempotência: um interesse por cliente+imóvel no CRM do corretor.
        $existing = self::open_lead_for( $user_id, $property_id );
        if ( $existing ) {
            wp_send_json_success(
                array(
                    'already'    => true,
                    'enquiry_id' => $existing,
                    'message'    => __( 'Interesse já registrado! O corretor responsável já foi avisado e entrará em contato.', 'imovel-parceiro-core' ),
                )
            );
        }

        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        if ( strlen( $message ) > 500 ) {
            $message = substr( $message, 0, 500 );
        }
        if ( '' === trim( $message ) ) {
            $message = __( 'Tenho interesse neste imóvel.', 'imovel-parceiro-core' );
        }

        $contact        = self::client_contact( $user_id );
        $property_title = get_the_title( $property_id );

        if ( '' === $contact['email'] || ! is_email( $contact['email'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Seu cadastro está sem um e-mail válido. Atualize seu perfil e tente novamente.', 'imovel-parceiro-core' ) ), 400 );
        }

        $tables = self::crm_tables();
        if ( ! $tables ) {
            wp_send_json_error( array( 'message' => __( 'Recurso indisponível no momento.', 'imovel-parceiro-core' ) ), 500 );
        }

        // Lead no CRM do corretor (mesmo mapeamento das leads nativas).
        $lead_id = self::find_crm_lead( $tables['leads'], $broker_id, $contact['email'] );
        if ( ! $lead_id ) {
            $lead_id = self::create_crm_lead( $tables['leads'], $broker_id, $property_id, $contact, $message );
        } elseif ( '' !== $contact['phone'] ) {
            self::touch_crm_lead_phone( $tables['leads'], $lead_id, $contact['phone'] );
        }
        if ( ! $lead_id ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível registrar seu interesse. Tente novamente.', 'imovel-parceiro-core' ) ), 500 );
        }

        // Enquiry vinculada ao imóvel (como as nativas de formulário).
        $enquiry_id = self::create_crm_enquiry( $tables['enquiries'], $broker_id, $lead_id, $property_id, $user_id, $contact, $message );
        if ( ! $enquiry_id ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível registrar seu interesse. Tente novamente.', 'imovel-parceiro-core' ) ), 500 );
        }

        $this->notify_broker( $lead_id, $enquiry_id, $property_id, $broker_id, $contact, $message );

        wp_send_json_success(
            array(
                'already'    => false,
                'lead_id'    => $lead_id,
                'enquiry_id' => $enquiry_id,
                'message'    => __( 'Interesse registrado! O corretor responsável foi avisado e entrará em contato com você.', 'imovel-parceiro-core' ),
            )
        );
    }

    /* ---------------------------------------------------------------------
     * CRM nativo (houzez_crm_leads + houzez_crm_enquiries)
     * ------------------------------------------------------------------ */

    private static function find_crm_lead( $leads_table, $broker_id, $email ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $lead_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT lead_id FROM {$leads_table} WHERE user_id = %d AND email = %s ORDER BY lead_id DESC LIMIT 1",
                $broker_id,
                $email
            )
        );

        return $lead_id ? absint( $lead_id ) : 0;
    }

    private static function split_name( $name ) {
        $parts = array_values( array_filter( preg_split( '/\s+/', trim( (string) $name ) ) ) );
        if ( empty( $parts ) ) {
            return array( '', '' );
        }
        $first = array_shift( $parts );
        return array( $first, implode( ' ', $parts ) );
    }

    /**
     * Insere a lead no CRM do corretor com o mesmo mapeamento das nativas
     * (Houzez_Leads::save_lead).
     */
    private static function create_crm_lead( $leads_table, $broker_id, $property_id, $contact, $message ) {
        global $wpdb;

        list( $first_name, $last_name ) = self::split_name( $contact['name'] );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ok = $wpdb->insert(
            $leads_table,
            array(
                'user_id'           => $broker_id,
                'prefix'            => '',
                'display_name'      => $contact['name'],
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email'             => $contact['email'],
                'mobile'            => $contact['phone'],
                'home_phone'        => '',
                'work_phone'        => '',
                'address'           => '',
                'city'              => '',
                'state'             => '',
                'country'           => '',
                'zipcode'           => '',
                'type'              => 'Buyer',
                'status'            => '',
                'source'            => 'Website',
                'source_link'       => get_permalink( $property_id ),
                'enquiry_to'        => $broker_id,
                'enquiry_user_type' => 'author_info',
                'twitter_url'       => '',
                'linkedin_url'      => '',
                'facebook_url'      => '',
                'private_note'      => sprintf(
                    /* translators: 1: ID do usuário, 2: IP */
                    __( 'Lead originada pelo site (botão "Tenho interesse neste imóvel"). Usuário #%1$d, IP %2$s.', 'imovel-parceiro-core' ),
                    get_current_user_id(),
                    self::request_ip_static()
                ),
                'message'           => $message,
                'time'              => gmdate( 'Y-m-d H:i:s' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $ok ? absint( $wpdb->insert_id ) : 0;
    }

    private static function touch_crm_lead_phone( $leads_table, $lead_id, $phone ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$leads_table} SET mobile = %s WHERE lead_id = %d AND ( mobile IS NULL OR mobile = '' )",
                $phone,
                $lead_id
            )
        );
    }

    /**
     * Insere a enquiry vinculada ao imóvel (como as nativas de formulário).
     */
    private static function create_crm_enquiry( $enquiries_table, $broker_id, $lead_id, $property_id, $user_id, $contact, $message ) {
        global $wpdb;

        $proof = sprintf(
            /* translators: 1: ID do usuário, 2: IP, 3: ID da lead */
            __( 'Interesse via botão "Tenho interesse neste imóvel" (lead #%3$d do CRM). Cliente: usuário #%1$d, IP %2$s. Prova de origem para a comissão da plataforma.', 'imovel-parceiro-core' ),
            $user_id,
            self::request_ip_static(),
            $lead_id
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ok = $wpdb->insert(
            $enquiries_table,
            array(
                'user_id'           => $broker_id,
                'lead_id'           => $lead_id,
                'listing_id'        => $property_id,
                'negotiator'        => '',
                'source'            => 'Website',
                'status'            => '',
                'enquiry_to'        => $broker_id,
                'enquiry_user_type' => 'author_info',
                'message'           => $message,
                'enquiry_type'      => 'Purchase',
                'enquiry_meta'      => maybe_serialize( self::property_enquiry_meta( $property_id, $user_id ) ),
                'private_note'      => $proof,
                'time'              => gmdate( 'Y-m-d H:i:s' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $ok ? absint( $wpdb->insert_id ) : 0;
    }

    /**
     * Meta da enquiry: dados do imóvel (mesmas chaves das nativas, para a
     * exportação CSV funcionar) + marcadores do fluxo.
     */
    private static function property_enquiry_meta( $property_id, $user_id ) {
        $meta = array(
            'min_beds'       => get_post_meta( $property_id, 'fave_property_bedrooms', true ),
            'max_beds'       => get_post_meta( $property_id, 'fave_property_bedrooms', true ),
            'min_baths'      => get_post_meta( $property_id, 'fave_property_bathrooms', true ),
            'max_baths'      => get_post_meta( $property_id, 'fave_property_bathrooms', true ),
            'min_price'      => get_post_meta( $property_id, 'fave_property_price', true ),
            'max_price'      => get_post_meta( $property_id, 'fave_property_price', true ),
            'min_area'       => get_post_meta( $property_id, 'fave_property_size', true ),
            'max_area'       => get_post_meta( $property_id, 'fave_property_size', true ),
            'zipcode'        => get_post_meta( $property_id, 'fave_property_zip', true ),
            'streat_address' => get_post_meta( $property_id, 'fave_property_address', true ),
        );

        foreach ( array(
            'property_type'   => 'property_type',
            'property_status' => 'property_status',
            'property_label'  => 'property_label',
            'country'         => 'property_country',
            'state'           => 'property_state',
            'city'            => 'property_city',
            'area'            => 'property_area',
        ) as $key => $taxonomy ) {
            $terms = get_the_terms( $property_id, $taxonomy );
            $meta[ $key ] = array();
            if ( $terms && ! is_wp_error( $terms ) ) {
                $term = array_shift( $terms );
                $meta[ $key ] = array( 'name' => $term->name, 'slug' => $term->slug );
            }
        }

        // Marcadores do fluxo (prova de origem + dedupe).
        $meta['ipc_kind']            = self::KIND_INTEREST;
        $meta['ipc_property_title']  = get_the_title( $property_id );
        $meta['ipc_property_url']    = get_permalink( $property_id );
        $meta['ipc_client_user_id']  = absint( $user_id );
        $meta['ipc_client_ip']       = self::request_ip_static();

        return $meta;
    }

    private static function request_ip_static() {
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }
            $value = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
            if ( $value ) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Intermediação: avisa o corretor (painel + e-mail) com os dados do
     * cliente. O primeiro contato parte do corretor — o cliente não recebe
     * o WhatsApp direto.
     */
    private function notify_broker( $lead_id, $enquiry_id, $property_id, $broker_id, $contact, $message ) {
        $property_title = get_the_title( $property_id );
        $property_url   = get_permalink( $property_id );

        // 1) Notificação in-app (painel do corretor).
        if ( class_exists( 'IPC_Notifications' ) ) {
            IPC_Notifications::send(
                array(
                    'user_id'     => $broker_id,
                    'property_id' => $property_id,
                    'type'        => 'LEAD_INTERESSE',
                    'category'    => IPC_Notifications::CATEGORY_PROPRIEDADES,
                    'title'       => sprintf( __( 'Novo interesse no imóvel "%s"', 'imovel-parceiro-core' ), $property_title ),
                    'message'     => sprintf( __( '%1$s demonstrou interesse. Entre em contato: %2$s', 'imovel-parceiro-core' ), $contact['name'], trim( $contact['email'] . ' ' . $contact['phone'] ) ),
                    'url'         => $property_url,
                    'priority'    => IPC_Notifications::PRIORITY_IMPORTANT,
                    'meta'        => array( 'lead_id' => $lead_id, 'enquiry_id' => $enquiry_id, 'client_user_id' => get_current_user_id() ),
                )
            );
        }

        // 2) E-mail ao corretor com os dados do cliente.
        $broker = get_userdata( $broker_id );
        $to     = $broker ? $broker->user_email : '';
        if ( ! $to || ! is_email( $to ) ) {
            return;
        }

        $subject = sprintf(
            __( '[%1$s] Novo interesse no imóvel "%2$s"', 'imovel-parceiro-core' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            $property_title
        );

        $lines = array(
            sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property_title ),
            sprintf( __( 'Link: %s', 'imovel-parceiro-core' ), $property_url ),
            '',
            __( 'Dados do cliente interessado (lead originada pelo site):', 'imovel-parceiro-core' ),
            sprintf( __( 'Nome: %s', 'imovel-parceiro-core' ), $contact['name'] ),
            sprintf( __( 'E-mail: %s', 'imovel-parceiro-core' ), $contact['email'] ),
            sprintf( __( 'Telefone: %s', 'imovel-parceiro-core' ), $contact['phone'] ? $contact['phone'] : __( 'não informado', 'imovel-parceiro-core' ) ),
        );
        if ( '' !== $message ) {
            $lines[] = sprintf( __( 'Mensagem do cliente: %s', 'imovel-parceiro-core' ), $message );
        }
        $lines[] = '';
        $lines[] = sprintf( __( 'Lead #%1$d no seu CRM (enquiry #%2$d) — prova de origem para a comissão da plataforma.', 'imovel-parceiro-core' ), $lead_id, $enquiry_id );

        if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
            Imovel_Parceiro_Email_Template::send(
                $to,
                $subject,
                Imovel_Parceiro_Email_Template::text_to_html( implode( "\n", $lines ) ),
                array( 'title' => __( 'Novo interesse em imóvel', 'imovel-parceiro-core' ) )
            );
        } elseif ( function_exists( 'houzez_send_emails' ) ) {
            houzez_send_emails( $to, $subject, implode( "\n", $lines ) );
        } else {
            wp_mail( $to, $subject, implode( "\n", $lines ) );
        }
    }
}

Imovel_Parceiro_Property_Interest::instance();

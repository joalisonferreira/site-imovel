<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notifications and e-mail links for the Houzez user verification flow.
 *
 * - Creates in-app notifications for users and admins on every verification
 *   transition (request, additional info, approve, reject, revoke, request info).
 * - Adds dashboard links to the verification e-mails so both sides can act.
 * - Adds the CRECI document type and requires it from corretores.
 */
class Imovel_Parceiro_Verification_Notifications {

    public function __construct() {
        // In-app notifications: user submits a request / additional info -> notify admins.
        add_action( 'houzez_after_verification_request', array( $this, 'notify_admin_new_request' ), 20, 2 );
        add_action( 'houzez_after_additional_info_submission', array( $this, 'notify_admin_additional_info' ), 20, 2 );

        // In-app notifications: admin decision -> notify the user.
        add_action( 'houzez_after_approve_verification', array( $this, 'notify_user_approved' ), 20, 2 );
        add_action( 'houzez_after_reject_verification', array( $this, 'notify_user_rejected' ), 20, 3 );
        add_action( 'houzez_after_revoke_verification', array( $this, 'notify_user_revoked' ), 20, 2 );
        add_action( 'houzez_after_request_info', array( $this, 'notify_user_request_info' ), 20, 3 );

        // Item 5: admin e-mails link to the dashboard management screen.
        add_filter( 'houzez_verification_admin_message', array( $this, 'filter_admin_message' ), 20, 4 );
        add_filter( 'houzez_verification_additional_info_admin_message', array( $this, 'filter_admin_message' ), 20, 4 );

        // Item 6: user e-mails (rejected / additional info) link to the profile screen.
        add_filter( 'houzez_verification_user_message', array( $this, 'filter_user_message' ), 20, 5 );

        // Item 4: CRECI document type + required for corretores.
        add_filter( 'houzez_verification_document_types', array( $this, 'add_creci_document_type' ), 20, 1 );
        add_filter( 'houzez_verification_document_types', array( $this, 'restrict_document_types_for_brokers' ), 30, 1 );
        add_action( 'houzez_before_verification_request', array( $this, 'require_creci_for_agents' ), 20, 2 );
        add_action( 'houzez_before_additional_info_submission', array( $this, 'require_creci_for_agents' ), 20, 2 );
    }

    /**
     * Profile page URL where the user manages their verification.
     */
    public static function user_verification_url( $user_id = 0 ) {
        $url = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );
        if ( ! $url ) {
            $url = home_url( '/meu-perfil/' );
        }

        return add_query_arg( array( 'hpage' => 'verification' ), $url );
    }

    /**
     * Dashboard URL where admins manage verification requests.
     */
    public static function admin_verification_url() {
        $url = houzez_get_template_link_2( 'template/user_dashboard.php' );
        if ( ! $url ) {
            $url = home_url( '/dashboard/' );
        }

        return add_query_arg(
            array(
                'imovel_admin_area'    => 'gestao',
                'imovel_admin_section' => 'verification_requests',
            ),
            $url
        );
    }

    private function admin_user_ids() {
        $ids = get_users(
            array(
                'role'   => 'administrator',
                'fields' => 'ID',
            )
        );

        return array_map( 'absint', (array) $ids );
    }

    private function display_name( $user_id ) {
        $user = get_userdata( $user_id );

        return $user ? $user->display_name : ( '#' . absint( $user_id ) );
    }

    private function notify_admins( $title, $message, $priority = 'normal' ) {
        if ( ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }

        foreach ( $this->admin_user_ids() as $admin_id ) {
            IPC_Notifications::send(
                array(
                    'user_id'  => $admin_id,
                    'type'     => 'VERIFICATION_ADMIN',
                    'category' => IPC_Notifications::CATEGORY_DOCUMENTACAO,
                    'title'    => $title,
                    'message'  => $message,
                    'url'      => self::admin_verification_url(),
                    'priority' => $priority,
                )
            );
        }
    }

    private function notify_user( $user_id, $title, $message, $priority = 'normal' ) {
        if ( ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }

        IPC_Notifications::send(
            array(
                'user_id'  => $user_id,
                'type'     => 'VERIFICATION_USER',
                'category' => IPC_Notifications::CATEGORY_DOCUMENTACAO,
                'title'    => $title,
                'message'  => $message,
                'url'      => self::user_verification_url( $user_id ),
                'priority' => $priority,
            )
        );
    }

    public function notify_admin_new_request( $user_id, $verification_data = array() ) {
        $this->notify_admins(
            __( 'Nova solicitação de verificação', 'imovel-parceiro-core' ),
            sprintf( __( '%s enviou documentos para verificação.', 'imovel-parceiro-core' ), $this->display_name( $user_id ) ),
            IPC_Notifications::PRIORITY_IMPORTANT
        );
    }

    public function notify_admin_additional_info( $user_id, $verification_data = array() ) {
        $this->notify_admins(
            __( 'Informações adicionais enviadas', 'imovel-parceiro-core' ),
            sprintf( __( '%s enviou informações adicionais para a verificação.', 'imovel-parceiro-core' ), $this->display_name( $user_id ) ),
            IPC_Notifications::PRIORITY_IMPORTANT
        );
    }

    public function notify_user_approved( $user_id, $verification_data = array() ) {
        $this->notify_user(
            $user_id,
            __( 'Verificação aprovada', 'imovel-parceiro-core' ),
            __( 'Sua verificação foi aprovada com sucesso.', 'imovel-parceiro-core' ),
            IPC_Notifications::PRIORITY_NORMAL
        );
    }

    public function notify_user_rejected( $user_id, $reason = '', $verification_data = array() ) {
        $message = __( 'Sua verificação foi recusada.', 'imovel-parceiro-core' );
        if ( ! empty( $reason ) ) {
            $message .= ' ' . sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), $reason );
        }
        $message .= ' ' . __( 'Acesse seu perfil para corrigir e reenviar.', 'imovel-parceiro-core' );

        $this->notify_user( $user_id, __( 'Verificação recusada', 'imovel-parceiro-core' ), $message, IPC_Notifications::PRIORITY_IMPORTANT );
    }

    public function notify_user_revoked( $user_id, $verification_data = array() ) {
        $this->notify_user(
            $user_id,
            __( 'Verificação revogada', 'imovel-parceiro-core' ),
            __( 'Sua verificação foi revogada. Acesse seu perfil para enviar novamente.', 'imovel-parceiro-core' ),
            IPC_Notifications::PRIORITY_IMPORTANT
        );
    }

    public function notify_user_request_info( $user_id, $info = '', $verification_data = array() ) {
        $message = __( 'Precisamos de mais informações para concluir sua verificação.', 'imovel-parceiro-core' );
        if ( ! empty( $info ) ) {
            $message .= ' ' . sprintf( __( 'Detalhes: %s', 'imovel-parceiro-core' ), $info );
        }
        $message .= ' ' . __( 'Acesse seu perfil para enviar.', 'imovel-parceiro-core' );

        $this->notify_user( $user_id, __( 'Informações adicionais solicitadas', 'imovel-parceiro-core' ), $message, IPC_Notifications::PRIORITY_IMPORTANT );
    }

    /**
     * Item 6/7: when an admin decides directly from the Houzez dashboard
     * management screen (which does not fire the theme verification hooks),
     * notify the user in-app and by e-mail with a link to fix/resubmit.
     *
     * @param int    $user_id           Target user.
     * @param string $action            approve|reject|additional_info|revoke.
     * @param string $notes             Justification/details.
     * @param array  $verification_data Current verification data.
     */
    public static function notify_dashboard_decision( $user_id, $action, $notes = '', $verification_data = array() ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return;
        }

        $instance = new self();
        $url      = self::user_verification_url( $user_id );
        $notes    = is_string( $notes ) ? trim( $notes ) : '';

        switch ( $action ) {
            case 'approve':
                $instance->notify_user_approved( $user_id, $verification_data );
                $subject = __( 'Sua verificação foi aprovada', 'imovel-parceiro-core' );
                $body    = __( 'Boa notícia! Sua verificação de documentos foi aprovada.', 'imovel-parceiro-core' );
                break;

            case 'reject':
                $instance->notify_user_rejected( $user_id, $notes, $verification_data );
                $subject = __( 'Sua verificação foi recusada', 'imovel-parceiro-core' );
                $body    = __( 'Sua verificação de documentos foi recusada.', 'imovel-parceiro-core' );
                if ( '' !== $notes ) {
                    $body .= ' ' . sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), $notes );
                }
                $body .= "\n\n" . sprintf( __( 'Acesse %s para corrigir e reenviar seus documentos.', 'imovel-parceiro-core' ), $url );
                break;

            case 'additional_info':
                $instance->notify_user_request_info( $user_id, $notes, $verification_data );
                $subject = __( 'Informações adicionais solicitadas', 'imovel-parceiro-core' );
                $body    = __( 'Precisamos de mais informações para concluir sua verificação.', 'imovel-parceiro-core' );
                if ( '' !== $notes ) {
                    $body .= ' ' . sprintf( __( 'Detalhes: %s', 'imovel-parceiro-core' ), $notes );
                }
                $body .= "\n\n" . sprintf( __( 'Acesse %s para enviar as informações.', 'imovel-parceiro-core' ), $url );
                break;

            case 'revoke':
                $instance->notify_user_revoked( $user_id, $verification_data );
                $subject = __( 'Sua verificação foi revogada', 'imovel-parceiro-core' );
                $body    = __( 'Sua verificação foi revogada.', 'imovel-parceiro-core' );
                $body   .= "\n\n" . sprintf( __( 'Acesse %s para enviar novamente.', 'imovel-parceiro-core' ), $url );
                break;

            default:
                return;
        }

        $user = get_userdata( $user_id );
        if ( $user && is_email( $user->user_email ) ) {
            $subject = '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject;
            $args    = array( 'title' => $subject );
            if ( in_array( $action, array( 'reject', 'additional_info', 'revoke' ), true ) ) {
                $args['cta_url']  = $url;
                $args['cta_text'] = __( 'Acessar painel', 'imovel-parceiro-core' );
            }
            Imovel_Parceiro_Email_Template::send(
                $user->user_email,
                $subject,
                Imovel_Parceiro_Email_Template::text_to_html( $body ),
                $args
            );
        }
    }

    /**
     * Item 5: point admin verification e-mails to the dashboard management screen.
     */
    public function filter_admin_message( $message, $user_id = 0, $verification_data = array(), $user = null ) {
        $url = self::admin_verification_url();
        $message .= "\n\n" . sprintf(
            __( 'Gerencie esta solicitação no painel: %s', 'imovel-parceiro-core' ),
            $url
        );

        return $message;
    }

    /**
     * Item 6: point user verification e-mails (rejected / additional info) to the fix-it page.
     */
    public function filter_user_message( $message, $user_id = 0, $status = '', $additional_message = '', $user = null ) {
        if ( in_array( $status, array( 'rejected', 'additional_info_required' ), true ) ) {
            $url = self::user_verification_url( $user_id );
            $message .= "\n\n" . sprintf(
                __( 'Acesse %s para corrigir e reenviar seus documentos.', 'imovel-parceiro-core' ),
                $url
            );
        }

        return $message;
    }

    /**
     * Mirror a dashboard decision (approve/reject/additional_info/revoke)
     * into the theme's verification history and agent/agency post flag.
     *
     * The dashboard management screen updates verification metas directly,
     * bypassing the theme flow that normally records these side effects.
     * Entry format, filters and actions mirror Houzez_User_Verification.
     *
     * @param int    $user_id Target user.
     * @param string $action  approve|reject|additional_info|revoke.
     * @param string $notes   Justification/details.
     */
    public static function record_dashboard_history( $user_id, $action, $notes = '' ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return;
        }

        $notes = is_string( $notes ) ? trim( $notes ) : '';

        switch ( $action ) {
            case 'approve':
                $status = 'approved';
                $note_token = 'verification_approved';
                $note_args = array();
                $agent_flag = 1;
                break;
            case 'reject':
                $status = 'rejected';
                $note_token = '' !== $notes ? 'custom_rejection' : 'verification_rejected';
                $note_args = '' !== $notes ? array( $notes ) : array();
                $agent_flag = 0;
                break;
            case 'additional_info':
                $status = 'additional_info_required';
                $note_token = '' !== $notes ? 'custom_additional_info' : 'additional_info_requested';
                $note_args = '' !== $notes ? array( $notes ) : array();
                $agent_flag = null;
                break;
            case 'revoke':
                $status = 'rejected';
                $note_token = 'verification_revoked';
                $note_args = array();
                $agent_flag = 0;
                break;
            default:
                return;
        }

        do_action( 'houzez_before_add_verification_history', $user_id, $status, $note_token, $note_args );

        $history = get_user_meta( $user_id, 'houzez_verification_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $user = get_userdata( $user_id );
        $admin_user = wp_get_current_user();

        $context = '';
        if ( 'pending' === $status ) {
            $context = sprintf( __( 'Submitted by %s', 'houzez' ), $user ? $user->display_name : '' );
        } elseif ( current_user_can( 'manage_options' ) && $admin_user && (int) $admin_user->ID !== $user_id ) {
            $context = sprintf( __( 'Processed by %s', 'houzez' ), $admin_user->display_name );
        }

        $verification_data = get_user_meta( $user_id, 'houzez_verification_data', true );
        $document_type = '';
        if ( ! empty( $verification_data ) && is_array( $verification_data ) ) {
            $document_type = isset( $verification_data['document_type'] ) ? $verification_data['document_type'] : '';
        }

        $entry = array(
            'status' => $status,
            'date' => current_time( 'mysql' ),
            'note_token' => $note_token,
            'args' => $note_args,
            'context' => $context,
            'document_type' => $document_type,
        );

        $entry = apply_filters( 'houzez_verification_history_entry', $entry, $user_id, $status, $verification_data );
        $history[] = $entry;
        $history = apply_filters( 'houzez_verification_history_array', $history, $user_id, $entry );
        update_user_meta( $user_id, 'houzez_verification_history', $history );
        do_action( 'houzez_after_add_verification_history', $user_id, $status, $entry, $history );

        if ( null !== $agent_flag ) {
            self::sync_agent_post_verification( $user_id, $agent_flag );
        }
    }

    /**
     * Mirror of the theme's agent/agency post verified flag.
     *
     * @param int $user_id Target user.
     * @param int $status  1 = verified, 0 = not verified.
     */
    public static function sync_agent_post_verification( $user_id, $status = 0 ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return;
        }

        $post_id = false;
        $prefix = '';
        if ( array_intersect( array( 'houzez_agent', 'author' ), (array) $user->roles ) ) {
            $post_id = get_user_meta( $user->ID, 'fave_author_agent_id', true );
            $prefix = 'fave_agent_';
        } elseif ( in_array( 'houzez_agency', (array) $user->roles, true ) ) {
            $post_id = get_user_meta( $user->ID, 'fave_author_agency_id', true );
            $prefix = 'fave_agency_';
        }

        if ( $post_id && get_post( $post_id ) ) {
            update_post_meta( $post_id, $prefix . 'verified', absint( $status ) ? 1 : 0 );
        }
    }

    /**
     * Item 4: register the CRECI (carteira) document type.
     */
    public function add_creci_document_type( $document_types ) {
        if ( ! is_array( $document_types ) ) {
            $document_types = array();
        }

        if ( ! isset( $document_types['creci'] ) ) {
            $document_types['creci'] = array(
                'label'         => __( 'CRECI (Carteira profissional)', 'imovel-parceiro-core' ),
                'requires_back' => false,
            );
        } else {
            $document_types['creci']['requires_back'] = false;
        }

        return $document_types;
    }

    /**
     * Corretores e imobiliárias veem somente o CRECI no seletor de documento.
     */
    public function restrict_document_types_for_brokers( $document_types ) {
        if ( ! is_array( $document_types ) || ! isset( $document_types['creci'] ) ) {
            return $document_types;
        }

        if ( ! is_user_logged_in() ) {
            return $document_types;
        }

        $roles = (array) wp_get_current_user()->roles;
        if ( array_intersect( array( 'houzez_agent', 'houzez_agency' ), $roles ) ) {
            return array( 'creci' => $document_types['creci'] );
        }

        return $document_types;
    }

    /**
     * Item 4: corretores e imobiliárias must submit the CRECI document type.
     */
    public function require_creci_for_agents( $user_id, $post_data = array() ) {
        $user = get_userdata( $user_id );
        if ( ! $user || ! array_intersect( array( 'houzez_agent', 'houzez_agency' ), (array) $user->roles ) ) {
            return;
        }

        $document_type = isset( $post_data['document_type'] ) ? sanitize_key( wp_unslash( $post_data['document_type'] ) ) : '';

        if ( 'creci' !== $document_type ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Corretores e imobiliárias devem enviar a carteira do CRECI para verificação.', 'imovel-parceiro-core' ),
                )
            );
        }
    }
}

new Imovel_Parceiro_Verification_Notifications();

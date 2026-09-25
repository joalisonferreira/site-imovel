<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Owner_Workflow {
    const ROLE_PROPRIETARIO = 'houzez_owner';

    const CAP_OWNER_DASHBOARD = 'imovel_parceiro_owner_access_dashboard';
    const CAP_OWNER_SUBMIT = 'imovel_parceiro_owner_submit_property';
    const CAP_OWNER_DOCS = 'imovel_parceiro_owner_manage_docs';
    const CAP_OWNER_BROKER_CHANGE = 'imovel_parceiro_owner_request_broker_change';
    const CAP_OWNER_DELETE_REQUEST = 'imovel_parceiro_owner_request_deletion';
    const CAP_MANAGE_WORKFLOW = 'imovel_parceiro_manage_owner_workflow';
    const CAP_MANAGE_COMMERCIAL = 'imovel_parceiro_manage_commercial';

    const META_OWNER_ID = '_imovel_parceiro_proprietario_id';
    const META_BROKER_ID = '_imovel_parceiro_corretor_responsavel_id';
    const META_WORKFLOW_STATUS = '_imovel_parceiro_workflow_status';
    const META_DOC_STATUS = '_imovel_parceiro_documentation_status';
    const META_APPROVAL_STATUS = '_imovel_parceiro_approval_status';
    const META_OWNER_TERM_ACCEPTED = '_imovel_parceiro_owner_term_accepted';
    const META_OWNER_TERM_VERSION = '_imovel_parceiro_owner_term_version';
    const META_OWNER_TERM_IP = '_imovel_parceiro_owner_term_ip';

    const USER_META_IDENTITY_STATUS = '_imovel_parceiro_owner_identity_status';

    const WORKFLOW_RASCUNHO = 'RASCUNHO';
    const WORKFLOW_DOCUMENTACAO_PENDENTE = 'DOCUMENTACAO_PENDENTE';
    const WORKFLOW_DOCUMENTACAO_ENVIADA = 'DOCUMENTACAO_ENVIADA';
    const WORKFLOW_EM_ANALISE = 'EM_ANALISE';
    const WORKFLOW_APROVADO = 'APROVADO';
    const WORKFLOW_REJEITADO = 'REJEITADO';
    const WORKFLOW_BLOQUEADO = 'BLOQUEADO';
    const WORKFLOW_SUSPENSO = 'SUSPENSO';
    const WORKFLOW_VENDIDO = 'VENDIDO';
    const WORKFLOW_ALUGADO = 'ALUGADO';
    const WORKFLOW_ARQUIVADO = 'ARQUIVADO';

    const DOC_STATUS_PENDENTE = 'pendente';
    const DOC_STATUS_EM_ANALISE = 'em_analise';
    const DOC_STATUS_APROVADA = 'aprovada';
    const DOC_STATUS_REJEITADA = 'rejeitada';

    const APPROVAL_STATUS_PENDENTE = 'pendente';
    const APPROVAL_STATUS_APROVADO = 'aprovado';
    const APPROVAL_STATUS_REJEITADO = 'rejeitado';
    const APPROVAL_STATUS_BLOQUEADO = 'bloqueado';
    const APPROVAL_STATUS_SUSPENSO = 'suspenso';

    public function __construct() {
        add_action( 'init', array( $this, 'register_role_and_caps' ) );
        add_action( 'init', array( $this, 'maybe_ensure_tables' ) );
        add_action( 'template_redirect', array( $this, 'enforce_owner_dashboard_access' ), 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_owner_dashboard_guards' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_owner_properties_dashboard_query' ) );

        add_filter( 'option_houzez_options', array( $this, 'filter_houzez_options_for_owner' ) );
        add_filter( 'houzez_before_submit_property', array( $this, 'validate_owner_submission' ) );
        add_filter( 'houzez_before_update_property', array( $this, 'validate_owner_submission' ) );
        add_filter( 'wp_insert_post_data', array( $this, 'enforce_publish_policy_before_approval' ), 10, 2 );

        add_action( 'houzez_after_property_submit', array( $this, 'register_owner_property_relation' ), 10, 1 );
        add_action( 'houzez_after_property_update', array( $this, 'register_owner_property_relation' ), 10, 1 );
        add_action( 'houzez_after_property_submit', array( $this, 'consume_staged_documents' ), 20, 1 );
        add_action( 'houzez_after_property_update', array( $this, 'consume_staged_documents' ), 20, 1 );
        add_action( 'save_post_property', array( $this, 'sync_relation_on_property_save' ), 10, 3 );

        add_action( 'before_delete_post', array( $this, 'prevent_owner_hard_delete_with_history' ), 5 );

        add_action( 'wp_ajax_imovel_parceiro_owner_upload_document', array( $this, 'ajax_owner_upload_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_stage_document', array( $this, 'ajax_owner_stage_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_remove_staged_document', array( $this, 'ajax_owner_remove_staged_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_search_brokers', array( $this, 'ajax_owner_search_brokers' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_request_broker_change', array( $this, 'ajax_owner_request_broker_change' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_process_broker_change', array( $this, 'ajax_admin_process_broker_change' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_review_document', array( $this, 'ajax_admin_review_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_request_property_delete', array( $this, 'ajax_owner_request_property_delete' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_process_property_delete', array( $this, 'ajax_admin_process_property_delete_request' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_download_document', array( $this, 'ajax_admin_download_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_document_preview', array( $this, 'ajax_admin_document_preview' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_stream_document', array( $this, 'ajax_admin_stream_document' ) );
        add_action( 'wp_ajax_imovel_parceiro_owner_property_context', array( $this, 'ajax_owner_property_context' ) );

        add_filter( 'wp_get_attachment_url', array( $this, 'hide_private_document_urls' ), 10, 2 );
    }

    private function get_relations_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_owner_relations';
    }

    private function get_documents_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_owner_documents';
    }

    private function get_broker_change_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_broker_change_requests';
    }

    private function get_partnerships_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_partnerships';
    }

    private function resolve_partnership_best_column( $table, $primary_column, $fallback_column, $columns ) {
        global $wpdb;

        $has_primary = in_array( $primary_column, $columns, true );
        $has_fallback = in_array( $fallback_column, $columns, true );

        if ( $has_primary && ! $has_fallback ) {
            return $primary_column;
        }

        if ( $has_fallback && ! $has_primary ) {
            return $fallback_column;
        }

        if ( ! $has_primary && ! $has_fallback ) {
            return $primary_column;
        }

        $primary_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$primary_column} > 0" );
        $fallback_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$fallback_column} > 0" );

        return $fallback_count > $primary_count ? $fallback_column : $primary_column;
    }

    private function get_partnership_table_schema() {
        global $wpdb;

        static $schema = null;
        if ( null !== $schema ) {
            return $schema;
        }

        $table = $this->get_partnerships_table();
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            $schema = array();
            return $schema;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( empty( $columns ) ) {
            $schema = array();
            return $schema;
        }

        $schema = array(
            'requester_col' => $this->resolve_partnership_best_column( $table, 'requester_id', 'captador_id', $columns ),
            'owner_col' => $this->resolve_partnership_best_column( $table, 'owner_id', 'partner_id', $columns ),
            'notes_col' => in_array( 'notes', $columns, true ) ? 'notes' : '',
            'has_updated_at' => in_array( 'updated_at', $columns, true ),
        );

        return $schema;
    }

    private function get_eligible_partnership_cancel_statuses() {
        return array( 'pending', 'solicitada', 'accepted', 'active', 'em_andamento', 'negotiating' );
    }

    private function get_partnership_status_to_apply_on_broker_change() {
        return 'cancelled';
    }

    private function begin_database_transaction() {
        global $wpdb;
        return false !== $wpdb->query( 'START TRANSACTION' );
    }

    private function commit_database_transaction() {
        global $wpdb;
        return false !== $wpdb->query( 'COMMIT' );
    }

    private function rollback_database_transaction() {
        global $wpdb;
        return false !== $wpdb->query( 'ROLLBACK' );
    }

    private function cancel_property_partnerships_after_broker_approval( $property_id, $actor_user_id, $reason, $request_id, $current_broker_id, $new_broker_id ) {
        global $wpdb;

        $table = $this->get_partnerships_table();
        $schema = $this->get_partnership_table_schema();
        if ( empty( $schema ) || empty( $schema['requester_col'] ) || empty( $schema['owner_col'] ) ) {
            return array(
                'found_ids' => array(),
                'cancelled_rows' => array(),
                'status_applied' => $this->get_partnership_status_to_apply_on_broker_change(),
            );
        }

        $eligible_statuses = $this->get_eligible_partnership_cancel_statuses();
        $placeholders = implode( ',', array_fill( 0, count( $eligible_statuses ), '%s' ) );
        $sql = "SELECT * FROM {$table} WHERE property_id = %d AND status IN ({$placeholders}) ORDER BY id ASC";
        $params = array_merge( array( absint( $property_id ) ), $eligible_statuses );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        if ( empty( $rows ) ) {
            return array(
                'found_ids' => array(),
                'cancelled_rows' => array(),
                'status_applied' => $this->get_partnership_status_to_apply_on_broker_change(),
            );
        }

        $found_ids = array();
        $cancelled_rows = array();
        $target_status = $this->get_partnership_status_to_apply_on_broker_change();

        foreach ( $rows as $row ) {
            $partnership_id = isset( $row->id ) ? absint( $row->id ) : 0;
            if ( ! $partnership_id ) {
                continue;
            }

            $previous_status = isset( $row->status ) ? sanitize_key( (string) $row->status ) : '';
            $found_ids[] = $partnership_id;

            $update_data = array( 'status' => $target_status );
            $update_format = array( '%s' );

            if ( ! empty( $schema['has_updated_at'] ) ) {
                $update_data['updated_at'] = current_time( 'mysql' );
                $update_format[] = '%s';
            }

            if ( ! empty( $schema['notes_col'] ) ) {
                $existing_notes = isset( $row->{$schema['notes_col']} ) ? trim( (string) $row->{$schema['notes_col']} ) : '';
                $system_note = sprintf(
                    'Encerrada automaticamente por troca de corretor aprovada (request_id: %d, corretor_anterior: %d, corretor_novo: %d, motivo: %s).',
                    absint( $request_id ),
                    absint( $current_broker_id ),
                    absint( $new_broker_id ),
                    sanitize_text_field( $reason )
                );
                $update_data[ $schema['notes_col'] ] = ! empty( $existing_notes ) ? $existing_notes . "\n\n" . $system_note : $system_note;
                $update_format[] = '%s';
            }

            $updated = $wpdb->update( $table, $update_data, array( 'id' => $partnership_id ), $update_format, array( '%d' ) );
            if ( false === $updated ) {
                return new WP_Error( 'partnership_cancel_failed', __( 'Falha ao cancelar parcerias elegíveis durante a troca de corretor.', 'imovel-parceiro-core' ) );
            }

            $cancelled_rows[] = array(
                'id' => $partnership_id,
                'property_id' => isset( $row->property_id ) ? absint( $row->property_id ) : absint( $property_id ),
                'requester_id' => isset( $row->{$schema['requester_col']} ) ? absint( $row->{$schema['requester_col']} ) : 0,
                'owner_id' => isset( $row->{$schema['owner_col']} ) ? absint( $row->{$schema['owner_col']} ) : 0,
                'previous_status' => $previous_status,
                'new_status' => $target_status,
            );
        }

        return array(
            'found_ids' => array_values( array_unique( array_map( 'absint', $found_ids ) ) ),
            'cancelled_rows' => $cancelled_rows,
            'status_applied' => $target_status,
        );
    }

    private function notify_partnership_cancellation_due_broker_change( $partnership_data, $property_id ) {
        $property_id = absint( $property_id );
        if ( $property_id <= 0 || ! class_exists( 'IPC_Notifications' ) || empty( $partnership_data['id'] ) ) {
            return;
        }

        $targets = array_unique(
            array_filter(
                array(
                    isset( $partnership_data['requester_id'] ) ? absint( $partnership_data['requester_id'] ) : 0,
                    isset( $partnership_data['owner_id'] ) ? absint( $partnership_data['owner_id'] ) : 0,
                )
            )
        );

        if ( empty( $targets ) ) {
            return;
        }

        $property_title = get_the_title( $property_id );
        if ( empty( $property_title ) ) {
            $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id );
        }

        $message = __( 'A parceria relacionada a este imóvel foi encerrada devido à aprovação da troca do corretor responsável.', 'imovel-parceiro-core' );

        foreach ( $targets as $user_id ) {
            IPC_Notifications::send(
                array(
                    'user_id' => (int) $user_id,
                    'property_id' => $property_id,
                    'partnership_id' => absint( $partnership_data['id'] ),
                    'type' => 'PARCERIA_CANCELADA_TROCA_CORRETOR',
                    'category' => IPC_Notifications::CATEGORY_PARCERIAS,
                    'title' => __( 'Parceria cancelada', 'imovel-parceiro-core' ),
                    'message' => $message,
                    'url' => get_permalink( $property_id ),
                    'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );

            $this->notify_user(
                (int) $user_id,
                __( 'Parceria encerrada por troca de corretor', 'imovel-parceiro-core' ),
                sprintf( __( 'A parceria vinculada ao imóvel "%s" foi encerrada após a aprovação da troca de corretor.', 'imovel-parceiro-core' ), $property_title )
            );
        }
    }

    private function get_broker_roles() {
        return apply_filters( 'imovel_parceiro_broker_roles', array( 'houzez_agent', 'houzez_agency' ) );
    }

    private function is_valid_broker_user( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return false;
        }

        if ( $this->is_proprietario_user( $user->ID ) ) {
            return false;
        }

        if ( ! empty( $user->user_status ) && (int) $user->user_status !== 0 ) {
            return false;
        }

        if ( empty( array_intersect( $this->get_broker_roles(), (array) $user->roles ) ) ) {
            return false;
        }

        return true;
    }

    private function get_broker_profile_summary( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user || ! $this->is_valid_broker_user( $user->ID ) ) {
            return array();
        }

        $user_roles = (array) $user->roles;
        $company = get_user_meta( $user->ID, 'fave_author_company', true );
        $license = get_user_meta( $user->ID, 'fave_author_license', true );
        $tax_no = get_user_meta( $user->ID, 'fave_author_tax_no', true );
        $phone = get_user_meta( $user->ID, 'fave_author_phone', true );
        $mobile = get_user_meta( $user->ID, 'fave_author_mobile', true );
        $whatsapp = get_user_meta( $user->ID, 'fave_author_whatsapp', true );

        return array(
            'id' => (int) $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
            'company' => sanitize_text_field( $company ),
            'license' => sanitize_text_field( $license ),
            'tax_no' => sanitize_text_field( $tax_no ),
            'phone' => sanitize_text_field( $phone ),
            'mobile' => sanitize_text_field( $mobile ),
            'whatsapp' => sanitize_text_field( $whatsapp ),
            'roles' => array_values( array_intersect( $this->get_broker_roles(), $user_roles ) ),
        );
    }

    private function get_deletion_requests_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_deletion_requests';
    }

    private function get_audit_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_audit_logs';
    }

    public function register_role_and_caps() {
        if ( get_option( 'imovel_parceiro_owner_roles_version' ) !== '1.0.0' ) {
            $this->ensure_role_proprietario();
            $this->ensure_capabilities();
            update_option( 'imovel_parceiro_owner_roles_version', '1.0.0', false );
        }

        $this->ensure_owner_membership_exemption_meta();
    }

    private function ensure_role_proprietario() {
        $caps = array(
            'read' => true,
            'upload_files' => true,
            'read_property' => true,
            'edit_property' => true,
            'create_properties' => true,
            'edit_properties' => true,
            'edit_published_properties' => true,
            'delete_properties' => true,
            'delete_published_properties' => true,
            'delete_private_properties' => false,
            self::CAP_OWNER_DASHBOARD => true,
            self::CAP_OWNER_SUBMIT => true,
            self::CAP_OWNER_DOCS => true,
            self::CAP_OWNER_BROKER_CHANGE => true,
            self::CAP_OWNER_DELETE_REQUEST => true,
        );

        $role = get_role( self::ROLE_PROPRIETARIO );
        if ( ! $role ) {
            add_role( self::ROLE_PROPRIETARIO, __( 'Proprietário', 'imovel-parceiro-core' ), $caps );
            return;
        }

        foreach ( $caps as $cap => $allowed ) {
            if ( $allowed ) {
                $role->add_cap( $cap );
            } else {
                $role->remove_cap( $cap );
            }
        }
    }

    private function ensure_capabilities() {
        $admin_roles = array( 'administrator', 'editor', 'houzez_manager' );
        foreach ( $admin_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            $role->add_cap( self::CAP_MANAGE_WORKFLOW );
            $role->add_cap( self::CAP_MANAGE_COMMERCIAL );
            $role->add_cap( self::CAP_OWNER_DASHBOARD );
        }

        $commercial_roles = array( 'houzez_agent', 'houzez_agency', 'houzez_owner', 'houzez_seller' );
        foreach ( $commercial_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            $role->add_cap( self::CAP_MANAGE_COMMERCIAL );
        }
    }

    private function ensure_owner_membership_exemption_meta() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id || ! $this->is_proprietario_user( $user_id ) ) {
            return;
        }

        $values = array(
            'package_listings'              => -1,
            'package_featured_listings'     => 0,
            'user_submit_has_no_membership' => '',
            'user_submitted_without_membership' => '',
        );

        foreach ( $values as $meta_key => $meta_value ) {
            if ( get_user_meta( $user_id, $meta_key, true ) !== $meta_value ) {
                update_user_meta( $user_id, $meta_key, $meta_value );
            }
        }
    }

    public function maybe_ensure_tables() {
        if ( get_option( 'imovel_parceiro_owner_db_version' ) === '1.0.0' ) {
            return;
        }

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $relations = "CREATE TABLE {$this->get_relations_table()} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL,
            broker_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            workflow_status varchar(40) NOT NULL DEFAULT '" . self::WORKFLOW_RASCUNHO . "',
            documentation_status varchar(30) NOT NULL DEFAULT '" . self::DOC_STATUS_PENDENTE . "',
            approval_status varchar(30) NOT NULL DEFAULT '" . self::APPROVAL_STATUS_PENDENTE . "',
            approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            approved_at datetime DEFAULT NULL,
            rejected_reason text DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY property_id (property_id),
            KEY owner_user_id (owner_user_id),
            KEY broker_user_id (broker_user_id),
            KEY workflow_status (workflow_status)
        ) {$charset_collate};";

        $documents = "CREATE TABLE {$this->get_documents_table()} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL,
            doc_type varchar(60) NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'enviado',
            submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_at datetime DEFAULT NULL,
            review_note text DEFAULT '',
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY owner_user_id (owner_user_id),
            KEY status (status)
        ) {$charset_collate};";

        $changes = "CREATE TABLE {$this->get_broker_change_table()} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL,
            current_broker_id bigint(20) unsigned NOT NULL DEFAULT 0,
            requested_broker_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reason text DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pendente',
            admin_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            admin_note text DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY owner_user_id (owner_user_id),
            KEY status (status)
        ) {$charset_collate};";

        $deletions = "CREATE TABLE {$this->get_deletion_requests_table()} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL,
            reason text DEFAULT '',
            has_commercial_history tinyint(1) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'pendente',
            admin_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            admin_note text DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY owner_user_id (owner_user_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $relations );
        dbDelta( $documents );
        dbDelta( $changes );
        dbDelta( $deletions );

        update_option( 'imovel_parceiro_owner_db_version', '1.0.0', false );
    }

    private function is_proprietario_user( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        return self::is_owner_role_user( $user_id );
    }

    public static function is_current_user_proprietario() {
        return self::is_owner_role_user( get_current_user_id() );
    }

    private static function is_owner_role_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        static $cache = array();
        if ( isset( $cache[ $user_id ] ) ) {
            return $cache[ $user_id ];
        }

        $user = get_userdata( $user_id );
        $cache[ $user_id ] = $user ? in_array( self::ROLE_PROPRIETARIO, (array) $user->roles, true ) : false;

        return $cache[ $user_id ];
    }

    private function user_can_manage_workflow() {
        return current_user_can( self::CAP_MANAGE_WORKFLOW ) || current_user_can( 'manage_options' );
    }

    private function get_owner_dashboard_allowed_templates() {
        return array(
            'template/user_dashboard_properties.php',
            'template/user_dashboard_submit.php',
            'template/user_dashboard_profile.php',
            'template/user_dashboard_messages.php',
            'template/user_dashboard.php',
        );
    }

    private function get_owner_dashboard_blocked_templates() {
        return array(
            'template/user_dashboard_crm.php',
            'template/user_dashboard_membership.php',
            'template/user_dashboard_invoices.php',
            'template/user_dashboard_insight.php',
        );
    }

    public function enforce_owner_dashboard_access() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $this->is_proprietario_user( $user_id ) ) {
            return;
        }

        if ( ! function_exists( 'houzez_get_template_link_2' ) ) {
            return;
        }

        $blocked_templates = $this->get_owner_dashboard_blocked_templates();
        foreach ( $blocked_templates as $template ) {
            if ( is_page_template( $template ) ) {
                wp_safe_redirect( houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) );
                exit;
            }
        }

        if ( isset( $_GET['imovel-parceiro'] ) && 'dashboard' === sanitize_key( wp_unslash( $_GET['imovel-parceiro'] ) ) ) {
            wp_safe_redirect( houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) );
            exit;
        }
    }

    public function filter_owner_properties_dashboard_query( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
            return;
        }

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() ) {
            return;
        }

        if ( ! is_page_template( 'template/user_dashboard_properties.php' ) ) {
            return;
        }

        if ( 'property' !== $query->get( 'post_type' ) ) {
            return;
        }

        $property_ids = $this->get_owner_property_ids( get_current_user_id() );
        if ( empty( $property_ids ) ) {
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        $query->set( 'post__in', $property_ids );
    }

    public function enqueue_owner_dashboard_guards() {
        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() ) {
            return;
        }

        if ( ! function_exists( 'houzez_is_dashboard' ) || ! houzez_is_dashboard() ) {
            return;
        }

        $css = '.sidebar-nav a[href*="user_dashboard_crm"],'
            . '.sidebar-nav a[href*="user_dashboard_membership"],'
            . '.sidebar-nav a[href*="user_dashboard_invoices"],'
            . '.sidebar-nav a[href*="user_dashboard_insight"],'
            . '.sidebar-nav a[href*="template-packages"],'
            . '.sidebar-nav a[href*="imovel-parceiro=dashboard"]{display:none !important;}';

        wp_register_style( 'imovel-parceiro-owner-guards', false, array(), IMOVEL_PARCEIRO_CORE_VERSION );
        wp_enqueue_style( 'imovel-parceiro-owner-guards' );
        wp_add_inline_style( 'imovel-parceiro-owner-guards', $css );
    }

    public function filter_houzez_options_for_owner( $options ) {
        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() ) {
            return $options;
        }

        if ( ! is_array( $options ) ) {
            $options = array();
        }

        $options['enable_paid_submission'] = 'no';

        return $options;
    }

    public static function get_owner_dashboard_stats( $owner_user_id ) {
        global $wpdb;

        $owner_user_id = absint( $owner_user_id );
        if ( ! $owner_user_id ) {
            return array(
                'total' => 0,
                'doc_pendente' => 0,
                'aprovados' => 0,
                'em_analise' => 0,
            );
        }

        $relations_table = $wpdb->prefix . 'imovel_parceiro_owner_relations';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relations_table ) );
        if ( $exists !== $relations_table ) {
            return array(
                'total' => 0,
                'doc_pendente' => 0,
                'aprovados' => 0,
                'em_analise' => 0,
            );
        }

        return array(
            'total' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$relations_table} WHERE owner_user_id = %d", $owner_user_id ) ),
            'doc_pendente' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$relations_table} WHERE owner_user_id = %d AND documentation_status IN ('pendente','rejeitada')", $owner_user_id ) ),
            'aprovados' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$relations_table} WHERE owner_user_id = %d AND documentation_status = 'aprovada' AND approval_status = 'aprovado'", $owner_user_id ) ),
            'em_analise' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$relations_table} WHERE owner_user_id = %d AND (workflow_status IN ('DOCUMENTACAO_ENVIADA','EM_ANALISE') OR documentation_status = 'em_analise')", $owner_user_id ) ),
        );
    }

    public static function get_owner_property_ids( $owner_user_id ) {
        global $wpdb;

        $owner_user_id = absint( $owner_user_id );
        if ( ! $owner_user_id ) {
            return array();
        }

        $relations_table = $wpdb->prefix . 'imovel_parceiro_owner_relations';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relations_table ) );
        if ( $exists !== $relations_table ) {
            return array();
        }

        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT property_id FROM {$relations_table} WHERE owner_user_id = %d", $owner_user_id ) );
        if ( empty( $ids ) ) {
            return array();
        }

        return array_values( array_unique( array_map( 'absint', $ids ) ) );
    }

    private function get_owner_term_version() {
        $version = get_option( 'imovel_parceiro_owner_term_version', '' );
        if ( empty( $version ) ) {
            $version = get_option( 'imovel_parceiro_terms_version', '1.0' );
        }

        return (string) $version;
    }

    public function validate_owner_submission( $property ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! $this->is_proprietario_user( $user_id ) ) {
            return $property;
        }

        if ( ! current_user_can( self::CAP_OWNER_SUBMIT ) ) {
            wp_die( esc_html__( 'Voce nao possui permissao para cadastrar ou editar imoveis.', 'imovel-parceiro-core' ) );
        }

        $property_id = $this->get_request_property_id();
        if ( $property_id && ! $this->is_property_owner( $property_id, $user_id ) ) {
            wp_die( esc_html__( 'Voce so pode editar os seus proprios imoveis.', 'imovel-parceiro-core' ) );
        }

        if ( isset( $_POST['property_author'] ) ) {
            $_POST['property_author'] = $user_id;
        }

        if ( isset( $_POST['prop_featured'] ) ) {
            $_POST['prop_featured'] = 0;
        }

        $submission_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        $is_draft = isset( $_POST['houzez_draft'] ) ? sanitize_key( wp_unslash( $_POST['houzez_draft'] ) ) : '';

        if ( 'draft' !== $is_draft && 'save_as_draft' !== $submission_action ) {
            $term_accepted = ! empty( $_POST['imovel_parceiro_owner_authorization_term'] ) || ! empty( $_POST['imovel_parceiro_acceptance']['authorization'] );
            if ( ! $term_accepted ) {
                wp_die( esc_html__( 'E obrigatorio aceitar o termo de autorizacao para envio do imovel para analise.', 'imovel-parceiro-core' ) );
            }
        }

        if ( $property_id && $this->property_has_commercial_activity( $property_id ) && $this->has_critical_change_attempt( $property_id ) ) {
            wp_die( esc_html__( 'Este imovel possui historico comercial e nao permite alteracoes criticas sem revisao administrativa.', 'imovel-parceiro-core' ) );
        }

        return $property;
    }

    private function get_request_property_id() {
        if ( isset( $_POST['id'] ) ) {
            return absint( wp_unslash( $_POST['id'] ) );
        }

        if ( isset( $_POST['prop_id'] ) ) {
            return absint( wp_unslash( $_POST['prop_id'] ) );
        }

        if ( isset( $_POST['property_id'] ) ) {
            return absint( wp_unslash( $_POST['property_id'] ) );
        }

        if ( isset( $_POST['draft_property_id'] ) ) {
            return absint( wp_unslash( $_POST['draft_property_id'] ) );
        }

        return 0;
    }

    private function has_critical_change_attempt( $property_id ) {
        $attempted = false;

        if ( isset( $_POST['property_price'] ) || isset( $_POST['prop_price'] ) ) {
            $current_price = (string) get_post_meta( $property_id, 'fave_property_price', true );
            $new_price = isset( $_POST['property_price'] ) ? wp_unslash( $_POST['property_price'] ) : wp_unslash( $_POST['prop_price'] );
            $normalize = static function( $value ) {
                return preg_replace( '/\D+/', '', (string) $value );
            };

            if ( $normalize( $current_price ) !== $normalize( $new_price ) ) {
                $attempted = true;
            }
        }

        if ( isset( $_POST['property_author'] ) && absint( $_POST['property_author'] ) !== get_current_user_id() ) {
            $attempted = true;
        }

        return $attempted;
    }

    public function enforce_publish_policy_before_approval( $data, $postarr ) {
        if ( ! is_user_logged_in() || empty( $data['post_type'] ) || 'property' !== $data['post_type'] ) {
            return $data;
        }

        $user_id = get_current_user_id();
        if ( ! $this->is_proprietario_user( $user_id ) || current_user_can( 'manage_options' ) ) {
            return $data;
        }

        $property_id = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
        if ( $property_id && ! $this->is_property_owner( $property_id, $user_id ) ) {
            return $data;
        }

        $submission_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        $is_draft = isset( $_POST['houzez_draft'] ) ? sanitize_key( wp_unslash( $_POST['houzez_draft'] ) ) : '';
        if ( 'save_as_draft' === $submission_action || 'draft' === $is_draft ) {
            $data['post_status'] = 'draft';
            return $data;
        }

        if ( $property_id && $this->is_property_publicable( $property_id ) ) {
            return $data;
        }

        if ( 'publish' === $data['post_status'] ) {
            $data['post_status'] = 'pending';
        }

        return $data;
    }

    private function is_property_publicable( $property_id ) {
        $relation = $this->get_relation_by_property( $property_id );
        if ( ! $relation ) {
            return false;
        }

        return self::DOC_STATUS_APROVADA === $relation->documentation_status
            && self::APPROVAL_STATUS_APROVADO === $relation->approval_status
            && self::WORKFLOW_APROVADO === $relation->workflow_status;
    }

    public function register_owner_property_relation( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return;
        }

        $owner_user_id = (int) get_post_field( 'post_author', $property_id );
        if ( ! $owner_user_id || ! $this->is_proprietario_user( $owner_user_id ) ) {
            return;
        }

        $broker_id = (int) get_post_meta( $property_id, self::META_BROKER_ID, true );
        if ( ! $broker_id ) {
            $broker_id = $this->get_default_broker_user_id();
        }

        $status = get_post_status( $property_id );
        $workflow_status = 'draft' === $status ? self::WORKFLOW_RASCUNHO : self::WORKFLOW_DOCUMENTACAO_PENDENTE;

        $now = current_time( 'mysql' );

        global $wpdb;
        $table = $this->get_relations_table();

        $existing = $this->get_relation_by_property( $property_id );
        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'owner_user_id' => $owner_user_id,
                    'broker_user_id' => absint( $broker_id ),
                    'updated_at' => $now,
                ),
                array( 'property_id' => $property_id ),
                array( '%d', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'property_id' => $property_id,
                    'owner_user_id' => $owner_user_id,
                    'broker_user_id' => absint( $broker_id ),
                    'workflow_status' => $workflow_status,
                    'documentation_status' => self::DOC_STATUS_PENDENTE,
                    'approval_status' => self::APPROVAL_STATUS_PENDENTE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
            );
        }

        update_post_meta( $property_id, self::META_OWNER_ID, $owner_user_id );
        update_post_meta( $property_id, self::META_BROKER_ID, absint( $broker_id ) );
        update_post_meta( $property_id, self::META_WORKFLOW_STATUS, $workflow_status );
        update_post_meta( $property_id, self::META_DOC_STATUS, self::DOC_STATUS_PENDENTE );
        update_post_meta( $property_id, self::META_APPROVAL_STATUS, self::APPROVAL_STATUS_PENDENTE );

        if ( ! empty( $_POST['imovel_parceiro_owner_authorization_term'] ) || ! empty( $_POST['imovel_parceiro_acceptance']['authorization'] ) ) {
            update_post_meta( $property_id, self::META_OWNER_TERM_ACCEPTED, 1 );
            update_post_meta( $property_id, self::META_OWNER_TERM_VERSION, $this->get_owner_term_version() );
            update_post_meta( $property_id, self::META_OWNER_TERM_IP, $this->get_request_ip() );
        }

        $this->insert_audit_log(
            'owner_property_registered',
            get_current_user_id(),
            $owner_user_id,
            $property_id,
            array(
                'workflow_status' => $workflow_status,
                'broker_user_id' => absint( $broker_id ),
            )
        );
    }

    public function sync_relation_on_property_save( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! $post instanceof WP_Post || 'property' !== $post->post_type ) {
            return;
        }

        $this->register_owner_property_relation( $post_id );
    }

    private function get_default_broker_user_id() {
        $admins = get_users(
            array(
                'role' => 'administrator',
                'number' => 1,
                'fields' => 'ID',
                'orderby' => 'ID',
                'order' => 'ASC',
            )
        );

        if ( empty( $admins ) ) {
            return 0;
        }

        return absint( $admins[0] );
    }

    private function get_relation_by_property( $property_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_relations_table() . ' WHERE property_id = %d LIMIT 1',
                absint( $property_id )
            )
        );
    }

    private function is_property_owner( $property_id, $user_id ) {
        $relation = $this->get_relation_by_property( $property_id );
        if ( $relation ) {
            return (int) $relation->owner_user_id === (int) $user_id;
        }

        return (int) get_post_field( 'post_author', $property_id ) === (int) $user_id;
    }

    public static function get_property_owner_context( $property_id, $owner_user_id = 0 ) {
        global $wpdb;

        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return array();
        }

        $relations_table = $wpdb->prefix . 'imovel_parceiro_owner_relations';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relations_table ) );
        if ( $exists !== $relations_table ) {
            return array();
        }

        $relation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$relations_table} WHERE property_id = %d LIMIT 1", $property_id ) );
        if ( ! $relation ) {
            return array();
        }

        if ( $owner_user_id && (int) $relation->owner_user_id !== absint( $owner_user_id ) ) {
            return array();
        }

        $broker_id = ! empty( $relation->broker_user_id ) ? absint( $relation->broker_user_id ) : 0;
        $broker = $broker_id ? get_userdata( $broker_id ) : false;

        return array(
            'property_id' => $property_id,
            'owner_user_id' => absint( $relation->owner_user_id ),
            'broker_user_id' => $broker_id,
            'broker_name' => $broker && ! empty( $broker->display_name ) ? $broker->display_name : '',
            'workflow_status' => ! empty( $relation->workflow_status ) ? sanitize_text_field( $relation->workflow_status ) : '',
            'documentation_status' => ! empty( $relation->documentation_status ) ? sanitize_text_field( $relation->documentation_status ) : '',
            'approval_status' => ! empty( $relation->approval_status ) ? sanitize_text_field( $relation->approval_status ) : '',
        );
    }

    private function get_property_broker_id( $property_id ) {
        $relation = $this->get_relation_by_property( $property_id );
        if ( $relation && ! empty( $relation->broker_user_id ) ) {
            return (int) $relation->broker_user_id;
        }

        return (int) get_post_meta( $property_id, self::META_BROKER_ID, true );
    }

    private function property_has_commercial_activity( $property_id ) {
        global $wpdb;

        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return false;
        }

        $tables = array(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            $wpdb->prefix . 'imovel_parceiro_opportunities',
            $wpdb->prefix . 'imovel_parceiro_deals',
            $wpdb->prefix . 'imovel_parceiro_commissions',
        );

        foreach ( $tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                continue;
            }

            if ( false !== strpos( $table, 'imovel_parceiro_commissions' ) ) {
                $deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
                $deals_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $deals_table ) );
                if ( $deals_exists !== $deals_table ) {
                    continue;
                }

                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$table} c
                         INNER JOIN {$deals_table} d ON d.id = c.deal_id
                         WHERE d.property_id = %d",
                        $property_id
                    )
                );
            } else {
                $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE property_id = %d", $property_id ) );
            }

            if ( $count > 0 ) {
                return true;
            }
        }

        return false;
    }

    private function get_request_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            if ( 'HTTP_X_FORWARDED_FOR' === $key && false !== strpos( $raw, ',' ) ) {
                $parts = array_map( 'trim', explode( ',', $raw ) );
                $raw = isset( $parts[0] ) ? $parts[0] : $raw;
            }

            if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
                return $raw;
            }
        }

        return '';
    }

    private function insert_audit_log( $event_type, $actor_user_id, $other_user_id, $property_id, $meta = array(), $partnership_id = 0 ) {
        global $wpdb;

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $audit_table = $this->get_audit_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(80) NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            other_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            property_id bigint(20) unsigned NOT NULL DEFAULT 0,
            partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY property_id (property_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql );

        $payload = array(
            'ip' => $this->get_request_ip(),
            'origin' => wp_doing_ajax() ? 'ajax' : 'frontend',
            'meta' => $meta,
        );

        $wpdb->insert(
            $audit_table,
            array(
                'event_type' => sanitize_key( $event_type ),
                'actor_user_id' => absint( $actor_user_id ),
                'other_user_id' => absint( $other_user_id ),
                'property_id' => absint( $property_id ),
                'partnership_id' => absint( $partnership_id ),
                'meta' => maybe_serialize( $payload ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    public function prevent_owner_hard_delete_with_history( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || 'property' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() ) {
            return;
        }

        if ( ! $this->is_property_owner( $post_id, get_current_user_id() ) ) {
            return;
        }

        if ( $this->property_has_commercial_activity( $post_id ) ) {
            wp_die( esc_html__( 'Nao e permitido excluir definitivamente imoveis com historico comercial. Utilize a solicitacao de exclusao.', 'imovel-parceiro-core' ) );
        }
    }

    private function get_allowed_document_types() {
        $default = array(
            'identificacao' => __( 'Documento de identificacao', 'imovel-parceiro-core' ),
            'matricula' => __( 'Matricula/documentacao do imovel', 'imovel-parceiro-core' ),
            'comprovante_propriedade' => __( 'Comprovante de propriedade', 'imovel-parceiro-core' ),
            'outros' => __( 'Outros documentos', 'imovel-parceiro-core' ),
        );

        return apply_filters( 'imovel_parceiro_owner_allowed_document_types', $default );
    }

    private function validate_uploaded_document( $file ) {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'invalid_file', __( 'Arquivo inválido.', 'imovel-parceiro-core' ) );
        }
        $max_size = 10 * 1024 * 1024;
        if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
            return new WP_Error( 'file_too_large', __( 'O arquivo deve ter no máximo 10MB.', 'imovel-parceiro-core' ) );
        }
        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : '';
            if ( $finfo ) {
                finfo_close( $finfo );
            }
            $allowed_mimes = array( 'image/jpeg', 'image/png', 'application/pdf' );
            $allowed_mimes = apply_filters( 'imovel_parceiro_owner_allowed_mimes', $allowed_mimes );
            if ( ! in_array( $mime, $allowed_mimes, true ) ) {
                return new WP_Error( 'invalid_mime', __( 'Apenas JPG, PNG e PDF são permitidos.', 'imovel-parceiro-core' ) );
            }
        }
        return true;
    }

    private function get_document_status_labels() {
        return array(
            'enviado' => __( 'Pendente', 'imovel-parceiro-core' ),
            'pendente' => __( 'Pendente', 'imovel-parceiro-core' ),
            'aprovado' => __( 'Aprovado', 'imovel-parceiro-core' ),
            'rejeitado' => __( 'Rejeitado', 'imovel-parceiro-core' ),
            'aguardando_informacoes' => __( 'Aguardando informações adicionais', 'imovel-parceiro-core' ),
        );
    }

    private function get_document_status_label( $status ) {
        $status = sanitize_key( (string) $status );
        $labels = $this->get_document_status_labels();
        if ( isset( $labels[ $status ] ) ) {
            return $labels[ $status ];
        }

        return ucfirst( str_replace( '_', ' ', $status ) );
    }

    private function get_document_by_id( $document_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_documents_table() . ' WHERE id = %d LIMIT 1',
                absint( $document_id )
            )
        );
    }

    private function get_authorized_document_record( $document_id ) {
        $document_id = absint( $document_id );
        if ( ! $document_id ) {
            return new WP_Error( 'invalid_document', __( 'Documento inválido.', 'imovel-parceiro-core' ) );
        }

        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'Usuário não autenticado.', 'imovel-parceiro-core' ) );
        }

        if ( ! $this->user_can_manage_workflow() ) {
            return new WP_Error( 'forbidden', __( 'Acesso negado para visualização do documento.', 'imovel-parceiro-core' ) );
        }

        $doc = $this->get_document_by_id( $document_id );
        if ( ! $doc ) {
            return new WP_Error( 'document_not_found', __( 'Documento não encontrado.', 'imovel-parceiro-core' ) );
        }

        $attachment_id = absint( $doc->attachment_id );
        if ( ! $attachment_id ) {
            return new WP_Error( 'invalid_attachment', __( 'Arquivo do documento inválido.', 'imovel-parceiro-core' ) );
        }

        $owner_id_from_meta = (int) get_post_meta( $attachment_id, '_imovel_parceiro_owner_document_owner_id', true );
        if ( $owner_id_from_meta && (int) $owner_id_from_meta !== (int) $doc->owner_user_id ) {
            return new WP_Error( 'owner_mismatch', __( 'Falha de autorização do documento.', 'imovel-parceiro-core' ) );
        }

        $property_id_from_meta = (int) get_post_meta( $attachment_id, '_imovel_parceiro_owner_document_property_id', true );
        if ( $property_id_from_meta && (int) $property_id_from_meta !== (int) $doc->property_id ) {
            return new WP_Error( 'property_mismatch', __( 'Falha de autorização do imóvel do documento.', 'imovel-parceiro-core' ) );
        }

        return $doc;
    }

    private function get_document_file_data( $attachment_id ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) {
            return new WP_Error( 'invalid_attachment', __( 'Arquivo inválido.', 'imovel-parceiro-core' ) );
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'Arquivo não encontrado.', 'imovel-parceiro-core' ) );
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( empty( $mime ) && function_exists( 'mime_content_type' ) ) {
            $mime = mime_content_type( $file_path );
        }
        if ( empty( $mime ) ) {
            $mime = 'application/octet-stream';
        }

        $ext = strtolower( pathinfo( (string) $file_path, PATHINFO_EXTENSION ) );

        return array(
            'path' => $file_path,
            'mime' => $mime,
            'ext' => $ext,
            'size' => filesize( $file_path ),
        );
    }

    private function can_preview_document_mime( $mime, $ext ) {
        $mime = strtolower( (string) $mime );
        $ext = strtolower( (string) $ext );

        if ( 'application/pdf' === $mime ) {
            return true;
        }

        $allowed_image_mimes = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp' );
        $allowed_image_exts = array( 'jpg', 'jpeg', 'png', 'webp' );

        return in_array( $mime, $allowed_image_mimes, true ) || in_array( $ext, $allowed_image_exts, true );
    }

    private function build_secure_document_file_name( $doc ) {
        $doc_type = ! empty( $doc->doc_type ) ? sanitize_file_name( (string) $doc->doc_type ) : 'documento';
        $date = ! empty( $doc->submitted_at ) ? gmdate( 'Ymd', strtotime( $doc->submitted_at ) ) : gmdate( 'Ymd' );
        $file_data = $this->get_document_file_data( (int) $doc->attachment_id );
        $ext = 'bin';

        if ( ! is_wp_error( $file_data ) && ! empty( $file_data['ext'] ) ) {
            $ext = sanitize_file_name( (string) $file_data['ext'] );
        }

        return sprintf( 'owner-documento-%s-%d-%s.%s', $doc_type, absint( $doc->id ), $date, $ext );
    }

    private function get_document_preview_payload( $doc ) {
        $owner_user = get_userdata( (int) $doc->owner_user_id );
        $property_title = get_the_title( (int) $doc->property_id );
        $doc_types = $this->get_allowed_document_types();
        $doc_type_key = sanitize_key( (string) $doc->doc_type );
        $doc_type_label = isset( $doc_types[ $doc_type_key ] ) ? $doc_types[ $doc_type_key ] : $doc_type_key;
        $file_data = $this->get_document_file_data( (int) $doc->attachment_id );
        $is_previewable = ! is_wp_error( $file_data )
            ? $this->can_preview_document_mime( $file_data['mime'], $file_data['ext'] )
            : false;
        $stream_url = add_query_arg(
            array(
                'action' => 'imovel_parceiro_owner_stream_document',
                'nonce' => wp_create_nonce( 'imovel_parceiro_core_nonce' ),
                'document_id' => absint( $doc->id ),
                'mode' => 'inline',
            ),
            admin_url( 'admin-ajax.php' )
        );
        $download_url = add_query_arg(
            array(
                'action' => 'imovel_parceiro_owner_download_document',
                'nonce' => wp_create_nonce( 'imovel_parceiro_core_nonce' ),
                'document_id' => absint( $doc->id ),
                'mode' => 'download',
            ),
            admin_url( 'admin-ajax.php' )
        );

        return array(
            'document_id' => absint( $doc->id ),
            'owner_name' => $owner_user ? $owner_user->display_name : '',
            'property_title' => $property_title ? $property_title : '',
            'document_type' => $doc_type_label,
            'status' => $this->get_document_status_label( $doc->status ),
            'status_key' => sanitize_key( (string) $doc->status ),
            'submitted_at' => ! empty( $doc->submitted_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $doc->submitted_at ) ) : '',
            'review_note' => ! empty( $doc->review_note ) ? sanitize_textarea_field( $doc->review_note ) : '',
            'file_name' => $this->build_secure_document_file_name( $doc ),
            'mime_type' => is_wp_error( $file_data ) ? '' : $file_data['mime'],
            'is_previewable' => $is_previewable,
            'stream_url' => $stream_url,
            'download_url' => $download_url,
            'reviewed_by' => ! empty( $doc->reviewed_by ) ? absint( $doc->reviewed_by ) : 0,
            'reviewed_at' => ! empty( $doc->reviewed_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $doc->reviewed_at ) ) : '',
        );
    }

    public function ajax_owner_upload_document() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_DOCS ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para envio de documentacao.', 'imovel-parceiro-core' ) ) );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $doc_type = isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : '';
        $user_id = get_current_user_id();

        if ( ! $property_id || ! $doc_type || ! isset( $_FILES['document_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para upload.', 'imovel-parceiro-core' ) ) );
        }

        if ( ! $this->is_property_owner( $property_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Voce so pode enviar documentos dos seus proprios imoveis.', 'imovel-parceiro-core' ) ) );
        }

        $allowed_types = $this->get_allowed_document_types();
        if ( ! isset( $allowed_types[ $doc_type ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Tipo de documento nao permitido.', 'imovel-parceiro-core' ) ) );
        }

        $validation = $this->validate_uploaded_document( $_FILES['document_file'] );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        add_filter( 'upload_dir', array( $this, 'filter_private_document_upload_dir' ) );
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'document_file', $property_id );
        remove_filter( 'upload_dir', array( $this, 'filter_private_document_upload_dir' ) );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        update_post_meta( $attachment_id, '_imovel_parceiro_private_owner_document', 1 );
        update_post_meta( $attachment_id, '_imovel_parceiro_owner_document_property_id', $property_id );
        update_post_meta( $attachment_id, '_imovel_parceiro_owner_document_owner_id', $user_id );
        wp_update_post(
            array(
                'ID' => $attachment_id,
                'post_status' => 'private',
            )
        );

        global $wpdb;
        $wpdb->insert(
            $this->get_documents_table(),
            array(
                'property_id' => $property_id,
                'owner_user_id' => $user_id,
                'doc_type' => $doc_type,
                'attachment_id' => $attachment_id,
                'status' => 'enviado',
                'submitted_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s' )
        );

        $this->update_relation_statuses(
            $property_id,
            array(
                'workflow_status' => self::WORKFLOW_DOCUMENTACAO_ENVIADA,
                'documentation_status' => self::DOC_STATUS_EM_ANALISE,
                'approval_status' => self::APPROVAL_STATUS_PENDENTE,
            )
        );

        $this->insert_audit_log(
            'owner_document_submitted',
            $user_id,
            0,
            $property_id,
            array(
                'document_id' => absint( $wpdb->insert_id ),
                'doc_type' => $doc_type,
                'attachment_id' => $attachment_id,
            )
        );

        $dash_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );
        $verify_url = add_query_arg(
            array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'verification_requests' ),
            $dash_url
        );
        $this->notify_admins(
            __( 'Nova documentacao enviada para analise', 'imovel-parceiro-core' ),
            sprintf(
                __( 'O proprietario #%1$d enviou documentacao para o imovel #%2$d.', 'imovel-parceiro-core' ),
                $user_id,
                $property_id
            ) . "\n\n" . __( 'Analisar documentação:', 'imovel-parceiro-core' ) . ' ' . $verify_url
        );

        $admin_users = get_users(
            array(
                'role__in' => array( 'administrator', 'houzez_manager' ),
                'fields' => array( 'ID' ),
                'number' => 50,
            )
        );
        $this->notify_users_with_in_app_notifications(
            wp_list_pluck( $admin_users, 'ID' ),
            array(
                'property_id' => $property_id,
                'type' => 'DOCUMENTACAO_ENVIADA',
                'category' => IPC_Notifications::CATEGORY_DOCUMENTACAO,
                'title' => __( 'Documentação enviada', 'imovel-parceiro-core' ),
                'message' => sprintf( __( 'O proprietário enviou documentação para %s.', 'imovel-parceiro-core' ), get_the_title( $property_id ) ),
                'url' => IPC_Notifications::get_dashboard_url( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'verification_requests' ) ),
                'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
            )
        );

        wp_send_json_success( array( 'message' => __( 'Documentacao enviada para analise.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_owner_stage_document() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_DOCS ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para envio de documentacao.', 'imovel-parceiro-core' ) ) );
        }

        $doc_type = isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : '';
        if ( ! $doc_type || ! isset( $_FILES['document_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para upload.', 'imovel-parceiro-core' ) ) );
        }

        $allowed_types = $this->get_allowed_document_types();
        if ( ! isset( $allowed_types[ $doc_type ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Tipo de documento nao permitido.', 'imovel-parceiro-core' ) ) );
        }

        $validation = $this->validate_uploaded_document( $_FILES['document_file'] );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        add_filter( 'upload_dir', array( $this, 'filter_private_document_upload_dir' ) );
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'document_file', 0 );
        remove_filter( 'upload_dir', array( $this, 'filter_private_document_upload_dir' ) );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        $user_id = get_current_user_id();
        update_post_meta( $attachment_id, '_imovel_parceiro_staged_document', 1 );
        update_post_meta( $attachment_id, '_imovel_parceiro_staged_document_type', $doc_type );
        update_post_meta( $attachment_id, '_imovel_parceiro_staged_document_owner', $user_id );
        wp_update_post(
            array(
                'ID' => $attachment_id,
                'post_status' => 'private',
            )
        );

        wp_send_json_success(
            array(
                'attachment_id' => $attachment_id,
                'file_name' => get_the_title( $attachment_id ),
                'doc_type' => $doc_type,
            )
        );
    }

    public function ajax_owner_remove_staged_document() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_DOCS ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        if ( ! $attachment_id ) {
            wp_send_json_error( array( 'message' => __( 'Documento invalido.', 'imovel-parceiro-core' ) ) );
        }

        if ( ! get_post_meta( $attachment_id, '_imovel_parceiro_staged_document', true )
            || (int) get_post_meta( $attachment_id, '_imovel_parceiro_staged_document_owner', true ) !== get_current_user_id() ) {
            wp_send_json_error( array( 'message' => __( 'Documento nao encontrado.', 'imovel-parceiro-core' ) ) );
        }

        wp_delete_attachment( $attachment_id, true );

        wp_send_json_success( array( 'message' => __( 'Documento removido.', 'imovel-parceiro-core' ) ) );
    }

    public function consume_staged_documents( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return;
        }

        $owner_user_id = (int) get_post_field( 'post_author', $property_id );
        if ( ! $owner_user_id || ! $this->is_proprietario_user( $owner_user_id ) ) {
            return;
        }

        if ( empty( $_POST['imovel_owner_documents'] ) || ! is_array( $_POST['imovel_owner_documents'] ) ) {
            return;
        }

        $allowed_types = $this->get_allowed_document_types();
        $submitted = array();

        foreach ( $_POST['imovel_owner_documents'] as $doc_type => $attachment_id ) {
            $doc_type = sanitize_key( (string) $doc_type );
            $attachment_id = absint( $attachment_id );

            if ( ! isset( $allowed_types[ $doc_type ] ) || ! $attachment_id ) {
                continue;
            }

            if ( ! get_post_meta( $attachment_id, '_imovel_parceiro_staged_document', true )
                || (int) get_post_meta( $attachment_id, '_imovel_parceiro_staged_document_owner', true ) !== $owner_user_id ) {
                continue;
            }

            wp_update_post(
                array(
                    'ID' => $attachment_id,
                    'post_parent' => $property_id,
                    'post_status' => 'private',
                )
            );
            update_post_meta( $attachment_id, '_imovel_parceiro_private_owner_document', 1 );
            update_post_meta( $attachment_id, '_imovel_parceiro_owner_document_property_id', $property_id );
            update_post_meta( $attachment_id, '_imovel_parceiro_owner_document_owner_id', $owner_user_id );
            delete_post_meta( $attachment_id, '_imovel_parceiro_staged_document' );
            delete_post_meta( $attachment_id, '_imovel_parceiro_staged_document_type' );
            delete_post_meta( $attachment_id, '_imovel_parceiro_staged_document_owner' );

            global $wpdb;
            $wpdb->insert(
                $this->get_documents_table(),
                array(
                    'property_id' => $property_id,
                    'owner_user_id' => $owner_user_id,
                    'doc_type' => $doc_type,
                    'attachment_id' => $attachment_id,
                    'status' => 'enviado',
                    'submitted_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%d', '%s', '%s' )
            );

            $submitted[] = $doc_type;
        }

        if ( empty( $submitted ) ) {
            return;
        }

        $this->update_relation_statuses(
            $property_id,
            array(
                'workflow_status' => self::WORKFLOW_DOCUMENTACAO_ENVIADA,
                'documentation_status' => self::DOC_STATUS_EM_ANALISE,
                'approval_status' => self::APPROVAL_STATUS_PENDENTE,
            )
        );

        $this->insert_audit_log(
            'owner_document_submitted',
            get_current_user_id(),
            $owner_user_id,
            $property_id,
            array(
                'doc_types' => $submitted,
            )
        );

        $dash_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );
        $verify_url = add_query_arg(
            array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'verification_requests' ),
            $dash_url
        );
        $this->notify_admins(
            __( 'Nova documentacao enviada para analise', 'imovel-parceiro-core' ),
            sprintf(
                __( 'O proprietario #%1$d enviou documentacao para o imovel #%2$d.', 'imovel-parceiro-core' ),
                $owner_user_id,
                $property_id
            ) . "\n\n" . __( 'Analisar documentação:', 'imovel-parceiro-core' ) . ' ' . $verify_url
        );

        $admin_users = get_users(
            array(
                'role__in' => array( 'administrator', 'houzez_manager' ),
                'fields' => array( 'ID' ),
                'number' => 50,
            )
        );
        $this->notify_users_with_in_app_notifications(
            wp_list_pluck( $admin_users, 'ID' ),
            array(
                'property_id' => $property_id,
                'type' => 'DOCUMENTACAO_ENVIADA',
                'category' => IPC_Notifications::CATEGORY_DOCUMENTACAO,
                'title' => __( 'Documentação enviada', 'imovel-parceiro-core' ),
                'message' => sprintf( __( 'O proprietário enviou documentação para %s.', 'imovel-parceiro-core' ), get_the_title( $property_id ) ),
                'url' => IPC_Notifications::get_dashboard_url( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'verification_requests' ) ),
                'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
            )
        );
    }

    public static function owner_required_document_types() {
        return array(
            'identificacao' => __( 'Documento de identificação', 'imovel-parceiro-core' ),
            'matricula' => __( 'Matrícula/documentação do imóvel', 'imovel-parceiro-core' ),
            'comprovante_propriedade' => __( 'Comprovante de propriedade', 'imovel-parceiro-core' ),
        );
    }

    public static function get_property_documents( $property_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_owner_documents';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE property_id = %d ORDER BY id DESC",
                absint( $property_id )
            )
        );
    }

    public function filter_private_document_upload_dir( $dirs ) {
        $subdir = '/imovel-parceiro-docs';
        $dirs['subdir'] = $subdir;
        $dirs['path'] = $dirs['basedir'] . $subdir;
        $dirs['url'] = $dirs['baseurl'] . $subdir;

        wp_mkdir_p( $dirs['path'] );
        $index_file = trailingslashit( $dirs['path'] ) . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
        }

        $htaccess = trailingslashit( $dirs['path'] ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" );
        }

        return $dirs;
    }

    public function hide_private_document_urls( $url, $post_id ) {
        if ( ! $post_id ) {
            return $url;
        }

        if ( ! get_post_meta( $post_id, '_imovel_parceiro_private_owner_document', true ) ) {
            return $url;
        }

        if ( current_user_can( 'manage_options' ) || current_user_can( self::CAP_MANAGE_WORKFLOW ) ) {
            return $url;
        }

        return '';
    }

    public function ajax_owner_request_broker_change() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_BROKER_CHANGE ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para solicitacao de troca de corretor.', 'imovel-parceiro-core' ) ) );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $requested_broker_id = isset( $_POST['requested_broker_id'] ) ? absint( $_POST['requested_broker_id'] ) : 0;
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $owner_user_id = get_current_user_id();

        if ( ! $property_id || ! $requested_broker_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para troca de corretor.', 'imovel-parceiro-core' ) ) );
        }

        if ( ! $this->is_property_owner( $property_id, $owner_user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Voce so pode solicitar troca de corretor dos seus imoveis.', 'imovel-parceiro-core' ) ) );
        }

        if ( ! $this->is_valid_broker_user( $requested_broker_id ) ) {
            wp_send_json_error( array( 'message' => __( 'O corretor selecionado nao esta disponivel para essa operacao.', 'imovel-parceiro-core' ) ) );
        }

        $current_broker_id = $this->get_property_broker_id( $property_id );
        if ( $current_broker_id && $current_broker_id === $requested_broker_id ) {
            wp_send_json_error( array( 'message' => __( 'O corretor informado ja esta associado a este imovel.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $wpdb->insert(
            $this->get_broker_change_table(),
            array(
                'property_id' => $property_id,
                'owner_user_id' => $owner_user_id,
                'current_broker_id' => $current_broker_id,
                'requested_broker_id' => $requested_broker_id,
                'reason' => $reason,
                'status' => 'pendente',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
        );

        $this->insert_audit_log(
            'owner_requested_broker_change',
            $owner_user_id,
            $requested_broker_id,
            $property_id,
            array(
                'current_broker_id' => $current_broker_id,
                'requested_broker_id' => $requested_broker_id,
                'reason' => $reason,
            )
        );

        $this->notify_admins(
            __( 'Nova solicitacao de troca de corretor', 'imovel-parceiro-core' ),
            sprintf( __( 'Ha uma nova solicitacao de troca de corretor para o imovel #%d.', 'imovel-parceiro-core' ), $property_id )
        );

        $admin_users = get_users(
            array(
                'role__in' => array( 'administrator', 'houzez_manager' ),
                'fields' => array( 'ID' ),
                'number' => 50,
            )
        );
        $this->notify_users_with_in_app_notifications(
            wp_list_pluck( $admin_users, 'ID' ),
            array(
                'property_id' => $property_id,
                'type' => 'SOLICITACAO_TROCA_CORRETOR',
                'category' => IPC_Notifications::CATEGORY_SISTEMA,
                'title' => __( 'Solicitação de troca de corretor', 'imovel-parceiro-core' ),
                'message' => sprintf( __( 'Existe uma nova solicitação de troca de corretor para o imóvel #%d.', 'imovel-parceiro-core' ), $property_id ),
                'url' => IPC_Notifications::get_dashboard_url( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'broker_changes' ) ),
                'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
            )
        );

        if ( $current_broker_id ) {
            $this->notify_user(
                $current_broker_id,
                __( 'Solicitacao de troca de corretor recebida', 'imovel-parceiro-core' ),
                sprintf( __( 'O proprietario do imovel #%d solicitou troca de corretor. Aguarde analise administrativa.', 'imovel-parceiro-core' ), $property_id )
            );

            IPC_Notifications::send(
                array(
                    'user_id' => $current_broker_id,
                    'property_id' => $property_id,
                    'type' => 'SOLICITACAO_TROCA_CORRETOR',
                    'category' => IPC_Notifications::CATEGORY_SISTEMA,
                    'title' => __( 'Solicitação de troca de corretor', 'imovel-parceiro-core' ),
                    'message' => sprintf( __( 'O proprietário do imóvel #%d solicitou troca de corretor.', 'imovel-parceiro-core' ), $property_id ),
                    'url' => get_permalink( $property_id ),
                    'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Solicitacao de troca enviada para analise administrativa.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_owner_search_brokers() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_BROKER_CHANGE ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para busca de corretores.', 'imovel-parceiro-core' ) ) );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $owner_user_id = get_current_user_id();

        if ( ! $property_id || ! $this->is_property_owner( $property_id, $owner_user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Imovel invalido para esta busca.', 'imovel-parceiro-core' ) ) );
        }

        $query_args = array(
            'number' => 12,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'role__in' => $this->get_broker_roles(),
            'fields' => array( 'ID', 'display_name', 'user_email', 'user_login' ),
        );

        if ( '' !== $search_term ) {
            $query_args['search'] = '*' . $search_term . '*';
            $query_args['search_columns'] = array( 'display_name', 'user_email', 'user_login' );
        }

        $current_broker_id = (int) $this->get_property_broker_id( $property_id );
        if ( $current_broker_id ) {
            $query_args['exclude'] = array( $current_broker_id );
        }

        $results = array();
        foreach ( get_users( $query_args ) as $user ) {
            $profile = $this->get_broker_profile_summary( $user->ID );
            if ( ! empty( $profile ) ) {
                $results[] = $profile;
            }
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    public function ajax_admin_process_broker_change() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! $this->user_can_manage_workflow() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para processar troca de corretor.', 'imovel-parceiro-core' ) ) );
        }

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $admin_note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';

        if ( ! $request_id || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para processamento da troca.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $table = $this->get_broker_change_table();
        $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $request_id ) );

        if ( ! $request || 'pendente' !== $request->status ) {
            wp_send_json_error( array( 'message' => __( 'Solicitacao nao encontrada ou ja processada.', 'imovel-parceiro-core' ) ) );
        }

        $actor_user_id = get_current_user_id();
        $property_id = (int) $request->property_id;
        $owner_user_id = (int) $request->owner_user_id;
        $current_broker_id = (int) $request->current_broker_id;
        $requested_broker_id = (int) $request->requested_broker_id;
        $new_status = 'approve' === $decision ? 'aprovada' : 'rejeitada';
        $cancellation_reason = 'TROCA_DE_CORRETOR_APROVADA';

        if ( 'approve' === $decision ) {
            if ( ! $property_id || ! $this->is_property_owner( $property_id, $owner_user_id ) ) {
                wp_send_json_error( array( 'message' => __( 'Imóvel ou proprietário inválido para concluir a troca.', 'imovel-parceiro-core' ) ) );
            }

            if ( ! $this->is_valid_broker_user( $requested_broker_id ) ) {
                wp_send_json_error( array( 'message' => __( 'Corretor solicitado inválido para concluir a troca.', 'imovel-parceiro-core' ) ) );
            }

            $tx_started = $this->begin_database_transaction();

            $updated_request = $wpdb->update(
                $table,
                array(
                    'status' => $new_status,
                    'admin_user_id' => $actor_user_id,
                    'admin_note' => $admin_note,
                    'processed_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $request_id ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated_request ) {
                if ( $tx_started ) {
                    $this->rollback_database_transaction();
                }
                wp_send_json_error( array( 'message' => __( 'Falha ao atualizar a solicitação de troca de corretor.', 'imovel-parceiro-core' ) ) );
            }

            $broker_updated = $this->update_property_broker_relation( $property_id, $requested_broker_id );
            if ( ! $broker_updated ) {
                if ( $tx_started ) {
                    $this->rollback_database_transaction();
                }
                wp_send_json_error( array( 'message' => __( 'Falha ao definir o novo corretor do imóvel.', 'imovel-parceiro-core' ) ) );
            }

            $cancel_result = $this->cancel_property_partnerships_after_broker_approval(
                $property_id,
                $actor_user_id,
                $cancellation_reason,
                $request_id,
                $current_broker_id,
                $requested_broker_id
            );

            if ( is_wp_error( $cancel_result ) ) {
                if ( $tx_started ) {
                    $this->rollback_database_transaction();
                }
                wp_send_json_error( array( 'message' => $cancel_result->get_error_message() ) );
            }

            if ( $tx_started && ! $this->commit_database_transaction() ) {
                $this->rollback_database_transaction();
                wp_send_json_error( array( 'message' => __( 'Falha ao confirmar transação da troca de corretor.', 'imovel-parceiro-core' ) ) );
            }

            $cancelled_rows = ! empty( $cancel_result['cancelled_rows'] ) && is_array( $cancel_result['cancelled_rows'] ) ? $cancel_result['cancelled_rows'] : array();
            $found_ids = ! empty( $cancel_result['found_ids'] ) && is_array( $cancel_result['found_ids'] ) ? $cancel_result['found_ids'] : array();

            $this->insert_audit_log(
                'admin_processed_broker_change',
                $actor_user_id,
                $owner_user_id,
                $property_id,
                array(
                    'request_id' => $request_id,
                    'decision' => $new_status,
                    'current_broker_id' => $current_broker_id,
                    'requested_broker_id' => $requested_broker_id,
                    'admin_note' => $admin_note,
                    'partnerships_found' => array_values( array_map( 'absint', $found_ids ) ),
                    'partnerships_cancelled' => array_values( array_map( 'absint', wp_list_pluck( $cancelled_rows, 'id' ) ) ),
                    'partnership_cancel_reason' => $cancellation_reason,
                )
            );

            foreach ( $cancelled_rows as $cancelled_row ) {
                $other_user_id = isset( $cancelled_row['requester_id'] ) ? absint( $cancelled_row['requester_id'] ) : 0;
                if ( $other_user_id === $owner_user_id ) {
                    $other_user_id = isset( $cancelled_row['owner_id'] ) ? absint( $cancelled_row['owner_id'] ) : 0;
                }

                $this->insert_audit_log(
                    'partnership_cancelled',
                    $actor_user_id,
                    $other_user_id,
                    $property_id,
                    array(
                        'request_id' => $request_id,
                        'previous_status' => isset( $cancelled_row['previous_status'] ) ? sanitize_key( $cancelled_row['previous_status'] ) : '',
                        'new_status' => isset( $cancelled_row['new_status'] ) ? sanitize_key( $cancelled_row['new_status'] ) : $this->get_partnership_status_to_apply_on_broker_change(),
                        'reason' => $cancellation_reason,
                        'current_broker_id' => $current_broker_id,
                        'requested_broker_id' => $requested_broker_id,
                    ),
                    isset( $cancelled_row['id'] ) ? absint( $cancelled_row['id'] ) : 0
                );

                $this->notify_partnership_cancellation_due_broker_change( $cancelled_row, $property_id );
            }
        } else {
            $updated_request = $wpdb->update(
                $table,
                array(
                    'status' => $new_status,
                    'admin_user_id' => $actor_user_id,
                    'admin_note' => $admin_note,
                    'processed_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $request_id ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated_request ) {
                wp_send_json_error( array( 'message' => __( 'Falha ao processar a rejeição da troca de corretor.', 'imovel-parceiro-core' ) ) );
            }

            $this->insert_audit_log(
                'admin_processed_broker_change',
                $actor_user_id,
                $owner_user_id,
                $property_id,
                array(
                    'request_id' => $request_id,
                    'decision' => $new_status,
                    'current_broker_id' => $current_broker_id,
                    'requested_broker_id' => $requested_broker_id,
                    'admin_note' => $admin_note,
                )
            );
        }

        $this->notify_user(
            $owner_user_id,
            __( 'Solicitacao de troca de corretor processada', 'imovel-parceiro-core' ),
            'approve' === $decision
                ? __( 'Sua solicitacao de troca foi aprovada e o novo corretor foi associado.', 'imovel-parceiro-core' )
                : __( 'Sua solicitacao de troca foi rejeitada pela administracao.', 'imovel-parceiro-core' )
        );

        IPC_Notifications::send(
            array(
                'user_id' => $owner_user_id,
                'property_id' => $property_id,
                'type' => 'approve' === $decision ? 'TROCA_CORRETOR_APROVADA' : 'SOLICITACAO_TROCA_CORRETOR_REJEITADA',
                'category' => IPC_Notifications::CATEGORY_SISTEMA,
                'title' => 'approve' === $decision ? __( 'Troca de corretor aprovada', 'imovel-parceiro-core' ) : __( 'Troca de corretor rejeitada', 'imovel-parceiro-core' ),
                'message' => 'approve' === $decision
                    ? __( 'Sua solicitação de troca foi aprovada e o novo corretor foi associado.', 'imovel-parceiro-core' )
                    : __( 'Sua solicitação de troca foi rejeitada pela administração.', 'imovel-parceiro-core' ),
                'url' => IPC_Notifications::get_dashboard_url( array( 'imovel_owner_area' => 'documentacao' ) ),
                'priority' => 'approve' === $decision ? IPC_Notifications::PRIORITY_NORMAL : IPC_Notifications::PRIORITY_IMPORTANT,
            )
        );

        if ( ! empty( $current_broker_id ) ) {
            $this->notify_user(
            $current_broker_id,
                __( 'Atualizacao de solicitacao de troca de corretor', 'imovel-parceiro-core' ),
                'approve' === $decision
                    ? __( 'A administracao aprovou a troca de corretor deste imovel.', 'imovel-parceiro-core' )
                    : __( 'A administracao rejeitou a troca de corretor deste imovel.', 'imovel-parceiro-core' )
            );

            IPC_Notifications::send(
                array(
                    'user_id' => $current_broker_id,
                    'property_id' => $property_id,
                    'type' => 'approve' === $decision ? 'TROCA_CORRETOR_APROVADA' : 'SOLICITACAO_TROCA_CORRETOR_REJEITADA',
                    'category' => IPC_Notifications::CATEGORY_SISTEMA,
                    'title' => 'approve' === $decision ? __( 'Troca de corretor aprovada', 'imovel-parceiro-core' ) : __( 'Troca de corretor rejeitada', 'imovel-parceiro-core' ),
                    'message' => 'approve' === $decision
                        ? __( 'A administração aprovou a troca de corretor deste imóvel.', 'imovel-parceiro-core' )
                        : __( 'A administração rejeitou a troca de corretor deste imóvel.', 'imovel-parceiro-core' ),
                    'url' => get_permalink( $property_id ),
                    'priority' => 'approve' === $decision ? IPC_Notifications::PRIORITY_NORMAL : IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );
        }

        if ( 'approve' === $decision && ! empty( $requested_broker_id ) ) {
            $this->notify_user(
                $requested_broker_id,
                __( 'Você assumiu um imóvel como corretor responsável', 'imovel-parceiro-core' ),
                __( 'A troca de corretor foi aprovada e você foi definido como responsável pelo imóvel.', 'imovel-parceiro-core' )
            );

            if ( class_exists( 'IPC_Notifications' ) ) {
                IPC_Notifications::send(
                    array(
                        'user_id' => $requested_broker_id,
                        'property_id' => $property_id,
                        'type' => 'TROCA_CORRETOR_APROVADA',
                        'category' => IPC_Notifications::CATEGORY_SISTEMA,
                        'title' => __( 'Novo imóvel sob sua responsabilidade', 'imovel-parceiro-core' ),
                        'message' => __( 'A troca de corretor foi aprovada e este imóvel agora está sob sua responsabilidade.', 'imovel-parceiro-core' ),
                        'url' => get_permalink( $property_id ),
                        'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
                    )
                );
            }
        }

        wp_send_json_success( array( 'message' => __( 'Solicitacao processada com sucesso.', 'imovel-parceiro-core' ) ) );
    }

    private function update_property_broker_relation( $property_id, $broker_user_id ) {
        global $wpdb;

        $property_id = absint( $property_id );
        $broker_user_id = absint( $broker_user_id );

        if ( ! $property_id || ! $broker_user_id ) {
            return false;
        }

        $table = $this->get_relations_table();
        $existing = $this->get_relation_by_property( $property_id );

        if ( $existing ) {
            $updated = $wpdb->update(
                $table,
                array(
                    'broker_user_id' => $broker_user_id,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'property_id' => $property_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return false;
            }
        }

        update_post_meta( $property_id, self::META_BROKER_ID, $broker_user_id );

        return (int) get_post_meta( $property_id, self::META_BROKER_ID, true ) === $broker_user_id;
    }

    public function ajax_admin_review_document() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! $this->user_can_manage_workflow() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para analise documental.', 'imovel-parceiro-core' ) ) );
        }

        $document_id = isset( $_POST['document_id'] ) ? absint( $_POST['document_id'] ) : 0;
        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $review_note = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';

        if ( ! $document_id || ! in_array( $decision, array( 'approve', 'reject', 'request_info' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para revisao documental.', 'imovel-parceiro-core' ) ) );
        }

        if ( in_array( $decision, array( 'reject', 'request_info' ), true ) && '' === trim( $review_note ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe um motivo para rejeitar ou solicitar informações adicionais.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $table = $this->get_documents_table();
        $document = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $document_id ) );

        if ( ! $document ) {
            wp_send_json_error( array( 'message' => __( 'Documento nao encontrado.', 'imovel-parceiro-core' ) ) );
        }

        $previous_status = sanitize_key( (string) $document->status );
        $new_status = 'approve' === $decision ? 'aprovado' : ( 'reject' === $decision ? 'rejeitado' : 'aguardando_informacoes' );
        $wpdb->update(
            $table,
            array(
                'status' => $new_status,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time( 'mysql' ),
                'review_note' => $review_note,
            ),
            array( 'id' => $document_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        $property_id = (int) $document->property_id;
        $owner_id = (int) $document->owner_user_id;
        $admin_id = get_current_user_id();
        $all_docs_approved = false;
        $should_mark_property_approved = false;

        if ( 'approve' === $decision ) {
            $all_docs_approved = $this->all_property_documents_are_approved( $property_id );
            $should_mark_property_approved = $all_docs_approved;

            if ( $should_mark_property_approved ) {
                $this->update_relation_statuses(
                    $property_id,
                    array(
                        'workflow_status' => self::WORKFLOW_APROVADO,
                        'documentation_status' => self::DOC_STATUS_APROVADA,
                        'approval_status' => self::APPROVAL_STATUS_APROVADO,
                        'approved_by' => $admin_id,
                        'approved_at' => current_time( 'mysql' ),
                        'rejected_reason' => '',
                    )
                );

                $this->ensure_property_post_status( $property_id, 'publish' );
            } else {
                $this->update_relation_statuses(
                    $property_id,
                    array(
                        'workflow_status' => self::WORKFLOW_EM_ANALISE,
                        'documentation_status' => self::DOC_STATUS_EM_ANALISE,
                        'approval_status' => self::APPROVAL_STATUS_PENDENTE,
                        'approved_by' => $admin_id,
                        'approved_at' => current_time( 'mysql' ),
                        'rejected_reason' => '',
                    )
                );

                $this->ensure_property_post_status( $property_id, 'pending' );
            }
        } elseif ( 'reject' === $decision ) {
            $this->update_relation_statuses(
                $property_id,
                array(
                    'workflow_status' => self::WORKFLOW_REJEITADO,
                    'documentation_status' => self::DOC_STATUS_REJEITADA,
                    'approval_status' => self::APPROVAL_STATUS_REJEITADO,
                    'approved_by' => $admin_id,
                    'approved_at' => current_time( 'mysql' ),
                    'rejected_reason' => $review_note,
                )
            );

            $this->ensure_property_post_status( $property_id, 'pending' );
        } else {
            $this->update_relation_statuses(
                $property_id,
                array(
                    'workflow_status' => self::WORKFLOW_EM_ANALISE,
                    'documentation_status' => self::DOC_STATUS_EM_ANALISE,
                    'approval_status' => self::APPROVAL_STATUS_PENDENTE,
                    'approved_by' => $admin_id,
                    'approved_at' => current_time( 'mysql' ),
                    'rejected_reason' => $review_note,
                )
            );

            $this->ensure_property_post_status( $property_id, 'pending' );
        }

        $relation = $this->get_relation_by_property( (int) $document->property_id );
        $broker_user_id = $relation ? (int) $relation->broker_user_id : 0;

        $this->insert_audit_log(
            'admin_reviewed_property_document',
            get_current_user_id(),
            (int) $document->owner_user_id,
            $property_id,
            array(
                'document_id' => $document_id,
                'previous_status' => $previous_status,
                'decision' => $new_status,
                'review_note' => $review_note,
                'broker_user_id' => $broker_user_id,
                'all_documents_approved' => $all_docs_approved ? 1 : 0,
                'property_marked_approved' => $should_mark_property_approved ? 1 : 0,
            )
        );

        $decision_text = 'approve' === $decision
            ? ( $should_mark_property_approved
                ? __( 'Documentacao do imovel aprovada.', 'imovel-parceiro-core' )
                : __( 'Documento aprovado. Ainda existem pendencias para aprovacao final do imovel.', 'imovel-parceiro-core' ) )
            : ( 'reject' === $decision
                ? __( 'Documentacao do imovel rejeitada.', 'imovel-parceiro-core' )
                : __( 'Necessario enviar informacoes/documentos adicionais para continuidade da analise.', 'imovel-parceiro-core' ) );

        $owner_obj = get_userdata( $owner_id );
        $owner_name = $owner_obj ? $owner_obj->display_name : __( 'Usuário', 'imovel-parceiro-core' );
        $property_title = $property_id ? get_the_title( $property_id ) : '';
        $docs_url = class_exists( 'IPC_Notifications' )
            ? IPC_Notifications::get_dashboard_url( array( 'imovel_owner_area' => 'documentacao' ) )
            : home_url( '/dashboard/' );

        if ( 'approve' === $decision && $should_mark_property_approved ) {
            $subject = __( 'Documentação aprovada — sua conta está verificada!', 'imovel-parceiro-core' );
            $body = implode( "\n", array(
                sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $owner_name ),
                '',
                __( 'Temos uma ótima notícia: a sua documentação foi aprovada e a sua conta está verificada.', 'imovel-parceiro-core' ),
                '',
                sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property_title ? $property_title : '—' ),
                '',
                __( 'Próximos passos:', 'imovel-parceiro-core' ),
                __( '• Você já pode publicar e administrar seus imóveis no dashboard;', 'imovel-parceiro-core' ),
                __( '• Acompanhe parcerias, documentação e oportunidades por lá;', 'imovel-parceiro-core' ),
                '',
                strtoupper( __( 'Acessar dashboard', 'imovel-parceiro-core' ) ) . ': ' . $docs_url,
                '',
                __( 'Atenciosamente,', 'imovel-parceiro-core' ),
                __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
            ) );
        } else {
            $subject = __( 'Resultado da análise documental', 'imovel-parceiro-core' );
            $body = implode( "\n", array(
                sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $owner_name ),
                '',
                sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property_title ? $property_title : '—' ),
                '',
                $decision_text,
                '',
                ( '' !== trim( $review_note ) ? sprintf( __( 'Observação da análise: %s', 'imovel-parceiro-core' ), $review_note ) : __( 'Acesse o dashboard para mais detalhes.', 'imovel-parceiro-core' ) ),
                '',
                strtoupper( __( 'Acessar dashboard', 'imovel-parceiro-core' ) ) . ': ' . $docs_url,
                '',
                __( 'Atenciosamente,', 'imovel-parceiro-core' ),
                __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
            ) );
        }

        $this->notify_user( $owner_id, $subject, $body );

        if ( class_exists( 'IPC_Notifications' ) ) {
            IPC_Notifications::send(
                array(
                    'user_id' => $owner_id,
                    'property_id' => $property_id,
                    'type' => 'approve' === $decision ? 'DOCUMENTACAO_APROVADA' : ( 'reject' === $decision ? 'DOCUMENTACAO_REJEITADA' : 'DOCUMENTACAO_INFO_ADICIONAL' ),
                    'category' => IPC_Notifications::CATEGORY_DOCUMENTACAO,
                    'title' => 'approve' === $decision
                        ? __( 'Documentação aprovada', 'imovel-parceiro-core' )
                        : ( 'reject' === $decision
                            ? __( 'Documentação rejeitada', 'imovel-parceiro-core' )
                            : __( 'Informações adicionais necessárias', 'imovel-parceiro-core' ) ),
                    'message' => $decision_text,
                    'url' => IPC_Notifications::get_dashboard_url( array( 'imovel_owner_area' => 'documentacao' ) ),
                    'priority' => 'approve' === $decision ? IPC_Notifications::PRIORITY_NORMAL : IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );

            if ( 'approve' === $decision && $should_mark_property_approved ) {
                IPC_Notifications::send(
                    array(
                        'user_id' => $owner_id,
                        'property_id' => $property_id,
                        'type' => 'IMOVEL_APROVADO',
                        'category' => IPC_Notifications::CATEGORY_PROPRIEDADES,
                        'title' => __( 'Imóvel aprovado', 'imovel-parceiro-core' ),
                        'message' => __( 'O imóvel foi aprovado e pode ser publicado conforme as regras da plataforma.', 'imovel-parceiro-core' ),
                        'url' => get_permalink( $property_id ),
                        'priority' => IPC_Notifications::PRIORITY_INFO,
                    )
                );
            }
        }

        if ( $broker_user_id ) {
            $this->notify_user( $broker_user_id, __( 'Atualizacao de documentacao do imovel', 'imovel-parceiro-core' ), $decision_text );
        }

        wp_send_json_success( array( 'message' => __( 'Analise documental registrada com sucesso.', 'imovel-parceiro-core' ) ) );
    }

    private function update_relation_statuses( $property_id, $data ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || empty( $data ) || ! is_array( $data ) ) {
            return;
        }

        global $wpdb;
        $table = $this->get_relations_table();
        $existing = $this->get_relation_by_property( $property_id );
        if ( ! $existing ) {
            return;
        }

        $allowed = array( 'workflow_status', 'documentation_status', 'approval_status', 'approved_by', 'approved_at', 'rejected_reason' );

        $update_data = array();
        $format = array();

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                continue;
            }

            $value = $data[ $key ];
            if ( in_array( $key, array( 'approved_by' ), true ) ) {
                $update_data[ $key ] = absint( $value );
                $format[] = '%d';
            } elseif ( in_array( $key, array( 'approved_at' ), true ) ) {
                $update_data[ $key ] = $value ? sanitize_text_field( $value ) : null;
                $format[] = '%s';
            } else {
                $update_data[ $key ] = sanitize_text_field( $value );
                $format[] = '%s';
            }
        }

        $update_data['updated_at'] = current_time( 'mysql' );
        $format[] = '%s';

        $wpdb->update( $table, $update_data, array( 'property_id' => $property_id ), $format, array( '%d' ) );

        if ( isset( $update_data['workflow_status'] ) ) {
            update_post_meta( $property_id, self::META_WORKFLOW_STATUS, $update_data['workflow_status'] );
        }
        if ( isset( $update_data['documentation_status'] ) ) {
            update_post_meta( $property_id, self::META_DOC_STATUS, $update_data['documentation_status'] );
        }
        if ( isset( $update_data['approval_status'] ) ) {
            update_post_meta( $property_id, self::META_APPROVAL_STATUS, $update_data['approval_status'] );
        }
    }

    private function ensure_property_post_status( $property_id, $status ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return;
        }

        $status = sanitize_key( $status );
        $current = get_post_status( $property_id );
        if ( $current === $status ) {
            return;
        }

        wp_update_post(
            array(
                'ID' => $property_id,
                'post_status' => $status,
            )
        );
    }

    public function ajax_owner_request_property_delete() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() || ! current_user_can( self::CAP_OWNER_DELETE_REQUEST ) ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para solicitacao de exclusao.', 'imovel-parceiro-core' ) ) );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $owner_user_id = get_current_user_id();

        if ( ! $property_id || ! $this->is_property_owner( $property_id, $owner_user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Solicitacao invalida para exclusao do imovel.', 'imovel-parceiro-core' ) ) );
        }

        $has_history = $this->property_has_commercial_activity( $property_id ) ? 1 : 0;

        global $wpdb;
        $wpdb->insert(
            $this->get_deletion_requests_table(),
            array(
                'property_id' => $property_id,
                'owner_user_id' => $owner_user_id,
                'reason' => $reason,
                'has_commercial_history' => $has_history,
                'status' => 'pendente',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s' )
        );

        $this->insert_audit_log(
            'owner_requested_property_deletion',
            $owner_user_id,
            0,
            $property_id,
            array(
                'has_commercial_history' => $has_history,
                'reason' => $reason,
            )
        );

        $this->notify_admins(
            __( 'Nova solicitacao de exclusao de imovel', 'imovel-parceiro-core' ),
            sprintf( __( 'O proprietario #%1$d solicitou exclusao do imovel #%2$d.', 'imovel-parceiro-core' ), $owner_user_id, $property_id )
        );

        wp_send_json_success( array( 'message' => __( 'Solicitacao de exclusao enviada para analise administrativa.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_admin_process_property_delete_request() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! $this->user_can_manage_workflow() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado para processar solicitacao de exclusao.', 'imovel-parceiro-core' ) ) );
        }

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $admin_note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';

        if ( ! $request_id || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para processamento da exclusao.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $table = $this->get_deletion_requests_table();
        $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $request_id ) );

        if ( ! $request || 'pendente' !== $request->status ) {
            wp_send_json_error( array( 'message' => __( 'Solicitacao nao encontrada ou ja processada.', 'imovel-parceiro-core' ) ) );
        }

        $new_status = 'approve' === $decision ? 'aprovada' : 'rejeitada';
        $wpdb->update(
            $table,
            array(
                'status' => $new_status,
                'admin_user_id' => get_current_user_id(),
                'admin_note' => $admin_note,
                'processed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $request_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( 'approve' === $decision ) {
            $this->update_relation_statuses(
                (int) $request->property_id,
                array(
                    'workflow_status' => self::WORKFLOW_ARQUIVADO,
                )
            );
            $this->ensure_property_post_status( (int) $request->property_id, 'draft' );
        }

        $this->insert_audit_log(
            'admin_processed_property_deletion_request',
            get_current_user_id(),
            (int) $request->owner_user_id,
            (int) $request->property_id,
            array(
                'request_id' => $request_id,
                'decision' => $new_status,
                'admin_note' => $admin_note,
                'has_commercial_history' => (int) $request->has_commercial_history,
            )
        );

        $this->notify_user(
            (int) $request->owner_user_id,
            __( 'Resultado da solicitacao de exclusao', 'imovel-parceiro-core' ),
            'approve' === $decision
                ? __( 'Sua solicitacao de exclusao foi aprovada. O imovel foi arquivado.', 'imovel-parceiro-core' )
                : __( 'Sua solicitacao de exclusao foi rejeitada.', 'imovel-parceiro-core' )
        );

        wp_send_json_success( array( 'message' => __( 'Solicitacao de exclusao processada.', 'imovel-parceiro-core' ) ) );
    }

    private function all_property_documents_are_approved( $property_id ) {
        global $wpdb;

        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return false;
        }

        $docs = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT doc_type, status FROM ' . $this->get_documents_table() . ' WHERE property_id = %d',
                $property_id
            )
        );

        if ( empty( $docs ) ) {
            return false;
        }

        $required_types = array( 'identificacao', 'matricula', 'comprovante_propriedade' );
        $approved_types = array();
        foreach ( $docs as $doc ) {
            if ( 'aprovado' !== sanitize_key( (string) $doc->status ) ) {
                return false;
            }
            $approved_types[] = sanitize_key( (string) $doc->doc_type );
        }

        return empty( array_diff( $required_types, array_unique( $approved_types ) ) );
    }

    public function ajax_admin_document_preview() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        $document_id = isset( $_POST['document_id'] ) ? absint( $_POST['document_id'] ) : 0;
        $doc = $this->get_authorized_document_record( $document_id );
        if ( is_wp_error( $doc ) ) {
            wp_send_json_error( array( 'message' => $doc->get_error_message() ) );
        }

        $payload = $this->get_document_preview_payload( $doc );
        wp_send_json_success( array( 'document' => $payload ) );
    }

    public function ajax_admin_stream_document() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        $document_id = isset( $_GET['document_id'] ) ? absint( $_GET['document_id'] ) : 0;
        $mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'inline';

        $doc = $this->get_authorized_document_record( $document_id );
        if ( is_wp_error( $doc ) ) {
            wp_die( esc_html( $doc->get_error_message() ) );
        }

        $file_data = $this->get_document_file_data( (int) $doc->attachment_id );
        if ( is_wp_error( $file_data ) ) {
            wp_die( esc_html( $file_data->get_error_message() ) );
        }

        $is_inline = 'inline' === $mode;
        if ( $is_inline && ! $this->can_preview_document_mime( $file_data['mime'], $file_data['ext'] ) ) {
            wp_die( esc_html__( 'Este formato não pode ser visualizado diretamente no navegador.', 'imovel-parceiro-core' ) );
        }

        $file_name = $this->build_secure_document_file_name( $doc );
        $disposition = $is_inline ? 'inline' : 'attachment';

        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . $file_data['mime'] );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . $file_name . '"' );
        if ( ! empty( $file_data['size'] ) ) {
            header( 'Content-Length: ' . (int) $file_data['size'] );
        }
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $file_data['path'] );
        exit;
    }

    public function ajax_admin_download_document() {
        $_GET['mode'] = 'download';
        $this->ajax_admin_stream_document();
    }

    public function ajax_owner_property_context() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        if ( ! is_user_logged_in() || ! $this->is_proprietario_user() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $owner_user_id = get_current_user_id();
        if ( ! $property_id || ! $this->is_property_owner( $property_id, $owner_user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Imovel invalido para este proprietario.', 'imovel-parceiro-core' ) ) );
        }

        $context = self::get_property_owner_context( $property_id, $owner_user_id );
        if ( empty( $context ) ) {
            wp_send_json_error( array( 'message' => __( 'Contexto do imovel nao encontrado.', 'imovel-parceiro-core' ) ) );
        }

        $context['has_commercial_history'] = $this->property_has_commercial_activity( $property_id );
        wp_send_json_success( array( 'context' => $context ) );
    }

    private function notify_admins( $subject, $message ) {
        $subject = wp_strip_all_tags( $subject );
        $message = wp_kses_post( $message );

        $admin_users = get_users(
            array(
                'role__in' => array( 'administrator', 'houzez_manager' ),
                'fields' => array( 'user_email' ),
                'number' => 50,
            )
        );

        $emails = array();
        foreach ( $admin_users as $admin_user ) {
            if ( ! empty( $admin_user->user_email ) ) {
                $emails[] = sanitize_email( $admin_user->user_email );
            }
        }

        $fallback = sanitize_email( get_option( 'admin_email' ) );
        if ( ! empty( $fallback ) ) {
            $emails[] = $fallback;
        }

        $emails = array_unique( array_filter( $emails ) );
        foreach ( $emails as $email ) {
            $this->send_email( $email, $subject, $message );
        }
    }

    private function notify_users_with_in_app_notifications( $user_ids, $notification_args ) {
        if ( ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }

        $user_ids = array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) );
        foreach ( $user_ids as $user_id ) {
            IPC_Notifications::send(
                array_merge(
                    array( 'user_id' => $user_id ),
                    is_array( $notification_args ) ? $notification_args : array()
                )
            );
        }
    }

    private function notify_user( $user_id, $subject, $message ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user || empty( $user->user_email ) ) {
            return;
        }

        $this->send_email( $user->user_email, $subject, $message );
    }

    private function send_email( $to, $subject, $message ) {
        if ( empty( $to ) ) {
            return;
        }

        if ( function_exists( 'houzez_send_emails' ) ) {
            houzez_send_emails( $to, $subject, $message );
            return;
        }

        wp_mail( $to, $subject, $message );
    }
}

new Imovel_Parceiro_Owner_Workflow();

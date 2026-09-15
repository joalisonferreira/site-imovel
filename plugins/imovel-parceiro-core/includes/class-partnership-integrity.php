<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imovel_Parceiro_Partnership_Integrity
 *
 * Camada antifraude do fluxo de parcerias. Responsavel por:
 *  - log de acesso ao contato + termo de nao-circunvencao;
 *  - fingerprint do cliente para detectar duplicidade entre corretores;
 *  - SLA de resposta/registro com suspensao automatica e escalonamento;
 *  - alertas administrativos (venda/edicao/exclusao fora do funil).
 */
class Imovel_Parceiro_Partnership_Integrity {
    const DB_VERSION = '1.1.0';
    const DB_VERSION_OPTION = 'imovel_parceiro_integrity_db_version';
    const CRON_HOOK = 'imovel_parceiro_integrity_scan';

    const ALERT_OWNER_NO_RESPONSE = 'partnership_owner_no_response';
    const ALERT_CLIENT_NOT_REGISTERED = 'partnership_client_not_registered';
    const ALERT_DUPLICATE_CLIENT = 'partnership_duplicate_client';
    const ALERT_PROPERTY_CHANGED = 'property_changed_with_active_partnership';
    const ALERT_PROPERTY_DELETED = 'property_deleted_with_active_partnership';

    const OWNER_RESPONSE_HOURS = 48;
    const CLIENT_REGISTRATION_DAYS = 7;

    /** Properties being legitimately changed by the funnel (avoids false alerts). */
    public static $suppressed_properties = array();

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_install' ) );
        add_action( self::CRON_HOOK, array( $this, 'scan' ) );
        add_action( 'transition_post_status', array( $this, 'on_property_status_change' ), 20, 3 );
        add_action( 'before_delete_post', array( $this, 'on_property_delete' ), 20, 1 );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    public static function active_statuses() {
        return array( 'pending', 'accepted', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal' );
    }

    public static function suppress_property( $property_id ) {
        self::$suppressed_properties[ absint( $property_id ) ] = time();
    }

    public static function is_suppressed( $property_id ) {
        $property_id = absint( $property_id );
        $when = isset( self::$suppressed_properties[ $property_id ] ) ? (int) self::$suppressed_properties[ $property_id ] : 0;
        return $when && ( time() - $when ) < 600;
    }

    /* ---------------------------------------------------------------------
     * Install / migrate
     * ------------------------------------------------------------------ */

    public function maybe_install() {
        if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$wpdb->prefix}imovel_parceiro_contact_access_log (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                action varchar(40) NOT NULL DEFAULT 'view',
                ip_address varchar(45) DEFAULT '',
                user_agent text DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                KEY partnership_id (partnership_id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$wpdb->prefix}imovel_parceiro_admin_alerts (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                alert_type varchar(80) NOT NULL DEFAULT '',
                severity varchar(20) NOT NULL DEFAULT 'warning',
                property_id bigint(20) unsigned NOT NULL DEFAULT 0,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                title varchar(255) NOT NULL DEFAULT '',
                message text DEFAULT '',
                status varchar(20) NOT NULL DEFAULT 'open',
                meta longtext DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                resolved_at datetime DEFAULT NULL,
                resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY alert_type (alert_type),
                KEY status (status),
                KEY partnership_id (partnership_id),
                KEY property_id (property_id),
                KEY created_at (created_at)
            ) {$charset_collate};"
        );

        $this->ensure_partnership_columns();

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    private function ensure_partnership_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
            return;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        $wanted = array(
            'sla_due_at'           => "ALTER TABLE {$table} ADD COLUMN sla_due_at datetime DEFAULT NULL",
            'contact_suspended_at' => "ALTER TABLE {$table} ADD COLUMN contact_suspended_at datetime DEFAULT NULL",
        );

        foreach ( $wanted as $column => $sql ) {
            if ( ! in_array( $column, $columns, true ) ) {
                $wpdb->query( $sql );
            }
        }

        // client_fingerprint guarda "tipo:" + hash sha256 (ate ~70 chars).
        if ( ! in_array( 'client_fingerprint', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN client_fingerprint varchar(80) NOT NULL DEFAULT ''" );
        } else {
            $wpdb->query( "ALTER TABLE {$table} MODIFY client_fingerprint varchar(80) NOT NULL DEFAULT ''" );
        }
    }

    /* ---------------------------------------------------------------------
     * Alerts
     * ------------------------------------------------------------------ */

    public static function alerts_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_admin_alerts';
    }

    public static function create_alert( $type, $title, $message, $args = array() ) {
        global $wpdb;

        $args = wp_parse_args(
            is_array( $args ) ? $args : array(),
            array(
                'severity'       => 'warning',
                'property_id'    => 0,
                'partnership_id' => 0,
                'user_id'        => 0,
                'meta'           => array(),
            )
        );

        // Deduplicate: one open alert per type + partnership/property.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . self::alerts_table() . ' WHERE alert_type = %s AND partnership_id = %d AND property_id = %d AND status = %s LIMIT 1',
                sanitize_key( $type ),
                absint( $args['partnership_id'] ),
                absint( $args['property_id'] ),
                'open'
            )
        );
        if ( $existing ) {
            return (int) $existing;
        }

        $wpdb->insert(
            self::alerts_table(),
            array(
                'alert_type'     => sanitize_key( $type ),
                'severity'       => sanitize_key( $args['severity'] ),
                'property_id'    => absint( $args['property_id'] ),
                'partnership_id' => absint( $args['partnership_id'] ),
                'user_id'        => absint( $args['user_id'] ),
                'title'          => sanitize_text_field( $title ),
                'message'        => sanitize_textarea_field( $message ),
                'status'         => 'open',
                'meta'           => maybe_serialize( $args['meta'] ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public static function get_alerts( $status = 'open', $limit = 100 ) {
        global $wpdb;
        $limit = max( 1, min( 500, absint( $limit ) ) );

        if ( 'all' === $status ) {
            return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::alerts_table() . ' ORDER BY id DESC LIMIT %d', $limit ) );
        }

        return $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::alerts_table() . ' WHERE status = %s ORDER BY severity = \'critical\' DESC, id DESC LIMIT %d', sanitize_key( $status ), $limit )
        );
    }

    public static function count_open_alerts() {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::alerts_table() . ' WHERE status = %s', 'open' ) );
    }

    public static function resolve_alert( $alert_id, $user_id = 0 ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::alerts_table(),
            array(
                'status'      => 'resolved',
                'resolved_at' => current_time( 'mysql' ),
                'resolved_by' => absint( $user_id ),
            ),
            array( 'id' => absint( $alert_id ) ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );
    }

    /* ---------------------------------------------------------------------
     * Contact access + non-circumvention term
     * ------------------------------------------------------------------ */

    public static function contact_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_contact_access_log';
    }

    public static function has_accepted_contact_terms( $partnership_id, $user_id ) {
        return (bool) get_user_meta( absint( $user_id ), 'imovel_parceiro_contact_terms_' . absint( $partnership_id ), true );
    }

    public static function accept_contact_terms( $partnership_id, $user_id ) {
        update_user_meta( absint( $user_id ), 'imovel_parceiro_contact_terms_' . absint( $partnership_id ), current_time( 'mysql' ) );
        self::log_contact_access( $partnership_id, $user_id, 'terms_accepted' );
    }

    public static function log_contact_access( $partnership_id, $user_id, $action = 'view' ) {
        global $wpdb;
        $wpdb->insert(
            self::contact_log_table(),
            array(
                'partnership_id' => absint( $partnership_id ),
                'user_id'        => absint( $user_id ),
                'action'         => sanitize_key( $action ),
                'ip_address'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    public static function get_contact_log( $partnership_id, $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::contact_log_table() . ' WHERE partnership_id = %d ORDER BY id DESC LIMIT %d',
                absint( $partnership_id ),
                max( 1, min( 200, absint( $limit ) ) )
            )
        );
    }

    /* ---------------------------------------------------------------------
     * Client fingerprint / duplicate detection
     * ------------------------------------------------------------------ */

    public static function client_fingerprint( $phone, $cpf = '', $email = '' ) {
        $cpf = preg_replace( '/\D/', '', (string) $cpf );
        if ( '' !== $cpf ) {
            return 'cpf:' . hash( 'sha256', $cpf );
        }

        $phone = preg_replace( '/\D/', '', (string) $phone );
        if ( '' !== $phone ) {
            return 'phone:' . hash( 'sha256', $phone );
        }

        $email = strtolower( trim( (string) $email ) );
        if ( '' !== $email ) {
            return 'email:' . hash( 'sha256', $email );
        }

        return '';
    }

    public static function set_client_fingerprint( $partnership_id, $fingerprint ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            array( 'client_fingerprint' => sanitize_text_field( $fingerprint ) ),
            array( 'id' => absint( $partnership_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Find another active partnership on the same property with the same client.
     */
    public static function find_duplicate_client( $property_id, $fingerprint, $exclude_partnership_id = 0 ) {
        global $wpdb;
        $fingerprint = trim( (string) $fingerprint );
        if ( '' === $fingerprint ) {
            return 0;
        }

        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $statuses = self::active_statuses();
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $params = array_merge(
            array( absint( $property_id ), $fingerprint, absint( $exclude_partnership_id ) ),
            $statuses
        );

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE property_id = %d AND client_fingerprint = %s AND id <> %d AND status IN ({$placeholders}) ORDER BY id DESC LIMIT 1",
                $params
            )
        );

        return absint( $id );
    }

    /* ---------------------------------------------------------------------
     * SLA
     * ------------------------------------------------------------------ */

    public static function set_sla( $partnership_id, $due_ts ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            array( 'sla_due_at' => gmdate( 'Y-m-d H:i:s', (int) $due_ts ) ),
            array( 'id' => absint( $partnership_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function clear_sla( $partnership_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            array( 'sla_due_at' => null ),
            array( 'id' => absint( $partnership_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function set_owner_response_sla( $partnership_id ) {
        self::set_sla( $partnership_id, time() + ( self::OWNER_RESPONSE_HOURS * HOUR_IN_SECONDS ) );
    }

    public static function set_client_registration_sla( $partnership_id ) {
        self::set_sla( $partnership_id, time() + ( self::CLIENT_REGISTRATION_DAYS * DAY_IN_SECONDS ) );
    }

    public static function suspend_contact( $partnership_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            array( 'contact_suspended_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $partnership_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function resume_contact( $partnership_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'imovel_parceiro_partnerships',
            array( 'contact_suspended_at' => null ),
            array( 'id' => absint( $partnership_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function is_contact_suspended( $row ) {
        return ! empty( $row->contact_suspended_at ) && '0000-00-00 00:00:00' !== $row->contact_suspended_at;
    }

    /* ---------------------------------------------------------------------
     * Cron scan (SLA escalation)
     * ------------------------------------------------------------------ */

    public function scan() {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
            return;
        }

        $now = current_time( 'mysql' );

        // 1) Pending requests past the owner response SLA.
        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status IN ('pending','solicitada') AND sla_due_at IS NOT NULL AND sla_due_at <> '0000-00-00 00:00:00' AND sla_due_at < %s LIMIT 100",
                $now
            )
        );
        foreach ( $pending as $row ) {
            self::create_alert(
                self::ALERT_OWNER_NO_RESPONSE,
                __( 'Solicitação de parceria sem resposta', 'imovel-parceiro-core' ),
                sprintf( __( 'A solicitação de parceria #%d está sem resposta do anunciante dentro do prazo.', 'imovel-parceiro-core' ), (int) $row->id ),
                array(
                    'severity'       => 'warning',
                    'partnership_id' => (int) $row->id,
                    'property_id'    => (int) $row->property_id,
                )
            );
        }

        // 2) Contact released but client not registered in time.
        $no_client = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'contact_released' AND ( contact_suspended_at IS NULL OR contact_suspended_at = '0000-00-00 00:00:00' ) AND sla_due_at IS NOT NULL AND sla_due_at <> '0000-00-00 00:00:00' AND sla_due_at < %s LIMIT 100",
                $now
            )
        );
        foreach ( $no_client as $row ) {
            self::suspend_contact( (int) $row->id );
            self::create_alert(
                self::ALERT_CLIENT_NOT_REGISTERED,
                __( 'Cliente não registrado após liberação do contato', 'imovel-parceiro-core' ),
                sprintf( __( 'O contato da parceria #%d foi suspenso porque o cliente não foi registrado dentro do prazo.', 'imovel-parceiro-core' ), (int) $row->id ),
                array(
                    'severity'       => 'critical',
                    'partnership_id' => (int) $row->id,
                    'property_id'    => (int) $row->property_id,
                )
            );
        }
    }

    /* ---------------------------------------------------------------------
     * Property monitoring (off-platform activity)
     * ------------------------------------------------------------------ */

    public function on_property_status_change( $new_status, $old_status, $post ) {
        if ( ! $post || 'property' !== $post->post_type || $new_status === $old_status ) {
            return;
        }
        if ( wp_is_post_revision( $post->ID ) || self::is_suppressed( $post->ID ) ) {
            return;
        }
        if ( in_array( $new_status, array( 'auto-draft', 'inherit' ), true ) ) {
            return;
        }

        // Legitimate funnel sale leaves a marker.
        if ( 'VENDIDO' === get_post_meta( $post->ID, '_imovel_parceiro_workflow_status', true ) ) {
            return;
        }

        $partnerships = $this->active_partnerships_for_property( $post->ID );
        if ( empty( $partnerships ) ) {
            return;
        }

        self::create_alert(
            self::ALERT_PROPERTY_CHANGED,
            __( 'Imóvel com parceria ativa foi alterado', 'imovel-parceiro-core' ),
            sprintf(
                __( 'O imóvel "%1$s" mudou de status (%2$s → %3$s) enquanto existem %4$d parcerias ativas.', 'imovel-parceiro-core' ),
                get_the_title( $post->ID ),
                $old_status,
                $new_status,
                count( $partnerships )
            ),
            array(
                'severity'    => 'critical',
                'property_id' => $post->ID,
                'meta'        => array( 'old_status' => $old_status, 'new_status' => $new_status ),
            )
        );
    }

    public function on_property_delete( $post_id ) {
        if ( 'property' !== get_post_type( $post_id ) || self::is_suppressed( $post_id ) ) {
            return;
        }
        if ( 'VENDIDO' === get_post_meta( $post_id, '_imovel_parceiro_workflow_status', true ) ) {
            return;
        }

        $partnerships = $this->active_partnerships_for_property( $post_id );
        if ( empty( $partnerships ) ) {
            return;
        }

        self::create_alert(
            self::ALERT_PROPERTY_DELETED,
            __( 'Imóvel com parceria ativa foi excluído', 'imovel-parceiro-core' ),
            sprintf(
                __( 'O imóvel "%1$s" foi excluído enquanto existem %2$d parcerias ativas.', 'imovel-parceiro-core' ),
                get_the_title( $post_id ),
                count( $partnerships )
            ),
            array(
                'severity'    => 'critical',
                'property_id' => $post_id,
            )
        );
    }

    private function active_partnerships_for_property( $property_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
            return array();
        }
        $statuses = self::active_statuses();
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $params = array_merge( array( absint( $property_id ) ), $statuses );

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE property_id = %d AND status IN ({$placeholders})", $params )
        );
    }
}

Imovel_Parceiro_Partnership_Integrity::instance();

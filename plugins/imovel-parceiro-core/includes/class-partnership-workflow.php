<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imovel_Parceiro_Partnership_Workflow
 *
 * Engine for the partnership funnel between brokers. Turns a partnership into a
 * small CRM: statuses, allowed transitions, permissions, history/audit,
 * opportunity, interactions, visits, proposals, contact release and
 * notifications. All writes/reads are server-side validated.
 */
class Imovel_Parceiro_Partnership_Workflow {
    const DB_VERSION_OPTION = 'imovel_parceiro_partnerships_db_version';
    const DB_VERSION = '2.0.0';

    // Canonical statuses (English keys stored in DB).
    const PENDING           = 'pending';
    const ACCEPTED          = 'accepted';
    const REJECTED          = 'rejected';
    const NEGOTIATING       = 'negotiating';
    const CONTACT_RELEASED  = 'contact_released';
    const OPPORTUNITY       = 'opportunity';
    const VISIT             = 'visit';
    const PROPOSAL          = 'proposal';
    const WON               = 'won';
    const LOST              = 'lost';
    const CLOSED            = 'closed';

    const NONCE = 'imovel_parceiro_core_nonce';
    const CAP_MANAGE = 'imovel_parceiro_manage_commercial';

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_install' ) );

        add_action( 'wp_ajax_imovel_parceiro_funnel_transition', array( $this, 'ajax_transition' ) );
        add_action( 'wp_ajax_imovel_parceiro_funnel_opportunity', array( $this, 'ajax_opportunity' ) );
        add_action( 'wp_ajax_imovel_parceiro_funnel_interaction', array( $this, 'ajax_interaction' ) );
        add_action( 'wp_ajax_imovel_parceiro_funnel_visit', array( $this, 'ajax_visit' ) );
        add_action( 'wp_ajax_imovel_parceiro_funnel_proposal', array( $this, 'ajax_proposal' ) );
        add_action( 'wp_ajax_imovel_parceiro_funnel_contact', array( $this, 'ajax_contact' ) );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    /* ---------------------------------------------------------------------
     * DB
     * ------------------------------------------------------------------ */

    public static function partnerships_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_partnerships';
    }

    public static function events_table() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_partnership_events';
    }

    public function maybe_install() {
        if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$wpdb->prefix}imovel_parceiro_partnerships (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                requester_id bigint(20) unsigned NOT NULL,
                owner_id bigint(20) unsigned NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'pending',
                commission_split varchar(20) NOT NULL DEFAULT '50/50',
                terms_version varchar(50) NOT NULL,
                requested_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                responded_at datetime DEFAULT NULL,
                notes text DEFAULT '',
                rejection_reason text DEFAULT '',
                business_reason text DEFAULT '',
                contact_released_at datetime DEFAULT NULL,
                contact_released_by bigint(20) unsigned NOT NULL DEFAULT 0,
                negotiation_started_at datetime DEFAULT NULL,
                resolved_at datetime DEFAULT NULL,
                resolved_type varchar(30) DEFAULT '',
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY requester_id (requester_id),
                KEY owner_id (owner_id),
                KEY status (status)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE " . self::events_table() . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                kind varchar(40) NOT NULL DEFAULT 'action',
                type varchar(60) NOT NULL DEFAULT '',
                title varchar(255) DEFAULT '',
                note text DEFAULT '',
                meta longtext NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                PRIMARY KEY  (id),
                KEY partnership_id (partnership_id),
                KEY kind (kind),
                KEY created_at (created_at)
            ) {$charset_collate};"
        );

        $this->migrate_statuses();

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Non-destructive migration of legacy statuses to canonical keys.
     */
    private function migrate_statuses() {
        global $wpdb;
        $table = self::partnerships_table();

        $to_canonical = array(
            'solicitada'  => self::PENDING,
            'solicitado'  => self::PENDING,
            'pending'     => self::PENDING,
            'aceita'      => self::ACCEPTED,
            'accepted'    => self::ACCEPTED,
            'recusada'    => self::REJECTED,
            'rejected'    => self::REJECTED,
            'ativa'       => self::NEGOTIATING,
            'active'      => self::NEGOTIATING,
            'emnegociacao'=> self::NEGOTIATING,
            'negotiating' => self::NEGOTIATING,
            'ganha'       => self::WON,
            'ganhou'      => self::WON,
            'won'         => self::WON,
            'perdida'     => self::LOST,
            'perdeu'      => self::LOST,
            'lost'        => self::LOST,
            'encerrada'   => self::CLOSED,
            'cancelada'   => self::CLOSED,
            'cancelled'   => self::CLOSED,
            'finalizada'  => self::CLOSED,
            'closed'      => self::CLOSED,
        );

        foreach ( $to_canonical as $from => $to ) {
            $wpdb->update( $table, array( 'status' => $to ), array( 'status' => $from ), array( '%s' ), array( '%s' ) );
        }
    }

    /* ---------------------------------------------------------------------
     * Status helpers
     * ------------------------------------------------------------------ */

    public static function funnel_status( $raw ) {
        $raw    = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';
        $raw    = str_replace( array( ' ', '-', '_' ), '', $raw );
        $map    = array(
            'solicitada'     => self::PENDING,
            'solicitado'     => self::PENDING,
            'pending'        => self::PENDING,
            'aceita'         => self::ACCEPTED,
            'accepted'       => self::ACCEPTED,
            'recusada'       => self::REJECTED,
            'rejected'       => self::REJECTED,
            'ativa'          => self::NEGOTIATING,
            'active'         => self::NEGOTIATING,
            'emnegociacao'   => self::NEGOTIATING,
            'emnegociação'   => self::NEGOTIATING,
            'negotiating'    => self::NEGOTIATING,
            'contactreleased'=> self::CONTACT_RELEASED,
            'contact_released'=> self::CONTACT_RELEASED,
            'opportunity'    => self::OPPORTUNITY,
            'oportunidade'   => self::OPPORTUNITY,
            'visit'          => self::VISIT,
            'visita'         => self::VISIT,
            'proposal'       => self::PROPOSAL,
            'proposta'       => self::PROPOSAL,
            'won'            => self::WON,
            'ganha'          => self::WON,
            'ganhou'         => self::WON,
            'lost'           => self::LOST,
            'perdida'        => self::LOST,
            'perdeu'         => self::LOST,
            'closed'         => self::CLOSED,
            'encerrada'      => self::CLOSED,
            'cancelada'      => self::CLOSED,
            'cancelled'      => self::CLOSED,
            'finalizada'     => self::CLOSED,
        );

        return isset( $map[ $raw ] ) ? $map[ $raw ] : '';
    }

    public static function status_label( $status ) {
        $status = self::funnel_status( $status );
        $labels = array(
            self::PENDING           => __( 'Solicitação enviada', 'imovel-parceiro-core' ),
            self::ACCEPTED          => __( 'Parceria aceita', 'imovel-parceiro-core' ),
            self::REJECTED          => __( 'Recusada', 'imovel-parceiro-core' ),
            self::NEGOTIATING       => __( 'Negociação', 'imovel-parceiro-core' ),
            self::CONTACT_RELEASED  => __( 'Contato liberado', 'imovel-parceiro-core' ),
            self::OPPORTUNITY       => __( 'Oportunidade registrada', 'imovel-parceiro-core' ),
            self::VISIT             => __( 'Visita', 'imovel-parceiro-core' ),
            self::PROPOSAL          => __( 'Proposta', 'imovel-parceiro-core' ),
            self::WON               => __( 'Negócio ganho', 'imovel-parceiro-core' ),
            self::LOST              => __( 'Negócio perdido', 'imovel-parceiro-core' ),
            self::CLOSED            => __( 'Encerrada', 'imovel-parceiro-core' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : ( is_string( $status ) && '' !== $status ? ucfirst( $status ) : __( 'Desconhecido', 'imovel-parceiro-core' ) );
    }

    public static function badge_class( $status ) {
        $status = self::funnel_status( $status );
        $map    = array(
            self::WON            => 'is-won',
            self::CLOSED         => 'is-cancelled',
            self::REJECTED       => 'is-cancelled',
            self::LOST           => 'is-cancelled',
            self::PENDING        => 'is-default',
            self::ACCEPTED       => 'is-accepted',
            self::NEGOTIATING    => 'is-negotiating',
            self::CONTACT_RELEASED => 'is-negotiating',
            self::OPPORTUNITY    => 'is-opportunity',
            self::VISIT          => 'is-opportunity',
            self::PROPOSAL       => 'is-proposal',
        );

        return isset( $map[ $status ] ) ? $map[ $status ] : 'is-default';
    }

    /* ---------------------------------------------------------------------
     * Transition rules
     * ------------------------------------------------------------------ */

    public static function transitions() {
        return array(
            self::PENDING          => array( self::ACCEPTED, self::REJECTED, self::CLOSED ),
            self::ACCEPTED         => array( self::NEGOTIATING, self::CLOSED ),
            self::NEGOTIATING      => array( self::CONTACT_RELEASED, self::WON, self::LOST, self::CLOSED ),
            self::CONTACT_RELEASED => array( self::OPPORTUNITY, self::CLOSED ),
            self::OPPORTUNITY      => array( self::VISIT, self::PROPOSAL, self::CLOSED ),
            self::VISIT            => array( self::PROPOSAL, self::CLOSED ),
            self::PROPOSAL         => array( self::WON, self::LOST, self::CLOSED ),
            self::WON              => array( self::CLOSED ),
            self::LOST             => array( self::CLOSED ),
            self::REJECTED         => array(),
            self::CLOSED           => array(),
        );
    }

    public static function next_action( $status ) {
        $status = self::funnel_status( $status );
        $map    = array(
            self::PENDING           => __( 'analisar a solicitação', 'imovel-parceiro-core' ),
            self::ACCEPTED          => __( 'iniciar a negociação', 'imovel-parceiro-core' ),
            self::NEGOTIATING       => __( 'liberar o contato', 'imovel-parceiro-core' ),
            self::CONTACT_RELEASED  => __( 'registrar a oportunidade', 'imovel-parceiro-core' ),
            self::OPPORTUNITY       => __( 'registrar uma visita ou proposta', 'imovel-parceiro-core' ),
            self::VISIT             => __( 'registrar uma proposta', 'imovel-parceiro-core' ),
            self::PROPOSAL          => __( 'definir o resultado', 'imovel-parceiro-core' ),
            self::WON               => __( 'encerrar a parceria', 'imovel-parceiro-core' ),
            self::LOST              => __( 'encerrar a parceria', 'imovel-parceiro-core' ),
            self::CLOSED            => __( 'parceria encerrada', 'imovel-parceiro-core' ),
            self::REJECTED          => __( 'solicitação recusada', 'imovel-parceiro-core' ),
        );

        return isset( $map[ $status ] ) ? $map[ $status ] : '';
    }

    /**
     * A linear visual order for the timeline: which stages come before/after.
     */
    public static function funnel_stages() {
        return array(
            self::PENDING,
            self::ACCEPTED,
            self::NEGOTIATING,
            self::CONTACT_RELEASED,
            self::OPPORTUNITY,
            self::VISIT,
            self::PROPOSAL,
            self::WON,
            self::LOST,
            self::CLOSED,
        );
    }

    /* ---------------------------------------------------------------------
     * Permission / role helpers
     * ------------------------------------------------------------------ */

    public static function is_admin() {
        return current_user_can( 'manage_options' );
    }

    public static function user_role( $row, $user_id ) {
        $requester_col = self::requester_col();
        $owner_col     = self::owner_col();

        if ( isset( $row->{$owner_col} ) && (int) $row->{$owner_col} === (int) $user_id ) {
            return 'owner';
        }
        if ( isset( $row->{$requester_col} ) && (int) $row->{$requester_col} === (int) $user_id ) {
            return 'requester';
        }
        return '';
    }

    public static function requester_col() {
        global $wpdb;
        $cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::partnerships_table() );
        return in_array( 'requester_id', $cols, true ) && self::col_has_data( 'requester_id' ) ? 'requester_id' : 'captador_id';
    }

    public static function owner_col() {
        global $wpdb;
        $cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::partnerships_table() );
        return in_array( 'owner_id', $cols, true ) && self::col_has_data( 'owner_id' ) ? 'owner_id' : 'partner_id';
    }

    public static function date_col() {
        global $wpdb;
        $cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::partnerships_table() );
        return in_array( 'requested_at', $cols, true ) && self::col_has_data( 'requested_at' ) ? 'requested_at' : 'created_at';
    }

    private static function col_has_data( $col ) {
        global $wpdb;
        static $cache = array();
        if ( isset( $cache[ $col ] ) ) {
            return $cache[ $col ];
        }
        $sql = "SELECT COUNT(*) FROM " . self::partnerships_table() . " WHERE {$col} > 0";
        $count = (int) $wpdb->get_var( $sql );
        $cache[ $col ] = $count > 0;
        return $cache[ $col ];
    }

    public static function get_partnership( $partnership_id ) {
        global $wpdb;
        $partnership_id = absint( $partnership_id );
        if ( ! $partnership_id ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::partnerships_table() . ' WHERE id = %d', $partnership_id ) );
        if ( ! $row ) {
            return null;
        }
        $row->canonical = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        return $row;
    }

    public static function can_access( $row, $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $row || ! $user_id ) {
            return false;
        }
        if ( self::is_admin() ) {
            return true;
        }
        return '' !== self::user_role( $row, $user_id );
    }

    public static function can_apply_transition( $row, $user_id, $to_status ) {
        $to_status = self::funnel_status( $to_status );
        if ( ! $row || ! $to_status ) {
            return false;
        }
        if ( self::is_admin() ) {
            return true;
        }

        $role = self::user_role( $row, $user_id );
        if ( '' === $role ) {
            return false;
        }

        $current = self::funnel_status( isset( $row->status ) ? $row->status : '' );

        // Only the owner can accept/reject and release contact.
        if ( in_array( $to_status, array( self::ACCEPTED, self::REJECTED, self::CONTACT_RELEASED ), true ) ) {
            return 'owner' === $role;
        }

        // The partnership can only progress after the owner accepted it. This is
        // a hard guard on top of transitions(): the requester must never start
        // or advance a partnership that is still pending (or was rejected).
        $progressing = array( self::NEGOTIATING, self::OPPORTUNITY, self::VISIT, self::PROPOSAL, self::WON, self::LOST );
        if ( in_array( $to_status, $progressing, true ) ) {
            if ( ! in_array( $current, array( self::ACCEPTED, self::NEGOTIATING, self::CONTACT_RELEASED, self::OPPORTUNITY, self::VISIT, self::PROPOSAL ), true ) ) {
                return false;
            }
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     * Events (history + interactions)
     * ------------------------------------------------------------------ */

    public static function record_event( $partnership_id, $actor_user_id, $kind, $type, $title, $note = '', $meta = array() ) {
        global $wpdb;
        $partnership_id = absint( $partnership_id );
        $actor_user_id  = absint( $actor_user_id );
        if ( ! $partnership_id ) {
            return 0;
        }

        $wpdb->insert(
            self::events_table(),
            array(
                'partnership_id' => $partnership_id,
                'actor_user_id'  => $actor_user_id,
                'kind'           => sanitize_key( $kind ),
                'type'           => sanitize_key( $type ),
                'title'          => sanitize_text_field( $title ),
                'note'           => sanitize_textarea_field( $note ),
                'meta'           => maybe_serialize( $meta ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public static function get_events( $partnership_id ) {
        global $wpdb;
        $partnership_id = absint( $partnership_id );
        if ( ! $partnership_id ) {
            return array();
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::events_table() . ' WHERE partnership_id = %d ORDER BY id ASC', $partnership_id )
        );
        if ( empty( $rows ) ) {
            return array();
        }
        foreach ( $rows as $row ) {
            $row->meta      = maybe_unserialize( $row->meta );
            $row->meta      = is_array( $row->meta ) ? $row->meta : array();
            $row->created   = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at );
        }
        return $rows;
    }

    public static function get_latest_event( $partnership_id, $kind, $type = '' ) {
        global $wpdb;
        $partnership_id = absint( $partnership_id );
        if ( ! $partnership_id ) {
            return null;
        }
        $sql = 'SELECT * FROM ' . self::events_table() . ' WHERE partnership_id = %d AND kind = %s';
        $args = array( $partnership_id, sanitize_key( $kind ) );
        if ( $type ) {
            $sql .= ' AND type = %s';
            $args[] = sanitize_key( $type );
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $row = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
        if ( ! $row ) {
            return null;
        }
        $row->meta = maybe_unserialize( $row->meta );
        $row->meta = is_array( $row->meta ) ? $row->meta : array();
        return $row;
    }

    public static function get_opportunity( $partnership_id ) {
        return self::get_latest_event( $partnership_id, 'opportunity' );
    }

    /* ---------------------------------------------------------------------
     * Notifications
     * ------------------------------------------------------------------ */

    private static function notify_participants( $partnership_id, $row, $type, $title, $message, $property_id = 0 ) {
        if ( ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }
        $property_id = $property_id ? absint( $property_id ) : ( isset( $row->property_id ) ? absint( $row->property_id ) : 0 );
        $property_url = $property_id ? get_permalink( $property_id ) : '';

        $requester_id = isset( $row->{ self::requester_col() } ) ? (int) $row->{ self::requester_col() } : 0;
        $owner_id     = isset( $row->{ self::owner_col() } ) ? (int) $row->{ self::owner_col() } : 0;

        $targets = array_filter( array_unique( array( $requester_id, $owner_id ) ) );
        foreach ( $targets as $uid ) {
            if ( ! $uid ) {
                continue;
            }
            IPC_Notifications::send(
                array(
                    'user_id'       => $uid,
                    'property_id'   => $property_id,
                    'partnership_id'=> $partnership_id,
                    'type'          => $type,
                    'category'      => IPC_Notifications::CATEGORY_PARCERIAS,
                    'title'         => $title,
                    'message'       => $message,
                    'url'           => $property_url,
                    'priority'      => IPC_Notifications::PRIORITY_NORMAL,
                )
            );
        }
    }

    /* ---------------------------------------------------------------------
     * Contact release / retrieval (server-side gated)
     * ------------------------------------------------------------------ */

    public static function is_contact_released( $row ) {
        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) && Imovel_Parceiro_Partnership_Integrity::is_contact_suspended( $row ) ) {
            return false;
        }
        $status = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        // Contact is released from acceptance onward (the partners can talk and
        // the responsible broker WhatsApp becomes available to the requester).
        return in_array( $status, array( self::ACCEPTED, self::NEGOTIATING, self::CONTACT_RELEASED, self::OPPORTUNITY, self::VISIT, self::PROPOSAL, self::WON, self::LOST, self::CLOSED ), true );
    }

    public static function get_partner_contact( $partnership_id, $viewer_id ) {
        $partnership_id = absint( $partnership_id );
        $viewer_id      = absint( $viewer_id );
        $row = self::get_partnership( $partnership_id );
        if ( ! $row || ! $viewer_id || ! self::can_access( $row, $viewer_id ) ) {
            return null;
        }
        if ( ! self::is_contact_released( $row ) ) {
            return null;
        }

        $role        = self::user_role( $row, $viewer_id );
        if ( '' === $role && ! self::is_admin() ) {
            return null;
        }

        $requester_id = isset( $row->{ self::requester_col() } ) ? (int) $row->{ self::requester_col() } : 0;
        $owner_id     = isset( $row->{ self::owner_col() } ) ? (int) $row->{ self::owner_col() } : 0;
        $partner_id   = ( 'owner' === $role ) ? $requester_id : $owner_id;

        return self::contact_for_user( $partner_id );
    }

    public static function contact_for_user( $user_id ) {
        $user_id = absint( $user_id );
        $user = $user_id ? get_userdata( $user_id ) : false;
        if ( ! $user ) {
            return null;
        }
        $phone    = (string) get_user_meta( $user->ID, 'fave_author_phone', true );
        $mobile   = (string) get_user_meta( $user->ID, 'fave_author_mobile', true );
        $whatsapp = (string) get_user_meta( $user->ID, 'fave_author_whatsapp', true );

        return array(
            'user_id'        => (int) $user->ID,
            'name'           => $user->display_name,
            'email'          => $user->user_email,
            'phone'          => $phone,
            'phone_call'     => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $phone ) : preg_replace( '/[^0-9+]/', '', $phone ),
            'mobile'         => $mobile,
            'mobile_call'    => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $mobile ) : preg_replace( '/[^0-9+]/', '', $mobile ),
            'whatsapp'       => $whatsapp,
            'whatsapp_call'  => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $whatsapp ) : preg_replace( '/[^0-9+]/', '', $whatsapp ),
            'company'        => get_user_meta( $user->ID, 'fave_author_company', true ),
            'creci'          => get_user_meta( $user->ID, 'fave_author_license', true ),
        );
    }

    /**
     * Auto-fill data for a visit term, pulled from the partnership.
     */
    public static function get_visit_term_defaults( $partnership_id, $user_id ) {
        $row = self::get_partnership( $partnership_id );
        if ( ! $row ) {
            return array(
                'property_title'   => '',
                'property_ref'     => 0,
                'property_address' => '',
                'broker_name'      => '',
                'broker_creci'     => '',
                'broker_company'   => '',
                'broker_phone'     => '',
            );
        }

        $property_id = isset( $row->property_id ) ? absint( $row->property_id ) : 0;
        $title       = $property_id ? get_the_title( $property_id ) : '';
        if ( '' === trim( (string) $title ) ) {
            $title = $property_id ? sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id ) : '';
        }

        $broker = self::contact_for_user( $user_id );
        $phone  = '';
        if ( is_array( $broker ) ) {
            if ( ! empty( $broker['mobile'] ) ) {
                $phone = $broker['mobile'];
            } elseif ( ! empty( $broker['phone'] ) ) {
                $phone = $broker['phone'];
            }
        }

        return array(
            'property_title'   => $title,
            'property_ref'     => $property_id,
            'property_address' => self::build_property_address( $property_id ),
            'broker_name'      => ( is_array( $broker ) && ! empty( $broker['name'] ) ) ? $broker['name'] : '',
            'broker_creci'     => ( is_array( $broker ) && ! empty( $broker['creci'] ) ) ? $broker['creci'] : '',
            'broker_company'   => ( is_array( $broker ) && ! empty( $broker['company'] ) ) ? $broker['company'] : '',
            'broker_phone'     => $phone,
        );
    }

    /**
     * Build a readable property address from Houzez meta + taxonomies.
     */
    public static function build_property_address( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return '';
        }

        $parts = array();

        $street = (string) get_post_meta( $property_id, 'fave_property_address', true );
        if ( '' === trim( $street ) ) {
            $street = (string) get_post_meta( $property_id, 'fave_property_map_address', true );
        }
        if ( '' !== trim( $street ) ) {
            $parts[] = trim( $street );
        }

        foreach ( array( 'property_area', 'property_city', 'property_state' ) as $taxonomy ) {
            $terms = get_the_terms( $property_id, $taxonomy );
            if ( is_array( $terms ) && ! empty( $terms ) ) {
                $parts[] = $terms[0]->name;
            }
        }

        $zip = (string) get_post_meta( $property_id, 'fave_property_zip', true );
        if ( '' !== trim( $zip ) ) {
            $parts[] = 'CEP ' . trim( $zip );
        }

        return implode( ', ', array_filter( $parts ) );
    }

    /* ---------------------------------------------------------------------
     * Commission on WON
     * ------------------------------------------------------------------ */

    public static function create_deal_and_commission( $row, $final_value, $sale_date, $client_name, $notes ) {
        global $wpdb;
        $partnership_id = absint( $row->id );
        $property_id    = absint( $row->property_id );
        $requester_id   = isset( $row->{ self::requester_col() } ) ? (int) $row->{ self::requester_col() } : 0;
        $owner_id       = isset( $row->{ self::owner_col() } ) ? (int) $row->{ self::owner_col() } : 0;
        if ( ! $partnership_id || ! $property_id || ! $requester_id || ! $owner_id ) {
            return;
        }

        // A comissão é acordada diretamente entre os corretores. A plataforma
        // NÃO calcula nem intermedeia valores: apenas registra o negócio e a
        // divisão combinada para visibilidade/auditoria do administrador.
        $deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
        $wpdb->insert(
            $deals_table,
            array(
                'opportunity_id'   => 0,
                'partnership_id'   => $partnership_id,
                'property_id'      => $property_id,
                'lead_id'          => 0,
                'owner_id'         => $owner_id,
                'partner_id'       => $requester_id,
                'status'           => 'won',
                'origin'           => 'partnership',
                'total_commission' => 0,
                'closed_at'        => $sale_date ? $sale_date : current_time( 'mysql' ),
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Parse a Brazilian currency string ("1.234.567,89" or "450000") into a float.
     */
    public static function parse_money( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return 0.0;
        }
        $negative = ( '-' === substr( $value, 0, 1 ) );
        $clean = preg_replace( '/[^\d,.]/', '', $value );
        // Treat the last separator as decimal; drop thousands separators.
        $last_dot   = strrpos( $clean, '.' );
        $last_comma = strrpos( $clean, ',' );
        if ( false === $last_dot && false === $last_comma ) {
            $number = (float) $clean;
        } elseif ( false !== $last_dot && false !== $last_comma ) {
            $decimal_sep = ( $last_dot > $last_comma ) ? '.' : ',';
            $number = (float) self::apply_decimal_sep( $clean, $decimal_sep );
        } elseif ( false !== $last_comma ) {
            $number = (float) self::apply_decimal_sep( $clean, ',' );
        } else {
            $number = (float) self::apply_decimal_sep( $clean, '.' );
        }

        return $negative ? -$number : $number;
    }

    private static function apply_decimal_sep( $clean, $decimal_sep ) {
        $decimal_sep = ( ',' === $decimal_sep ) ? ',' : '.';
        $other       = ( ',' === $decimal_sep ) ? '.' : ',';
        $clean = str_replace( $other, '', $clean );
        if ( ',' === $decimal_sep ) {
            $clean = str_replace( ',', '.', $clean );
        }
        return $clean;
    }

    public static function parse_split( $split ) {
        $split = trim( (string) $split );
        if ( false === strpos( $split, '/' ) ) {
            return array( 'owner' => 50, 'partner' => 50 );
        }
        $parts = array_map( 'intval', array_map( 'trim', explode( '/', $split ) ) );
        $owner = isset( $parts[0] ) ? absint( $parts[0] ) : 50;
        $partner = isset( $parts[1] ) ? absint( $parts[1] ) : ( 100 - $owner );
        if ( $owner + $partner !== 100 ) {
            $partner = 100 - $owner;
        }
        return array( 'owner' => $owner, 'partner' => $partner );
    }

    /* ---------------------------------------------------------------------
     * Core transition executor
     * ------------------------------------------------------------------ */

    private function do_transition( $partnership_id, $to_status, $payload = array() ) {
        $partnership_id = absint( $partnership_id );
        $to_status      = self::funnel_status( $to_status );
        $user_id        = get_current_user_id();
        if ( ! $partnership_id || ! $user_id || ! $to_status ) {
            return new WP_Error( 'invalid', __( 'Dados inválidos.', 'imovel-parceiro-core' ) );
        }

        $row = self::get_partnership( $partnership_id );
        if ( ! $row ) {
            return new WP_Error( 'missing', __( 'Parceria não encontrada.', 'imovel-parceiro-core' ) );
        }

        $current = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        if ( self::CLOSED === $current || self::REJECTED === $current ) {
            return new WP_Error( 'finalized', __( 'Esta parceria já está finalizada.', 'imovel-parceiro-core' ) );
        }

        $allowed = self::transitions();
        $can     = isset( $allowed[ $current ] ) ? $allowed[ $current ] : array();
        if ( ! in_array( $to_status, $can, true ) ) {
            return new WP_Error(
                'transition',
                sprintf(
                    /* translators: 1: current label, 2: next label */
                    __( 'Transição não permitida: %1$s → %2$s.', 'imovel-parceiro-core' ),
                    self::status_label( $current ),
                    self::status_label( $to_status )
                )
            );
        }

        if ( ! self::can_apply_transition( $row, $user_id, $to_status ) ) {
            return new WP_Error( 'permission', __( 'Sem permissão para executar esta ação.', 'imovel-parceiro-core' ) );
        }

        if ( self::CLOSED === $to_status ) {
            $reason = isset( $payload['reason'] ) ? sanitize_textarea_field( wp_unslash( $payload['reason'] ) ) : '';
            if ( '' === trim( $reason ) ) {
                return new WP_Error( 'reason', __( 'Informe um motivo para encerrar a parceria.', 'imovel-parceiro-core' ) );
            }
        }

        return $this->apply( $row, $current, $to_status, $user_id, $payload );
    }

    private static function reason_text( $to_status, $payload ) {
        $reason = isset( $payload['reason'] ) ? sanitize_text_field( wp_unslash( $payload['reason'] ) ) : '';
        $detail = isset( $payload['reason_detail'] ) ? sanitize_textarea_field( wp_unslash( $payload['reason_detail'] ) ) : '';
        $outcome = ( self::WON === $to_status ) ? 'won' : ( ( self::LOST === $to_status ) ? 'lost' : 'closed' );
        $map = self::outcome_reasons( $outcome );
        $label = isset( $map[ $reason ] ) ? $map[ $reason ] : $reason;
        if ( 'outro' === $reason ) {
            return ( '' !== $detail ) ? $detail : $label;
        }
        if ( '' !== $reason && '' !== $detail ) {
            return $label . ': ' . $detail;
        }
        return $label;
    }

    /**
     * Partnership table columns, cached per request (avoids SHOW COLUMNS
     * round-trips on every transition).
     *
     * @return string[]
     */
    private static function partnership_columns() {
        global $wpdb;

        static $columns = null;
        if ( null !== $columns ) {
            return $columns;
        }

        $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::partnerships_table() );
        if ( ! is_array( $columns ) ) {
            $columns = array();
        }

        return $columns;
    }

    private function apply( $row, $current, $to_status, $user_id, $payload ) {
        global $wpdb;
        $table = self::partnerships_table();

        $update = array( 'status' => $to_status );
        $format = array( '%s' );

        $columns = self::partnership_columns();
        $has_updated = in_array( 'updated_at', $columns, true );
        $now = current_time( 'mysql' );

        if ( $has_updated ) {
            $update['updated_at'] = $now;
            $format[] = '%s';
        }

        if ( self::ACCEPTED === $to_status && in_array( 'responded_at', $columns, true ) ) {
            $update['responded_at'] = $now;
            $format[] = '%s';
        }

        if ( self::NEGOTIATING === $to_status && in_array( 'negotiation_started_at', $columns, true ) ) {
            $update['negotiation_started_at'] = $now;
            $format[] = '%s';
        }

        if ( self::REJECTED === $to_status ) {
            $reason = self::reason_text( $to_status, $payload );
            $update['rejection_reason'] = $reason;
            $format[] = '%s';
        }

        if ( self::CONTACT_RELEASED === $to_status ) {
            $update['contact_released_at'] = $now;
            $format[] = '%s';
            $update['contact_released_by'] = $user_id;
            $format[] = '%d';
        }

        if ( in_array( $to_status, array( self::WON, self::LOST ), true ) ) {
            $update['resolved_at']   = $now;
            $format[] = '%s';
            $update['resolved_type'] = $to_status;
            $format[] = '%s';
        }

        if ( self::CLOSED === $to_status ) {
            $reason = self::reason_text( $to_status, $payload );
            $update['business_reason'] = $reason;
            $format[] = '%s';
        }

        $updated = $wpdb->update( $table, $update, array( 'id' => $row->id ), $format, array( '%d' ) );
        if ( false === $updated ) {
            return new WP_Error( 'db', __( 'Falha ao atualizar a parceria.', 'imovel-parceiro-core' ) );
        }

        $row->status = $to_status;

        // SLA + antifraude por etapa.
        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            if ( self::CONTACT_RELEASED === $to_status ) {
                Imovel_Parceiro_Partnership_Integrity::set_client_registration_sla( $row->id );
            } else {
                Imovel_Parceiro_Partnership_Integrity::clear_sla( $row->id );
            }
            if ( self::OPPORTUNITY === $to_status ) {
                Imovel_Parceiro_Partnership_Integrity::resume_contact( $row->id );
            }
        }

        // Extra handling per target status.
        if ( self::WON === $to_status ) {
            $final_value = isset( $payload['final_value'] ) ? self::parse_money( $payload['final_value'] ) : 0;
            $sale_date   = isset( $payload['sale_date'] ) ? sanitize_text_field( wp_unslash( $payload['sale_date'] ) ) : '';
            $client_name = isset( $payload['client_name'] ) ? sanitize_text_field( wp_unslash( $payload['client_name'] ) ) : '';
            $notes       = isset( $payload['notes'] ) ? sanitize_textarea_field( wp_unslash( $payload['notes'] ) ) : '';
            $reason      = self::reason_text( $to_status, $payload );
            self::create_deal_and_commission( $row, $final_value, $sale_date, $client_name, $notes );
            self::take_property_off_market( $row->property_id );
            self::record_event( $row->id, $user_id, 'status', self::WON, __( 'Negócio ganho', 'imovel-parceiro-core' ), $notes, array(
                'reason'      => $reason,
                'final_value' => $final_value,
                'sale_date'   => $sale_date,
                'client_name' => $client_name,
                'notes'       => $notes,
                'previous'    => $current,
            ) );
        } elseif ( self::LOST === $to_status ) {
            $reason = self::reason_text( $to_status, $payload );
            self::record_event( $row->id, $user_id, 'status', self::LOST, __( 'Oportunidade perdida', 'imovel-parceiro-core' ), $reason, array(
                'reason'   => $reason,
                'previous' => $current,
            ) );
        } elseif ( self::CONTACT_RELEASED === $to_status ) {
            self::record_event( $row->id, $user_id, 'action', 'contact_released', __( 'Contato liberado', 'imovel-parceiro-core' ), '', array(
                'released_by' => $user_id,
                'previous'    => $current,
            ) );
        } elseif ( self::NEGOTIATING === $to_status ) {
            self::record_event( $row->id, $user_id, 'action', 'negotiating', __( 'Negociação iniciada', 'imovel-parceiro-core' ), '', array( 'previous' => $current ) );
        } elseif ( self::ACCEPTED === $to_status ) {
            self::record_event( $row->id, $user_id, 'action', 'accepted', __( 'Parceria aceita', 'imovel-parceiro-core' ), '', array( 'previous' => $current ) );
        } elseif ( self::REJECTED === $to_status ) {
            $reason = self::reason_text( $to_status, $payload );
            self::record_event( $row->id, $user_id, 'action', 'rejected', __( 'Parceria recusada', 'imovel-parceiro-core' ), $reason, array( 'previous' => $current ) );
        } elseif ( self::CLOSED === $to_status ) {
            $reason = self::reason_text( $to_status, $payload );
            self::record_event( $row->id, $user_id, 'action', 'closed', __( 'Parceria encerrada', 'imovel-parceiro-core' ), $reason, array( 'previous' => $current ) );
        }

        // Notify participants.
        $this->notify_for_transition( $row, $to_status );

        // Emails + audit for impactful milestones (non-fatal to the operation).
        if ( in_array( $to_status, array( self::ACCEPTED, self::WON, self::LOST, self::CLOSED ), true ) ) {
            $email_data = $payload;
            if ( self::WON === $to_status ) {
                $email_data['final_value'] = isset( $final_value ) ? $final_value : 0;
                $email_data['client_name'] = isset( $client_name ) ? $client_name : '';
                $email_data['commission']  = $this->commission_summary( $row );
            }
            $email_data['previous'] = $current;
            $email_data['reason']   = isset( $reason ) ? $reason : '';
            $event = ( self::WON === $to_status ) ? 'won' : $to_status;
            $this->email_funnel_event( $row->id, $row, $event, $email_data );
        }

        // After the owner accepts, the "Negociação" and "Contato liberado"
        // steps are released automatically, so the partner can immediately
        // work the listing without extra manual transitions.
        if ( self::ACCEPTED === $to_status ) {
            $this->auto_release_after_accept( $row->id, $user_id );
            $to_status = self::CONTACT_RELEASED;
        }

        return array(
            'ok'           => true,
            'status'       => $to_status,
            'status_label' => self::status_label( $to_status ),
            'next_action'  => self::next_action( $to_status ),
        );
    }

    /**
     * Progress an accepted partnership to "negotiating" and "contact_released".
     * Used automatically after the owner accepts and by the legacy accept path.
     */
    public function auto_release_after_accept( $partnership_id, $actor_user_id = 0 ) {
        $row = self::get_partnership( $partnership_id );
        if ( ! $row ) {
            return false;
        }

        $actor_user_id = absint( $actor_user_id );
        if ( ! $actor_user_id ) {
            $actor_user_id = get_current_user_id();
        }

        $current = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        if ( self::ACCEPTED !== $current ) {
            return false;
        }

        $this->apply( $row, self::ACCEPTED, self::NEGOTIATING, $actor_user_id, array() );
        $row->status = self::NEGOTIATING;
        $this->apply( $row, self::NEGOTIATING, self::CONTACT_RELEASED, $actor_user_id, array() );

        return true;
    }

    private function commission_summary( $row ) {
        if ( empty( $row ) || empty( $row->property_id ) ) {
            return '';
        }
        $split = self::parse_split( isset( $row->commission_split ) ? $row->commission_split : '50/50' );
        return sprintf( '%d%% / %d%%', $split['owner'], $split['partner'] );
    }

    /**
     * Mark a property as sold and take it off the market without deleting it:
     * preserves the record, images, history, partnership and audit.
     */
    private static function take_property_off_market( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return;
        }
        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            Imovel_Parceiro_Partnership_Integrity::suppress_property( $property_id );
        }
        if ( get_post_meta( $property_id, '_imovel_parceiro_workflow_status', true ) === 'VENDIDO' ) {
            return;
        }
        $current_status = get_post_status( $property_id );
        if ( empty( get_post_meta( $property_id, '_imovel_parceiro_pre_sale_status', true ) ) ) {
            update_post_meta( $property_id, '_imovel_parceiro_pre_sale_status', $current_status );
        }
        // Keep the listing hidden from sale while preserving everything.
        if ( in_array( $current_status, array( 'publish' ), true ) ) {
            wp_update_post( array( 'ID' => $property_id, 'post_status' => 'pending' ) );
        }
        update_post_meta( $property_id, '_imovel_parceiro_workflow_status', 'VENDIDO' );
        update_post_meta( $property_id, '_imovel_parceiro_sold_at', current_time( 'mysql' ) );

        // Best-effort Houzez "sold" taxonomy marker, if it exists.
        $status_term = get_term_by( 'slug', 'sold', 'property_status' );
        if ( $status_term ) {
            wp_set_post_terms( $property_id, array( $status_term->term_id ), 'property_status', true );
        }
    }

    private function notify_for_transition( $row, $to_status ) {
        $title = __( 'Atualização de parceria', 'imovel-parceiro-core' );
        $message = sprintf(
            /* translators: 1: partnership status label */
            __( 'A parceria agora está em: %s.', 'imovel-parceiro-core' ),
            self::status_label( $to_status )
        );

        switch ( $to_status ) {
            case self::ACCEPTED:
                $title   = __( 'Parceria aceita', 'imovel-parceiro-core' );
                $message = __( 'Sua solicitação de parceria foi aceita.', 'imovel-parceiro-core' );
                break;
            case self::REJECTED:
                $title   = __( 'Parceria recusada', 'imovel-parceiro-core' );
                $message = __( 'Sua solicitação de parceria foi recusada.', 'imovel-parceiro-core' );
                break;
            case self::NEGOTIATING:
                $title   = __( 'Negociação iniciada', 'imovel-parceiro-core' );
                $message = __( 'A negociação da parceria foi iniciada.', 'imovel-parceiro-core' );
                break;
            case self::CONTACT_RELEASED:
                $title   = __( 'Contato liberado', 'imovel-parceiro-core' );
                $message = __( 'O dono do imóvel liberou o contato para o parceiro.', 'imovel-parceiro-core' );
                break;
            case self::WON:
                $title   = __( 'Negócio ganho', 'imovel-parceiro-core' );
                $message = __( 'A oportunidade foi marcada como ganha. Parabéns!', 'imovel-parceiro-core' );
                break;
            case self::LOST:
                $title   = __( 'Oportunidade perdida', 'imovel-parceiro-core' );
                $message = __( 'A oportunidade foi marcada como perdida.', 'imovel-parceiro-core' );
                break;
            case self::CLOSED:
                $title   = __( 'Parceria encerrada', 'imovel-parceiro-core' );
                $message = __( 'Esta parceria foi encerrada.', 'imovel-parceiro-core' );
                break;
        }

        self::notify_participants( $row->id, $row, 'PARCERIA_' . strtoupper( sanitize_key( $to_status ) ), $title, $message );
    }

    /* ---------------------------------------------------------------------
     * Centralized email helper (ad-hoc but single source for funnel emails)
     * ------------------------------------------------------------------ */

    private static function send_plain_email( $to, $subject, $body ) {
        if ( empty( $to ) ) {
            return false;
        }
        if ( function_exists( 'houzez_send_emails' ) ) {
            return (bool) houzez_send_emails( sanitize_email( $to ), $subject, $body );
        }
        return (bool) wp_mail( sanitize_email( $to ), $subject, $body );
    }

    public static function participant_emails( $row ) {
        $ids = array_filter( array( self::partnership_user_ids( $row ) ) );
        $emails = array();
        foreach ( $ids as $id ) {
            $u = get_userdata( $id );
            if ( $u && $u->user_email ) {
                $emails[] = sanitize_email( $u->user_email );
            }
        }
        return array_values( array_unique( $emails ) );
    }

    public static function partnership_user_ids( $row ) {
        $requester_id = isset( $row->{ self::requester_col() } ) ? (int) $row->{ self::requester_col() } : 0;
        $owner_id     = isset( $row->{ self::owner_col() } ) ? (int) $row->{ self::owner_col() } : 0;
        return array_unique( array_filter( array( $requester_id, $owner_id ) ) );
    }

    public static function admin_emails() {
        $emails = array();
        $admins = get_users( array( 'role__in' => array( 'administrator', 'houzez_manager' ), 'fields' => array( 'ID' ), 'number' => 50 ) );
        foreach ( $admins as $admin ) {
            $u = get_userdata( $admin->ID );
            if ( $u && $u->user_email ) {
                $emails[] = sanitize_email( $u->user_email );
            }
        }
        if ( empty( $emails ) ) {
            $emails[] = get_option( 'admin_email' );
        }
        return array_values( array_unique( array_filter( $emails ) ) );
    }

    private static function audit( $event_type, $actor_user_id, $property_id, $partnership_id, $meta = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(60) NOT NULL DEFAULT '',
                actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                other_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                property_id bigint(20) unsigned NOT NULL DEFAULT 0,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                meta longtext NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY actor_user_id (actor_user_id),
                KEY property_id (property_id),
                KEY partnership_id (partnership_id)
            ) {$charset};"
        );

        if ( ! empty( $meta['ip'] ) || isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $meta['ip'] = isset( $meta['ip'] ) ? $meta['ip'] : sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return (int) $wpdb->insert(
            $table,
            array(
                'event_type' => sanitize_key( $event_type ),
                'actor_user_id' => absint( $actor_user_id ),
                'other_user_id' => isset( $meta['other_user_id'] ) ? absint( $meta['other_user_id'] ) : 0,
                'property_id' => absint( $property_id ),
                'partnership_id' => absint( $partnership_id ),
                'meta' => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    /**
     * Send humanized emails for funnel events to participants and admin.
     * Never throws; a mail failure must not undo the main operation.
     */
    public function email_funnel_event( $partnership_id, $row, $event, $data = array() ) {
        if ( ! $row ) {
            return;
        }
        $data        = is_array( $data ) ? $data : array();
        $property_id = isset( $row->property_id ) ? absint( $row->property_id ) : 0;
        $property    = $property_id ? get_the_title( $property_id ) : '';
        $requester_id = isset( $row->{ self::requester_col() } ) ? (int) $row->{ self::requester_col() } : 0;
        $owner_id     = isset( $row->{ self::owner_col() } ) ? (int) $row->{ self::owner_col() } : 0;
        $actor_id     = get_current_user_id();
        $actor        = $actor_id ? get_userdata( $actor_id ) : false;
        $actor_name   = $actor ? $actor->display_name : '';
        $now          = current_time( 'mysql' );
        $client       = isset( $data['client_name'] ) ? $data['client_name'] : '';

        $party = function( $id ) {
            $u = $id ? get_userdata( $id ) : false;
            return $u ? $u->display_name : '';
        };
        $requester_name = $party( $requester_id );
        $owner_name     = $party( $owner_id );

        // Audit FIRST (the main operation outcome), then email.
        $this->audit_funnel_event( $partnership_id, $row, $event, $data, $actor_id );

        $subject = '';
        $lines = array();
        $to_participants = false;
        $to_admin = false;

        switch ( $event ) {
            case 'accepted':
                $subject = __( 'Parceria iniciada! Você já pode entrar em contato', 'imovel-parceiro-core' );
                $requester_contact = self::contact_for_user( $requester_id );
                $words = array(
                    __( 'Olá!', 'imovel-parceiro-core' ),
                    '',
                    __( 'Temos uma boa notícia!', 'imovel-parceiro-core' ),
                    '',
                    sprintf( __( 'A parceria para o imóvel "%s" foi aceita e já está ativa.', 'imovel-parceiro-core' ), $property ),
                    '',
                    __( 'Agora vocês já podem trabalhar juntos nessa oportunidade.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Corretor responsável: %s', 'imovel-parceiro-core' ), $owner_name ),
                );
                $words[] = sprintf( __( 'WhatsApp: %s', 'imovel-parceiro-core' ), $requester_contact && ! empty( $requester_contact['whatsapp'] ) ? $requester_contact['whatsapp'] : implode( '/', array_filter( array( $requester_contact['phone'], $requester_contact['mobile'] ) ) ) );
                $words[] = sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property );
                $words[] = '';
                $words[] = strtoupper( __( 'Acessar parceria', 'imovel-parceiro-core' ) ) . ': ' . home_url( '/dashboard/' );
                $words[] = '';
                $words[] = __( 'Boa negociação!', 'imovel-parceiro-core' );
                $words[] = __( 'Atenciosamente,', 'imovel-parceiro-core' );
                $words[] = __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' );
                $lines = $words;
                $to_participants = true;
                $to_admin = false;
                break;

            case 'opportunity':
                $subject = __( 'Oportunidade registrada — parceria', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'Uma oportunidade foi registrada para uma parceria.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Cliente: %s', 'imovel-parceiro-core' ), $client ? $client : '-' ),
                    sprintf( __( 'Telefone: %s', 'imovel-parceiro-core' ), isset( $data['client_phone'] ) ? $data['client_phone'] : '-' ),
                    sprintf( __( 'E-mail: %s', 'imovel-parceiro-core' ), isset( $data['client_email'] ) ? $data['client_email'] : '-' ),
                    sprintf( __( 'Corretor dono: %s', 'imovel-parceiro-core' ), $owner_name ),
                    sprintf( __( 'Parceiro: %s', 'imovel-parceiro-core' ), $requester_name ),
                    sprintf( __( 'Usuário responsável: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Observações: %s', 'imovel-parceiro-core' ), isset( $data['notes'] ) ? $data['notes'] : '-' ),
                    sprintf( __( 'Data/hora: %s', 'imovel-parceiro-core' ), $now ),
                );
                $to_admin = true;
                break;

            case 'visit':
                $subject = __( 'Visita registrada — parceria', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'Uma visita foi registrada para uma parceria.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Cliente: %s', 'imovel-parceiro-core' ), $client ? $client : '-' ),
                    sprintf( __( 'Data: %s', 'imovel-parceiro-core' ), isset( $data['visit_date'] ) ? $data['visit_date'] : '-' ),
                    sprintf( __( 'Horário: %s', 'imovel-parceiro-core' ), isset( $data['visit_time'] ) ? $data['visit_time'] : '-' ),
                    sprintf( __( 'Participantes: %s', 'imovel-parceiro-core' ), isset( $data['participants'] ) ? $data['participants'] : '-' ),
                    sprintf( __( 'Observações: %s', 'imovel-parceiro-core' ), isset( $data['notes'] ) ? $data['notes'] : '-' ),
                    sprintf( __( 'Corretor dono: %s', 'imovel-parceiro-core' ), $owner_name ),
                    sprintf( __( 'Parceiro: %s', 'imovel-parceiro-core' ), $requester_name ),
                    sprintf( __( 'Usuário responsável: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Data/hora: %s', 'imovel-parceiro-core' ), $now ),
                );
                $to_admin = true;
                $to_participants = true;
                break;

            case 'proposal':
                $subject = __( 'Proposta registrada — parceria', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'Uma proposta foi registrada para uma parceria.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Cliente: %s', 'imovel-parceiro-core' ), $client ? $client : '-' ),
                    sprintf( __( 'Valor: %s', 'imovel-parceiro-core' ), isset( $data['amount'] ) ? $data['amount'] : '-' ),
                    sprintf( __( 'Condições: %s', 'imovel-parceiro-core' ), isset( $data['conditions'] ) ? $data['conditions'] : '-' ),
                    sprintf( __( 'Observações: %s', 'imovel-parceiro-core' ), isset( $data['notes'] ) ? $data['notes'] : '-' ),
                    sprintf( __( 'Corretor dono: %s', 'imovel-parceiro-core' ), $owner_name ),
                    sprintf( __( 'Parceiro: %s', 'imovel-parceiro-core' ), $requester_name ),
                    sprintf( __( 'Usuário responsável: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Data/hora: %s', 'imovel-parceiro-core' ), $now ),
                );
                $to_admin = true;
                $to_participants = true;
                break;

            case 'won':
                $deal = self::get_latest_event_for_kind( $partnership_id, 'status', self::WON );
                $wv = $deal && ! empty( $deal->meta['final_value'] ) ? $deal->meta['final_value'] : '';
                $subject = __( 'Negócio ganho! — Parabéns', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'Parabéns! O negócio foi registrado como ganho.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Envolvidos: %s e %s', 'imovel-parceiro-core' ), $owner_name, $requester_name ),
                    sprintf( __( 'Valor: %s', 'imovel-parceiro-core' ), $wv ? $wv : '-' ),
                    sprintf( __( 'Data: %s', 'imovel-parceiro-core' ), $now ),
                    '',
                    __( 'Lembre-se de realizar a divisão da comissão conforme as regras definidas para esta parceria.', 'imovel-parceiro-core' ),
                );
                $to_participants = true;
                $to_admin = true;
                break;

            case 'lost':
                $subject = __( 'Oportunidade perdida — parceria', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'A oportunidade foi marcada como perdida.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Cliente: %s', 'imovel-parceiro-core' ), $client ? $client : '-' ),
                    sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), isset( $data['reason'] ) ? $data['reason'] : '-' ),
                    sprintf( __( 'Observações: %s', 'imovel-parceiro-core' ), isset( $data['notes'] ) ? $data['notes'] : '-' ),
                    sprintf( __( 'Usuário: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Data/hora: %s', 'imovel-parceiro-core' ), $now ),
                );
                $to_admin = true;
                break;

            case 'closed':
                $subject = __( 'Parceria encerrada', 'imovel-parceiro-core' );
                $previous = isset( $data['previous'] ) ? $data['previous'] : '';
                $lines = array(
                    __( 'Uma parceria foi encerrada.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Proprietário: %s', 'imovel-parceiro-core' ), $owner_name ),
                    sprintf( __( 'Parceiro: %s', 'imovel-parceiro-core' ), $requester_name ),
                    sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), isset( $data['reason'] ) ? $data['reason'] : '-' ),
                    sprintf( __( 'Status anterior: %s', 'imovel-parceiro-core' ), self::status_label( $previous ) ),
                    sprintf( __( 'Status final: %s', 'imovel-parceiro-core' ), self::status_label( self::CLOSED ) ),
                    sprintf( __( 'Usuário: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Data/hora: %s', 'imovel-parceiro-core' ), $now ),
                );
                $to_admin = true;
                $to_participants = true;
                break;

            case 'deal_won':
                $subject = __( 'Negócio ganho — administração', 'imovel-parceiro-core' );
                $lines = array(
                    __( 'Um negócio foi registrado como ganho.', 'imovel-parceiro-core' ),
                    sprintf( __( 'Imóvel: %s', 'imovel-parceiro-core' ), $property ),
                    sprintf( __( 'Envolvidos: %s e %s', 'imovel-parceiro-core' ), $owner_name, $requester_name ),
                    sprintf( __( 'Cliente: %s', 'imovel-parceiro-core' ), $client ? $client : '-' ),
                    sprintf( __( 'Valor: %s', 'imovel-parceiro-core' ), isset( $data['final_value'] ) ? $data['final_value'] : '-' ),
                    sprintf( __( 'Comissão: %s', 'imovel-parceiro-core' ), isset( $data['commission'] ) ? $data['commission'] : '-' ),
                    sprintf( __( 'Data: %s', 'imovel-parceiro-core' ), $now ),
                    sprintf( __( 'Usuário que encerrou: %s', 'imovel-parceiro-core' ), $actor_name ),
                    sprintf( __( 'Status: %s', 'imovel-parceiro-core' ), self::status_label( self::WON ) ),
                );
                $to_admin = true;
                break;
        }

        if ( '' === $subject ) {
            return;
        }

        $body = implode( "\n", $lines );

        $recipients = array();
        if ( $to_participants ) {
            $recipients = array_merge( $recipients, self::participant_emails( $row ) );
        }
        if ( $to_admin ) {
            $recipients = array_merge( $recipients, self::admin_emails() );
        }
        $recipients = array_values( array_unique( array_filter( $recipients ) ) );

        $email_jobs = array();
        foreach ( $recipients as $recipient ) {
            $email_jobs[] = array(
                'to' => $recipient,
                'subject' => $subject,
                'body' => $body,
            );
        }

        // Funnel emails go out after the HTTP response (SMTP is slow).
        if ( class_exists( 'Imovel_Parceiro_Mailer' ) ) {
            Imovel_Parceiro_Mailer::defer( $email_jobs, array( 'Imovel_Parceiro_Mailer', 'send_via_houzez' ) );
        } else {
            foreach ( $email_jobs as $job ) {
                try {
                    self::send_plain_email( $job['to'], $job['subject'], $job['body'] );
                } catch ( \Exception $e ) {
                    // Mail failure must not undo the main operation.
                }
            }
        }
    }

    private function audit_funnel_event( $partnership_id, $row, $event, $data, $actor_id ) {
        $meta = is_array( $data ) ? $data : array();
        $property_id = isset( $row->property_id ) ? absint( $row->property_id ) : 0;
        $event_type = 'partnership_' . sanitize_key( $event );

        $this->audit( $event_type, $actor_id, $property_id, $partnership_id, $meta );
    }

    private static function get_latest_event_for_kind( $partnership_id, $kind, $type ) {
        return self::get_latest_event( $partnership_id, $kind, $type );
    }

    /* ---------------------------------------------------------------------
     * AJAX handlers
     * ------------------------------------------------------------------ */

    public function ajax_transition() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $to_status      = isset( $_POST['to_status'] ) ? sanitize_key( wp_unslash( $_POST['to_status'] ) ) : '';
        $payload        = isset( $_POST['payload'] ) && is_array( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : array();

        $result = $this->do_transition( $partnership_id, $to_status, $payload );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public function ajax_opportunity() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $user_id        = get_current_user_id();
        $row = self::get_partnership( $partnership_id );
        if ( ! $row || ! self::can_access( $row, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'imovel-parceiro-core' ) ) );
        }
        $status = self::funnel_status( isset( $row->status ) ? $row->status : '' );

        $data = array(
            'client_name'      => isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '',
            'client_phone'     => isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '',
            'client_email'     => isset( $_POST['client_email'] ) && is_email( wp_unslash( $_POST['client_email'] ) ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '',
            'first_contact_at' => isset( $_POST['first_contact_at'] ) ? sanitize_text_field( wp_unslash( $_POST['first_contact_at'] ) ) : '',
            'interest'         => isset( $_POST['interest'] ) ? sanitize_text_field( wp_unslash( $_POST['interest'] ) ) : '',
            'estimated_value'  => isset( $_POST['estimated_value'] ) ? sanitize_text_field( wp_unslash( $_POST['estimated_value'] ) ) : '',
            'notes'            => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
        );
        if ( '' === trim( $data['client_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe o nome do cliente.', 'imovel-parceiro-core' ) ) );
        }

        // Antifraude: exige identificacao do cliente e detecta duplicidade.
        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            $client_doc  = isset( $_POST['client_doc'] ) ? sanitize_text_field( wp_unslash( $_POST['client_doc'] ) ) : '';
            $fingerprint = Imovel_Parceiro_Partnership_Integrity::client_fingerprint( $data['client_phone'], $client_doc, $data['client_email'] );
            if ( '' === $fingerprint ) {
                wp_send_json_error( array( 'message' => __( 'Informe o telefone (ou CPF/e-mail) do cliente para registrar a oportunidade.', 'imovel-parceiro-core' ) ) );
            }

            $property_id  = isset( $row->property_id ) ? absint( $row->property_id ) : 0;
            $duplicate_id = Imovel_Parceiro_Partnership_Integrity::find_duplicate_client( $property_id, $fingerprint, $partnership_id );
            if ( $duplicate_id ) {
                Imovel_Parceiro_Partnership_Integrity::create_alert(
                    Imovel_Parceiro_Partnership_Integrity::ALERT_DUPLICATE_CLIENT,
                    __( 'Cliente possivelmente duplicado', 'imovel-parceiro-core' ),
                    sprintf(
                        /* translators: 1: current partnership id, 2: other partnership id */
                        __( 'O cliente registrado na parceria #%1$d também aparece na parceria #%2$d para o mesmo imóvel.', 'imovel-parceiro-core' ),
                        $partnership_id,
                        $duplicate_id
                    ),
                    array(
                        'severity'       => 'warning',
                        'partnership_id' => $partnership_id,
                        'property_id'    => $property_id,
                        'meta'           => array( 'duplicate_partnership_id' => $duplicate_id, 'fingerprint' => $fingerprint ),
                    )
                );
            }

            Imovel_Parceiro_Partnership_Integrity::set_client_fingerprint( $partnership_id, $fingerprint );
            Imovel_Parceiro_Partnership_Integrity::resume_contact( $partnership_id );
            Imovel_Parceiro_Partnership_Integrity::clear_sla( $partnership_id );
            $data['client_fingerprint'] = $fingerprint;
        }

        $this->record_event( $partnership_id, $user_id, 'opportunity', 'opportunity', __( 'Oportunidade registrada', 'imovel-parceiro-core' ), $data['notes'], $data );

        // Move status to opportunity (only when it makes sense from contact_released).
        if ( self::CONTACT_RELEASED === $status ) {
            $this->transition_status_internal( $partnership_id, self::OPPORTUNITY, array() );
        }

        self::notify_participants( $partnership_id, $row, 'PARCERIA_OPORTUNIDADE', __( 'Oportunidade registrada', 'imovel-parceiro-core' ), sprintf( __( 'Uma oportunidade foi registrada para %s.', 'imovel-parceiro-core' ), $data['client_name'] ) );

        $this->email_funnel_event( $partnership_id, $row, 'opportunity', $data );

        wp_send_json_success( array( 'message' => __( 'Oportunidade registrada.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_interaction() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $user_id        = get_current_user_id();
        $row = self::get_partnership( $partnership_id );
        if ( ! $row || ! self::can_access( $row, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'imovel-parceiro-core' ) ) );
        }

        $types = array( 'contato', 'ligacao', 'whatsapp', 'email', 'observacao' );
        $type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'observacao';
        if ( ! in_array( $type, $types, true ) ) {
            $type = 'observacao';
        }

        $note = isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '';
        if ( '' === trim( $note ) ) {
            wp_send_json_error( array( 'message' => __( 'Descreva a interação.', 'imovel-parceiro-core' ) ) );
        }

        $this->record_event( $partnership_id, $user_id, 'interaction', $type, __( 'Interação registrada', 'imovel-parceiro-core' ), $note );

        self::notify_participants( $partnership_id, $row, 'PARCERIA_INTERACAO', __( 'Nova interação', 'imovel-parceiro-core' ), $note );

        wp_send_json_success( array( 'message' => __( 'Interação registrada.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_visit() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $user_id        = get_current_user_id();
        $row = self::get_partnership( $partnership_id );
        if ( ! $row || ! self::can_access( $row, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'imovel-parceiro-core' ) ) );
        }

        $results = array( 'interessado', 'muito_interessado', 'sem_interesse', 'avaliar', 'nova_visita' );
        $result  = isset( $_POST['result'] ) ? sanitize_key( wp_unslash( $_POST['result'] ) ) : '';
        if ( ! in_array( $result, $results, true ) ) {
            $result = 'interessado';
        }

        $defaults = self::get_visit_term_defaults( $partnership_id, $user_id );

        $broker_name = isset( $_POST['broker_name'] ) ? sanitize_text_field( wp_unslash( $_POST['broker_name'] ) ) : '';
        if ( '' === $broker_name ) {
            $broker_name = isset( $defaults['broker_name'] ) ? $defaults['broker_name'] : '';
        }
        $broker_creci = isset( $_POST['broker_creci'] ) ? sanitize_text_field( wp_unslash( $_POST['broker_creci'] ) ) : '';
        if ( '' === $broker_creci ) {
            $broker_creci = isset( $defaults['broker_creci'] ) ? $defaults['broker_creci'] : '';
        }
        $broker_company = isset( $_POST['broker_company'] ) ? sanitize_text_field( wp_unslash( $_POST['broker_company'] ) ) : '';
        if ( '' === $broker_company ) {
            $broker_company = isset( $defaults['broker_company'] ) ? $defaults['broker_company'] : '';
        }

        $data = array(
            'client_name'      => isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '',
            'client_doc'       => isset( $_POST['client_doc'] ) ? sanitize_text_field( wp_unslash( $_POST['client_doc'] ) ) : '',
            'client_phone'     => isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '',
            'property_title'   => isset( $defaults['property_title'] ) ? $defaults['property_title'] : '',
            'property_ref'     => isset( $defaults['property_ref'] ) ? absint( $defaults['property_ref'] ) : 0,
            'property_address' => isset( $defaults['property_address'] ) ? $defaults['property_address'] : '',
            'broker_name'      => $broker_name,
            'broker_creci'     => $broker_creci,
            'broker_company'   => $broker_company,
            'visit_date'       => isset( $_POST['visit_date'] ) ? sanitize_text_field( wp_unslash( $_POST['visit_date'] ) ) : '',
            'visit_time'       => isset( $_POST['visit_time'] ) ? sanitize_text_field( wp_unslash( $_POST['visit_time'] ) ) : '',
            'participants'     => isset( $_POST['participants'] ) ? sanitize_text_field( wp_unslash( $_POST['participants'] ) ) : '',
            'result'           => $result,
            'notes'            => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
        );

        $this->record_event( $partnership_id, $user_id, 'visit', 'visit', __( 'Visita registrada', 'imovel-parceiro-core' ), $data['notes'], $data );

        $status = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        if ( self::OPPORTUNITY === $status ) {
            $this->transition_status_internal( $partnership_id, self::VISIT, array() );
        }

        self::notify_participants( $partnership_id, $row, 'PARCERIA_VISITA', __( 'Visita registrada', 'imovel-parceiro-core' ), sprintf( __( 'Uma visita foi registrada para %s.', 'imovel-parceiro-core' ), $data['client_name'] ) );

        $this->email_funnel_event( $partnership_id, $row, 'visit', $data );

        $term = array_merge(
            $data,
            array(
                'partnership_id' => $partnership_id,
                'generated_at'   => current_time( 'mysql' ),
            )
        );

        wp_send_json_success(
            array(
                'message' => __( 'Visita registrada.', 'imovel-parceiro-core' ),
                'term'    => $term,
            )
        );
    }

    public function ajax_proposal() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $user_id        = get_current_user_id();
        $row = self::get_partnership( $partnership_id );
        if ( ! $row || ! self::can_access( $row, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'imovel-parceiro-core' ) ) );
        }

        $data = array(
            'client_name'  => isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '',
            'amount'       => isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '',
            'proposal_date'=> isset( $_POST['proposal_date'] ) ? sanitize_text_field( wp_unslash( $_POST['proposal_date'] ) ) : '',
            'conditions'   => isset( $_POST['conditions'] ) ? sanitize_text_field( wp_unslash( $_POST['conditions'] ) ) : '',
            'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
        );
        if ( '' === trim( $data['client_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe o cliente.', 'imovel-parceiro-core' ) ) );
        }

        $amount_display = $data['amount'];
        $this->record_event( $partnership_id, $user_id, 'proposal', 'proposal', sprintf( __( 'Proposta registrada: %s', 'imovel-parceiro-core' ), $amount_display ), $data['notes'], $data );

        $status = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        if ( in_array( $status, array( self::OPPORTUNITY, self::VISIT ), true ) ) {
            $this->transition_status_internal( $partnership_id, self::PROPOSAL, array() );
        }

        self::notify_participants( $partnership_id, $row, 'PARCERIA_PROPOSTA', __( 'Proposta registrada', 'imovel-parceiro-core' ), sprintf( __( 'Uma proposta de %s foi registrada para %s.', 'imovel-parceiro-core' ), $amount_display, $data['client_name'] ) );

        $this->email_funnel_event( $partnership_id, $row, 'proposal', $data );

        wp_send_json_success( array( 'message' => __( 'Proposta registrada.', 'imovel-parceiro-core' ) ) );
    }

    public function ajax_contact() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $user_id        = get_current_user_id();
        $accept_terms   = ! empty( $_POST['contact_terms'] );

        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            $row = self::get_partnership( $partnership_id );

            // Contato suspenso por falta de registro do cliente.
            if ( $row && Imovel_Parceiro_Partnership_Integrity::is_contact_suspended( $row ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'O contato está suspenso até que o cliente seja registrado nesta parceria.', 'imovel-parceiro-core' ),
                        'code'    => 'contact_suspended',
                    )
                );
            }

            if ( ! $accept_terms && ! Imovel_Parceiro_Partnership_Integrity::has_accepted_contact_terms( $partnership_id, $user_id ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Aceite o termo de não-circunvenção para visualizar o contato.', 'imovel-parceiro-core' ),
                        'code'    => 'contact_terms_required',
                    )
                );
            }

            if ( $accept_terms ) {
                Imovel_Parceiro_Partnership_Integrity::accept_contact_terms( $partnership_id, $user_id );
            }
        }

        $contact = self::get_partner_contact( $partnership_id, $user_id );
        if ( null === $contact ) {
            wp_send_json_error( array( 'message' => __( 'Contato ainda não liberado ou sem permissão.', 'imovel-parceiro-core' ) ) );
        }

        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            Imovel_Parceiro_Partnership_Integrity::log_contact_access( $partnership_id, $user_id, 'view' );
            $contact['notice'] = __( 'Dados liberados exclusivamente para esta parceria. O uso fora da plataforma ou o repasse a terceiros é vedado e fica registrado.', 'imovel-parceiro-core' );
        }

        wp_send_json_success( array( 'contact' => $contact ) );
    }

    /**
     * Internal status change used by opportunity/visit/proposal registration.
     */
    private function transition_status_internal( $partnership_id, $to_status, $payload ) {
        $row = self::get_partnership( $partnership_id );
        if ( ! $row ) {
            return false;
        }
        $current = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        if ( $current === self::CLOSED || $current === $to_status ) {
            return false;
        }
        // Same permission rules as the public transition endpoint.
        if ( ! self::can_apply_transition( $row, get_current_user_id(), $to_status ) ) {
            return false;
        }
        $this->apply( $row, $current, $to_status, get_current_user_id(), $payload );
        return true;
    }

    /* ---------------------------------------------------------------------
     * Render helpers used by the detail template
     * ------------------------------------------------------------------ */

    public static function timeline_items( $row ) {
        $status   = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        $steps    = self::funnel_stages();
        $active   = array_search( $status, $steps, true );
        if ( false === $active ) {
            $active = -1;
        }

        $items = array();
        $sibling = ( self::WON === $status ) ? self::LOST : ( ( self::LOST === $status ) ? self::WON : '' );

        foreach ( array(
            self::PENDING          => array(
                __( 'Solicitação enviada', 'imovel-parceiro-core' ),
                __( 'O pedido de parceria foi enviado ao corretor responsável pelo imóvel.', 'imovel-parceiro-core' ),
            ),
            self::ACCEPTED         => array(
                __( 'Parceria aceita', 'imovel-parceiro-core' ),
                __( 'O corretor responsável aceitou trabalhar em parceria com você.', 'imovel-parceiro-core' ),
            ),
            self::NEGOTIATING      => array(
                __( 'Negociação', 'imovel-parceiro-core' ),
                __( 'As condições da parceria (como a divisão da comissão) estão sendo combinadas.', 'imovel-parceiro-core' ),
            ),
            self::CONTACT_RELEASED => array(
                __( 'Contato liberado', 'imovel-parceiro-core' ),
                __( 'Os dados de contato do corretor parceiro foram liberados.', 'imovel-parceiro-core' ),
            ),
            self::OPPORTUNITY      => array(
                __( 'Oportunidade registrada', 'imovel-parceiro-core' ),
                __( 'Um cliente interessado foi registrado nesta parceria.', 'imovel-parceiro-core' ),
            ),
            self::VISIT            => array(
                __( 'Visita', 'imovel-parceiro-core' ),
                __( 'Uma visita ao imóvel foi registrada com o cliente.', 'imovel-parceiro-core' ),
            ),
            self::PROPOSAL         => array(
                __( 'Proposta', 'imovel-parceiro-core' ),
                __( 'Uma proposta foi registrada para o cliente.', 'imovel-parceiro-core' ),
            ),
            self::WON              => array(
                __( 'Negócio ganho', 'imovel-parceiro-core' ),
                __( 'O negócio foi fechado. A comissão será dividida conforme combinado.', 'imovel-parceiro-core' ),
            ),
            self::LOST             => array(
                __( 'Negócio perdido', 'imovel-parceiro-core' ),
                __( 'O negócio não foi concluído nesta parceria.', 'imovel-parceiro-core' ),
            ),
            self::CLOSED           => array(
                __( 'Encerramento', 'imovel-parceiro-core' ),
                __( 'A parceria foi encerrada.', 'imovel-parceiro-core' ),
            ),
        ) as $key => $info ) {
            $idx  = array_search( $key, $steps, true );
            $state = 'future';
            if ( $key === $sibling ) {
                $state = 'skipped';
            } elseif ( $idx < $active ) {
                $state = 'done';
            } elseif ( $idx === $active ) {
                $state = 'current';
            }
            $items[] = array( 'key' => $key, 'label' => $info[0], 'desc' => $info[1], 'state' => $state );
        }

        return $items;
    }

    /**
     * Reason options (value => label) offered when closing a partnership as
     * won, lost or closed. The "outro" option reveals a free-text field.
     */
    public static function outcome_reasons( $outcome ) {
        $outcome = self::funnel_status( $outcome );

        $sets = array(
            self::WON => array(
                'vendido'         => __( 'Imóvel vendido', 'imovel-parceiro-core' ),
                'alugado'         => __( 'Imóvel alugado', 'imovel-parceiro-core' ),
                'direto_proprietario' => __( 'Negócio fechado diretamente com o proprietário', 'imovel-parceiro-core' ),
                'outra_imobiliaria' => __( 'Cliente fechou por outra imobiliária/corretor', 'imovel-parceiro-core' ),
                'outro'           => __( 'Outro (descrever)', 'imovel-parceiro-core' ),
            ),
            self::LOST => array(
                'cliente_desistiu' => __( 'Cliente desistiu', 'imovel-parceiro-core' ),
                'sem_retorno'      => __( 'Sem retorno do cliente', 'imovel-parceiro-core' ),
                'proposta_recusada' => __( 'Proposta recusada pelo proprietário', 'imovel-parceiro-core' ),
                'fora_do_mercado'  => __( 'Imóvel saiu do mercado (vendido/alugado por terceiros)', 'imovel-parceiro-core' ),
                'documentacao'     => __( 'Documentação irregular', 'imovel-parceiro-core' ),
                'exclusividade'    => __( 'Prazo de exclusividade expirado', 'imovel-parceiro-core' ),
                'outro'            => __( 'Outro (descrever)', 'imovel-parceiro-core' ),
            ),
            self::CLOSED => array(
                'pedido_proprietario' => __( 'Solicitação do proprietário', 'imovel-parceiro-core' ),
                'pedido_corretor'     => __( 'Solicitação do corretor', 'imovel-parceiro-core' ),
                'imovel_indisponivel' => __( 'Imóvel indisponível', 'imovel-parceiro-core' ),
                'mudanca_estrategia'  => __( 'Mudança de estratégia', 'imovel-parceiro-core' ),
                'outro'               => __( 'Outro (descrever)', 'imovel-parceiro-core' ),
            ),
        );

        return isset( $sets[ $outcome ] ) ? $sets[ $outcome ] : array();
    }

    public static function allowed_actions( $row, $user_id ) {
        $status   = self::funnel_status( isset( $row->status ) ? $row->status : '' );
        $role     = self::user_role( $row, $user_id );
        $actions  = array();
        $is_admin = self::is_admin();

        $any_basic = ( 'owner' === $role || 'requester' === $role || $is_admin );

        if ( self::PENDING === $status && ( 'owner' === $role || $is_admin ) ) {
            $actions[] = array( 'kind' => 'accept', 'label' => __( 'Aceitar parceria', 'imovel-parceiro-core' ) );
            $actions[] = array( 'kind' => 'reject', 'label' => __( 'Recusar', 'imovel-parceiro-core' ) );
        }

        if ( self::ACCEPTED === $status && $any_basic ) {
            $actions[] = array( 'kind' => 'negotiate', 'label' => __( 'Iniciar negociação', 'imovel-parceiro-core' ) );
        }

        if ( self::NEGOTIATING === $status && ( 'owner' === $role || $is_admin ) ) {
            $actions[] = array( 'kind' => 'release_contact', 'label' => __( 'Liberar contato', 'imovel-parceiro-core' ) );
        }

        if ( in_array( $status, array( self::CONTACT_RELEASED, self::NEGOTIATING ), true ) && $any_basic ) {
            $actions[] = array( 'kind' => 'opportunity', 'label' => __( 'Registrar oportunidade', 'imovel-parceiro-core' ) );
        }

        if ( in_array( $status, array( self::OPPORTUNITY, self::VISIT, self::CONTACT_RELEASED, self::NEGOTIATING ), true ) && $any_basic ) {
            $actions[] = array( 'kind' => 'interaction', 'label' => __( 'Registrar interação', 'imovel-parceiro-core' ) );
        }

        if ( in_array( $status, array( self::OPPORTUNITY, self::CONTACT_RELEASED, self::VISIT ), true ) && $any_basic ) {
            $actions[] = array( 'kind' => 'visit', 'label' => __( 'Registrar visita', 'imovel-parceiro-core' ) );
        }

        if ( in_array( $status, array( self::OPPORTUNITY, self::VISIT, self::CONTACT_RELEASED ), true ) && $any_basic ) {
            $actions[] = array( 'kind' => 'proposal', 'label' => __( 'Registrar proposta', 'imovel-parceiro-core' ) );
        }

        if ( self::PROPOSAL === $status && $any_basic ) {
            $actions[] = array( 'kind' => 'won', 'label' => __( 'Marcar como ganho', 'imovel-parceiro-core' ) );
            $actions[] = array( 'kind' => 'lost', 'label' => __( 'Marcar como perdido', 'imovel-parceiro-core' ) );
        }

        // Early / final close with reason.
        if ( ! in_array( $status, array( self::CLOSED, self::REJECTED ), true ) && $any_basic ) {
            $actions[] = array( 'kind' => 'close', 'label' => __( 'Encerrar parceria', 'imovel-parceiro-core' ) );
        }

        return $actions;
    }
}

Imovel_Parceiro_Partnership_Workflow::instance();

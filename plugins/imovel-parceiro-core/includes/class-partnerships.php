<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Partnerships {
    private $terminal_statuses = array( 'recusada', 'ganha', 'perdida', 'encerrada', 'rejected', 'won', 'lost', 'cancelled' );

    public function __construct() {
        add_action( 'wp_ajax_imovel_parceiro_request_partnership', array( $this, 'request_partnership' ) );
        add_action( 'wp_ajax_imovel_parceiro_handle_partnership', array( $this, 'handle_partnership' ) );
        add_action( 'wp_ajax_imovel_parceiro_cancel_partnership', array( $this, 'cancel_partnership' ) );
        add_action( 'wp_ajax_imovel_parceiro_transition_partnership', array( $this, 'transition_partnership' ) );
        add_action( 'wp_footer', array( $this, 'render_request_modal' ) );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    public static function normalize_status_key( $status ) {
        if ( ! is_string( $status ) ) {
            return '';
        }

        $status = strtolower( trim( $status ) );
        $status = str_replace( array( ' ', '-', '_' ), '', $status );

        $aliases = array(
            'pending' => 'solicitada',
            'solicitada' => 'solicitada',
            'solicitado' => 'solicitada',
            'accepted' => 'aceita',
            'aceita' => 'aceita',
            'rejected' => 'recusada',
            'recusada' => 'recusada',
            'active' => 'ativa',
            'ativa' => 'ativa',
            'negotiating' => 'emnegociacao',
            'emnegociacao' => 'emnegociacao',
            'emnegociação' => 'emnegociacao',
            'emandamento' => 'emnegociacao',
            'em_andamento' => 'emnegociacao',
            'em andamento' => 'emnegociacao',
            'won' => 'ganha',
            'ganha' => 'ganha',
            'ganhou' => 'ganha',
            'lost' => 'perdida',
            'perdida' => 'perdida',
            'perdeu' => 'perdida',
            'cancelled' => 'encerrada',
            'canceled' => 'encerrada',
            'encerrada' => 'encerrada',
            'finalizada' => 'encerrada',
            'finalizado' => 'encerrada',
        );

        return isset( $aliases[ $status ] ) ? $aliases[ $status ] : $status;
    }

    public static function get_status_label( $status ) {
        $normalized = self::normalize_status_key( $status );
        $labels = array(
            'solicitada' => __( 'SOLICITADA', 'imovel-parceiro-core' ),
            'aceita' => __( 'ACEITA', 'imovel-parceiro-core' ),
            'recusada' => __( 'RECUSADA', 'imovel-parceiro-core' ),
            'ativa' => __( 'ATIVA', 'imovel-parceiro-core' ),
            'emnegociacao' => __( 'EM NEGOCIAÇÃO', 'imovel-parceiro-core' ),
            'ganha' => __( 'GANHA', 'imovel-parceiro-core' ),
            'perdida' => __( 'PERDIDA', 'imovel-parceiro-core' ),
            'encerrada' => __( 'ENCERRADA', 'imovel-parceiro-core' ),
            'pending' => __( 'SOLICITADA', 'imovel-parceiro-core' ),
            'accepted' => __( 'ACEITA', 'imovel-parceiro-core' ),
            'rejected' => __( 'RECUSADA', 'imovel-parceiro-core' ),
            'active' => __( 'ATIVA', 'imovel-parceiro-core' ),
            'negotiating' => __( 'EM NEGOCIAÇÃO', 'imovel-parceiro-core' ),
            'won' => __( 'GANHA', 'imovel-parceiro-core' ),
            'lost' => __( 'PERDIDA', 'imovel-parceiro-core' ),
            'cancelled' => __( 'ENCERRADA', 'imovel-parceiro-core' ),
        );

        return isset( $labels[ $normalized ] ) ? $labels[ $normalized ] : ( is_string( $status ) ? strtoupper( $status ) : __( 'DESCONHECIDO', 'imovel-parceiro-core' ) );
    }

    public static function user_participates_in_partnership( $partnership_id, $user_id ) {
        global $wpdb;

        $partnership_id = absint( $partnership_id );
        $user_id = absint( $user_id );
        if ( ! $partnership_id || ! $user_id ) {
            return false;
        }

        $table = self::partnerships_table();
        if ( ! self::partnerships_table_exists() ) {
            return false;
        }

        $schema = self::get_partnership_table_schema();
        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];

        $row = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND ( {$requester_col} = %d OR {$owner_col} = %d ) LIMIT 1",
                $partnership_id,
                $user_id,
                $user_id
            )
        );

        return ! empty( $row );
    }

    /**
     * Login URL for the Houzez theme (never the WordPress wp-login.php).
     * When a Houzez login page template exists it is used; otherwise the site
     * home is returned so the theme's native login modal can take over.
     */
    public static function login_url( $redirect = '' ) {
        $login_url = function_exists( 'houzez_get_template_link' ) ? houzez_get_template_link( 'template/template-login.php' ) : '';

        if ( empty( $login_url ) || untrailingslashit( $login_url ) === untrailingslashit( home_url( '/' ) ) ) {
            $login_url = home_url( '/' );
        }

        if ( ! empty( $redirect ) ) {
            $login_url = add_query_arg( 'redirect_to', $redirect, $login_url );
        }

        return $login_url;
    }

    public static function build_secure_partnership_url( $partnership_id, $user_id = 0 ) {
        $partnership_id = absint( $partnership_id );
        $user_id = absint( $user_id );
        if ( ! $partnership_id ) {
            return home_url( '/' );
        }

        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );
        $dashboard_url = add_query_arg(
            array(
                'imovel_dashboard_area' => 'parcerias',
                'imovel_parceiro_partnership_id' => $partnership_id,
            ),
            $dashboard_url
        );

        if ( ! $user_id ) {
            return self::login_url( $dashboard_url );
        }

        return $dashboard_url;
    }

    public static function partnerships_table() {
        global $wpdb;

        return $wpdb->prefix . 'imovel_parceiro_partnerships';
    }

    public static function partnerships_table_exists() {
        static $exists = null;
        if ( null !== $exists ) {
            return $exists;
        }

        global $wpdb;

        $table = self::partnerships_table();
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        $exists = ( $found === $table );

        return $exists;
    }

    private static function resolve_best_column( $table, $primary_column, $fallback_column, $columns ) {
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

        if ( $fallback_count > $primary_count ) {
            return $fallback_column;
        }

        return $primary_column;
    }

    public static function get_partnership_table_schema() {
        global $wpdb;

        static $schema = null;
        if ( null !== $schema ) {
            return $schema;
        }

        $table = self::partnerships_table();
        if ( ! self::partnerships_table_exists() ) {
            $schema = array(
                'requester_col' => 'requester_id',
                'owner_col' => 'owner_id',
                'created_col' => 'requested_at',
                'notes_col' => 'notes',
                'has_commission_split' => false,
                'has_terms_accepted' => false,
                'has_updated_at' => false,
                'has_responded_at' => false,
                'has_partnership_deal_id' => false,
            );

            return $schema;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        $requester_col = self::resolve_best_column( $table, 'requester_id', 'captador_id', $columns );
        $owner_col = self::resolve_best_column( $table, 'owner_id', 'partner_id', $columns );
        $created_col = self::resolve_best_column( $table, 'requested_at', 'created_at', $columns );

        $schema = array(
            'requester_col' => $requester_col,
            'owner_col' => $owner_col,
            'created_col' => $created_col,
            'notes_col' => in_array( 'notes', $columns, true ) ? 'notes' : '',
            'has_commission_split' => in_array( 'commission_split', $columns, true ),
            'has_terms_accepted' => in_array( 'terms_accepted', $columns, true ),
            'has_updated_at' => in_array( 'updated_at', $columns, true ),
            'has_responded_at' => in_array( 'responded_at', $columns, true ),
            'has_partnership_deal_id' => in_array( 'partnership_deal_id', $columns, true ),
        );

        return $schema;
    }

    private function can_request_partnership( $property_id, $user_id ) {
        $property_id = absint( $property_id );
        $user_id = absint( $user_id );

        if ( ! $property_id || ! $user_id ) {
            return false;
        }

        $post_type = get_post_type( $property_id );
        if ( 'property' !== $post_type ) {
            return false;
        }

        $post_status = get_post_status( $property_id );
        if ( 'publish' !== $post_status && 'pending' !== $post_status ) {
            return false;
        }

        $is_admin = current_user_can( 'manage_options' );
        $user = get_userdata( $user_id );
        if ( ! $user || ( ! $is_admin && ! current_user_can( 'imovel_parceiro_manage_commercial' ) ) ) {
            return false;
        }

        return true;
    }

    private function has_existing_partnership_request( $property_id, $user_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();
        $requester_col = $schema['requester_col'];

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE property_id = %d AND {$requester_col} = %d ORDER BY id DESC LIMIT 1",
                absint( $property_id ),
                absint( $user_id )
            )
        );

        return ! empty( $existing_id );
    }

    private function get_existing_partnership_request_status( $property_id, $user_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();
        $requester_col = $schema['requester_col'];

        $status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$table} WHERE property_id = %d AND {$requester_col} = %d ORDER BY id DESC LIMIT 1",
                absint( $property_id ),
                absint( $user_id )
            )
        );

        return is_string( $status ) ? sanitize_key( $status ) : '';
    }

    private function get_transition_map() {
        return array(
            'solicitada' => array( 'aceita', 'recusada', 'encerrada' ),
            'aceita' => array( 'ativa', 'encerrada' ),
            'ativa' => array( 'emnegociacao', 'encerrada' ),
            'emnegociacao' => array( 'ganha', 'perdida', 'encerrada' ),
            'pending' => array( 'accepted', 'rejected', 'cancelled' ),
            'accepted' => array( 'active', 'cancelled' ),
            'active' => array( 'negotiating', 'cancelled' ),
            'negotiating' => array( 'won', 'lost', 'cancelled' ),
        );
    }

    private function can_transition_status( $current_status, $next_status ) {
        $map = $this->get_transition_map();
        $current_status = self::normalize_status_key( $current_status );
        $next_status = self::normalize_status_key( $next_status );

        if ( ! isset( $map[ $current_status ] ) ) {
            $legacy_current = self::normalize_status_key( $current_status );
            if ( isset( $map[ $legacy_current ] ) ) {
                return in_array( $next_status, $map[ $legacy_current ], true );
            }
            return false;
        }

        return in_array( $next_status, $map[ $current_status ], true );
    }

    private function get_user_role_in_partnership( $row, $schema, $user_id ) {
        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];

        if ( isset( $row->{$owner_col} ) && (int) $row->{$owner_col} === (int) $user_id ) {
            return 'owner';
        }

        if ( isset( $row->{$requester_col} ) && (int) $row->{$requester_col} === (int) $user_id ) {
            return 'requester';
        }

        return '';
    }

    private function user_can_apply_transition( $row, $schema, $user_id, $next_status ) {
        $role = $this->get_user_role_in_partnership( $row, $schema, $user_id );
        if ( empty( $role ) ) {
            return false;
        }

        $normalized_next = self::normalize_status_key( $next_status );
        $current_status = self::normalize_status_key( isset( $row->status ) ? $row->status : '' );

        if ( in_array( $normalized_next, array( 'recusada', 'aceita' ), true ) ) {
            return 'owner' === $role;
        }

        if ( in_array( $normalized_next, array( 'encerrada' ), true ) ) {
            return in_array( $current_status, array( 'solicitada', 'aceita', 'ativa', 'emnegociacao' ), true );
        }

        if ( in_array( $normalized_next, array( 'ativa', 'emnegociacao', 'ganha', 'perdida' ), true ) ) {
            // The partnership can only progress after the owner accepted it.
            if ( ! in_array( $current_status, array( 'aceita', 'ativa', 'emnegociacao' ), true ) ) {
                return false;
            }

            return in_array( $role, array( 'owner', 'requester' ), true );
        }

        return false;
    }

    private function should_sync_status_with_crm( $status ) {
        $normalized = self::normalize_status_key( $status );
        return in_array( $normalized, array( 'ganha', 'won', 'perdida', 'lost' ), true );
    }

    private function get_crm_status_by_partnership_status( $status ) {
        $normalized = self::normalize_status_key( $status );

        if ( in_array( $normalized, array( 'ganha', 'won' ), true ) ) {
            return 'Won Deal';
        }

        if ( in_array( $normalized, array( 'perdida', 'lost' ), true ) ) {
            return 'Lost Deal';
        }

        return 'Negotiation';
    }

    private function get_existing_deal_id_from_partnership( $row, $schema ) {
        if ( $schema['has_partnership_deal_id'] && ! empty( $row->partnership_deal_id ) ) {
            return absint( $row->partnership_deal_id );
        }

        return 0;
    }

    private function find_crm_deal_id_by_marker( $partnership_id, $property_id ) {
        global $wpdb;
        $deals_table = $wpdb->prefix . 'houzez_crm_deals';
        $marker = '[IPC_PARTNERSHIP_ID:' . absint( $partnership_id ) . ']';

        $deal_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT deal_id FROM {$deals_table} WHERE listing_id = %d AND private_note LIKE %s ORDER BY deal_id DESC LIMIT 1",
                absint( $property_id ),
                '%' . $wpdb->esc_like( $marker ) . '%'
            )
        );

        return absint( $deal_id );
    }

    private function build_crm_private_note( $partnership_id, $owner_user_id, $requester_user_id, $status ) {
        $parts = array(
            '[IPC_PARTNERSHIP_ID:' . absint( $partnership_id ) . ']',
            'owner_user_id=' . absint( $owner_user_id ),
            'requester_user_id=' . absint( $requester_user_id ),
            'status=' . sanitize_key( $status ),
        );

        return implode( ' | ', $parts );
    }

    private function get_property_price_value( $property_id ) {
        $raw = (string) get_post_meta( $property_id, 'fave_property_price', true );
        if ( '' === trim( $raw ) ) {
            return '';
        }

        if ( is_numeric( $raw ) ) {
            return (string) $raw;
        }

        $normalized = preg_replace( '/[^0-9\.,]/', '', $raw );
        if ( '' === $normalized ) {
            return '';
        }

        // Detect the decimal separator as whichever appears last; the other is a thousands separator.
        $last_dot = strrpos( $normalized, '.' );
        $last_comma = strrpos( $normalized, ',' );

        if ( false === $last_dot && false === $last_comma ) {
            return is_numeric( $normalized ) ? $normalized : '';
        }

        $decimal_sep = '.';
        if ( false !== $last_dot && false !== $last_comma ) {
            $decimal_sep = $last_dot > $last_comma ? '.' : ',';
        } elseif ( false !== $last_comma ) {
            $decimal_sep = ',';
        }

        if ( ',' === $decimal_sep ) {
            $clean = str_replace( '.', '', $normalized );
            $clean = str_replace( ',', '.', $clean );
        } else {
            $clean = str_replace( ',', '', $normalized );
        }

        return is_numeric( $clean ) ? $clean : '';
    }

    private function get_table_columns( $table ) {
        global $wpdb;

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        return is_array( $columns ) ? $columns : array();
    }

    private function get_crm_deals_columns() {
        global $wpdb;
        static $columns = null;

        if ( null === $columns ) {
            $columns = $this->get_table_columns( $wpdb->prefix . 'houzez_crm_deals' );
        }

        return $columns;
    }

    private function crm_deals_available() {
        $columns = $this->get_crm_deals_columns();
        if ( empty( $columns ) ) {
            return false;
        }

        $required = array( 'user_id', 'deal_group', 'title', 'listing_id', 'lead_id', 'agent_id', 'agent_type', 'status', 'next_action', 'action_due_date', 'deal_value', 'last_contact_date', 'private_note', 'time' );
        foreach ( $required as $column ) {
            if ( ! in_array( $column, $columns, true ) ) {
                return false;
            }
        }

        return true;
    }

    private function get_crm_activities_columns() {
        global $wpdb;
        static $columns = null;

        if ( null === $columns ) {
            $columns = $this->get_table_columns( $wpdb->prefix . 'houzez_crm_activities' );
        }

        return $columns;
    }

    private function crm_activities_available() {
        $columns = $this->get_crm_activities_columns();
        if ( empty( $columns ) ) {
            return false;
        }

        $required = array( 'user_id', 'meta', 'time' );
        foreach ( $required as $column ) {
            if ( ! in_array( $column, $columns, true ) ) {
                return false;
            }
        }

        return true;
    }

    private function maybe_sync_partnership_deal_to_crm( $row, $schema, $next_status ) {
        if ( ! $this->should_sync_status_with_crm( $next_status ) ) {
            return;
        }

        if ( ! $this->crm_deals_available() ) {
            return;
        }

        global $wpdb;
        $deals_table = $wpdb->prefix . 'houzez_crm_deals';
        $partnerships_table = $wpdb->prefix . 'imovel_parceiro_partnerships';

        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];

        $owner_user_id = isset( $row->{$owner_col} ) ? absint( $row->{$owner_col} ) : 0;
        $requester_user_id = isset( $row->{$requester_col} ) ? absint( $row->{$requester_col} ) : 0;
        $property_id = isset( $row->property_id ) ? absint( $row->property_id ) : 0;
        $partnership_id = isset( $row->id ) ? absint( $row->id ) : 0;

        if ( ! $owner_user_id || ! $requester_user_id || ! $property_id || ! $partnership_id ) {
            return;
        }

        $normalized_next_status = self::normalize_status_key( $next_status );
        $deal_group = in_array( $normalized_next_status, array( 'ganha', 'won' ), true ) ? 'won' : 'lost';
        $deal_status = $this->get_crm_status_by_partnership_status( $normalized_next_status );
        $property_title = get_the_title( $property_id );
        if ( empty( $property_title ) ) {
            $property_title = 'Imovel #' . $property_id;
        }

        $deal_title = sprintf( 'Parceria #%d - %s', $partnership_id, $property_title );
        $private_note = $this->build_crm_private_note( $partnership_id, $owner_user_id, $requester_user_id, $next_status );
        $deal_value = $this->get_property_price_value( $property_id );

        $deal_id = $this->get_existing_deal_id_from_partnership( $row, $schema );
        if ( ! $deal_id ) {
            $deal_id = $this->find_crm_deal_id_by_marker( $partnership_id, $property_id );
        }

        if ( $deal_id ) {
            $wpdb->update(
                $deals_table,
                array(
                    'deal_group' => $deal_group,
                    'title' => $deal_title,
                    'listing_id' => $property_id,
                    'user_id' => $owner_user_id,
                    'agent_id' => $requester_user_id,
                    'agent_type' => 'author_info',
                    'status' => $deal_status,
                    'private_note' => $private_note,
                    'deal_value' => $deal_value,
                    'time' => gmdate( 'Y-m-d H:i:s' ),
                ),
                array( 'deal_id' => $deal_id ),
                array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $inserted = $wpdb->insert(
                $deals_table,
                array(
                    'user_id' => $owner_user_id,
                    'deal_group' => $deal_group,
                    'title' => $deal_title,
                    'listing_id' => $property_id,
                    'lead_id' => 0,
                    'agent_id' => $requester_user_id,
                    'agent_type' => 'author_info',
                    'status' => $deal_status,
                    'next_action' => '',
                    'action_due_date' => '0000-00-00 00:00:00',
                    'deal_value' => $deal_value,
                    'last_contact_date' => '0000-00-00 00:00:00',
                    'private_note' => $private_note,
                    'time' => gmdate( 'Y-m-d H:i:s' ),
                ),
                array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( $inserted ) {
                $deal_id = absint( $wpdb->insert_id );
            }
        }

        if ( $deal_id && $schema['has_partnership_deal_id'] ) {
            $update_data = array( 'partnership_deal_id' => $deal_id );
            $update_format = array( '%d' );
            if ( $schema['has_updated_at'] ) {
                $update_data['updated_at'] = current_time( 'mysql' );
                $update_format[] = '%s';
            }

            $wpdb->update(
                $partnerships_table,
                $update_data,
                array( 'id' => $partnership_id ),
                $update_format,
                array( '%d' )
            );
        }

        if ( $deal_id ) {
            $this->insert_crm_activity(
                $owner_user_id,
                array(
                    'plugin' => 'imovel-parceiro-core',
                    'activity_type' => 'partnership_status_changed',
                    'partnership_id' => $partnership_id,
                    'deal_id' => $deal_id,
                    'listing_id' => $property_id,
                    'status' => $next_status,
                    'message' => sprintf( 'Parceria #%d marcada como %s.', $partnership_id, $this->get_status_label( $next_status ) ),
                )
            );
        }
    }

    private function insert_crm_activity( $user_id, $meta ) {
        global $wpdb;
        $activities_table = $wpdb->prefix . 'houzez_crm_activities';

        if ( ! $user_id || ! $this->crm_activities_available() ) {
            return;
        }

        $wpdb->insert(
            $activities_table,
            array(
                'user_id' => absint( $user_id ),
                'meta' => maybe_serialize( $meta ),
                'time' => gmdate( 'Y-m-d H:i:s' ),
            ),
            array( '%d', '%s', '%s' )
        );
    }

    private static function user_exists( $user_id ) {
        $user_id = absint( $user_id );

        return $user_id > 0 && false !== get_userdata( $user_id );
    }

    /**
     * Resolve the property owner (dono do imóvel). Public so other modules can
     * hide/deny partnership actions for the owner.
     */
    public static function property_owner_id( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return 0;
        }

        // The owner relation is authoritative for properties registered by an owner.
        // Only trust it when the referenced user actually exists (stale/deleted IDs are skipped).
        $registered_owner_id = absint( get_post_meta( $property_id, '_imovel_parceiro_proprietario_id', true ) );
        if ( $registered_owner_id > 0 && self::user_exists( $registered_owner_id ) ) {
            return $registered_owner_id;
        }

        $post_author_id = absint( get_post_field( 'post_author', $property_id ) );
        $post_author = $post_author_id ? get_userdata( $post_author_id ) : false;
        if ( $post_author && in_array( 'houzez_owner', (array) $post_author->roles, true ) ) {
            return $post_author_id;
        }

        $assigned_broker_id = absint( get_post_meta( $property_id, '_imovel_parceiro_corretor_responsavel_id', true ) );
        if ( $assigned_broker_id > 0 && self::user_exists( $assigned_broker_id ) ) {
            return $assigned_broker_id;
        }

        $receiver_id = $post_author_id;

        $agent_display_option = get_post_meta( $property_id, 'fave_agent_display_option', true );
        $prop_agent_display = get_post_meta( $property_id, 'fave_agents', true );

        if ( '-1' !== (string) $prop_agent_display && 'agent_info' === $agent_display_option ) {
            $prop_agent_id = absint( get_post_meta( $property_id, 'fave_agents', true ) );
            if ( $prop_agent_id ) {
                $agent_user_id = (int) get_post_meta( $prop_agent_id, 'houzez_user_meta_id', true );
                if ( $agent_user_id > 0 && self::user_exists( $agent_user_id ) ) {
                    return $agent_user_id;
                }

                $agent_post = get_post( $prop_agent_id );
                $agent_author_id = $agent_post && ! empty( $agent_post->post_author ) ? (int) $agent_post->post_author : 0;
                if ( $agent_author_id > 0 && self::user_exists( $agent_author_id ) ) {
                    return $agent_author_id;
                }
            }
        } elseif ( 'agency_info' === $agent_display_option ) {
            $prop_agency_id = absint( get_post_meta( $property_id, 'fave_property_agency', true ) );
            if ( $prop_agency_id ) {
                $agency_user_id = (int) get_post_meta( $prop_agency_id, 'houzez_user_meta_id', true );
                if ( $agency_user_id > 0 && self::user_exists( $agency_user_id ) ) {
                    return $agency_user_id;
                }

                $agency_post = get_post( $prop_agency_id );
                $agency_author_id = $agency_post && ! empty( $agency_post->post_author ) ? (int) $agency_post->post_author : 0;
                if ( $agency_author_id > 0 && self::user_exists( $agency_author_id ) ) {
                    return $agency_author_id;
                }
            }
        }

        // Fallback: the property author is the owner. No existence check needed because
        // an authored property always has a real author.
        return $receiver_id;
    }

    private function get_property_owner_user_id( $property_id ) {
        return self::property_owner_id( $property_id );
    }

    private function get_user_contact_details( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return array();
        }

        $phone = (string) get_user_meta( $user->ID, 'fave_author_phone', true );
        $mobile = (string) get_user_meta( $user->ID, 'fave_author_mobile', true );
        $whatsapp = (string) get_user_meta( $user->ID, 'fave_author_whatsapp', true );

        return array(
            'user_id' => (int) $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'phone_call' => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $phone ) : preg_replace( '/[^0-9+]/', '', $phone ),
            'mobile' => $mobile,
            'mobile_call' => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $mobile ) : preg_replace( '/[^0-9+]/', '', $mobile ),
            'whatsapp' => $whatsapp,
            'whatsapp_call' => function_exists( 'houzez_clean_phone_number' ) ? houzez_clean_phone_number( $whatsapp ) : preg_replace( '/[^0-9+]/', '', $whatsapp ),
        );
    }

    private function get_partnership_action_label( $action ) {
        $labels = array(
            'request' => __( 'Solicitação de parceria', 'imovel-parceiro-core' ),
            'accept' => __( 'Aceite de parceria', 'imovel-parceiro-core' ),
            'reject' => __( 'Recusa de parceria', 'imovel-parceiro-core' ),
            'cancel' => __( 'Cancelamento de parceria', 'imovel-parceiro-core' ),
            'update' => __( 'Atualização de parceria', 'imovel-parceiro-core' ),
        );

        return isset( $labels[ $action ] ) ? $labels[ $action ] : __( 'Atualização de parceria', 'imovel-parceiro-core' );
    }

    private function get_partnership_email_recipient_data( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return array();
        }

        return array(
            'user_id' => (int) $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
        );
    }

    public static function get_secure_dashboard_link( $partnership_id ) {
        return self::build_secure_partnership_url( $partnership_id );
    }

    public static function get_partnerships_dashboard_url( $args = array() ) {
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );
        $args = is_array( $args ) ? $args : array();
        $args = array_merge( array( 'imovel_dashboard_area' => 'parcerias' ), $args );

        return add_query_arg( $args, $dashboard_url );
    }

    /**
     * URL da pagina "Minhas parcerias" do dashboard (template partnerships.php).
     */
    public static function dashboard_partnerships_url() {
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );

        return add_query_arg( 'imovel-parceiro', 'dashboard', $dashboard_url );
    }

    private function user_display_name( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return '';
        }

        $name = trim( (string) $user->display_name );
        if ( '' === $name ) {
            $name = trim( (string) $user->user_login );
        }

        return $name ? $name : ( '# ' . absint( $user_id ) );
    }

    public function get_user_partnerships( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! self::partnerships_table_exists() ) {
            return array();
        }

        global $wpdb;
        $table = self::partnerships_table();
        $schema = self::get_partnership_table_schema();
        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$requester_col} = %d OR {$owner_col} = %d ORDER BY id DESC LIMIT 100",
                $user_id,
                $user_id
            )
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $items = array();
        foreach ( $rows as $row ) {
            $raw_status = isset( $row->status ) ? $row->status : '';
            $status = self::normalize_status_key( $raw_status );
            $requester_id = isset( $row->{$requester_col} ) ? absint( $row->{$requester_col} ) : 0;
            $owner_id = isset( $row->{$owner_col} ) ? absint( $row->{$owner_col} ) : 0;
            $property_id = isset( $row->property_id ) ? absint( $row->property_id ) : 0;

            $items[] = array(
                'id' => isset( $row->id ) ? absint( $row->id ) : 0,
                'property_id' => $property_id,
                'property_title' => $property_id ? get_the_title( $property_id ) : '',
                'property_url' => $property_id ? get_permalink( $property_id ) : '',
                'requester_id' => $requester_id,
                'owner_id' => $owner_id,
                'requester_name' => $this->user_display_name( $requester_id ),
                'owner_name' => $this->user_display_name( $owner_id ),
                'status' => $status,
                'status_label' => self::get_status_label( $raw_status ),
                'role' => $this->get_user_role_in_partnership( $row, $schema, $user_id ),
                'is_terminal' => in_array( $status, $this->terminal_statuses, true ),
            );
        }

        return $items;
    }

    public function render_partnerships_panel() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $partnerships = $this->get_user_partnerships( $user_id );
        ?>
        <div class="imovel-parceiro-partnerships-panel">
            <h3><?php esc_html_e( 'Minhas Parcerias', 'imovel-parceiro-core' ); ?></h3>
            <?php if ( empty( $partnerships ) ) : ?>
                <p class="imovel-parceiro-empty"><?php esc_html_e( 'Você ainda não possui parcerias.', 'imovel-parceiro-core' ); ?></p>
            <?php else : ?>
                <ul class="imovel-parceiro-partnership-list">
                    <?php foreach ( $partnerships as $partnership ) : ?>
                        <?php
                        $status = $partnership['status'];
                        $role = $partnership['role'];
                        $is_terminal = $partnership['is_terminal'];
                        $forward_map = array(
                            'aceita' => array( 'ativa' => __( 'Iniciar parceria', 'imovel-parceiro-core' ) ),
                            'ativa' => array( 'emnegociacao' => __( 'Iniciar negociação', 'imovel-parceiro-core' ) ),
                            'emnegociacao' => array(
                                'ganha' => __( 'Marcar como ganha', 'imovel-parceiro-core' ),
                                'perdida' => __( 'Marcar como perdida', 'imovel-parceiro-core' ),
                            ),
                        );
                        ?>
                        <li class="imovel-parceiro-partnership-item" data-partnership-id="<?php echo absint( $partnership['id'] ); ?>">
                            <div class="imovel-parceiro-partnership-head">
                                <span class="imovel-parceiro-partnership-title">
                                    <?php
                                    if ( ! empty( $partnership['property_url'] ) ) {
                                        echo '<a href="' . esc_url( $partnership['property_url'] ) . '">' . esc_html( $partnership['property_title'] ? $partnership['property_title'] : ( 'Imóvel #' . $partnership['property_id'] ) ) . '</a>';
                                    } else {
                                        echo esc_html( $partnership['property_title'] ? $partnership['property_title'] : ( 'Imóvel #' . $partnership['property_id'] ) );
                                    }
                                    ?>
                                </span>
                                <span class="imovel-parceiro-partnership-status imovel-parceiro-status-<?php echo esc_attr( sanitize_key( $status ) ); ?>">
                                    <?php echo esc_html( $partnership['status_label'] ); ?>
                                </span>
                            </div>
                            <div class="imovel-parceiro-partnership-meta">
                                <span><?php esc_html_e( 'Corretor:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $partnership['requester_name'] ); ?></span>
                                <span><?php esc_html_e( 'Anunciante:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $partnership['owner_name'] ); ?></span>
                                <?php if ( $role ) : ?>
                                    <span class="imovel-parceiro-my-role"><?php esc_html_e( 'Meu papel:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( 'owner' === $role ? __( 'Anunciante', 'imovel-parceiro-core' ) : __( 'Corretor', 'imovel-parceiro-core' ) ); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! $is_terminal ) : ?>
                                <div class="imovel-parceiro-partnership-actions">
                                    <?php if ( 'solicitada' === $status && 'owner' === $role ) : ?>
                                        <button type="button" class="imovel-parceiro-button imovel-parceiro-partnership-action imovel-parceiro-partnership-accept" data-partnership-id="<?php echo absint( $partnership['id'] ); ?>">
                                            <?php esc_html_e( 'Aceitar', 'imovel-parceiro-core' ); ?>
                                        </button>
                                        <button type="button" class="imovel-parceiro-button imovel-parceiro-button-secondary imovel-parceiro-partnership-action imovel-parceiro-partnership-reject" data-partnership-id="<?php echo absint( $partnership['id'] ); ?>">
                                            <?php esc_html_e( 'Recusar', 'imovel-parceiro-core' ); ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ( isset( $forward_map[ $status ] ) ) : ?>
                                        <?php foreach ( $forward_map[ $status ] as $next_status => $label ) : ?>
                                            <button type="button" class="imovel-parceiro-button imovel-parceiro-partnership-action imovel-parceiro-partnership-transition" data-partnership-id="<?php echo absint( $partnership['id'] ); ?>" data-next-status="<?php echo esc_attr( $next_status ); ?>">
                                                <?php echo esc_html( $label ); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <button type="button" class="imovel-parceiro-button imovel-parceiro-button-danger imovel-parceiro-partnership-action imovel-parceiro-partnership-cancel" data-partnership-id="<?php echo absint( $partnership['id'] ); ?>">
                                        <?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?>
                                    </button>
                                </div>
                            <?php else : ?>
                                <div class="imovel-parceiro-partnership-final">
                                    <?php esc_html_e( 'Esta parceria está finalizada.', 'imovel-parceiro-core' ); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private function send_email_using_houzez_config( $to, $subject, $message ) {
        if ( empty( $to ) ) {
            return false;
        }

        if ( function_exists( 'houzez_send_emails' ) ) {
            houzez_send_emails( $to, $subject, $message );
            return true;
        }

        return (bool) wp_mail( $to, $subject, $message );
    }

    private function send_in_app_notifications( $action, $context ) {
        if ( ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }

        $property_id = ! empty( $context['property_id'] ) ? absint( $context['property_id'] ) : 0;
        $property_url = ! empty( $context['property_url'] ) ? $context['property_url'] : get_permalink( $property_id );
        $property_title = ! empty( $context['property_title'] ) ? $context['property_title'] : sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id );
        $partnership_id = ! empty( $context['partnership_id'] ) ? absint( $context['partnership_id'] ) : 0;
        $status_label = ! empty( $context['status_label'] ) ? $context['status_label'] : $this->get_status_label( ! empty( $context['status'] ) ? $context['status'] : '' );
        $requester_id = ! empty( $context['requester_id'] ) ? absint( $context['requester_id'] ) : 0;
        $owner_id = ! empty( $context['owner_id'] ) ? absint( $context['owner_id'] ) : 0;
        $actor_name = ! empty( $context['actor_name'] ) ? $context['actor_name'] : '';

        $targets = array();

        if ( 'request' === $action ) {
            $targets[] = array(
                'user_id' => $owner_id,
                'title' => __( 'Nova solicitação de parceria', 'imovel-parceiro-core' ),
                'message' => sprintf( __( '%1$s solicitou parceria para %2$s.', 'imovel-parceiro-core' ), ! empty( $actor_name ) ? $actor_name : __( 'Um corretor', 'imovel-parceiro-core' ), $property_title ),
                'priority' => IPC_Notifications::PRIORITY_NORMAL,
            );
            $targets[] = array(
                'user_id' => $requester_id,
                'title' => __( 'Solicitação de parceria enviada', 'imovel-parceiro-core' ),
                'message' => sprintf( __( 'Sua solicitação para %s foi enviada.', 'imovel-parceiro-core' ), $property_title ),
                'priority' => IPC_Notifications::PRIORITY_INFO,
            );
        } elseif ( in_array( $action, array( 'accept', 'reject', 'cancel', 'update' ), true ) ) {
            $base_message = 'accept' === $action
                ? sprintf( __( 'Sua parceria para %s foi aprovada.', 'imovel-parceiro-core' ), $property_title )
                : ( 'reject' === $action
                    ? sprintf( __( 'Sua parceria para %s foi recusada.', 'imovel-parceiro-core' ), $property_title )
                    : ( 'cancel' === $action
                        ? sprintf( __( 'A parceria para %s foi cancelada.', 'imovel-parceiro-core' ), $property_title )
                        : sprintf( __( 'A parceria para %s foi atualizada para o status %s.', 'imovel-parceiro-core' ), $property_title, $status_label ) ) );

            $targets[] = array(
                'user_id' => $requester_id,
                'title' => $this->get_partnership_action_label( $action ),
                'message' => $base_message,
                'priority' => 'reject' === $action ? IPC_Notifications::PRIORITY_IMPORTANT : IPC_Notifications::PRIORITY_NORMAL,
            );
            $targets[] = array(
                'user_id' => $owner_id,
                'title' => $this->get_partnership_action_label( $action ),
                'message' => sprintf( __( 'A parceria de %s foi atualizada.', 'imovel-parceiro-core' ), $property_title ),
                'priority' => IPC_Notifications::PRIORITY_NORMAL,
            );
        }

        foreach ( $targets as $target ) {
            if ( empty( $target['user_id'] ) ) {
                continue;
            }

            IPC_Notifications::send(
                array(
                    'user_id' => (int) $target['user_id'],
                    'property_id' => $property_id,
                    'partnership_id' => $partnership_id,
                    'type' => strtoupper( sanitize_key( $action . '_partnership' ) ),
                    'category' => IPC_Notifications::CATEGORY_PARCERIAS,
                    'title' => $target['title'],
                    'message' => $target['message'],
                    'url' => $property_url,
                    'priority' => $target['priority'],
                )
            );
        }

        if ( 'request' === $action ) {
            $admin_users = get_users(
                array(
                    'role__in' => array( 'administrator', 'houzez_manager' ),
                    'fields' => array( 'ID' ),
                    'number' => 50,
                )
            );

            foreach ( $admin_users as $admin_user ) {
                IPC_Notifications::send(
                    array(
                        'user_id' => (int) $admin_user->ID,
                        'property_id' => $property_id,
                        'partnership_id' => $partnership_id,
                        'type' => 'NOVA_PARCERIA',
                        'category' => IPC_Notifications::CATEGORY_PARCERIAS,
                        'title' => __( 'Nova solicitação de parceria', 'imovel-parceiro-core' ),
                        'message' => sprintf( __( 'Uma solicitação de parceria foi enviada para %s.', 'imovel-parceiro-core' ), $property_title ),
                        'url' => $property_url,
                        'priority' => IPC_Notifications::PRIORITY_NORMAL,
                    )
                );
            }
        }
    }

    private function send_partnership_notifications( $action, $context ) {
        $requester = ! empty( $context['requester_id'] ) ? $this->get_partnership_email_recipient_data( $context['requester_id'] ) : array();
        $owner = ! empty( $context['owner_id'] ) ? $this->get_partnership_email_recipient_data( $context['owner_id'] ) : array();
        $admin_email = get_option( 'admin_email' );

        $recipients = array();
        if ( ! empty( $requester['email'] ) ) {
            $recipients[] = $requester['email'];
        }
        if ( ! empty( $owner['email'] ) ) {
            $recipients[] = $owner['email'];
        }
        if ( ! empty( $admin_email ) ) {
            $recipients[] = $admin_email;
        }

        $recipients = array_values( array_unique( array_filter( array_map( 'sanitize_email', $recipients ) ) ) );
        if ( empty( $recipients ) ) {
            return;
        }

        $property_title = ! empty( $context['property_title'] ) ? $context['property_title'] : sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $context['property_id'] ) );
        $property_url = ! empty( $context['property_url'] ) ? $context['property_url'] : get_permalink( absint( $context['property_id'] ) );
        $partnership_id = ! empty( $context['partnership_id'] ) ? absint( $context['partnership_id'] ) : 0;
        $status_label = ! empty( $context['status_label'] ) ? $context['status_label'] : self::get_status_label( ! empty( $context['status'] ) ? $context['status'] : '' );
        $actor_name = ! empty( $context['actor_name'] ) ? $context['actor_name'] : '';
        $actor_role = ! empty( $context['actor_role'] ) ? $context['actor_role'] : '';
        $actor_email = ! empty( $context['actor_email'] ) ? $context['actor_email'] : '';
        $message_note = ! empty( $context['message'] ) ? $context['message'] : '';
        $reason_note = ! empty( $context['reason'] ) ? $context['reason'] : '';

        $email_jobs = array();
        foreach ( $recipients as $recipient_email ) {
            $recipient_role = 'admin';
            $recipient_name = __( 'Administrador', 'imovel-parceiro-core' );
            if ( ! empty( $requester['email'] ) && $recipient_email === $requester['email'] ) {
                $recipient_role = 'requester';
                $recipient_name = ! empty( $requester['name'] ) ? $requester['name'] : '';
            } elseif ( ! empty( $owner['email'] ) && $recipient_email === $owner['email'] ) {
                $recipient_role = 'owner';
                $recipient_name = ! empty( $owner['name'] ) ? $owner['name'] : '';
            }

            $mail_config = $this->get_partnership_email_spec( $action, $recipient_role );
            $subject = is_string( $mail_config['subject'] ) ? sprintf( $mail_config['subject'], $property_title ) : $mail_config['subject'];
            $subject = apply_filters( 'imovel_parceiro_partnership_email_subject', $subject, $action, $context, $recipient_email );

            $recipient_user = get_user_by( 'email', $recipient_email );
            $direct_link = self::build_secure_partnership_url( $partnership_id, $recipient_user ? (int) $recipient_user->ID : 0 );

            $comparison_name = 'owner' === $recipient_role
                ? ( ! empty( $actor_name ) ? $actor_name : __( 'O corretor', 'imovel-parceiro-core' ) )
                : ( ! empty( $owner['name'] ) ? $owner['name'] : __( 'Anunciante', 'imovel-parceiro-core' ) );

            $template = array(
                sprintf( __( 'Olá %s,', 'imovel-parceiro-core' ), $recipient_name ),
                '',
                sprintf( $mail_config['intro'], $comparison_name ),
                $property_title,
                '',
            );

            if ( 'request' === $action && 'owner' === $recipient_role && ! empty( $message_note ) ) {
                $template[] = sprintf( __( 'Mensagem do solicitante: %s', 'imovel-parceiro-core' ), $message_note );
                $template[] = '';
            }

            if ( ! empty( $reason_note ) ) {
                $template[] = sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), $reason_note );
                $template[] = '';
            }

            if ( 'update' === $action ) {
                $template[] = sprintf( __( 'O status atual da parceria é %s.', 'imovel-parceiro-core' ), $status_label );
                $template[] = '';
            }

            foreach ( (array) $mail_config['body'] as $body_line ) {
                if ( ! empty( $body_line ) ) {
                    $template[] = $body_line;
                }
            }

            $template[] = '';
            $template[] = strtoupper( $mail_config['cta'] ) . ': ' . $direct_link;
            $template[] = '';
            $template[] = __( 'Atenciosamente,', 'imovel-parceiro-core' );
            $template[] = __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' );

            $message = implode( "\n", $template );
            $message = apply_filters( 'imovel_parceiro_partnership_email_message', $message, $action, $context, $recipient_email );

            $email_jobs[] = array(
                'to' => $recipient_email,
                'subject' => $subject,
                'body' => $message,
            );
        }

        // Emails go out after the HTTP response (SMTP is slow); in-app first.
        if ( class_exists( 'Imovel_Parceiro_Mailer' ) ) {
            Imovel_Parceiro_Mailer::defer( $email_jobs, array( 'Imovel_Parceiro_Mailer', 'send_via_houzez' ) );
        } else {
            foreach ( $email_jobs as $job ) {
                $this->send_email_using_houzez_config( $job['to'], $job['subject'], $job['body'] );
            }
        }

        $this->send_in_app_notifications( $action, $context );
    }

    private function get_partnership_email_spec( $action, $role ) {
        $role = in_array( $role, array( 'owner', 'requester' ), true ) ? $role : 'admin';

        $spec = array(
            'request' => array(
                'owner' => array(
                    'subject' => __( 'Você recebeu uma nova solicitação de parceria', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver solicitação de parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'O corretor %1$s enviou uma solicitação de parceria para o seu imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'Você pode analisar a solicitação e decidir se deseja aceitar ou recusar a parceria.', 'imovel-parceiro-core' ) ),
                ),
                'requester' => array(
                    'subject' => __( 'Solicitação de parceria enviada', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parcerias', 'imovel-parceiro-core' ),
                    'intro' => __( 'Sua solicitação de parceria para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'A solicitação foi enviada e está aguardando a decisão do anunciante.', 'imovel-parceiro-core' ) ),
                ),
                'admin' => array(
                    'subject' => __( 'Nova solicitação de parceria', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver solicitação de parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'Uma solicitação de parceria foi enviada para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
            ),
            'accept' => array(
                'owner' => array(
                    'subject' => __( 'Parceria aceita: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'Você aceitou a parceria para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'Você pode continuar o processo a partir do painel.', 'imovel-parceiro-core' ) ),
                ),
                'requester' => array(
                    'subject' => __( 'Parceria aceita: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'Boas notícias! %1$s aceitou sua solicitação de parceria para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'Agora você pode acessar a parceria e continuar o processo.', 'imovel-parceiro-core' ) ),
                ),
                'admin' => array(
                    'subject' => __( 'Parceria aceita: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria para o imóvel foi aceita:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
            ),
            'reject' => array(
                'owner' => array(
                    'subject' => __( 'Parceria recusada: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parcerias', 'imovel-parceiro-core' ),
                    'intro' => __( 'Você recusou a parceria para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
                'requester' => array(
                    'subject' => __( 'Atualização sobre sua solicitação de parceria', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parcerias', 'imovel-parceiro-core' ),
                    'intro' => __( 'A solicitação de parceria referente ao imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'A solicitação não foi aceita neste momento. Agradecemos pelo interesse e esperamos novas oportunidades.', 'imovel-parceiro-core' ) ),
                ),
                'admin' => array(
                    'subject' => __( 'Parceria recusada: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parcerias', 'imovel-parceiro-core' ),
                    'intro' => __( 'A solicitação de parceria para o imóvel foi recusada:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
            ),
            'cancel' => array(
                'owner' => array(
                    'subject' => __( 'Parceria encerrada: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'Você encerrou a parceria para o imóvel:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'A parceria foi encerrada.', 'imovel-parceiro-core' ) ),
                ),
                'requester' => array(
                    'subject' => __( 'Parceria encerrada: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria para o imóvel foi encerrada:', 'imovel-parceiro-core' ),
                    'body' => array( __( 'A parceria foi encerrada.', 'imovel-parceiro-core' ) ),
                ),
                'admin' => array(
                    'subject' => __( 'Parceria encerrada: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Ver parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria para o imóvel foi encerrada:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
            ),
            'update' => array(
                'owner' => array(
                    'subject' => __( 'Atualização da parceria: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Acompanhar parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria referente ao imóvel:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
                'requester' => array(
                    'subject' => __( 'Atualização da parceria: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Acompanhar parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria referente ao imóvel:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
                'admin' => array(
                    'subject' => __( 'Atualização da parceria: %s', 'imovel-parceiro-core' ),
                    'cta' => __( 'Acompanhar parceria', 'imovel-parceiro-core' ),
                    'intro' => __( 'A parceria referente ao imóvel:', 'imovel-parceiro-core' ),
                    'body' => array(),
                ),
            ),
        );

        $action = isset( $spec[ $action ] ) ? $action : 'update';

        return $spec[ $action ][ $role ];
    }

    private function build_partnership_email_context( $partnership_id, $property_id, $requester_id, $owner_id, $status, $actor_user_id, $meta = array() ) {
        $actor = $this->get_partnership_email_recipient_data( $actor_user_id );
        $requester = $this->get_partnership_email_recipient_data( $requester_id );
        $owner = $this->get_partnership_email_recipient_data( $owner_id );

        return array_merge(
            array(
                'partnership_id' => absint( $partnership_id ),
                'property_id' => absint( $property_id ),
                'property_title' => get_the_title( $property_id ),
                'property_url' => get_permalink( $property_id ),
                'status' => sanitize_key( $status ),
                'status_label' => $this->get_status_label( $status ),
                'actor_user_id' => absint( $actor_user_id ),
                'actor_name' => ! empty( $actor['name'] ) ? $actor['name'] : '',
                'actor_email' => ! empty( $actor['email'] ) ? $actor['email'] : '',
                'actor_role' => $actor_user_id && $owner_id && (int) $actor_user_id === (int) $owner_id ? __( 'anunciante', 'imovel-parceiro-core' ) : __( 'solicitante', 'imovel-parceiro-core' ),
                'requester_id' => absint( $requester_id ),
                'requester_name' => ! empty( $requester['name'] ) ? $requester['name'] : '',
                'requester_email' => ! empty( $requester['email'] ) ? $requester['email'] : '',
                'owner_id' => absint( $owner_id ),
                'owner_name' => ! empty( $owner['name'] ) ? $owner['name'] : '',
                'owner_email' => ! empty( $owner['email'] ) ? $owner['email'] : '',
            ),
            is_array( $meta ) ? $meta : array()
        );
    }

    private function get_audit_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'imovel_parceiro_audit_logs';
    }

    private function maybe_create_audit_table() {
        global $wpdb;

        $table = $this->get_audit_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
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
            KEY partnership_id (partnership_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private function insert_audit_log( $event_type, $actor_user_id, $other_user_id, $property_id, $partnership_id, $meta = array() ) {
        global $wpdb;

        $this->maybe_create_audit_table();

        $wpdb->insert(
            $this->get_audit_table_name(),
            array(
                'event_type' => sanitize_key( $event_type ),
                'actor_user_id' => absint( $actor_user_id ),
                'other_user_id' => absint( $other_user_id ),
                'property_id' => absint( $property_id ),
                'partnership_id' => absint( $partnership_id ),
                'meta' => maybe_serialize( $meta ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    public function render_request_modal() {
        if ( ! is_singular( 'property' ) || ! is_user_logged_in() ) {
            return;
        }

        $property_id = (int) get_queried_object_id();
        if ( ! $property_id ) {
            $property_id = (int) get_the_ID();
        }

        if ( ! $property_id && isset( $GLOBALS['post']->ID ) ) {
            $property_id = (int) $GLOBALS['post']->ID;
        }

        if ( ! $property_id ) {
            return;
        }

        $user_id = get_current_user_id();
        // Cliente nunca vê modal de parceria
        if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $user_id ) ) {
            return;
        }
        $u = get_userdata( $user_id );
        if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'administrator' ), (array) $u->roles ) && ! current_user_can( 'imovel_parceiro_manage_commercial' ) ) {
            return;
        }
        $owner_id = $this->get_property_owner_user_id( $property_id );

        // The property owner (and the responsible broker) cannot request a
        // partnership for themselves, so do not render the modal for them.
        if ( $owner_id && (int) $owner_id === (int) $user_id ) {
            return;
        }
        if ( class_exists( 'Imovel_Parceiro_Contact_Widget' ) && (int) Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id ) === (int) $user_id ) {
            return;
        }

        $owner_contact = $this->get_user_contact_details( $owner_id );
        $owner_name = ! empty( $owner_contact['display_name'] ) ? $owner_contact['display_name'] : __( 'Proprietário do imóvel', 'imovel-parceiro-core' );
        $owner_email = ! empty( $owner_contact['email'] ) ? $owner_contact['email'] : '';
        $owner_phone = ! empty( $owner_contact['phone'] ) ? $owner_contact['phone'] : '';
        $owner_phone_call = ! empty( $owner_contact['phone_call'] ) ? $owner_contact['phone_call'] : '';
        $owner_mobile = ! empty( $owner_contact['mobile'] ) ? $owner_contact['mobile'] : '';
        $owner_mobile_call = ! empty( $owner_contact['mobile_call'] ) ? $owner_contact['mobile_call'] : '';
        $owner_whatsapp = ! empty( $owner_contact['whatsapp'] ) ? $owner_contact['whatsapp'] : '';
        $owner_whatsapp_call = ! empty( $owner_contact['whatsapp_call'] ) ? $owner_contact['whatsapp_call'] : '';
        ?>
        <script>
            window.imovelParceiroCoreOwnerContact = <?php echo wp_json_encode( array(
                'property_id' => $property_id,
                'owner_id' => $owner_id,
                'owner_name' => $owner_name,
                'owner_email' => $owner_email,
                'owner_phone' => $owner_phone,
                'owner_phone_call' => $owner_phone_call,
                'owner_mobile' => $owner_mobile,
                'owner_mobile_call' => $owner_mobile_call,
                'owner_whatsapp' => $owner_whatsapp,
                'owner_whatsapp_call' => $owner_whatsapp_call,
                'property_title' => get_the_title( $property_id ),
                'property_permalink' => get_permalink( $property_id ),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
        </script>
        <div class="modal fade" id="imovel-parceiro-partnership-modal" tabindex="-1" role="dialog" aria-labelledby="imovel-parceiro-partnership-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imovel-parceiro-partnership-modal-label"><?php esc_html_e( 'Solicitar parceria', 'imovel-parceiro-core' ); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
                    </div>
                    <div class="modal-body imovel-parceiro-modal-body">
                        <div class="mb-3">
                            <strong><?php esc_html_e( 'Imóvel:', 'imovel-parceiro-core' ); ?></strong>
                            <div><?php echo esc_html( get_the_title( $property_id ) ); ?></div>
                        </div>
                        <div class="mb-3">
                            <strong><?php esc_html_e( 'Anunciante:', 'imovel-parceiro-core' ); ?></strong>
                            <div><?php echo esc_html( $owner_name ); ?></div>
                        </div>
                        <div class="mb-3">
                            <strong><?php esc_html_e( 'Termos da parceria', 'imovel-parceiro-core' ); ?></strong>
                            <ul class="mb-0 ps-3">
                                <li><?php esc_html_e( 'O imóvel será compartilhado com o anunciante para a parceria comercial.', 'imovel-parceiro-core' ); ?></li>
                                <li><?php esc_html_e( 'A comissão segue a regra vigente do sistema e será aplicada conforme o combinado.', 'imovel-parceiro-core' ); ?></li>
                                <li><?php esc_html_e( 'A comunicação e o acompanhamento da parceria devem ser transparentes.', 'imovel-parceiro-core' ); ?></li>
                            </ul>
                        </div>
                        <div class="alert alert-light border mb-3" role="alert">
                            <strong><?php esc_html_e( 'Comissão:', 'imovel-parceiro-core' ); ?></strong>
                            <?php esc_html_e( 'Conforme regra atual do sistema.', 'imovel-parceiro-core' ); ?>
                        </div>
                        <form id="imovel-parceiro-partnership-form" class="imovel-parceiro-partnership-form">
                            <input type="hidden" name="property_id" value="<?php echo absint( $property_id ); ?>" />
                            <input type="hidden" name="partnership_terms" value="1" />
                            <div class="mb-3">
                                <label for="imovel-parceiro-partnership-message" class="form-label"><?php esc_html_e( 'Mensagem para o anunciante', 'imovel-parceiro-core' ); ?></label>
                                <textarea id="imovel-parceiro-partnership-message" name="message" rows="4" class="form-control" maxlength="500" placeholder="<?php esc_attr_e( 'Escreva uma mensagem para o anunciante, se desejar.', 'imovel-parceiro-core' ); ?>"></textarea>
                            </div>
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                                <button type="submit" class="btn btn-success imovel-parceiro-submit-request"><?php esc_html_e( 'Enviar solicitação', 'imovel-parceiro-core' ); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function request_partnership() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $terms_accepted = ! empty( $_POST['partnership_terms'] );
        $user_id = get_current_user_id();
        if ( ! $property_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        if ( ! $terms_accepted ) {
            wp_send_json_error( array( 'message' => __( 'Voce deve aceitar os termos da parceria para continuar.', 'imovel-parceiro-core' ) ) );
        }
        $owner_id = $this->get_property_owner_user_id( $property_id );

        // Allow the widget to target the responsible broker explicitly. The
        // recipient is only trusted when it matches the property's broker and is
        // never the current user.
        $partner_user_id = isset( $_POST['partner_user_id'] ) ? absint( $_POST['partner_user_id'] ) : 0;
        if ( $partner_user_id && class_exists( 'Imovel_Parceiro_Contact_Widget' ) ) {
            $broker_id = Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id );
            if ( $partner_user_id === $broker_id ) {
                $owner_id = $partner_user_id;
            }
        }

        if ( ! $owner_id || $owner_id === $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Não é possível solicitar parceria para este imóvel.', 'imovel-parceiro-core' ) ) );
        }

        // The property owner cannot request a partnership for their own listing.
        $property_owner_id = self::property_owner_id( $property_id );
        if ( $property_owner_id && $property_owner_id === $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Você é o proprietário deste imóvel e não pode solicitar parceria para ele mesmo.', 'imovel-parceiro-core' ) ) );
        }

        if ( ! $this->can_request_partnership( $property_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Voce nao possui permissao para solicitar parceria neste imovel.', 'imovel-parceiro-core' ) ) );
        }

        // Subscription rule: a broker must have an active plan to request a partnership.
        if ( class_exists( 'Imovel_Parceiro_Subscriptions' ) && ! Imovel_Parceiro_Subscriptions::has_active_subscription( $user_id ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Para solicitar uma parceria, você precisa ter um plano ativo.', 'imovel-parceiro-core' ),
                    'code' => 'subscription_required',
                    'plans_url' => Imovel_Parceiro_Subscriptions::plans_url(),
                )
            );
        }

        if ( $this->has_existing_partnership_request( $property_id, $user_id ) ) {
            $existing_status = self::normalize_status_key( $this->get_existing_partnership_request_status( $property_id, $user_id ) );
            $message = __( 'Você já enviou uma solicitação para este imóvel e não pode pedir novamente.', 'imovel-parceiro-core' );

            if ( 'solicitada' === $existing_status ) {
                $message = __( 'Você já possui uma solicitação pendente para este imóvel.', 'imovel-parceiro-core' );
            } elseif ( in_array( $existing_status, array( 'aceita', 'ativa', 'emnegociacao' ), true ) ) {
                $message = __( 'Você já possui uma parceria em andamento para este imóvel.', 'imovel-parceiro-core' );
            }

            wp_send_json_error( array( 'message' => $message ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();

        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];

        $insert_data = array(
            'property_id' => $property_id,
            $requester_col => $user_id,
            $owner_col => $owner_id,
            'status' => 'solicitada',
            'terms_version' => '1.0',
            $schema['created_col'] => current_time( 'mysql' ),
        );

        $insert_format = array( '%d', '%d', '%d', '%s', '%s', '%s' );

        if ( $schema['has_commission_split'] ) {
            $commission_split = isset( $_POST['commission_split'] ) ? sanitize_text_field( wp_unslash( $_POST['commission_split'] ) ) : '50/50';
            if ( ! preg_match( '/^\d{1,3}\/\d{1,3}$/', $commission_split ) ) {
                $commission_split = '50/50';
            }
            $insert_data['commission_split'] = $commission_split;
            $insert_format[] = '%s';
        }

        if ( $schema['has_terms_accepted'] ) {
            $insert_data['terms_accepted'] = wp_json_encode(
                array(
                    'accepted' => true,
                    'accepted_at' => current_time( 'mysql' ),
                    'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                    'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                )
            );
            $insert_format[] = '%s';
        }

        if ( ! empty( $schema['notes_col'] ) ) {
            $insert_data[ $schema['notes_col'] ] = $message;
            $insert_format[] = '%s';
        }

        $inserted = $wpdb->insert( $table, $insert_data, $insert_format );
        if ( false === $inserted ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível salvar a solicitação de parceria.', 'imovel-parceiro-core' ) ) );
        }

        // SLA: o anunciante tem um prazo para responder a solicitação.
        if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
            Imovel_Parceiro_Partnership_Integrity::set_owner_response_sla( (int) $wpdb->insert_id );
        }

        $this->insert_audit_log(
            'partnership_requested',
            $user_id,
            $owner_id,
            $property_id,
            (int) $wpdb->insert_id,
            array(
                'status' => 'solicitada',
                'message' => $message,
                'terms_accepted' => $terms_accepted,
            )
        );

        if ( class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ) {
            Imovel_Parceiro_Partnership_Workflow::record_event(
                (int) $wpdb->insert_id,
                $user_id,
                'status',
                'request',
                __( 'Solicitação enviada', 'imovel-parceiro-core' ),
                $message,
                array( 'status' => 'solicitada' )
            );
        }

        $this->send_partnership_notifications(
            'request',
            $this->build_partnership_email_context(
                (int) $wpdb->insert_id,
                $property_id,
                $user_id,
                $owner_id,
                'solicitada',
                $user_id,
                array(
                    'message' => $message,
                )
            )
        );

        wp_send_json_success( array( 'message' => __( 'Solicitação enviada.', 'imovel-parceiro-core' ) ) );
    }

    public function handle_partnership() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $user_id = get_current_user_id();
        if ( ! $partnership_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }

        $status = self::normalize_status_key( $status );
        if ( ! in_array( $status, array( 'aceita', 'recusada' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Status inválido para atualização da parceria.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();
        $owner_col = $schema['owner_col'];
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $partnership_id ) );
        if ( ! $row || (int) $row->{$owner_col} !== $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão para alterar esta parceria.', 'imovel-parceiro-core' ) ) );
        }

        $current_status = self::normalize_status_key( isset( $row->status ) ? $row->status : '' );
        if ( 'solicitada' !== $current_status ) {
            wp_send_json_error( array( 'message' => __( 'Apenas solicitações pendentes podem ser aprovadas ou rejeitadas.', 'imovel-parceiro-core' ) ) );
        }

        $update_data = array( 'status' => $status );
        $update_format = array( '%s' );

        if ( $schema['has_responded_at'] ) {
            $update_data['responded_at'] = current_time( 'mysql' );
            $update_format[] = '%s';
        }

        if ( $schema['has_updated_at'] ) {
            $update_data['updated_at'] = current_time( 'mysql' );
            $update_format[] = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $partnership_id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível salvar a decisão da parceria.', 'imovel-parceiro-core' ) ) );
        }

        $this->insert_audit_log(
            'partnership_status_updated',
            $user_id,
            isset( $row->{$schema['requester_col']} ) ? (int) $row->{$schema['requester_col']} : 0,
            isset( $row->property_id ) ? absint( $row->property_id ) : 0,
            $partnership_id,
            array(
                'previous_status' => isset( $row->status ) ? sanitize_key( $row->status ) : '',
                'new_status' => $status,
            )
        );

        $this->send_partnership_notifications(
            'aceita' === $status ? 'accept' : 'reject',
            $this->build_partnership_email_context(
                $partnership_id,
                isset( $row->property_id ) ? absint( $row->property_id ) : 0,
                isset( $row->{$schema['requester_col']} ) ? (int) $row->{$schema['requester_col']} : 0,
                (int) $row->{$owner_col},
                $status,
                $user_id
            )
        );

        // When accepted, release negotiation + contact automatically.
        if ( 'aceita' === $status && class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ) {
            Imovel_Parceiro_Partnership_Workflow::instance()->auto_release_after_accept( $partnership_id, $user_id );
        }

        wp_send_json_success( array( 'message' => __( 'Parceria atualizada.', 'imovel-parceiro-core' ) ) );
    }

    public function cancel_partnership() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );
        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $user_id = get_current_user_id();
        if ( ! $partnership_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'imovel-parceiro-core' ) ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();
        $requester_col = $schema['requester_col'];
        $owner_col = $schema['owner_col'];
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $partnership_id ) );
        if ( ! $row || ( (int) $row->{$requester_col} !== $user_id && (int) $row->{$owner_col} !== $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissão para cancelar esta parceria.', 'imovel-parceiro-core' ) ) );
        }

        $current_status = self::normalize_status_key( isset( $row->status ) ? $row->status : '' );
        if ( in_array( $current_status, $this->terminal_statuses, true ) || in_array( $row->status, $this->terminal_statuses, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Esta parceria já foi finalizada e não pode ser cancelada novamente.', 'imovel-parceiro-core' ) ) );
        }

        if ( 'emnegociacao' !== $current_status && '' === trim( $reason ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe um motivo para encerrar a parceria antes da negociação.', 'imovel-parceiro-core' ) ) );
        }

        $cancel_data = array( 'status' => 'encerrada' );
        $cancel_format = array( '%s' );

        if ( $schema['has_updated_at'] ) {
            $cancel_data['updated_at'] = current_time( 'mysql' );
            $cancel_format[] = '%s';
        }

        if ( ! empty( $schema['notes_col'] ) ) {
            $existing_notes = isset( $row->{$schema['notes_col']} ) ? trim( (string) $row->{$schema['notes_col']} ) : '';
            $reason_note = 'Motivo do encerramento: ' . $reason;
            $cancel_data[ $schema['notes_col'] ] = ! empty( $existing_notes ) ? $existing_notes . "\n\n" . $reason_note : $reason_note;
            $cancel_format[] = '%s';
        }

        $updated = $wpdb->update( $table, $cancel_data, array( 'id' => $partnership_id ), $cancel_format, array( '%d' ) );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível encerrar a parceria.', 'imovel-parceiro-core' ) ) );
        }

        $other_user_id = (int) $row->{$requester_col};
        if ( $other_user_id === $user_id ) {
            $other_user_id = isset( $row->{$owner_col} ) ? (int) $row->{$owner_col} : 0;
        }

        $this->insert_audit_log(
            'partnership_cancelled',
            $user_id,
            $other_user_id,
            isset( $row->property_id ) ? absint( $row->property_id ) : 0,
            $partnership_id,
            array(
                'previous_status' => isset( $row->status ) ? sanitize_key( $row->status ) : '',
                'new_status' => 'encerrada',
                'reason' => $reason,
            )
        );

        $this->send_partnership_notifications(
            'cancel',
            $this->build_partnership_email_context(
                $partnership_id,
                isset( $row->property_id ) ? absint( $row->property_id ) : 0,
                isset( $row->{$requester_col} ) ? (int) $row->{$requester_col} : 0,
                isset( $row->{$owner_col} ) ? (int) $row->{$owner_col} : 0,
                'encerrada',
                $user_id,
                array(
                    'reason' => $reason,
                )
            )
        );

        wp_send_json_success( array( 'message' => __( 'Parceria cancelada.', 'imovel-parceiro-core' ) ) );
    }

    public function transition_partnership() {
        check_ajax_referer( 'imovel_parceiro_core_nonce', 'nonce' );

        $partnership_id = isset( $_POST['partnership_id'] ) ? absint( $_POST['partnership_id'] ) : 0;
        $next_status = isset( $_POST['next_status'] ) ? sanitize_key( wp_unslash( $_POST['next_status'] ) ) : '';
        $user_id = get_current_user_id();

        if ( ! $partnership_id || ! $user_id || empty( $next_status ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados invalidos para transicao.', 'imovel-parceiro-core' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        $schema = $this->get_partnership_table_schema();

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $partnership_id ) );
        if ( ! $row ) {
            wp_send_json_error( array( 'message' => __( 'Parceria nao encontrada.', 'imovel-parceiro-core' ) ) );
        }

        $current_status = isset( $row->status ) ? sanitize_key( $row->status ) : '';
        if ( in_array( $current_status, $this->terminal_statuses, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Esta parceria ja esta finalizada.', 'imovel-parceiro-core' ) ) );
        }

        $next_status = self::normalize_status_key( $next_status );
        $current_status = self::normalize_status_key( $current_status );

        if ( ! $this->can_transition_status( $current_status, $next_status ) ) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: 1: current status label, 2: next status label */
                        __( 'Transicao nao permitida: %1$s -> %2$s.', 'imovel-parceiro-core' ),
                        self::get_status_label( $current_status ),
                        self::get_status_label( $next_status )
                    ),
                )
            );
        }

        if ( ! $this->user_can_apply_transition( $row, $schema, $user_id, $next_status ) ) {
            wp_send_json_error( array( 'message' => __( 'Sem permissao para esta transicao.', 'imovel-parceiro-core' ) ) );
        }

        $update_data = array( 'status' => $next_status );
        $update_format = array( '%s' );

        if ( $schema['has_updated_at'] ) {
            $update_data['updated_at'] = current_time( 'mysql' );
            $update_format[] = '%s';
        }

        if ( $schema['has_responded_at'] && in_array( $next_status, array( 'aceita', 'recusada', 'accepted', 'rejected' ), true ) ) {
            $update_data['responded_at'] = current_time( 'mysql' );
            $update_format[] = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $partnership_id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Falha ao atualizar o status da parceria.', 'imovel-parceiro-core' ) ) );
        }

        $row->status = $next_status;
        $this->maybe_sync_partnership_deal_to_crm( $row, $schema, $next_status );

        $this->insert_audit_log(
            'partnership_status_changed',
            $user_id,
            isset( $row->{$schema['requester_col']} ) && (int) $row->{$schema['requester_col']} === $user_id
                ? (int) $row->{$schema['owner_col']}
                : (int) $row->{$schema['requester_col']},
            isset( $row->property_id ) ? absint( $row->property_id ) : 0,
            $partnership_id,
            array(
                'previous_status' => $current_status,
                'new_status' => $next_status,
            )
        );

        $notify_action = 'update';
        if ( in_array( $next_status, array( 'aceita', 'accepted' ), true ) ) {
            $notify_action = 'accept';
        } elseif ( in_array( $next_status, array( 'recusada', 'rejected' ), true ) ) {
            $notify_action = 'reject';
        }

        $this->send_partnership_notifications(
            $notify_action,
            $this->build_partnership_email_context(
                $partnership_id,
                isset( $row->property_id ) ? absint( $row->property_id ) : 0,
                isset( $row->{$schema['requester_col']} ) ? (int) $row->{$schema['requester_col']} : 0,
                isset( $row->{$schema['owner_col']} ) ? (int) $row->{$schema['owner_col']} : 0,
                $next_status,
                $user_id
            )
        );

        wp_send_json_success(
            array(
                'message' => __( 'Status da parceria atualizado.', 'imovel-parceiro-core' ),
                'status' => $next_status,
                'status_label' => $this->get_status_label( $next_status ),
            )
        );
    }
}

Imovel_Parceiro_Partnerships::instance();

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPC_Notifications {
    const PRIORITY_INFO = 'info';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_IMPORTANT = 'important';
    const PRIORITY_CRITICAL = 'critical';

    const CATEGORY_PROPRIEDADES = 'imoveis';
    const CATEGORY_DOCUMENTACAO = 'documentacao';
    const CATEGORY_PARCERIAS = 'parcerias';
    const CATEGORY_PROPOSTAS = 'propostas';
    const CATEGORY_VISITAS = 'visitas';
    const CATEGORY_NEGOCIACOES = 'negociacoes';
    const CATEGORY_SISTEMA = 'sistema';

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_ensure_tables' ) );
        add_action( 'wp_ajax_imovel_parceiro_notifications_fetch', array( $this, 'ajax_fetch_notifications' ) );
        add_action( 'wp_ajax_imovel_parceiro_notifications_mark_read', array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_imovel_parceiro_notifications_mark_all_read', array( $this, 'ajax_mark_all_read' ) );
        add_action( 'wp_ajax_imovel_parceiro_notifications_save_subscription', array( $this, 'ajax_save_subscription' ) );

        // Native Houzez events feed the in-app notifications.
        add_action( 'houzez_after_schedule_tour_form_submission', array( $this, 'notify_schedule_tour' ), 20, 1 );
        add_action( 'houzez_send_notification', array( $this, 'notify_houzez_message' ), 20, 1 );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_notifications';
    }

    public static function subscriptions_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'imovel_parceiro_notification_subscriptions';
    }

    public function maybe_ensure_tables() {
        global $wpdb;

        if ( get_option( 'imovel_parceiro_notifications_db_version' ) === '1.0.0' ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE " . self::table_name() . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                property_id bigint(20) unsigned NOT NULL DEFAULT 0,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                type varchar(80) NOT NULL,
                category varchar(30) NOT NULL DEFAULT 'sistema',
                title varchar(255) NOT NULL,
                message text DEFAULT '',
                url text DEFAULT '',
                priority varchar(20) NOT NULL DEFAULT 'normal',
                is_read tinyint(1) NOT NULL DEFAULT 0,
                dashboard_sent tinyint(1) NOT NULL DEFAULT 1,
                browser_sent tinyint(1) NOT NULL DEFAULT 0,
                email_sent tinyint(1) NOT NULL DEFAULT 0,
                meta longtext DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                read_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY is_read (is_read),
                KEY created_at (created_at),
                KEY user_id_is_read (user_id, is_read),
                KEY property_id (property_id),
                KEY partnership_id (partnership_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE " . self::subscriptions_table_name() . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                endpoint_hash varchar(64) NOT NULL,
                endpoint longtext NOT NULL,
                p256dh text DEFAULT '',
                auth_secret text DEFAULT '',
                user_agent text DEFAULT '',
                device_label varchar(120) DEFAULT '',
                last_seen_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                UNIQUE KEY endpoint_hash (endpoint_hash),
                KEY user_id (user_id)
            ) {$charset_collate};"
        );

        update_option( 'imovel_parceiro_notifications_db_version', '1.0.0' );
    }

    public static function get_dashboard_url( $args = array() ) {
        $url = houzez_get_template_link_2( 'template/user_dashboard.php' );
        $args = is_array( $args ) ? $args : array();
        $args = array_merge( array( 'imovel_dashboard_area' => 'notificacoes' ), $args );

        return add_query_arg( $args, $url );
    }

    public static function send( $args ) {
        $instance = self::instance();
        return $instance->create_notification( $args );
    }

    public function create_notification( $args ) {
        global $wpdb;

        $payload = wp_parse_args(
            is_array( $args ) ? $args : array(),
            array(
                'user_id' => 0,
                'property_id' => 0,
                'partnership_id' => 0,
                'type' => 'SYSTEM',
                'category' => self::CATEGORY_SISTEMA,
                'title' => '',
                'message' => '',
                'url' => '',
                'priority' => self::PRIORITY_NORMAL,
                'meta' => array(),
                'dashboard_sent' => 1,
                'browser_sent' => 0,
                'email_sent' => 0,
            )
        );

        $user_id = absint( $payload['user_id'] );
        if ( ! $user_id || ! get_userdata( $user_id ) ) {
            return 0;
        }

        $property_id = absint( $payload['property_id'] );
        $partnership_id = absint( $payload['partnership_id'] );
        $type = strtoupper( sanitize_key( $payload['type'] ) );
        $category = sanitize_key( $payload['category'] );
        $title = sanitize_text_field( $payload['title'] );
        $message = sanitize_textarea_field( $payload['message'] );
        $url = esc_url_raw( $payload['url'] );
        $priority = in_array( $payload['priority'], array( self::PRIORITY_INFO, self::PRIORITY_NORMAL, self::PRIORITY_IMPORTANT, self::PRIORITY_CRITICAL ), true ) ? $payload['priority'] : self::PRIORITY_NORMAL;
        $meta = is_array( $payload['meta'] ) ? wp_json_encode( $payload['meta'] ) : wp_json_encode( array() );

        if ( empty( $title ) ) {
            $title = $type;
        }

        if ( empty( $url ) ) {
            $url = self::get_dashboard_url();
        }

        $inserted = $wpdb->insert(
            self::table_name(),
            array(
                'user_id' => $user_id,
                'property_id' => $property_id,
                'partnership_id' => $partnership_id,
                'type' => $type,
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'priority' => $priority,
                'is_read' => 0,
                'dashboard_sent' => (int) ! empty( $payload['dashboard_sent'] ),
                'browser_sent' => (int) ! empty( $payload['browser_sent'] ),
                'email_sent' => (int) ! empty( $payload['email_sent'] ),
                'meta' => $meta,
                'created_at' => current_time( 'mysql' ),
                'read_at' => null,
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function unread_count( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return 0;
        }

        $table = self::table_name();

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0", $user_id ) );
    }

    public function recent_notifications( $user_id, $limit = 5 ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $limit = max( 1, min( 20, absint( $limit ) ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::table_name() . " WHERE user_id = %d ORDER BY is_read ASC, created_at DESC, id DESC LIMIT %d",
                $user_id,
                $limit
            )
        );
    }

    public function list_notifications( $user_id, $args = array() ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $args = wp_parse_args(
            $args,
            array(
                'status' => 'all',
                'category' => '',
                'paged' => 1,
                'per_page' => 20,
            )
        );

        $paged = max( 1, absint( $args['paged'] ) );
        $per_page = max( 5, min( 50, absint( $args['per_page'] ) ) );
        $offset = ( $paged - 1 ) * $per_page;

        $where = array( 'user_id = %d' );
        $params = array( $user_id );

        if ( 'unread' === $args['status'] ) {
            $where[] = 'is_read = 0';
        }

        if ( ! empty( $args['category'] ) ) {
            $where[] = 'category = %s';
            $params[] = sanitize_key( $args['category'] );
        }

        $sql = 'SELECT * FROM ' . self::table_name() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY is_read ASC, priority DESC, created_at DESC, id DESC LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public function count_notifications( $user_id, $status = 'all', $category = '' ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $where = array( 'user_id = %d' );
        $params = array( $user_id );

        if ( 'unread' === $status ) {
            $where[] = 'is_read = 0';
        }

        if ( ! empty( $category ) ) {
            $where[] = 'category = %s';
            $params[] = sanitize_key( $category );
        }

        $sql = 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE ' . implode( ' AND ', $where );

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    }

    public function mark_read( $user_id, $notification_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $notification_id = absint( $notification_id );
        if ( ! $user_id || ! $notification_id ) {
            return false;
        }

        return (bool) $wpdb->update(
            self::table_name(),
            array(
                'is_read' => 1,
                'read_at' => current_time( 'mysql' ),
            ),
            array(
                'id' => $notification_id,
                'user_id' => $user_id,
            ),
            array( '%d', '%s' ),
            array( '%d', '%d' )
        );
    }

    public function mark_all_read( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        return (bool) $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table_name() . ' SET is_read = 1, read_at = CASE WHEN read_at IS NULL THEN %s ELSE read_at END WHERE user_id = %d AND is_read = 0',
                current_time( 'mysql' ),
                $user_id
            )
        );
    }

    public function save_subscription( $user_id, $subscription, $device_label = '' ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $subscription = is_array( $subscription ) ? $subscription : array();
        $endpoint = isset( $subscription['endpoint'] ) ? esc_url_raw( $subscription['endpoint'] ) : '';
        if ( ! $user_id || empty( $endpoint ) ) {
            return 0;
        }

        $keys = isset( $subscription['keys'] ) && is_array( $subscription['keys'] ) ? $subscription['keys'] : array();
        $endpoint_hash = hash( 'sha256', $endpoint );

        $wpdb->replace(
            self::subscriptions_table_name(),
            array(
                'user_id' => $user_id,
                'endpoint_hash' => $endpoint_hash,
                'endpoint' => wp_json_encode( $subscription ),
                'p256dh' => isset( $keys['p256dh'] ) ? sanitize_text_field( $keys['p256dh'] ) : '',
                'auth_secret' => isset( $keys['auth'] ) ? sanitize_text_field( $keys['auth'] ) : '',
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'device_label' => sanitize_text_field( $device_label ),
                'last_seen_at' => current_time( 'mysql' ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function ajax_fetch_notifications() {
        check_ajax_referer( 'imovel_parceiro_notifications_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $user_id = get_current_user_id();
        $limit = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 5;
        $items = array();

        foreach ( $this->recent_notifications( $user_id, $limit ) as $item ) {
            $items[] = $this->format_notification( $item );
        }

        wp_send_json_success(
            array(
                'unread_count' => $this->unread_count( $user_id ),
                'notifications' => $items,
            )
        );
    }

    public function ajax_mark_read() {
        check_ajax_referer( 'imovel_parceiro_notifications_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $notification_id = isset( $_POST['notification_id'] ) ? absint( wp_unslash( $_POST['notification_id'] ) ) : 0;
        if ( ! $notification_id || ! $this->mark_read( get_current_user_id(), $notification_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Nao foi possivel atualizar a notificacao.', 'imovel-parceiro-core' ) ) );
        }

        wp_send_json_success( array( 'unread_count' => $this->unread_count( get_current_user_id() ) ) );
    }

    public function ajax_mark_all_read() {
        check_ajax_referer( 'imovel_parceiro_notifications_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $this->mark_all_read( get_current_user_id() );
        wp_send_json_success( array( 'unread_count' => 0 ) );
    }

    public function ajax_save_subscription() {
        check_ajax_referer( 'imovel_parceiro_notifications_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
        }

        $subscription_json = isset( $_POST['subscription'] ) ? wp_unslash( $_POST['subscription'] ) : '';
        $subscription = json_decode( $subscription_json, true );
        if ( ! is_array( $subscription ) ) {
            wp_send_json_error( array( 'message' => __( 'Assinatura invalida.', 'imovel-parceiro-core' ) ) );
        }

        $device_label = isset( $_POST['device_label'] ) ? sanitize_text_field( wp_unslash( $_POST['device_label'] ) ) : '';
        $subscription_id = $this->save_subscription( get_current_user_id(), $subscription, $device_label );

        if ( ! $subscription_id ) {
            wp_send_json_error( array( 'message' => __( 'Nao foi possivel salvar a assinatura.', 'imovel-parceiro-core' ) ) );
        }

        wp_send_json_success( array( 'subscription_id' => $subscription_id ) );
    }

    /* ---------------------------------------------------------------------
     * Native Houzez events (schedule tour + messages)
     * ------------------------------------------------------------------ */

    /**
     * Resolve who should receive a property-related notification: the
     * responsible broker first, then the recipient email when available.
     */
    private function resolve_property_recipient( $property_id, $email = '' ) {
        $property_id = absint( $property_id );
        if ( $property_id && class_exists( 'Imovel_Parceiro_Contact_Widget' ) ) {
            $broker = Imovel_Parceiro_Contact_Widget::property_broker_user_id( $property_id );
            if ( $broker && get_userdata( $broker ) ) {
                return (int) $broker;
            }
        }

        if ( $email ) {
            $user = get_user_by( 'email', sanitize_email( $email ) );
            if ( $user ) {
                return (int) $user->ID;
            }
        }

        return 0;
    }

    private function request_property_id( $data ) {
        $data = is_array( $data ) ? $data : array();
        foreach ( array( 'property_id', 'lead_page_id' ) as $key ) {
            if ( ! empty( $data[ $key ] ) ) {
                return absint( $data[ $key ] );
            }
        }
        foreach ( array( 'property_id', 'lead_page_id' ) as $key ) {
            if ( ! empty( $_POST[ $key ] ) ) {
                return absint( $_POST[ $key ] );
            }
        }

        return 0;
    }

    /**
     * Native Houzez "Schedule a tour" form -> visit notification.
     */
    public function notify_schedule_tour( $data ) {
        $data = is_array( $data ) ? $data : $_POST;
        $property_id = $this->request_property_id( $data );
        $email = isset( $data['target_email'] ) ? sanitize_email( wp_unslash( $data['target_email'] ) ) : '';
        $recipient = $this->resolve_property_recipient( $property_id, $email );
        if ( ! $recipient ) {
            return;
        }

        $sender = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
        $date = isset( $data['schedule_date'] ) ? sanitize_text_field( wp_unslash( $data['schedule_date'] ) ) : '';
        $time = isset( $data['schedule_time'] ) ? sanitize_text_field( wp_unslash( $data['schedule_time'] ) ) : '';
        $property_title = $property_id ? get_the_title( $property_id ) : ( isset( $data['property_title'] ) ? sanitize_text_field( wp_unslash( $data['property_title'] ) ) : '' );
        if ( '' === $property_title ) {
            $property_title = __( 'o imóvel', 'imovel-parceiro-core' );
        }

        $message = sprintf(
            __( 'Nova visita agendada para "%1$s" em %2$s às %3$s.', 'imovel-parceiro-core' ),
            $property_title,
            '' !== $date ? $date : '-',
            '' !== $time ? $time : '-'
        );
        if ( '' !== $sender ) {
            $message .= ' ' . sprintf( __( 'Solicitante: %s.', 'imovel-parceiro-core' ), $sender );
        }

        self::send(
            array(
                'user_id' => $recipient,
                'property_id' => $property_id,
                'type' => 'PROPERTY_SCHEDULE_TOUR',
                'category' => self::CATEGORY_VISITAS,
                'title' => __( 'Nova visita agendada', 'imovel-parceiro-core' ),
                'message' => $message,
                'url' => $property_id ? get_permalink( $property_id ) : self::get_dashboard_url(),
                'priority' => self::PRIORITY_IMPORTANT,
                'meta' => array( 'source' => 'houzez_schedule_tour_form' ),
            )
        );
    }

    /**
     * Native Houzez notifications (inquiry/contact forms, reviews, etc.).
     */
    public function notify_houzez_message( $args ) {
        if ( ! is_array( $args ) || empty( $args['to'] ) ) {
            return;
        }

        $user = get_user_by( 'email', sanitize_email( $args['to'] ) );
        if ( ! $user ) {
            return;
        }

        $type = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : 'message';
        $property_id = $this->request_property_id( $_POST );
        $title = ! empty( $args['title'] ) ? wp_strip_all_tags( $args['title'] ) : __( 'Nova mensagem recebida', 'imovel-parceiro-core' );
        $message = ! empty( $args['message'] ) ? wp_strip_all_tags( $args['message'] ) : '';
        if ( '' === $message ) {
            $message = __( 'Você recebeu uma nova mensagem pelo site.', 'imovel-parceiro-core' );
        }

        self::send(
            array(
                'user_id' => (int) $user->ID,
                'property_id' => $property_id,
                'type' => 'HOUZEZ_' . strtoupper( $type ),
                'category' => self::CATEGORY_PROPRIEDADES,
                'title' => $title,
                'message' => $message,
                'url' => $property_id ? get_permalink( $property_id ) : self::get_dashboard_url(),
                'priority' => self::PRIORITY_NORMAL,
                'meta' => array( 'source' => 'houzez_send_notification', 'houzez_type' => $type ),
            )
        );
    }

    public function format_notification( $item ) {
        $created_at = ! empty( $item->created_at ) ? strtotime( $item->created_at ) : time();
        $time_label = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at );

        return array(
            'id' => (int) $item->id,
            'type' => sanitize_key( $item->type ),
            'category' => sanitize_key( $item->category ),
            'title' => wp_strip_all_tags( $item->title ),
            'message' => wp_strip_all_tags( $item->message ),
            'url' => esc_url_raw( $item->url ),
            'priority' => sanitize_key( $item->priority ),
            'is_read' => (bool) $item->is_read,
            'created_at' => $time_label,
            'created_at_raw' => $item->created_at,
            'property_id' => (int) $item->property_id,
            'partnership_id' => (int) $item->partnership_id,
        );
    }
}

IPC_Notifications::instance();
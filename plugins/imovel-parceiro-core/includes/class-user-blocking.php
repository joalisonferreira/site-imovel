<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Blocks/unblocks platform users (brokers) from the admin dashboard.
 *
 * When a user is blocked:
 * - login is denied with a clear message;
 * - the user is logged out if already authenticated;
 * - their properties are hidden from the front-end listings;
 * - active partnerships are cancelled;
 * - an e-mail + in-app notification with the reason is sent.
 */
class Imovel_Parceiro_User_Blocking {
    const BLOCKED_META = 'imovel_parceiro_blocked';
    const REASON_META  = 'imovel_parceiro_blocked_reason';
    const AT_META      = 'imovel_parceiro_blocked_at';
    const BY_META      = 'imovel_parceiro_blocked_by';

    private static $blocked_ids_cache = null;

    public function __construct() {
        add_filter( 'authenticate', array( $this, 'block_login' ), 40, 3 );
        add_action( 'init', array( $this, 'enforce_logged_in_block' ), 5 );
        add_action( 'init', array( $this, 'handle_admin_action' ), 20 );
        add_action( 'pre_get_posts', array( $this, 'hide_blocked_properties' ) );
        add_action( 'template_redirect', array( $this, 'block_single_property' ) );
    }

    public static function is_blocked( $user_id ) {
        return 'yes' === get_user_meta( absint( $user_id ), self::BLOCKED_META, true );
    }

    public static function blocked_user_ids() {
        if ( null !== self::$blocked_ids_cache ) {
            return self::$blocked_ids_cache;
        }

        $ids = get_users(
            array(
                'meta_key'   => self::BLOCKED_META,
                'meta_value' => 'yes',
                'fields'     => 'ID',
            )
        );

        self::$blocked_ids_cache = array_map( 'absint', (array) $ids );

        return self::$blocked_ids_cache;
    }

    public static function block( $user_id, $reason, $by = 0 ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        $reason = sanitize_text_field( (string) $reason );

        update_user_meta( $user_id, self::BLOCKED_META, 'yes' );
        update_user_meta( $user_id, self::REASON_META, $reason );
        update_user_meta( $user_id, self::AT_META, current_time( 'mysql' ) );
        update_user_meta( $user_id, self::BY_META, absint( $by ) );

        self::$blocked_ids_cache = null;

        self::cancel_user_partnerships( $user_id );
        self::notify_user( $user_id, $reason );

        return true;
    }

    public static function unblock( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        delete_user_meta( $user_id, self::BLOCKED_META );
        delete_user_meta( $user_id, self::REASON_META );
        delete_user_meta( $user_id, self::AT_META );
        delete_user_meta( $user_id, self::BY_META );

        self::$blocked_ids_cache = null;

        return true;
    }

    public function block_login( $user, $username, $password ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return $user;
        }

        if ( self::is_blocked( $user->ID ) && ! user_can( $user, 'manage_options' ) ) {
            return new WP_Error(
                'imovel_parceiro_blocked',
                __( 'Sua conta foi bloqueada. Se você acredita que isso é um erro, entre em contato com a plataforma.', 'imovel-parceiro-core' )
            );
        }

        return $user;
    }

    public function enforce_logged_in_block() {
        if ( is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( self::is_blocked( $user_id ) && ! current_user_can( 'manage_options' ) ) {
            wp_logout();
            wp_safe_redirect( add_query_arg( 'ipc_blocked', '1', wp_login_url() ) );
            exit;
        }
    }

    public function hide_blocked_properties( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( empty( $post_type ) ) {
            return;
        }

        if ( ! in_array( 'property', (array) $post_type, true ) ) {
            return;
        }

        $blocked = self::blocked_user_ids();
        if ( empty( $blocked ) ) {
            return;
        }

        $existing = (array) $query->get( 'author__not_in' );
        $query->set( 'author__not_in', array_values( array_unique( array_merge( $existing, $blocked ) ) ) );
    }

    public function block_single_property() {
        if ( is_admin() || ! is_singular( 'property' ) ) {
            return;
        }

        $post = get_queried_object();
        if ( ! $post || ! isset( $post->post_author ) ) {
            return;
        }

        if ( self::is_blocked( $post->post_author ) && ! current_user_can( 'manage_options' ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            include get_query_template( '404' );
            exit;
        }
    }

    public function handle_admin_action() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( empty( $_POST['imovel_admin_block_action'] ) ) {
            return;
        }

        $section = isset( $_POST['imovel_admin_section'] ) ? sanitize_key( wp_unslash( $_POST['imovel_admin_section'] ) ) : '';
        if ( 'agents' !== $section ) {
            return;
        }

        $nonce = isset( $_POST['_imovel_admin_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_imovel_admin_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'imovel_admin_update' ) ) {
            return;
        }

        $user_id = isset( $_POST['imovel_admin_id'] ) ? absint( wp_unslash( $_POST['imovel_admin_id'] ) ) : 0;
        if ( ! $user_id ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['imovel_admin_block_action'] ) );

        if ( 'block' === $action ) {
            $reason = isset( $_POST['imovel_admin_block_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['imovel_admin_block_reason'] ) ) : '';
            if ( '' === trim( $reason ) ) {
                wp_die( esc_html__( 'Informe o motivo do bloqueio.', 'imovel-parceiro-core' ) );
            }
            self::block( $user_id, $reason, get_current_user_id() );
        } elseif ( 'unblock' === $action ) {
            self::unblock( $user_id );
        }

        $redirect = wp_get_referer();
        if ( $redirect ) {
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    private static function cancel_user_partnerships( $user_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'closed' WHERE ( requester_id = %d OR owner_id = %d ) AND status NOT IN ( 'rejected', 'won', 'lost', 'closed', 'cancelled' )",
                $user_id,
                $user_id
            )
        );
    }

    private static function notify_user( $user_id, $reason ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        $subject = sprintf(
            /* translators: %s: site name */
            __( 'Sua conta foi bloqueada em %s', 'imovel-parceiro-core' ),
            $site_name
        );

        $message  = __( 'Sua conta foi bloqueada.', 'imovel-parceiro-core' ) . "\n\n";
        if ( '' !== $reason ) {
            $message .= __( 'Motivo:', 'imovel-parceiro-core' ) . ' ' . $reason . "\n\n";
        }
        $message .= __( 'Se você acredita que isso é um erro, entre em contato com a plataforma.', 'imovel-parceiro-core' ) . "\n";

        if ( class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
            Imovel_Parceiro_Email_Template::send(
                $user->user_email,
                $subject,
                Imovel_Parceiro_Email_Template::text_to_html( $message ),
                array( 'title' => __( 'Conta bloqueada', 'imovel-parceiro-core' ) )
            );
        } else {
            wp_mail( $user->user_email, $subject, $message );
        }

        if ( class_exists( 'IPC_Notifications' ) ) {
            IPC_Notifications::send(
                array(
                    'user_id'  => $user_id,
                    'type'     => 'USER_BLOCKED',
                    'category' => IPC_Notifications::CATEGORY_SISTEMA,
                    'title'    => __( 'Conta bloqueada', 'imovel-parceiro-core' ),
                    'message'  => '' !== $reason
                        ? sprintf( __( 'Sua conta foi bloqueada. Motivo: %s', 'imovel-parceiro-core' ), $reason )
                        : __( 'Sua conta foi bloqueada. Entre em contato com a plataforma.', 'imovel-parceiro-core' ),
                    'priority' => 'critical',
                )
            );
        }
    }
}

new Imovel_Parceiro_User_Blocking();

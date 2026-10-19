<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * First login redirect for brokers and agencies + cliente redirect para /buscar.
 *
 * - Corretor/imobiliária: primeira vez vai para verificação (se não verificado).
 * - Cliente (houzez_buyer): toda vez que logar vai para /buscar.
 */
class Imovel_Parceiro_First_Login_Redirect {

    const DONE_META = 'imovel_parceiro_first_login_redirect_done';
    const TARGET_ROLES = array( 'houzez_agent', 'houzez_agency' );
    const CLIENT_ROLES = array( 'houzez_buyer' );

    public function __construct() {
        add_filter( 'login_redirect', array( $this, 'login_redirect' ), 20, 3 );
        add_filter( 'woocommerce_login_redirect', array( $this, 'woocommerce_redirect' ), 20, 2 );
        add_action( 'wp_login', array( $this, 'on_buyer_login' ), 10, 2 );
        add_action( 'template_redirect', array( $this, 'safety_net_redirect' ), 1 );
        add_action( 'houzez_before_login', array( $this, 'override_houzez_modal_redirect' ), 1 );
    }

    /**
     * Login modal Houzez usa $_POST['redirect_to'] direto.
     * Força dashboard para corretor/imobiliária (ou verificação no 1º acesso).
     */
    public function override_houzez_modal_redirect() {
        if ( empty( $_POST['username'] ) ) {
            return;
        }
        $login = sanitize_text_field( wp_unslash( $_POST['username'] ) );
        $user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
        if ( ! $user ) {
            return;
        }
        if ( self::is_client( $user->ID ) ) {
            $_POST['redirect_to'] = self::buscar_url();
            return;
        }
        if ( ! self::is_broker( $user->ID ) ) {
            return;
        }
        if ( self::needs_redirect( $user->ID ) ) {
            $_POST['redirect_to'] = self::verification_url( $user->ID );
            update_user_meta( $user->ID, self::DONE_META, 1 );
            return;
        }
        $_POST['redirect_to'] = self::dashboard_url();
        delete_user_meta( $user->ID, '_imovel_parceiro_dashboard_redirect_pending' );
    }

    /**
     * Whether the user qualifies for the first-login verification redirect.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function needs_redirect( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );
        if ( ! $user || ! array_intersect( self::TARGET_ROLES, (array) $user->roles ) ) {
            return false;
        }

        if ( get_user_meta( $user_id, self::DONE_META, true ) ) {
            return false;
        }

        if ( 'approved' === get_user_meta( $user_id, 'houzez_verification_status', true ) ) {
            update_user_meta( $user_id, self::DONE_META, 1 );
            return false;
        }

        return true;
    }

    /**
     * Verification screen URL.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public static function verification_url( $user_id = 0 ) {
        if ( class_exists( 'Imovel_Parceiro_Verification_Notifications' ) ) {
            return Imovel_Parceiro_Verification_Notifications::user_verification_url( $user_id );
        }

        $url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard_profile.php' ) : '';
        if ( ! $url ) {
            $url = home_url( '/meu-perfil/' );
        }

        return add_query_arg( array( 'hpage' => 'verification' ), $url );
    }

    public static function is_client( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        return $user && array_intersect( self::CLIENT_ROLES, (array) $user->roles );
    }

    public static function buscar_url() {
        $page = get_page_by_path( 'buscar' );
        if ( $page ) {
            return get_permalink( $page->ID );
        }
        return home_url( '/buscar/' );
    }

    public static function dashboard_url() {
        $url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : '';
        if ( ! $url ) {
            $url = home_url( '/dashboard/' );
        }
        return $url;
    }

    public static function is_broker( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        return $user && array_intersect( self::TARGET_ROLES, (array) $user->roles );
    }

    public function on_buyer_login( $user_login, $user ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return;
        }
        if ( self::is_client( $user->ID ) ) {
            update_user_meta( $user->ID, '_imovel_parceiro_buyer_redirect_pending', 1 );
            return;
        }
        // Corretor/imobiliária: marca para ir ao dashboard (cobre login modal/AJAX).
        if ( self::is_broker( $user->ID ) && ! self::needs_redirect( $user->ID ) ) {
            update_user_meta( $user->ID, '_imovel_parceiro_dashboard_redirect_pending', 1 );
        }
    }

    /**
     * wp-login.php logins.
     */
    public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( $user instanceof WP_User && self::is_client( $user->ID ) ) {
            update_user_meta( $user->ID, '_imovel_parceiro_buyer_redirect_pending', 1 );
            return self::buscar_url();
        }
        if ( $user instanceof WP_User && self::needs_redirect( $user->ID ) ) {
            update_user_meta( $user->ID, self::DONE_META, 1 );
            return self::verification_url( $user->ID );
        }
        // Corretor/imobiliária sempre vai para o dashboard ao fazer login.
        if ( $user instanceof WP_User && self::is_broker( $user->ID ) ) {
            delete_user_meta( $user->ID, '_imovel_parceiro_dashboard_redirect_pending' );
            return self::dashboard_url();
        }

        return $redirect_to;
    }

    /**
     * WooCommerce my-account logins.
     */
    public function woocommerce_redirect( $redirect, $user ) {
        if ( $user instanceof WP_User && self::is_client( $user->ID ) ) {
            update_user_meta( $user->ID, '_imovel_parceiro_buyer_redirect_pending', 1 );
            return self::buscar_url();
        }
        if ( $user instanceof WP_User && self::needs_redirect( $user->ID ) ) {
            update_user_meta( $user->ID, self::DONE_META, 1 );
            return self::verification_url( $user->ID );
        }
        if ( $user instanceof WP_User && self::is_broker( $user->ID ) ) {
            delete_user_meta( $user->ID, '_imovel_parceiro_dashboard_redirect_pending' );
            return self::dashboard_url();
        }

        return $redirect;
    }

    /**
     * One-time safety net for login paths that redirect client-side
     * (modal/AJAX login, auto-login after registration).
     */
    public function safety_net_redirect() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        // Buyer: se acabou de logar, manda para /buscar (uma vez)
        $user_id = get_current_user_id();
        if ( self::is_client( $user_id ) && get_user_meta( $user_id, '_imovel_parceiro_buyer_redirect_pending', true ) ) {
            delete_user_meta( $user_id, '_imovel_parceiro_buyer_redirect_pending' );
            // Evita loop se já está em /buscar ou na verificação
            $is_buscar = ( false !== strpos( $_SERVER['REQUEST_URI'], '/buscar' ) );
            if ( ! $is_buscar && ! isset( $_GET['hpage'] ) ) {
                wp_safe_redirect( self::buscar_url() );
                exit;
            }
            return;
        }

        // Corretor/imobiliária via login modal/AJAX: leva ao dashboard uma vez.
        if ( self::is_broker( $user_id ) && get_user_meta( $user_id, '_imovel_parceiro_dashboard_redirect_pending', true ) ) {
            delete_user_meta( $user_id, '_imovel_parceiro_dashboard_redirect_pending' );
            $is_dash = ( false !== strpos( $_SERVER['REQUEST_URI'], '/dashboard' ) ) || isset( $_GET['imovel_dashboard_area'] ) || isset( $_GET['imovel-parceiro'] ) || isset( $_GET['hpage'] );
            if ( ! $is_dash ) {
                wp_safe_redirect( self::dashboard_url() );
                exit;
            }
            return;
        }

        // Already there: just mark as done, never loop.
        if ( isset( $_GET['hpage'] ) && 'verification' === sanitize_key( wp_unslash( $_GET['hpage'] ) ) ) {
            update_user_meta( get_current_user_id(), self::DONE_META, 1 );
            return;
        }

        if ( ! self::needs_redirect( $user_id ) ) {
            return;
        }

        update_user_meta( $user_id, self::DONE_META, 1 );
        wp_safe_redirect( self::verification_url( $user_id ) );
        exit;
    }
}

new Imovel_Parceiro_First_Login_Redirect();

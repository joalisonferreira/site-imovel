<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * One-click logout.
 *
 * Dashboard logout links redirect straight to the homepage, skipping the
 * wp-login.php "do you really want to log out?" confirmation screen (which
 * shows up whenever the link nonce is stale, e.g. cached pages).
 */
class Imovel_Parceiro_Instant_Logout {

    const QUERY_VAR = 'ipc_logout';

    public function __construct() {
        add_filter( 'logout_url', array( $this, 'logout_url' ), 20, 2 );
        add_action( 'init', array( $this, 'maybe_logout' ), 1 );
    }

    /**
     * Point every logout link to the one-click handler (homepage).
     *
     * @param string $logout_url Default logout URL (unused).
     * @param string $redirect   Requested redirect (unused, always home).
     * @return string
     */
    public function logout_url( $logout_url, $redirect ) {
        return add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
    }

    /**
     * Log out immediately and land on the homepage.
     */
    public function maybe_logout() {
        if ( empty( $_GET[ self::QUERY_VAR ] ) || ! is_user_logged_in() ) {
            return;
        }

        wp_logout();
        nocache_headers();
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
}

new Imovel_Parceiro_Instant_Logout();

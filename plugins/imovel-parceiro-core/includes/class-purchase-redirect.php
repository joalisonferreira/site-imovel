<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Item 8: after a successful subscription purchase, send the customer to the
 * Houzez dashboard instead of leaving them on the WooCommerce order page.
 */
class Imovel_Parceiro_Purchase_Redirect {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_redirect_after_purchase' ) );
    }

    public function maybe_redirect_after_purchase() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
            return;
        }

        $order_id = absint( get_query_var( 'order-received' ) );
        if ( ! $order_id ) {
            $order_id = isset( $_GET['order-received'] ) ? absint( wp_unslash( $_GET['order-received'] ) ) : 0;
        }
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( ! function_exists( 'wcs_order_contains_subscription' ) || ! wcs_order_contains_subscription( $order ) ) {
            return;
        }

        $target = self::dashboard_url();
        if ( ! $target ) {
            return;
        }

        wp_safe_redirect( $target );
        exit;
    }

    public static function dashboard_url() {
        if ( function_exists( 'houzez_get_template_link_2' ) ) {
            $url = houzez_get_template_link_2( 'template/user_dashboard.php' );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/dashboard/' );
    }
}

new Imovel_Parceiro_Purchase_Redirect();

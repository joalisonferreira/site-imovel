<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Item 9: route subscription / "my account" links to the Houzez dashboard.
 *
 * WooCommerce Subscriptions builds the "view subscription" URL from the
 * WooCommerce My Account page. Everywhere that URL is used (emails, related
 * subscriptions tables, etc.) we rewrite it to the Houzez dashboard
 * "Informacoes da assinatura" page so the customer stays inside the theme.
 */
class Imovel_Parceiro_Subscription_Links {

    public function __construct() {
        add_filter( 'wcs_get_view_subscription_url', array( $this, 'rewrite_view_subscription_url' ), 20, 2 );
    }

    /**
     * Houzez dashboard page that shows the subscription panel.
     */
    public static function dashboard_membership_url( $subscription_id = 0 ) {
        $url = function_exists( 'houzez_get_template_link_2' )
            ? houzez_get_template_link_2( 'template/user_dashboard_membership.php' )
            : '';

        if ( ! $url ) {
            return '';
        }

        $subscription_id = absint( $subscription_id );
        if ( $subscription_id ) {
            $url = add_query_arg( 'subscription_id', $subscription_id, $url );
        }

        return $url;
    }

    public function rewrite_view_subscription_url( $url, $subscription_id = 0 ) {
        $dashboard_url = self::dashboard_membership_url( $subscription_id );

        return $dashboard_url ? $dashboard_url : $url;
    }
}

new Imovel_Parceiro_Subscription_Links();

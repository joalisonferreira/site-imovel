<?php
/**
 * Related Subscriptions section beneath order details table.
 *
 * Override do template do WooCommerce Subscriptions: os links de "Ver" e do
 * ID da assinatura apontam para o dashboard do Houzez (informações da
 * assinatura) em vez da area "Minha Conta" do WooCommerce.
 *
 * @package Houzez Child
 * @version 8.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ipc_membership_url = function_exists( 'houzez_get_template_link_2' )
    ? houzez_get_template_link_2( 'template/user_dashboard_membership.php' )
    : home_url( '/' );

$ipc_subscription_url = static function ( $subscription_id ) use ( $ipc_membership_url ) {
    $subscription_id = absint( $subscription_id );
    return $subscription_id ? add_query_arg( 'subscription_id', $subscription_id, $ipc_membership_url ) : $ipc_membership_url;
};
?>
<header>
    <h2><?php esc_html_e( 'Related subscriptions', 'woocommerce-subscriptions' ); ?></h2>
</header>

<table class="shop_table shop_table_responsive my_account_orders woocommerce-orders-table woocommerce-MyAccount-subscriptions woocommerce-orders-table--subscriptions">
    <thead>
        <tr>
            <th class="subscription-id order-number woocommerce-orders-table__header woocommerce-orders-table__header-order-number woocommerce-orders-table__header-subscription-id"><span class="nobr"><?php esc_html_e( 'Subscription', 'woocommerce-subscriptions' ); ?></span></th>
            <th class="subscription-status order-status woocommerce-orders-table__header woocommerce-orders-table__header-order-status woocommerce-orders-table__header-subscription-status"><span class="nobr"><?php esc_html_e( 'Status', 'woocommerce-subscriptions' ); ?></span></th>
            <th class="subscription-next-payment order-date woocommerce-orders-table__header woocommerce-orders-table__header-order-date woocommerce-orders-table__header-subscription-next-payment"><span class="nobr"><?php echo esc_html_x( 'Next payment', 'table heading', 'woocommerce-subscriptions' ); ?></span></th>
            <th class="subscription-total order-total woocommerce-orders-table__header woocommerce-orders-table__header-order-total woocommerce-orders-table__header-subscription-total"><span class="nobr"><?php echo esc_html_x( 'Total', 'table heading', 'woocommerce-subscriptions' ); ?></span></th>
            <th class="subscription-actions order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions woocommerce-orders-table__header-subscription-actions">&nbsp;</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ( $subscriptions as $subscription_id => $subscription ) {
            $view_url = $ipc_subscription_url( $subscription_id );
            $view_order_label = sprintf(
                /* Translators: %1$d is the subscription number. */
                __( 'View subscription %1$d', 'woocommerce-subscriptions' ),
                $subscription_id
            );
            ?>
            <tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $subscription->get_status() ); ?>">
                <td class="subscription-id order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-id woocommerce-orders-table__cell-order-number" data-title="<?php esc_attr_e( 'ID', 'woocommerce-subscriptions' ); ?>">
                    <a href="<?php echo esc_url( $view_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View subscription number %s', 'woocommerce-subscriptions' ), $subscription->get_order_number() ) ); ?>">
                        <?php echo sprintf( esc_html_x( '#%s', 'hash before order number', 'woocommerce-subscriptions' ), esc_html( $subscription->get_order_number() ) ); ?>
                    </a>
                </td>
                <td class="subscription-status order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-status woocommerce-orders-table__cell-order-status" style="white-space:nowrap;" data-title="<?php esc_attr_e( 'Status', 'woocommerce-subscriptions' ); ?>">
                    <?php echo esc_html( wcs_get_subscription_status_name( $subscription->get_status() ) ); ?>
                </td>
                <td class="subscription-next-payment order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-next-payment woocommerce-orders-table__cell-order-date" data-title="<?php echo esc_attr_x( 'Next payment', 'table heading', 'woocommerce-subscriptions' ); ?>">
                    <?php echo esc_html( $subscription->get_date_to_display( 'next_payment' ) ); ?>
                </td>
                <td class="subscription-total order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-total woocommerce-orders-table__cell-order-total" data-title="<?php echo esc_attr_x( 'Total', 'Used in data attribute. Escaped', 'woocommerce-subscriptions' ); ?>">
                    <?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
                </td>
                <td class="subscription-actions order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-actions woocommerce-orders-table__cell-order-actions">
                    <a
                        href="<?php echo esc_url( $view_url ); ?>"
                        class="woocommerce-button button view<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"
                        aria-label="<?php echo esc_attr( $view_order_label ); ?>"
                    >
                        <?php echo esc_html_x( 'View', 'view a subscription', 'woocommerce-subscriptions' ); ?>
                    </a>
                </td>
            </tr>
        <?php } // endforeach ?>
    </tbody>
</table>

<?php do_action( 'woocommerce_subscription_after_related_subscriptions_table', $subscriptions, $order_id ); ?>

<?php
/**
 * Template Name: User Dashboard Invoices
 */
if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url() );
    exit;
}

global $houzez_local;
$user_id = get_current_user_id();
$invoice_query_args = array(
    'post_type' => 'houzez_invoice',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
);
if ( ! houzez_is_admin() && ! houzez_is_editor() ) {
    $invoice_query_args['meta_key'] = 'HOUZEZ_invoice_buyer';
    $invoice_query_args['meta_value'] = $user_id;
}
$invoices = get_posts( $invoice_query_args );
$subscription_invoice_packages = array();
foreach ( $invoices as $invoice ) {
    if ( get_post_meta( $invoice->ID, '_imovel_parceiro_subscription_id', true ) ) {
        $subscription_invoice_packages[] = absint( get_post_meta( $invoice->ID, 'HOUZEZ_invoice_item_id', true ) );
    }
}
$invoices = array_filter(
    $invoices,
    function( $invoice ) use ( $subscription_invoice_packages ) {
        if ( get_post_meta( $invoice->ID, '_imovel_parceiro_subscription_id', true ) ) {
            return true;
        }
        $package_id = absint( get_post_meta( $invoice->ID, 'HOUZEZ_invoice_item_id', true ) );
        return ! $package_id || ! in_array( $package_id, $subscription_invoice_packages, true );
    }
);
get_header( 'dashboard' );
get_template_part( 'template-parts/dashboard/sidebar' );
?>
<div class="dashboard-right">
    <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
    <div class="dashboard-content">
        <div class="heading d-flex align-items-center justify-content-between"><div class="heading-text"><h2><?php echo esc_html( houzez_option( 'dsh_invoices', 'Faturas' ) ); ?></h2></div></div>
        <div class="houzez-data-content"><div class="houzez-data-table"><div class="table-responsive"><table class="table table-hover align-middle m-0"><thead><tr><th><?php esc_html_e( 'Ordem', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Cobrança de', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Tipo de faturamento', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Cliente', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Método de pagamento', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Total', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th><th><?php esc_html_e( 'Visualizar', 'imovel-parceiro-core' ); ?></th></tr></thead><tbody>
        <?php foreach ( $invoices as $invoice ) : ?>
            <?php
            $meta = houzez_get_invoice_meta( $invoice->ID );
            $subscription_id = absint( get_post_meta( $invoice->ID, '_imovel_parceiro_subscription_id', true ) );
            $order_number = $subscription_id ? $subscription_id : $invoice->ID;
            ?>
            <tr><td><?php echo esc_html( $order_number ); ?></td><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $invoice->post_date ) ) ); ?></td><td><?php echo esc_html( $meta['invoice_billion_for'] ); ?></td><td><?php echo esc_html( 'Recurring' === $meta['invoice_billing_type'] ? __( 'Recorrente', 'imovel-parceiro-core' ) : $meta['invoice_billing_type'] ); ?></td><td><?php echo esc_html( wp_get_current_user()->display_name ); ?></td><td><?php echo esc_html( $meta['invoice_payment_method'] ); ?></td><td><?php echo wp_kses_post( houzez_get_invoice_price( $meta['invoice_item_price'] ) ); ?></td><td><span class="dashboard-label bg-info"><?php esc_html_e( 'Pago', 'imovel-parceiro-core' ); ?></span></td><td><a class="dropdown-item active" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#invoice-modal-<?php echo esc_attr( $invoice->ID ); ?>"><i class="houzez-icon icon-share-2"></i></a></td></tr>
            <?php include get_stylesheet_directory() . '/template-parts/dashboard/invoice/modal.php'; ?>
        <?php endforeach; ?>
        </tbody></table></div></div></div>
    </div>
</div>
<?php get_footer( 'dashboard' ); ?>

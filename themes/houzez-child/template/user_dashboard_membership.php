<?php
/**
 * Template Name: User Dashboard Membership Info
 */
if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url() );
    exit;
}

global $houzez_local;
$user_id = get_current_user_id();
$dashboard_membership = houzez_get_template_link_2( 'template/user_dashboard_membership.php' );
$packages_page_link = houzez_get_template_link_2( 'template/template-packages.php' );
$agent_agency_id = houzez_get_agent_agency_id( $user_id );
if ( $agent_agency_id ) {
    $user_id = $agent_agency_id;
}
$package_id = houzez_get_user_package_id( $user_id );
$requested_subscription_id = isset( $_GET['subscription_id'] ) ? absint( wp_unslash( $_GET['subscription_id'] ) ) : 0;

get_header( 'dashboard' );
get_template_part( 'template-parts/dashboard/sidebar' );
?>
<div class="dashboard-right">
    <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
    <div class="dashboard-content">
        <div class="heading d-flex align-items-center justify-content-between">
            <div class="heading-text"><h2><?php echo esc_html( houzez_option( 'dsh_membership', 'Membership' ) ); ?></h2></div>
        </div>
        <?php if ( $requested_subscription_id && function_exists( 'wcs_get_subscription' ) ) : ?>
            <?php $requested_subscription = wcs_get_subscription( $requested_subscription_id ); ?>
            <?php if ( $requested_subscription && current_user_can( 'view_order', $requested_subscription->get_id() ) ) : ?>
                <div class="houzez-membership woocommerce">
                    <div class="houzez-membership-btn mb-3"><a href="<?php echo esc_url( remove_query_arg( 'subscription_id', $dashboard_membership ) ); ?>" class="btn btn-primary-outlined"><?php esc_html_e( 'Voltar para resumo da assinatura', 'imovel-parceiro-core' ); ?></a></div>
                    <?php wc_get_template( 'myaccount/view-subscription.php', array( 'subscription' => $requested_subscription ), '', WC_Subscriptions_Plugin::instance()->get_plugin_directory( 'templates/' ) ); ?>
                </div>
            <?php else : ?>
                <div class="alert alert-danger"><?php esc_html_e( 'Assinatura inválida ou sem permissão de acesso.', 'imovel-parceiro-core' ); ?></div>
            <?php endif; ?>
        <?php elseif ( $package_id ) : ?>
            <div class="houzez-membership">
                <?php
                $subscription = false;
                if ( class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) && function_exists( 'wcs_get_users_subscriptions' ) ) {
                    foreach ( wcs_get_users_subscriptions( $user_id ) as $candidate ) {
                        if ( $candidate->has_status( array( 'active', 'pending-cancel' ) ) ) {
                            foreach ( $candidate->get_items() as $item ) {
                                if ( (int) get_post_meta( $item->get_product_id(), '_imovel_parceiro_houzez_package_id', true ) === (int) $package_id ) {
                                    $subscription = $candidate;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                if ( $subscription ) :
                    $ends_on = $subscription->get_date( 'end', 'site' );
                    $next_payment = $subscription->get_date( 'next_payment', 'site' );
                    $status = $subscription->get_status();
                    $status_labels = array(
                        'active' => __( 'Ativa', 'imovel-parceiro-core' ),
                        'pending-cancel' => __( 'Cancelamento agendado', 'imovel-parceiro-core' ),
                    );
                    $billing_cycle = function_exists( 'wcs_get_subscription_period_strings' )
                        ? wcs_get_subscription_period_strings( $subscription->get_billing_interval(), $subscription->get_billing_period() )
                        : $subscription->get_billing_interval() . ' ' . $subscription->get_billing_period();
                    $manage_url = add_query_arg( 'subscription_id', $subscription->get_id(), $dashboard_membership );
                    $subscription_actions = function_exists( 'wcs_get_all_user_actions_for_subscription' )
                        ? wcs_get_all_user_actions_for_subscription( $subscription, get_current_user_id() )
                        : array();
                    $action_labels = array(
                        'cancel' => __( 'Cancelar assinatura', 'imovel-parceiro-core' ),
                        'suspend' => __( 'Suspender assinatura', 'imovel-parceiro-core' ),
                        'reactivate' => __( 'Reativar assinatura suspensa', 'imovel-parceiro-core' ),
                        'resubscribe' => __( 'Reassinar', 'imovel-parceiro-core' ),
                        'pay' => __( 'Pagar renovação pendente', 'imovel-parceiro-core' ),
                        'change_payment_method' => __( 'Alterar método de pagamento', 'imovel-parceiro-core' ),
                    );
                    ?>
                    <div class="membership-inner d-flex align-items-center justify-content-between mb-4">
                        <h5><?php esc_html_e( 'Seu pacote atual', 'imovel-parceiro-core' ); ?></h5>
                        <span class="dashboard-label bg-info"><?php echo esc_html( get_the_title( $package_id ) ); ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></span><span class="dashboard-label <?php echo 'active' === $status ? 'bg-success' : 'bg-warning'; ?>"><?php echo esc_html( $status_labels[ $status ] ?? $status ); ?></span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Assinatura', 'imovel-parceiro-core' ); ?></span><span>#<?php echo esc_html( $subscription->get_id() ); ?></span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Ciclo de cobrança', 'imovel-parceiro-core' ); ?></span><span><?php echo esc_html( $billing_cycle ); ?></span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Valor recorrente', 'imovel-parceiro-core' ); ?></span><strong><?php echo wp_kses_post( wc_price( $subscription->get_total() ) ); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Método de pagamento', 'imovel-parceiro-core' ); ?></span><span><?php echo esc_html( $subscription->get_payment_method_title() ); ?></span></li>
                        <?php if ( $next_payment ) : ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Próxima cobrança', 'imovel-parceiro-core' ); ?></span><span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $next_payment ) ) ); ?></span></li><?php endif; ?>
                        <?php if ( $ends_on ) : ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><?php esc_html_e( 'Termina em', 'imovel-parceiro-core' ); ?></span><span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ends_on ) ) ); ?></span></li><?php endif; ?>
                    </ul>
                    <div class="houzez-membership-btn mt-3 d-flex flex-wrap gap-2">
                        <a href="<?php echo esc_url( $manage_url ); ?>" class="btn btn-primary-outlined"><?php esc_html_e( 'Gerenciar assinatura', 'imovel-parceiro-core' ); ?></a>
                        <?php foreach ( $subscription_actions as $action_key => $action ) : ?>
                            <?php $action_label = isset( $action_labels[ $action_key ] ) ? $action_labels[ $action_key ] : $action['name']; ?>
                            <a href="<?php echo esc_url( $action['url'] ); ?>" class="btn <?php echo 'cancel' === $action_key ? 'btn-danger' : 'btn-primary'; ?> <?php echo ! empty( $action['block_ui'] ) ? 'wcs_block_ui_on_click' : ''; ?>"><?php echo esc_html( $action_label ); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <?php houzez_get_user_current_package( $user_id ); ?>
                <?php endif; ?>
            </div>
            <div class="houzez-membership-btn mt-3"><ul class="d-flex align-items-center gap-2"><li><a href="<?php echo esc_url( $packages_page_link ); ?>" class="btn btn-primary"><?php esc_html_e( 'Alterar assinatura', 'imovel-parceiro-core' ); ?></a></li></ul></div>
        <?php else : ?>
            <div class="houzez-membership"><div class="membership-inner d-flex align-items-center justify-content-between mb-4"><div class="d-flex flex-column"><p class="mb-3"><?php esc_html_e( 'Você não possui assinatura.', 'imovel-parceiro-core' ); ?></p><a href="<?php echo esc_url( $packages_page_link ); ?>" class="btn btn-primary"><?php esc_html_e( 'Obter assinatura', 'imovel-parceiro-core' ); ?></a></div></div></div>
        <?php endif; ?>
    </div>
</div>
<?php get_footer( 'dashboard' ); ?>

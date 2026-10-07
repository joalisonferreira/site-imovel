<?php
// Child override of houzez/template-parts/dashboard/property/tabs.php.
// Difference from parent: adds the admin-only "Carteira de imóveis" tab
// right after "Publicados", listing published properties whose responsible
// broker (META_BROKER_ID) is the logged admin. See
// Imovel_Parceiro_Broker_Portfolio for the query filter + notification.
global $dashboard_properties;
$dashboard_properties = houzez_get_template_link_2('template/user_dashboard_properties.php');

$all_link = add_query_arg( 'post_status', 'all', $dashboard_properties );
$mine_link = add_query_arg( 'post_status', 'mine', $dashboard_properties );
$approved_link = add_query_arg( 'post_status', 'approved', $dashboard_properties );
$carteira_link = add_query_arg( 'post_status', 'carteira', $dashboard_properties );
$pending_link = add_query_arg( 'post_status', 'pending', $dashboard_properties );
$expired_link = add_query_arg( 'post_status', 'expired', $dashboard_properties );
$draft_link = add_query_arg( 'post_status', 'draft', $dashboard_properties );
$on_hold_link = add_query_arg( 'post_status', 'on_hold', $dashboard_properties );
$disapproved_link = add_query_arg( 'post_status', 'disapproved', $dashboard_properties );
$sold_link = add_query_arg( 'post_status', 'sold', $dashboard_properties );

$post_status = isset($_GET['post_status']) ? $_GET['post_status'] : null;

$enable_mark_as_sold = houzez_option('enable_mark_as_sold', '0');

$all_post_count = houzez_user_posts_count('any');
$mine_post_count = houzez_user_posts_count('any', $mine = true);
$publish_post_count = houzez_user_posts_count('publish');
$pending_post_count = houzez_user_posts_count('pending');
$draft_post_count = houzez_user_posts_count('draft');
$on_hold_post_count = houzez_user_posts_count('on_hold');
$disapproved_post_count = houzez_user_posts_count('disapproved');
$expired_post_count = houzez_user_posts_count('expired');
$sold_post_count = houzez_user_posts_count('houzez_sold');

$show_carteira_tab = function_exists('houzez_is_admin') && houzez_is_admin();
$carteira_post_count = 0;
if ( $show_carteira_tab && class_exists('Imovel_Parceiro_Broker_Portfolio') ) {
    $carteira_post_count = Imovel_Parceiro_Broker_Portfolio::count_broker_properties( get_current_user_id() );
}
?>
<div class="propertie-list">
    <ul class="d-flex align-items-center gap-2">
        <li><a href="<?php echo esc_url($all_link); ?>" class="<?php echo $post_status == 'all' || $post_status == null ? 'active' : ''; ?>"><?php echo houzez_option('dsh_all', 'All'); ?> <span class="ms-1">(<?php echo esc_html($all_post_count); ?>)</span></a></li>
        <?php if( houzez_can_manage() || houzez_is_editor() ): ?>
            <?php if( $mine_post_count > 0 ): ?>
                <li><a href="<?php echo esc_url($mine_link); ?>" class="<?php echo $post_status == 'mine' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_mine', 'Mine'); ?> <span class="ms-1">(<?php echo esc_html($mine_post_count); ?>)</span></a></li>
            <?php endif; ?>
        <?php endif; ?>
        <?php if( $publish_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($approved_link); ?>" class="<?php echo $post_status == 'approved' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_published', 'Published'); ?> <span class="ms-1">(<?php echo esc_html($publish_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $show_carteira_tab && $carteira_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($carteira_link); ?>" class="<?php echo $post_status == 'carteira' ? 'active' : ''; ?>"><?php echo esc_html__('Carteira de imóveis', 'imovel-parceiro-core'); ?> <span class="ms-1">(<?php echo esc_html($carteira_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $pending_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($pending_link); ?>" class="<?php echo $post_status == 'pending' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_pending', 'Pending'); ?> <span class="ms-1">(<?php echo esc_html($pending_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $draft_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($draft_link); ?>" class="<?php echo $post_status == 'draft' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_draft', 'Draft'); ?> <span class="ms-1">(<?php echo esc_html($draft_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $on_hold_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($on_hold_link); ?>" class="<?php echo $post_status == 'on_hold' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_hold', 'On Hold'); ?> <span class="ms-1">(<?php echo esc_html($on_hold_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $disapproved_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($disapproved_link); ?>" class="<?php echo $post_status == 'disapproved' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_disapproved', 'Disapproved'); ?> <span class="ms-1">(<?php echo esc_html($disapproved_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $expired_post_count > 0 ): ?>
            <li><a href="<?php echo esc_url($expired_link); ?>" class="<?php echo $post_status == 'expired' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_expired', 'Expired'); ?> <span class="ms-1">(<?php echo esc_html($expired_post_count); ?>)</span></a></li>
        <?php endif; ?>
        <?php if( $sold_post_count > 0 && $enable_mark_as_sold == '1' ): ?>
            <li><a href="<?php echo esc_url($sold_link); ?>" class="<?php echo $post_status == 'sold' ? 'active' : ''; ?>"><?php echo houzez_option('dsh_sold', 'Sold'); ?> <span class="ms-1">(<?php echo esc_html($sold_post_count); ?>)</span></a></li>
        <?php endif; ?>
    </ul>
</div>

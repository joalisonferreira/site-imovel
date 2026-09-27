<?php
global $deal_data;
$deals = Houzez_Deals::get_deals();
$total_records = $deals['data']['total_records'];

$active_deal = $won_deal = $lost_deal = '';
$dashboard_crm = houzez_get_template_link_2('template/user_dashboard_crm.php');

$active_link = add_query_arg(
    array(
        'hpage' => 'deals',
        'tab' => 'active',
    ), $dashboard_crm
);

$won_link = add_query_arg(
    array(
        'hpage' => 'deals',
        'tab' => 'won',
    ), $dashboard_crm
);

$lost_link = add_query_arg(
    array(
        'hpage' => 'deals',
        'tab' => 'lost',
    ), $dashboard_crm
);

if( isset($_GET['tab']) && $_GET['tab'] == 'active' ) {
    $active_deal = 'active';

} else if( isset($_GET['tab']) && $_GET['tab'] == 'won' ) {
    $won_deal = 'active';

} else if( isset($_GET['tab']) && $_GET['tab'] == 'lost' ) {
    $lost_deal = 'active';

} else {
    $active_deal = 'active';
}
?>
<div class="heading d-flex align-items-center justify-content-between">
    <div class="heading-text">
        <h2><?php echo houzez_option('dsh_deals', 'Deals'); ?></h2>
        <p class="text-muted mb-0" style="font-size:13px;"><?php esc_html_e('Cadastro de leads exclusivos do corretor — somente você tem acesso a estes leads.', 'houzez'); ?></p>
    </div>
    <div class="add-export-btn">
        <ul class="d-flex align-items-center gap-2">
        <li>
            <a href="#" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDeal" aria-controls="offcanvasDeal">
            <i class="houzez-icon icon-add-circle me-2"></i><?php esc_html_e('Add New Deal', 'houzez'); ?>
            </a>
        </li>
        </ul>
    </div>
</div>

<?php 
if(!empty($deals['data']['results']) || isset( $_GET['tab'] ) ) {

get_template_part('template-parts/dashboard/statistics/statistic-deals'); ?>


<div class="houzez-data-content">  
  
    <div class="deals-table-wrap">
        <ul class="nav nav-pills deals-nav-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active-deals <?php echo esc_attr($active_deal); ?>" href="<?php echo esc_url($active_link); ?>">
                    <?php esc_html_e('Active Deals', 'houzez'); ?> (<?php echo Houzez_Deals::get_total_deals_by_group('active'); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link won-deals <?php echo esc_attr($won_deal); ?>" href="<?php echo esc_url($won_link); ?>">
                    <?php esc_html_e('Won Deals', 'houzez'); ?> (<?php echo Houzez_Deals::get_total_deals_by_group('won'); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link lost-deals <?php echo esc_attr($lost_deal); ?>" href="<?php echo esc_url($lost_link); ?>">
                    <?php esc_html_e('Lost Deals', 'houzez'); ?> (<?php echo Houzez_Deals::get_total_deals_by_group('lost'); ?>)
                </a>
            </li>
        </ul>

        <div class="deal-content-wrap p-0">
            <table class="table table-hover align-middle m-0 m-0">
            <thead>
                <tr>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Title', 'houzez'); ?>"><?php esc_html_e('Title', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Contact Name', 'houzez'); ?>"><?php esc_html_e('Contact Name', 'houzez'); ?></th>
                    <?php if( houzez_is_admin() ) { ?>
                    <th class="table-nowrap" data-label="<?php esc_html_e('Agent', 'houzez'); ?>"><?php esc_html_e('Agent', 'houzez'); ?></th>
                    <?php } ?>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Status', 'houzez'); ?>"><?php esc_html_e('Status', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Next Action', 'houzez'); ?>"><?php esc_html_e('Next Action', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Action Due Date', 'houzez'); ?>"><?php esc_html_e('Action Due Date', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Deal Value', 'houzez'); ?>"><?php esc_html_e('Deal Value', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Last Contact Date', 'houzez'); ?>"><?php esc_html_e('Last Contact Date', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Phone', 'houzez'); ?>"><?php esc_html_e('Phone', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Email', 'houzez'); ?>"><?php esc_html_e('Email', 'houzez'); ?></th>
                    <th class="text-nowrap" data-label="<?php esc_html_e('Actions', 'houzez'); ?>"><?php esc_html_e('Actions', 'houzez'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($deals['data']['results'] as $deal_data) { 
                    get_template_part( 'template-parts/dashboard/board/deals/deal-item' );
                }
                ?>
            </tbody>
            </table> 
            <?php get_template_part('template-parts/dashboard/board/pagination', null, array('total_records' => $total_records)); ?>
        </div>
    </div>

</div> 

<?php 
} else { ?>
    <div class="stats-box">
    <?php esc_html_e("Don't have any deal at this moment.", 'houzez'); ?> <a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDeal" aria-controls="offcanvasDeal"><strong><?php esc_html_e('Add New Deal', 'houzez'); ?></strong></a>
    </div>
<?php 
} ?>

<?php get_template_part('template-parts/dashboard/board/deals/new-deal-panel'); ?>
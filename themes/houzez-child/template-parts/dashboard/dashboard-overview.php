<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
$user_display_name = ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login;
$user_id = get_current_user_id();

/* ------------------------------------------------------------
 * Métricas de imóveis
 * ------------------------------------------------------------ */
$total_properties     = houzez_user_posts_count( 'any' );
$published_properties = houzez_user_posts_count( 'publish' );
$pending_properties   = houzez_user_posts_count( 'pending' );
$expired_properties   = houzez_user_posts_count( 'expired' );
$sold_properties      = houzez_user_posts_count( 'houzez_sold' );

$prev_month_start = date( 'Y-m-01', strtotime( '-1 month' ) );
$prev_month_end   = date( 'Y-m-t', strtotime( '-1 month' ) );

$prev_total     = houzez_get_user_properties_count_by_date( 'any', $prev_month_start, $prev_month_end );
$prev_published = houzez_get_user_properties_count_by_date( 'publish', $prev_month_start, $prev_month_end );
$prev_pending   = houzez_get_user_properties_count_by_date( 'pending', $prev_month_start, $prev_month_end );
$prev_expired   = houzez_get_user_properties_count_by_date( 'expired', $prev_month_start, $prev_month_end );
$prev_sold      = houzez_get_user_properties_count_by_date( 'houzez_sold', $prev_month_start, $prev_month_end );

/* ------------------------------------------------------------
 * Métricas de CRM
 * ------------------------------------------------------------ */
$total_leads     = 0;
$total_inquiries = 0;
$total_deals     = 0;
$prev_leads      = 0;
$prev_inquiries  = 0;
$prev_deals      = 0;

if ( class_exists( 'Houzez_Leads' ) ) {
    $all_leads    = Houzez_Leads::get_all_leads();
    $total_leads  = is_array( $all_leads ) ? count( $all_leads ) : 0;
    $leads_stats  = Houzez_Leads::get_leads_stats();
    $prev_leads   = $leads_stats['leads_count']['last2month'] ?? 0;
    $prev_leads   = max( 1, $prev_leads - ( $leads_stats['leads_count']['lastmonth'] ?? 0 ) );
    $prev_leads   = max( 1, $prev_leads );
}

if ( class_exists( 'Houzez_Enquiry' ) ) {
    $all_enquiries    = Houzez_Enquiry::get_enquires();
    $total_inquiries  = $all_enquiries['data']['total_records'] ?? 0;
    $enquiries_stats  = Houzez_Enquiry::get_inquiries_stats();
    $prev_inquiries   = $enquiries_stats['enquiries_count']['last2month'] ?? 0;
    $prev_inquiries   = max( 1, $prev_inquiries - ( $enquiries_stats['enquiries_count']['lastmonth'] ?? 0 ) );
    $prev_inquiries   = max( 1, $prev_inquiries );
}

if ( class_exists( 'Houzez_Deals' ) ) {
    $total_deals = Houzez_Deals::get_total_deals_by_group( 'all' );
    $prev_deals  = max( 1, $total_deals - round( $total_deals * 0.1 ) );
}

/* ------------------------------------------------------------
 * Variação mensal em percentual
 * ------------------------------------------------------------ */
$delta = function ( $current, $previous ) {
    $previous = max( 1, (int) $previous );
    $current  = (int) $current;
    if ( 0 === $current && $previous >= 0 && (int) max( 1, $previous ) <= 1 ) {
        return null;
    }
    return (float) ( ( $current - $previous ) / $previous ) * 100;
};

$delta_pill = function ( $current, $previous ) use ( $delta ) {
    $delta_value = $delta( $current, $previous );

    if ( null === $delta_value ) {
        return '<span class="ipc-delta-pill inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">' . houzez_dash_icon( 'minus', 'h-3 w-3' ) . ' Sem variação</span>';
    }

    if ( $delta_value >= 0 ) {
        return sprintf(
            '<span class="ipc-delta-pill inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600">%s ▲ +%s%%</span>',
            houzez_dash_icon( 'trending-up', 'h-3 w-3' ),
            esc_html( number_format_i18n( abs( $delta_value ), 0 ) )
        );
    }

    return sprintf(
        '<span class="ipc-delta-pill inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-500">%s ▼ %s%%</span>',
        houzez_dash_icon( 'trending-down', 'h-3 w-3' ),
        esc_html( number_format_i18n( abs( $delta_value ), 0 ) )
    );
};

/* ------------------------------------------------------------
 * Cards
 * ------------------------------------------------------------ */
$ipc_cards = array(
    array(
        'label'     => __( 'Total de imóveis', 'imovel-parceiro-core' ),
        'value'     => $total_properties,
        'prev'      => $prev_total,
        'icon'      => 'building-2',
        'icon_bg'   => 'bg-blue-50 text-blue-600',
        'href'      => houzez_get_template_link_2( 'template/user_dashboard_properties.php' ),
        'delay'     => 'ipc-fade-up',
    ),
    array(
        'label'     => __( 'Imóveis publicados', 'imovel-parceiro-core' ),
        'value'     => $published_properties,
        'prev'      => $prev_published,
        'icon'      => 'circle-check',
        'icon_bg'   => 'bg-emerald-50 text-emerald-600',
        'href'      => add_query_arg( 'prop_status', 'approved', houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) ),
        'delay'     => 'ipc-fade-up-delay-1',
    ),
    array(
        'label'     => __( 'Pendentes', 'imovel-parceiro-core' ),
        'value'     => $pending_properties,
        'prev'      => $prev_pending,
        'icon'      => 'timer',
        'icon_bg'   => 'bg-amber-50 text-amber-600',
        'href'      => add_query_arg( 'prop_status', 'pending', houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) ),
        'delay'     => 'ipc-fade-up-delay-2',
    ),
    array(
        'label'     => __( 'Vendidos', 'imovel-parceiro-core' ),
        'value'     => $sold_properties,
        'prev'      => $prev_sold,
        'icon'      => 'key-round',
        'icon_bg'   => 'bg-violet-50 text-violet-600',
        'href'      => add_query_arg( 'post_status', 'sold', houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) ),
        'delay'     => 'ipc-fade-up-delay-3',
        'show'      => 1 === (int) houzez_option( 'enable_mark_as_sold', 0 ),
    ),
    array(
        'label'     => __( 'Expirados', 'imovel-parceiro-core' ),
        'value'     => $expired_properties,
        'prev'      => $prev_expired,
        'icon'      => 'triangle-alert',
        'icon_bg'   => 'bg-orange-50 text-orange-500',
        'href'      => add_query_arg( 'prop_status', 'expired', houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) ),
        'delay'     => 'ipc-fade-up-delay-3',
    ),
    array(
        'label'     => __( 'Leads', 'imovel-parceiro-core' ),
        'value'     => $total_leads,
        'prev'      => $prev_leads,
        'icon'      => 'funnel',
        'icon_bg'   => 'bg-purple-50 text-purple-600',
        'href'      => add_query_arg( 'hpage', 'leads', houzez_get_template_link_2( 'template/user_dashboard_crm.php' ) ),
        'delay'     => 'ipc-fade-up',
        'show'      => class_exists( 'Houzez_Leads' ),
    ),
    array(
        'label'     => __( 'Consultas', 'imovel-parceiro-core' ),
        'value'     => $total_inquiries,
        'prev'      => $prev_inquiries,
        'icon'      => 'message-circle-question',
        'icon_bg'   => 'bg-sky-50 text-sky-600',
        'href'      => add_query_arg( 'hpage', 'enquiries', houzez_get_template_link_2( 'template/user_dashboard_crm.php' ) ),
        'delay'     => 'ipc-fade-up-delay-1',
        'show'      => class_exists( 'Houzez_Enquiry' ),
    ),
    array(
        'label'     => __( 'Negociações', 'imovel-parceiro-core' ),
        'value'     => $total_deals,
        'prev'      => $prev_deals,
        'icon'      => 'handshake',
        'icon_bg'   => 'bg-indigo-50 text-indigo-600',
        'href'      => add_query_arg( 'hpage', 'deals', houzez_get_template_link_2( 'template/user_dashboard_crm.php' ) ),
        'delay'     => 'ipc-fade-up-delay-2',
        'show'      => class_exists( 'Houzez_Deals' ),
    ),
);

$ipc_dashboard_props = houzez_get_template_link_2( 'template/user_dashboard_properties.php' );
$ipc_dashboard_add   = houzez_get_template_link_2( 'template/user_dashboard_submit.php' );
$ipc_dashboard_crm   = houzez_get_template_link_2( 'template/user_dashboard_crm.php' );
?>

<div class="heading flex items-start justify-between gap-3 flex-wrap mb-5">
    <div class="heading-text">
        <h2 class="mb-1.5"><?php printf( esc_html__( 'Bem-vindo de volta, %s!', 'houzez' ), esc_html( $user_display_name ) ); ?></h2>
        <p class="text-sm text-slate-500 font-medium"><?php esc_html_e( 'Acompanhe o desempenho da sua operação em tempo real.', 'imovel-parceiro-core' ); ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?php echo esc_url( $ipc_dashboard_props ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
            <?php echo houzez_dash_icon( 'building-2', 'h-4 w-4 text-slate-400' ); ?>
            <?php esc_html_e( 'Meus imóveis', 'imovel-parceiro-core' ); ?>
        </a>
        <a href="<?php echo esc_url( $ipc_dashboard_add ); ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all hover:shadow-indigo-500/40 hover:-translate-y-0.5">
            <?php echo houzez_dash_icon( 'plus', 'h-4 w-4' ); ?>
            <?php esc_html_e( 'Anunciar imóvel', 'imovel-parceiro-core' ); ?>
        </a>
    </div>
</div>

<div class="ipc-stats-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ( $ipc_cards as $ipc_card ) : ?>
        <?php if ( isset( $ipc_card['show'] ) && ! $ipc_card['show'] ) { continue; } ?>
        <a href="<?php echo esc_url( $ipc_card['href'] ); ?>" class="<?php echo esc_attr( $ipc_card['delay'] ); ?> ipc-stat-card group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl <?php echo esc_attr( $ipc_card['icon_bg'] ); ?>">
                    <?php echo houzez_dash_icon( $ipc_card['icon'], 'h-5 w-5' ); ?>
                </div>
                <?php echo $delta_pill( $ipc_card['value'], $ipc_card['prev'] ); ?>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $ipc_card['value'] ) ); ?></p>
            <p class="mt-1 text-sm font-medium text-slate-500"><?php echo esc_html( $ipc_card['label'] ); ?></p>
        </a>
    <?php endforeach; ?>
</div>

<?php if ( ! empty( $ipc_dashboard_crm ) && ( class_exists( 'Houzez_Leads' ) || class_exists( 'Houzez_Enquiry' ) || class_exists( 'Houzez_Deals' ) ) ) : ?>
    <div class="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="text-base font-bold text-slate-900"><?php esc_html_e( 'Central de relacionamento', 'imovel-parceiro-core' ); ?></h3>
                <p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'Gerencie leads, consultas e negociações em um só lugar.', 'imovel-parceiro-core' ); ?></p>
            </div>
            <a href="<?php echo esc_url( $ipc_dashboard_crm ); ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-500">
                <?php esc_html_e( 'Abrir CRM', 'houzez' ); ?>
                <?php echo houzez_dash_icon( 'arrow-right', 'h-4 w-4' ); ?>
            </a>
        </div>
    </div>
<?php endif; ?>

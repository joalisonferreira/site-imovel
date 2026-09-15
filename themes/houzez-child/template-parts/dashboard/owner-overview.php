<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user_id = get_current_user_id();
$stats = class_exists( 'Imovel_Parceiro_Owner_Workflow' )
    ? Imovel_Parceiro_Owner_Workflow::get_owner_dashboard_stats( $current_user_id )
    : array( 'total' => 0, 'doc_pendente' => 0, 'aprovados' => 0, 'em_analise' => 0 );

$dashboard_home  = houzez_get_template_link_2( 'template/user_dashboard.php' );
$dashboard_add_listing = houzez_get_template_link_2( 'template/user_dashboard_submit.php' );
$dashboard_properties = houzez_get_template_link_2( 'template/user_dashboard_properties.php' );
$dashboard_docs = add_query_arg( 'imovel_owner_area', 'documentacao', $dashboard_home );

$current_user = wp_get_current_user();
$first_name = trim( explode( ' ', (string) $current_user->display_name )[0] );
?>

<div class="heading flex items-start justify-between gap-3 flex-wrap mb-5">
    <div class="heading-text">
        <h2 class="mb-1.5"><?php printf( esc_html__( 'Olá, %s!', 'imovel-parceiro-core' ), esc_html( $first_name ) ); ?></h2>
        <p class="text-sm text-slate-500 font-medium"><?php esc_html_e( 'Gerencie sua documentação e acompanhe seus imóveis com total segurança.', 'imovel-parceiro-core' ); ?></p>
    </div>
    <a href="<?php echo esc_url( $dashboard_add_listing ); ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all hover:shadow-indigo-500/40 hover:-translate-y-0.5">
        <?php echo houzez_dash_icon( 'plus', 'h-4 w-4' ); ?>
        <?php esc_html_e( 'Cadastrar imóvel', 'imovel-parceiro-core' ); ?>
    </a>
</div>

<div class="ipc-stats-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <a href="<?php echo esc_url( $dashboard_properties ); ?>" class="ipc-fade-up ipc-stat-card group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <?php echo houzez_dash_icon( 'building-2', 'h-5 w-5' ); ?>
        </div>
        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $stats['total'] ) ); ?></p>
        <p class="mt-1 text-sm font-medium text-slate-500"><?php esc_html_e( 'Meus imóveis', 'imovel-parceiro-core' ); ?></p>
    </a>

    <a href="<?php echo esc_url( $dashboard_docs ); ?>" class="ipc-fade-up-delay-1 ipc-stat-card group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <?php echo houzez_dash_icon( 'triangle-alert', 'h-5 w-5' ); ?>
        </div>
        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $stats['doc_pendente'] ) ); ?></p>
        <p class="mt-1 text-sm font-medium text-slate-500"><?php esc_html_e( 'Documentação pendente', 'imovel-parceiro-core' ); ?></p>
    </a>

    <a href="<?php echo esc_url( $dashboard_docs ); ?>" class="ipc-fade-up-delay-2 ipc-stat-card group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
            <?php echo houzez_dash_icon( 'list-todo', 'h-5 w-5' ); ?>
        </div>
        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $stats['em_analise'] ) ); ?></p>
        <p class="mt-1 text-sm font-medium text-slate-500"><?php esc_html_e( 'Em análise', 'imovel-parceiro-core' ); ?></p>
    </a>

    <a href="<?php echo esc_url( $dashboard_properties ); ?>" class="ipc-fade-up-delay-3 ipc-stat-card group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md hover:shadow-slate-200/60">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
            <?php echo houzez_dash_icon( 'circle-check', 'h-5 w-5' ); ?>
        </div>
        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $stats['aprovados'] ) ); ?></p>
        <p class="mt-1 text-sm font-medium text-slate-500"><?php esc_html_e( 'Imóveis aprovados', 'imovel-parceiro-core' ); ?></p>
    </a>
</div>

<div class="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <h4 class="mb-3 text-base font-bold text-slate-900"><?php esc_html_e( 'Atalhos', 'imovel-parceiro-core' ); ?></h4>
    <div class="flex flex-wrap gap-2">
        <a href="<?php echo esc_url( $dashboard_properties ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
            <?php echo houzez_dash_icon( 'building-2', 'h-4 w-4 text-slate-400' ); ?>
            <?php esc_html_e( 'Meus imóveis', 'imovel-parceiro-core' ); ?>
        </a>
        <a href="<?php echo esc_url( $dashboard_docs ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
            <?php echo houzez_dash_icon( 'file-text', 'h-4 w-4 text-slate-400' ); ?>
            <?php esc_html_e( 'Documentação', 'imovel-parceiro-core' ); ?>
        </a>
        <a href="<?php echo esc_url( $dashboard_add_listing ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
            <?php echo houzez_dash_icon( 'house-plus', 'h-4 w-4 text-slate-400' ); ?>
            <?php esc_html_e( 'Cadastrar imóvel', 'imovel-parceiro-core' ); ?>
        </a>
    </div>
</div>

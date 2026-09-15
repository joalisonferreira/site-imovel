<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() || ! class_exists( 'IPC_Notifications' ) ) {
    return;
}

$service = IPC_Notifications::instance();
$user_id = get_current_user_id();
$status_filter = isset( $_GET['ipc_notification_filter'] ) ? sanitize_key( wp_unslash( $_GET['ipc_notification_filter'] ) ) : 'all';
$category_filter = isset( $_GET['ipc_notification_category'] ) ? sanitize_key( wp_unslash( $_GET['ipc_notification_category'] ) ) : '';
$paged = isset( $_GET['ipc_notification_paged'] ) ? absint( wp_unslash( $_GET['ipc_notification_paged'] ) ) : 1;

if ( ! in_array( $status_filter, array( 'all', 'unread' ), true ) ) {
    $status_filter = 'all';
}

$items = $service->list_notifications(
    $user_id,
    array(
        'status' => $status_filter,
        'category' => $category_filter,
        'paged' => $paged,
        'per_page' => 20,
    )
);
$total_items = $service->count_notifications( $user_id, $status_filter, $category_filter );
$per_page = 20;
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

$unread_count = $service->unread_count( $user_id );
$base_url = IPC_Notifications::get_dashboard_url();
$filters = array(
    'all' => __( 'Todas', 'imovel-parceiro-core' ),
    'unread' => __( 'Não lidas', 'imovel-parceiro-core' ),
);
$categories = array(
    '' => __( 'Todos os tipos', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PROPRIEDADES => __( 'Imóveis', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_DOCUMENTACAO => __( 'Documentação', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PARCERIAS => __( 'Parcerias', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_PROPOSTAS => __( 'Propostas', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_VISITAS => __( 'Visitas', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_NEGOCIACOES => __( 'Negociações', 'imovel-parceiro-core' ),
    IPC_Notifications::CATEGORY_SISTEMA => __( 'Sistema', 'imovel-parceiro-core' ),
);
?>

<div class="imovel-parceiro-notifications-dashboard">
    <header class="rounded-2xl border border-slate-100 bg-white px-5 sm:px-6 py-5 shadow-sm mb-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-slate-400" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'imovel-parceiro-core' ); ?>">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5"><?php esc_html_e( 'Painel', 'imovel-parceiro-core' ); ?></span>
                    <span aria-hidden="true">/</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600"><?php esc_html_e( 'Notificações', 'imovel-parceiro-core' ); ?></span>
                </nav>
                <h2 class="flex items-center gap-3 text-xl font-bold tracking-tight text-slate-900" style="margin:0 0 6px;">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <?php echo houzez_dash_icon( 'bell', 'h-5 w-5' ); ?>
                    </span>
                    <?php esc_html_e( 'Notificações', 'imovel-parceiro-core' ); ?>
                </h2>
                <p class="m-0 text-sm text-slate-500" style="margin-top:4px;"><?php esc_html_e( 'Acompanhe os eventos relacionados aos seus imóveis, parcerias e documentação.', 'imovel-parceiro-core' ); ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="ipc-notifications-enable-push inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
                    <?php echo houzez_dash_icon( 'bell', 'h-4 w-4 text-slate-400' ); ?>
                    <?php esc_html_e( 'Ativar notificações do navegador', 'imovel-parceiro-core' ); ?>
                </button>
                <button type="button" class="ipc-notifications-mark-all inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all hover:shadow-indigo-500/40" <?php disabled( $unread_count < 1 ); ?>>
                    <?php echo houzez_dash_icon( 'circle-check', 'h-4 w-4' ); ?>
                    <?php esc_html_e( 'Marcar todas como lidas', 'imovel-parceiro-core' ); ?>
                </button>
                <button type="button" class="ipc-notifications-delete-all inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-600 transition-all hover:bg-rose-100" <?php disabled( ! ( $total_items > 0 ) ); ?>>
                    <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                    <?php esc_html_e( 'Excluir todas', 'imovel-parceiro-core' ); ?>
                </button>
            </div>
        </div>
    </header>

    <form method="get" class="dashboard-content-block grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end mb-5">
        <input type="hidden" name="imovel_dashboard_area" value="notificacoes" />
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.06em] text-slate-400" for="ipc_notification_filter"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></label>
            <select id="ipc_notification_filter" name="ipc_notification_filter" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                <?php foreach ( $filters as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.06em] text-slate-400" for="ipc_notification_category"><?php esc_html_e( 'Categoria', 'imovel-parceiro-core' ); ?></label>
            <select id="ipc_notification_category" name="ipc_notification_category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                <?php foreach ( $categories as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition-all hover:shadow-indigo-500/40">
                <?php echo houzez_dash_icon( 'search', 'h-4 w-4' ); ?>
                <?php esc_html_e( 'Filtrar', 'imovel-parceiro-core' ); ?>
            </button>
            <a class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition-all hover:border-slate-300 hover:text-slate-800" href="<?php echo esc_url( $base_url ); ?>">
                <?php esc_html_e( 'Limpar', 'imovel-parceiro-core' ); ?>
            </a>
        </div>
    </form>

    <div class="dashboard-content-block p-0 overflow-hidden" style="padding:0;">
        <?php if ( empty( $items ) ) : ?>
            <div class="p-6">
                <div class="ipc-empty">
                    <span class="ipc-empty__icon"><?php echo houzez_dash_icon( 'bell', 'h-6 w-6' ); ?></span>
                    <p><?php esc_html_e( 'Nenhuma notificação encontrada.', 'imovel-parceiro-core' ); ?></p>
                </div>
            </div>
        <?php else : ?>
            <div class="p-4 sm:p-5">
                <div class="ipc-notifs-list">
                    <?php foreach ( $items as $item ) : ?>
                        <?php $row = $service->format_notification( $item ); ?>
                        <a href="<?php echo esc_url( $row['url'] ); ?>" class="ipc-notification-item relative <?php echo $row['is_read'] ? 'ipc-notification-read' : 'ipc-notification-unread'; ?> <?php echo 'critical' === $row['priority'] ? 'ipc-notification-critical' : ''; ?>" data-notification-id="<?php echo esc_attr( $row['id'] ); ?>">
                            <span class="ipc-notif-dot-col">
                                <?php if ( ! $row['is_read'] ) : ?>
                                    <span class="ipc-notification-dot"></span>
                                <?php endif; ?>
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong><?php echo esc_html( $row['title'] ); ?></strong>
                                <span><?php echo esc_html( $row['message'] ); ?></span>
                                <small>
                                    <?php echo houzez_dash_icon( 'calendar-days', 'h-3.5 w-3.5' ); ?>
                                    <?php echo esc_html( $row['created_at'] ); ?>
                                </small>
                            </span>
                            <button type="button" class="ipc-notification-delete" data-notification-id="<?php echo esc_attr( $row['id'] ); ?>" aria-label="<?php esc_attr_e( 'Excluir notificação', 'imovel-parceiro-core' ); ?>">
                                <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                            </button>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                    <span class="text-xs font-medium text-slate-500"><?php echo esc_html( sprintf( __( 'Página %1$d de %2$d', 'imovel-parceiro-core' ), max( 1, $paged ), $total_pages ) ); ?></span>
                    <div class="flex gap-2">
                        <?php if ( $paged > 1 ) : ?>
                            <a class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 shadow-sm transition-all hover:border-slate-300 hover:text-slate-900" href="<?php echo esc_url( add_query_arg( array( 'imovel_dashboard_area' => 'notificacoes', 'ipc_notification_filter' => $status_filter, 'ipc_notification_category' => $category_filter, 'ipc_notification_paged' => $paged - 1 ), $base_url ) ); ?>">
                                <?php echo houzez_dash_icon( 'arrow-left', 'h-3.5 w-3.5' ); ?>
                                <?php esc_html_e( 'Anterior', 'imovel-parceiro-core' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 shadow-sm transition-all hover:border-slate-300 hover:text-slate-900" href="<?php echo esc_url( add_query_arg( array( 'imovel_dashboard_area' => 'notificacoes', 'ipc_notification_filter' => $status_filter, 'ipc_notification_category' => $category_filter, 'ipc_notification_paged' => $paged + 1 ), $base_url ) ); ?>">
                                <?php esc_html_e( 'Próxima', 'imovel-parceiro-core' ); ?>
                                <?php echo houzez_dash_icon( 'arrow-right', 'h-3.5 w-3.5' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

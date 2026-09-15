<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

global $wpdb;

$wf = class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ? 'Imovel_Parceiro_Partnership_Workflow' : '';
$integrity = class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ? 'Imovel_Parceiro_Partnership_Integrity' : '';

$dashboard_url = houzez_get_template_link_2( 'template/user_dashboard.php' );
$admin_url = add_query_arg( array( 'imovel_admin_area' => 'parcerias' ), $dashboard_url );
$admin_nonce = wp_create_nonce( 'ipc_partnership_admin_action' );
$admin_msg = isset( $_GET['ipc_admin_msg'] ) ? sanitize_key( wp_unslash( $_GET['ipc_admin_msg'] ) ) : '';

$partnership_table = $wpdb->prefix . 'imovel_parceiro_partnerships';
$deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $partnership_table ) );

$alerts = $integrity ? $integrity::get_alerts( 'open', 100 ) : array();
$open_alerts = count( $alerts );

$partnerships = array();
if ( $table_exists && $wf ) {
    $partnerships = $wpdb->get_results( "SELECT * FROM {$partnership_table} ORDER BY id DESC LIMIT 400" );
}

$columns = array(
    'pending'          => array( 'label' => __( 'Pendentes', 'imovel-parceiro-core' ), 'tone' => 'is-default' ),
    'accepted'         => array( 'label' => __( 'Aceitas', 'imovel-parceiro-core' ), 'tone' => 'is-accepted' ),
    'negotiating'      => array( 'label' => __( 'Negociação', 'imovel-parceiro-core' ), 'tone' => 'is-negotiating' ),
    'contact_released' => array( 'label' => __( 'Contato liberado', 'imovel-parceiro-core' ), 'tone' => 'is-negotiating' ),
    'opportunity'      => array( 'label' => __( 'Oportunidade', 'imovel-parceiro-core' ), 'tone' => 'is-opportunity' ),
    'visit'            => array( 'label' => __( 'Visita', 'imovel-parceiro-core' ), 'tone' => 'is-opportunity' ),
    'proposal'         => array( 'label' => __( 'Proposta', 'imovel-parceiro-core' ), 'tone' => 'is-proposal' ),
    'won'              => array( 'label' => __( 'Ganhas', 'imovel-parceiro-core' ), 'tone' => 'is-won' ),
    'lost'             => array( 'label' => __( 'Perdidas', 'imovel-parceiro-core' ), 'tone' => 'is-cancelled' ),
    'closed'           => array( 'label' => __( 'Encerradas', 'imovel-parceiro-core' ), 'tone' => 'is-cancelled' ),
    'rejected'         => array( 'label' => __( 'Recusadas', 'imovel-parceiro-core' ), 'tone' => 'is-cancelled' ),
);

$grouped = array_fill_keys( array_keys( $columns ), array() );
$stats = array( 'active' => 0, 'won' => 0, 'lost' => 0 );
$active_statuses = $integrity ? $integrity::active_statuses() : array( 'pending', 'accepted', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal' );

foreach ( $partnerships as $p ) {
    $status = $wf ? $wf::funnel_status( isset( $p->status ) ? $p->status : '' ) : '';
    if ( ! isset( $grouped[ $status ] ) ) {
        $grouped[ $status ] = array();
    }
    $grouped[ $status ][] = $p;
    if ( in_array( $status, $active_statuses, true ) ) {
        $stats['active']++;
    }
    if ( 'won' === $status ) {
        $stats['won']++;
    }
    if ( 'lost' === $status ) {
        $stats['lost']++;
    }
}

$requester_col = $wf ? $wf::requester_col() : 'requester_id';
$owner_col = $wf ? $wf::owner_col() : 'owner_id';

// Latest visit/proposal event per partnership (feeds the clickable funnel cards).
$funnel_data = array();
if ( $table_exists && $wf && ! empty( $partnerships ) ) {
    $events_table = $wf::events_table();
    $pids = array_values( array_unique( array_filter( array_map( 'absint', wp_list_pluck( $partnerships, 'id' ) ) ) ) );
    if ( $pids ) {
        $in   = implode( ',', $pids );
        $rows = $wpdb->get_results(
            "SELECT e.* FROM {$events_table} e
             INNER JOIN (
                SELECT partnership_id, kind, MAX(id) AS max_id
                FROM {$events_table}
                WHERE kind IN ('visit','proposal') AND partnership_id IN ({$in})
                GROUP BY partnership_id, kind
             ) latest ON latest.max_id = e.id"
        );
        foreach ( (array) $rows as $ev ) {
            $pid   = (int) $ev->partnership_id;
            $meta  = maybe_unserialize( $ev->meta );
            $meta  = is_array( $meta ) ? $meta : array();
            $actor = $ev->actor_user_id ? get_userdata( $ev->actor_user_id ) : false;
            if ( ! isset( $funnel_data[ $pid ] ) ) {
                $funnel_data[ $pid ] = array();
            }
            $funnel_data[ $pid ][ $ev->kind ] = array(
                'title'   => $ev->title,
                'note'    => $ev->note,
                'created' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ev->created_at ),
                'actor'   => $actor ? ( $actor->display_name ? $actor->display_name : $actor->user_login ) : '',
                'meta'    => $meta,
            );
        }
    }
}

// Payload consumed by the kanban detail modal.
$funnel_json = array();
foreach ( $partnerships as $p ) {
    $pid = (int) $p->id;
    if ( empty( $funnel_data[ $pid ] ) ) {
        continue;
    }
    $title = get_the_title( $p->property_id );
    $funnel_json[ $pid ] = array(
        'property_title' => $title ? $title : sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), (int) $p->property_id ),
        'visit'          => isset( $funnel_data[ $pid ]['visit'] ) ? $funnel_data[ $pid ]['visit'] : null,
        'proposal'       => isset( $funnel_data[ $pid ]['proposal'] ) ? $funnel_data[ $pid ]['proposal'] : null,
    );
}

$user_name = function ( $uid ) {
    $uid = absint( $uid );
    $u = $uid ? get_userdata( $uid ) : false;
    return $u ? ( $u->display_name ? $u->display_name : $u->user_login ) : sprintf( __( 'Usuário #%d', 'imovel-parceiro-core' ), $uid );
};

$now = current_time( 'timestamp' );
?>

<style>
    .ipc-admin-kanban { display:flex; gap:14px; overflow-x:auto; padding-bottom:10px; }
    .ipc-admin-col { flex:0 0 280px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:10px; }
    .ipc-admin-col__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .ipc-admin-col__title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#64748b; }
    .ipc-admin-col__count { background:#e2e8f0; color:#334155; border-radius:999px; padding:1px 9px; font-size:12px; font-weight:700; }
    .ipc-admin-card { background:#fff; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:10px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .ipc-admin-card__title { font-size:13px; font-weight:700; color:#0f172a; margin:0 0 4px; }
    .ipc-admin-card__meta { font-size:12px; color:#64748b; margin:0 0 8px; }
    .ipc-admin-chip { display:inline-flex; align-items:center; gap:4px; border-radius:999px; padding:2px 9px; font-size:11px; font-weight:700; }
    .ipc-admin-chip--danger { background:#fee2e2; color:#b91c1c; }
    .ipc-admin-chip--warn { background:#fef3c7; color:#92400e; }
    .ipc-admin-chip--ok { background:#dcfce7; color:#166534; }
    .ipc-admin-chip--info { background:#eef2ff; color:#4338ca; }
    .ipc-admin-chip--info svg { width:12px; height:12px; }
    .ipc-admin-card__actions { display:flex; align-items:center; gap:12px; margin-top:10px; flex-wrap:wrap; }
    .ipc-funnel-view { display:inline-flex; align-items:center; gap:5px; border:0; border-radius:9px; background:#eef2ff; color:#4338ca; padding:5px 10px; font-size:11.5px; font-weight:700; cursor:pointer; transition:background-color .15s ease; }
    .ipc-funnel-view svg { width:14px; height:14px; }
    .ipc-funnel-view:hover { background:#e0e7ff; }
    .ipc-kd-section + .ipc-kd-section { margin-top:20px; padding-top:18px; border-top:1px solid #f1f5f9; }
    .ipc-kd-section h6 { margin:0 0 8px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#64748b; }
    .ipc-kd-dl { margin:0; }
    .ipc-kd-dl > div { display:flex; align-items:baseline; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px solid #f1f5f9; }
    .ipc-kd-dl > div:last-child { border-bottom:0; }
    .ipc-kd-dl dt { font-size:12.5px; font-weight:600; color:#64748b; }
    .ipc-kd-dl dd { margin:0; font-size:13px; font-weight:600; color:#0f172a; text-align:right; }
    .ipc-kd-empty { margin:0; font-size:13px; color:#94a3b8; }
</style>

<div class="imovel-parceiro-admin-section rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
    <div class="imovel-parceiro-admin-section-head">
        <div>
            <h5 style="margin:0 0 4px;font-size:18px;font-weight:800;color:#0f172a;"><?php esc_html_e( 'Central de Parcerias', 'imovel-parceiro-core' ); ?></h5>
            <p style="margin:0;color:#64748b;font-size:13px;"><?php esc_html_e( 'Visibilidade total do funil, alertas antifraude e acordos entre corretores.', 'imovel-parceiro-core' ); ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 shadow-sm hover:border-slate-300 hover:text-slate-900" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ipc_run_scan', 1, $admin_url ), 'ipc_partnership_admin_action' ) ); ?>">
                <?php echo houzez_dash_icon( 'refresh-cw', 'h-3.5 w-3.5' ); ?>
                <?php esc_html_e( 'Rodar varredura', 'imovel-parceiro-core' ); ?>
            </a>
        </div>
    </div>

    <?php if ( 'alert_resolved' === $admin_msg ) : ?>
        <div class="mb-4 inline-flex w-full items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            <?php echo houzez_dash_icon( 'circle-check', 'h-4 w-4' ); ?><?php esc_html_e( 'Alerta resolvido.', 'imovel-parceiro-core' ); ?>
        </div>
    <?php elseif ( 'scan_done' === $admin_msg ) : ?>
        <div class="mb-4 inline-flex w-full items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700">
            <?php echo houzez_dash_icon( 'shield-check', 'h-4 w-4' ); ?><?php esc_html_e( 'Varredura concluída.', 'imovel-parceiro-core' ); ?>
        </div>
    <?php endif; ?>

    <!-- Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
            <p class="m-0 text-xs font-bold uppercase tracking-wide text-rose-500"><?php esc_html_e( 'Alertas abertos', 'imovel-parceiro-core' ); ?></p>
            <p class="m-0 mt-1 text-2xl font-extrabold text-rose-700"><?php echo esc_html( number_format_i18n( $open_alerts ) ); ?></p>
        </div>
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
            <p class="m-0 text-xs font-bold uppercase tracking-wide text-indigo-500"><?php esc_html_e( 'Parcerias ativas', 'imovel-parceiro-core' ); ?></p>
            <p class="m-0 mt-1 text-2xl font-extrabold text-indigo-700"><?php echo esc_html( number_format_i18n( $stats['active'] ) ); ?></p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
            <p class="m-0 text-xs font-bold uppercase tracking-wide text-emerald-500"><?php esc_html_e( 'Negócios ganhos', 'imovel-parceiro-core' ); ?></p>
            <p class="m-0 mt-1 text-2xl font-extrabold text-emerald-700"><?php echo esc_html( number_format_i18n( $stats['won'] ) ); ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500"><?php esc_html_e( 'Perdidos', 'imovel-parceiro-core' ); ?></p>
            <p class="m-0 mt-1 text-2xl font-extrabold text-slate-700"><?php echo esc_html( number_format_i18n( $stats['lost'] ) ); ?></p>
        </div>
    </div>

    <!-- Alertas -->
    <div class="mb-6">
        <h6 class="m-0 mb-3 text-sm font-extrabold uppercase tracking-wide text-slate-500"><?php esc_html_e( 'Alertas antifraude', 'imovel-parceiro-core' ); ?></h6>
        <?php if ( empty( $alerts ) ) : ?>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 text-sm font-medium text-emerald-700 inline-flex items-center gap-2">
                <?php echo houzez_dash_icon( 'shield-check', 'h-4 w-4' ); ?><?php esc_html_e( 'Nenhum alerta aberto.', 'imovel-parceiro-core' ); ?>
            </div>
        <?php else : ?>
            <div class="space-y-2">
                <?php foreach ( $alerts as $alert ) : ?>
                    <?php $is_critical = ( 'critical' === $alert->severity ); ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3 <?php echo $is_critical ? 'border-rose-100 bg-rose-50/60' : 'border-amber-100 bg-amber-50/60'; ?>">
                        <div class="min-w-0">
                            <p class="m-0 text-sm font-bold <?php echo $is_critical ? 'text-rose-700' : 'text-amber-700'; ?>">
                                <?php echo esc_html( $alert->title ); ?>
                                <?php if ( $alert->property_id ) : ?>
                                    <span class="text-slate-400 font-medium">· <?php echo esc_html( get_the_title( $alert->property_id ) ?: '#' . $alert->property_id ); ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="m-0 text-xs text-slate-500"><?php echo esc_html( $alert->message ); ?></p>
                        </div>
                        <a class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:border-slate-300" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'ipc_resolve_alert', (int) $alert->id, $admin_url ), 'ipc_partnership_admin_action' ) ); ?>">
                            <?php echo houzez_dash_icon( 'check', 'h-3.5 w-3.5' ); ?><?php esc_html_e( 'Resolver', 'imovel-parceiro-core' ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Kanban -->
    <h6 class="m-0 mb-3 text-sm font-extrabold uppercase tracking-wide text-slate-500"><?php esc_html_e( 'Funil de parcerias', 'imovel-parceiro-core' ); ?></h6>
    <div class="ipc-admin-kanban">
        <?php foreach ( $columns as $key => $col ) : ?>
            <div class="ipc-admin-col">
                <div class="ipc-admin-col__head">
                    <span class="ipc-admin-col__title"><?php echo esc_html( $col['label'] ); ?></span>
                    <span class="ipc-admin-col__count"><?php echo esc_html( number_format_i18n( count( $grouped[ $key ] ) ) ); ?></span>
                </div>
                <?php if ( empty( $grouped[ $key ] ) ) : ?>
                    <p class="m-0 px-1 py-4 text-center text-xs text-slate-400"><?php esc_html_e( 'Sem parcerias', 'imovel-parceiro-core' ); ?></p>
                <?php else : ?>
                    <?php foreach ( array_slice( $grouped[ $key ], 0, 20 ) as $p ) : ?>
                        <?php
                        $pid = (int) $p->id;
                        $requester_id = isset( $p->{$requester_col} ) ? (int) $p->{$requester_col} : 0;
                        $owner_id = isset( $p->{$owner_col} ) ? (int) $p->{$owner_col} : 0;
                        $suspended = $integrity && $integrity::is_contact_suspended( $p );
                        $sla_due = ! empty( $p->sla_due_at ) ? strtotime( $p->sla_due_at ) : 0;
                        $sla_late = $sla_due && $sla_due < $now && in_array( $key, $active_statuses, true );
                        $detail_url = add_query_arg( array( 'imovel-parceiro' => 'dashboard', 'imovel_parceiro_parceria' => $pid ), $dashboard_url );
                        ?>
                        <div class="ipc-admin-card">
                            <p class="ipc-admin-card__title"><?php echo esc_html( get_the_title( $p->property_id ) ?: sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $p->property_id ) ); ?></p>
                            <p class="ipc-admin-card__meta">
                                <?php echo esc_html( $user_name( $requester_id ) ); ?> → <?php echo esc_html( $user_name( $owner_id ) ); ?>
                            </p>
                            <?php
                            $fv = isset( $funnel_data[ $pid ]['visit'] ) ? $funnel_data[ $pid ]['visit'] : null;
                            $fp = isset( $funnel_data[ $pid ]['proposal'] ) ? $funnel_data[ $pid ]['proposal'] : null;
                            ?>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="ipc-admin-chip <?php echo $sla_late ? 'ipc-admin-chip--danger' : 'ipc-admin-chip--ok'; ?>">
                                    <?php echo $sla_late ? esc_html__( 'SLA vencido', 'imovel-parceiro-core' ) : esc_html__( 'Em dia', 'imovel-parceiro-core' ); ?>
                                </span>
                                <?php if ( $suspended ) : ?>
                                    <span class="ipc-admin-chip ipc-admin-chip--warn"><?php esc_html_e( 'Contato suspenso', 'imovel-parceiro-core' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $fv ) : ?>
                                    <span class="ipc-admin-chip ipc-admin-chip--info"><?php echo houzez_dash_icon( 'calendar-days', 'h-3 w-3' ); ?><?php esc_html_e( 'Visita', 'imovel-parceiro-core' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $fp ) : ?>
                                    <span class="ipc-admin-chip ipc-admin-chip--info"><?php echo houzez_dash_icon( 'file-text', 'h-3 w-3' ); ?><?php esc_html_e( 'Proposta', 'imovel-parceiro-core' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ipc-admin-card__actions">
                                <?php if ( $fv || $fp ) : ?>
                                    <button type="button" class="ipc-funnel-view" data-ipc-pid="<?php echo esc_attr( $pid ); ?>"><?php echo houzez_dash_icon( 'search', 'h-3.5 w-3.5' ); ?><?php esc_html_e( 'Ver dados', 'imovel-parceiro-core' ); ?></button>
                                <?php endif; ?>
                                <a class="inline-flex text-xs font-bold text-indigo-600 hover:text-indigo-500" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Abrir parceria', 'imovel-parceiro-core' ); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Acordos -->
    <h6 class="m-0 mb-3 mt-6 text-sm font-extrabold uppercase tracking-wide text-slate-500"><?php esc_html_e( 'Acordos registrados (entre corretores)', 'imovel-parceiro-core' ); ?></h6>
    <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="ipc-table w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/70 text-left">
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Anunciante', 'imovel-parceiro-core' ); ?></th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Parceiro', 'imovel-parceiro-core' ); ?></th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Divisão acordada', 'imovel-parceiro-core' ); ?></th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $deals = $wpdb->get_results( "SELECT * FROM {$deals_table} ORDER BY id DESC LIMIT 50" );
                if ( empty( $deals ) ) :
                    ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400"><?php esc_html_e( 'Nenhum acordo registrado ainda.', 'imovel-parceiro-core' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $deals as $deal ) : ?>
                        <?php
                        $split = '';
                        if ( $table_exists ) {
                            $split = $wpdb->get_var( $wpdb->prepare( "SELECT commission_split FROM {$partnership_table} WHERE id = %d", (int) $deal->partnership_id ) );
                        }
                        ?>
                        <tr>
                            <td class="px-4 py-3 text-slate-700"><?php echo esc_html( get_the_title( $deal->property_id ) ?: '#' . $deal->property_id ); ?></td>
                            <td class="px-4 py-3 text-slate-600"><?php echo esc_html( $user_name( $deal->owner_id ) ); ?></td>
                            <td class="px-4 py-3 text-slate-600"><?php echo esc_html( $user_name( $deal->partner_id ) ); ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-800"><?php echo esc_html( $split ? $split : '—' ); ?></td>
                            <td class="px-4 py-3 text-slate-500"><?php echo esc_html( ! empty( $deal->closed_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $deal->closed_at ) ) : '-' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Funnel detail modal (visit / proposal) -->
<div class="modal fade" id="ipc-kanban-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ipc-kanban-detail-title"><?php esc_html_e( 'Dados da visita e proposta', 'imovel-parceiro-core' ); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
            </div>
            <div class="modal-body" id="ipc-kanban-detail-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="ipc-kanban-detail-data"><?php echo wp_json_encode( (object) $funnel_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<script>
(function () {
    'use strict';

    var dataEl = document.getElementById('ipc-kanban-detail-data');
    var data = {};
    try { data = JSON.parse(dataEl ? dataEl.textContent : '{}'); } catch (e) { data = {}; }

    var LABELS = {
        client_name: '<?php echo esc_js( __( 'Cliente', 'imovel-parceiro-core' ) ); ?>',
        client_doc: '<?php echo esc_js( __( 'CPF/CNPJ', 'imovel-parceiro-core' ) ); ?>',
        client_phone: '<?php echo esc_js( __( 'Telefone', 'imovel-parceiro-core' ) ); ?>',
        property_title: '<?php echo esc_js( __( 'Imóvel', 'imovel-parceiro-core' ) ); ?>',
        property_address: '<?php echo esc_js( __( 'Endereço', 'imovel-parceiro-core' ) ); ?>',
        broker_name: '<?php echo esc_js( __( 'Corretor', 'imovel-parceiro-core' ) ); ?>',
        broker_creci: '<?php echo esc_js( __( 'CRECI', 'imovel-parceiro-core' ) ); ?>',
        broker_company: '<?php echo esc_js( __( 'Imobiliária', 'imovel-parceiro-core' ) ); ?>',
        visit_date: '<?php echo esc_js( __( 'Data da visita', 'imovel-parceiro-core' ) ); ?>',
        visit_time: '<?php echo esc_js( __( 'Horário', 'imovel-parceiro-core' ) ); ?>',
        participants: '<?php echo esc_js( __( 'Participantes', 'imovel-parceiro-core' ) ); ?>',
        result: '<?php echo esc_js( __( 'Resultado', 'imovel-parceiro-core' ) ); ?>',
        amount: '<?php echo esc_js( __( 'Valor da proposta', 'imovel-parceiro-core' ) ); ?>',
        proposal_date: '<?php echo esc_js( __( 'Data da proposta', 'imovel-parceiro-core' ) ); ?>',
        conditions: '<?php echo esc_js( __( 'Condições', 'imovel-parceiro-core' ) ); ?>',
        notes: '<?php echo esc_js( __( 'Observações', 'imovel-parceiro-core' ) ); ?>'
    };

    var RESULTS = {
        interessado: '<?php echo esc_js( __( 'Interessado', 'imovel-parceiro-core' ) ); ?>',
        muito_interessado: '<?php echo esc_js( __( 'Muito interessado', 'imovel-parceiro-core' ) ); ?>',
        sem_interesse: '<?php echo esc_js( __( 'Sem interesse', 'imovel-parceiro-core' ) ); ?>',
        avaliar: '<?php echo esc_js( __( 'A avaliar', 'imovel-parceiro-core' ) ); ?>',
        nova_visita: '<?php echo esc_js( __( 'Nova visita agendada', 'imovel-parceiro-core' ) ); ?>'
    };

    var META_ORDER = ['client_name', 'client_doc', 'client_phone', 'property_title', 'property_address', 'broker_name', 'broker_creci', 'broker_company', 'visit_date', 'visit_time', 'participants', 'result', 'amount', 'proposal_date', 'conditions', 'notes'];

    function esc(v) {
        var d = document.createElement('div');
        d.textContent = (v === null || v === undefined) ? '' : String(v);
        return d.innerHTML;
    }

    function buildSection(title, entry) {
        var html = '<div class="ipc-kd-section"><h6>' + esc(title) + '</h6>';
        var rows = [];

        if (entry) {
            if (entry.created) { rows.push(['<?php echo esc_js( __( 'Data/hora do registro', 'imovel-parceiro-core' ) ); ?>', entry.created]); }
            if (entry.actor) { rows.push(['<?php echo esc_js( __( 'Registrado por', 'imovel-parceiro-core' ) ); ?>', entry.actor]); }
            var meta = entry.meta || {};
            META_ORDER.forEach(function (k) {
                var val = meta[k];
                if (val === null || val === undefined || String(val).trim() === '') { return; }
                if (k === 'result' && RESULTS[val]) { val = RESULTS[val]; }
                rows.push([LABELS[k], val]);
            });
        }

        if (!rows.length) {
            return html + '<p class="ipc-kd-empty"><?php echo esc_js( __( 'Nenhum registro.', 'imovel-parceiro-core' ) ); ?></p></div>';
        }

        html += '<dl class="ipc-kd-dl">';
        rows.forEach(function (pair) {
            html += '<div><dt>' + esc(pair[0]) + '</dt><dd>' + esc(pair[1]) + '</dd></div>';
        });
        return html + '</dl></div>';
    }

    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest ? event.target.closest('.ipc-funnel-view') : null;
        if (!btn) { return; }
        event.preventDefault();

        var item = data[btn.getAttribute('data-ipc-pid')];
        if (!item) { return; }

        var modalEl = document.getElementById('ipc-kanban-detail-modal');
        if (!modalEl) { return; }

        document.getElementById('ipc-kanban-detail-title').textContent = item.property_title || '<?php echo esc_js( __( 'Dados da visita e proposta', 'imovel-parceiro-core' ) ); ?>';
        document.getElementById('ipc-kanban-detail-body').innerHTML =
            buildSection('<?php echo esc_js( __( 'Visita', 'imovel-parceiro-core' ) ); ?>', item.visit) +
            buildSection('<?php echo esc_js( __( 'Proposta', 'imovel-parceiro-core' ) ); ?>', item.proposal);

        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
})();
</script>

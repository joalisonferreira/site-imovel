<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'imovel_parceiro_status_label' ) ) {
    function imovel_parceiro_canonical_status( $status ) {
        $status = is_string( $status ) ? strtolower( trim( $status ) ) : '';
        $status = str_replace( array( ' ', '-', '_' ), '', $status );

        $aliases = array(
            'solicitada' => 'pending',
            'solicitado' => 'pending',
            'aceita' => 'accepted',
            'recusada' => 'rejected',
            'ativa' => 'active',
            'emnegociacao' => 'negotiating',
            'emnegociação' => 'negotiating',
            'contactreleased' => 'contact_released',
            'ganha' => 'won',
            'ganhou' => 'won',
            'perdida' => 'lost',
            'encerrada' => 'closed',
            'finalizada' => 'closed',
            'cancelada' => 'cancelled',
        );

        return isset( $aliases[ $status ] ) ? $aliases[ $status ] : $status;
    }
}

if ( ! function_exists( 'imovel_parceiro_status_label' ) ) {
    function imovel_parceiro_status_label( $status ) {
        $status = imovel_parceiro_canonical_status( $status );

        $labels = array(
            'pending' => __( 'Pendente', 'imovel-parceiro-core' ),
            'accepted' => __( 'Aceita', 'imovel-parceiro-core' ),
            'rejected' => __( 'Recusada', 'imovel-parceiro-core' ),
            'active' => __( 'Ativa', 'imovel-parceiro-core' ),
            'negotiating' => __( 'Negociação', 'imovel-parceiro-core' ),
            'contact_released' => __( 'Contato liberado', 'imovel-parceiro-core' ),
            'opportunity' => __( 'Oportunidade', 'imovel-parceiro-core' ),
            'visit' => __( 'Visita', 'imovel-parceiro-core' ),
            'proposal' => __( 'Proposta', 'imovel-parceiro-core' ),
            'won' => __( 'Negocio ganho', 'imovel-parceiro-core' ),
            'lost' => __( 'Negocio perdido', 'imovel-parceiro-core' ),
            'cancelled' => __( 'Cancelada', 'imovel-parceiro-core' ),
            'closed' => __( 'Encerrada', 'imovel-parceiro-core' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }
}

if ( ! function_exists( 'imovel_parceiro_status_badge_class' ) ) {
    function imovel_parceiro_status_badge_class( $status ) {
        $status = imovel_parceiro_canonical_status( $status );

        $won_statuses = array( 'won' );
        $cancelled_statuses = array( 'cancelled', 'rejected', 'lost', 'closed' );
        $closed_statuses = array( 'closed' );

        if ( in_array( $status, $won_statuses, true ) ) {
            return 'is-won';
        }

        if ( in_array( $status, $cancelled_statuses, true ) ) {
            return 'is-cancelled';
        }

        if ( in_array( $status, $closed_statuses, true ) ) {
            return 'is-closed';
        }

        return 'is-default';
    }
}

if ( ! isset( $_GET['imovel-parceiro'] ) || 'dashboard' !== sanitize_key( wp_unslash( $_GET['imovel-parceiro'] ) ) ) {
    return;
}

// Single partnership detail (funnel).
if ( isset( $_GET['imovel_parceiro_parceria'] ) ) {
    get_template_part( 'template-parts/dashboard/partnership-detail' );
    return;
}

global $wpdb;
$user_id = get_current_user_id();

if ( ! function_exists( 'imovel_parceiro_best_column' ) ) {
    function imovel_parceiro_best_column( $wpdb, $table, $primary, $fallback, $columns ) {
        $has_primary = in_array( $primary, $columns, true );
        $has_fallback = in_array( $fallback, $columns, true );

        if ( $has_primary && ! $has_fallback ) {
            return $primary;
        }

        if ( $has_fallback && ! $has_primary ) {
            return $fallback;
        }

        if ( ! $has_primary && ! $has_fallback ) {
            return $primary;
        }

        $primary_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$primary} > 0" );
        $fallback_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$fallback} > 0" );

        return $fallback_count > $primary_count ? $fallback : $primary;
    }
}

if ( ! function_exists( 'imovel_parceiro_requester_profile_data' ) ) {
    function imovel_parceiro_requester_profile_data( $user_id ) {
        $user = get_userdata( absint( $user_id ) );
        if ( ! $user ) {
            return array();
        }

        return array(
            'name' => ! empty( $user->display_name ) ? $user->display_name : sprintf( __( 'Usuário #%d', 'imovel-parceiro-core' ), absint( $user_id ) ),
            'email' => ! empty( $user->user_email ) ? $user->user_email : '',
            'avatar' => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
            'company' => get_user_meta( $user->ID, 'fave_author_company', true ),
            'license' => get_user_meta( $user->ID, 'fave_author_license', true ),
            'tax_no' => get_user_meta( $user->ID, 'fave_author_tax_no', true ),
            'phone' => get_user_meta( $user->ID, 'fave_author_phone', true ),
            'mobile' => get_user_meta( $user->ID, 'fave_author_mobile', true ),
            'whatsapp' => get_user_meta( $user->ID, 'fave_author_whatsapp', true ),
        );
    }
}

$partnerships_table = $wpdb->prefix . 'imovel_parceiro_partnerships';
$opportunities_table = $wpdb->prefix . 'imovel_parceiro_opportunities';
$deals_table = $wpdb->prefix . 'imovel_parceiro_deals';
$commissions_table = $wpdb->prefix . 'imovel_parceiro_commissions';

$partnership_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$partnerships_table}" );
$partnership_requester_col = imovel_parceiro_best_column( $wpdb, $partnerships_table, 'requester_id', 'captador_id', $partnership_columns );
$partnership_owner_col = imovel_parceiro_best_column( $wpdb, $partnerships_table, 'owner_id', 'partner_id', $partnership_columns );
$partnership_date_col = imovel_parceiro_best_column( $wpdb, $partnerships_table, 'requested_at', 'created_at', $partnership_columns );
$partnership_notes_col = in_array( 'notes', $partnership_columns, true ) ? 'notes' : '';
$partnership_has_commission_split = in_array( 'commission_split', $partnership_columns, true );

$opportunities_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$opportunities_table}" );
$opportunity_owner_col = in_array( 'owner_id', $opportunities_columns, true ) ? 'owner_id' : '';
$opportunity_partner_col = in_array( 'partner_id', $opportunities_columns, true ) ? 'partner_id' : ( in_array( 'requester_id', $opportunities_columns, true ) ? 'requester_id' : '' );

$partnership_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$partnerships_table} WHERE {$partnership_requester_col} = %d OR {$partnership_owner_col} = %d", $user_id, $user_id ) );
$opportunity_count = 0;
if ( ! empty( $opportunity_owner_col ) || ! empty( $opportunity_partner_col ) ) {
    if ( ! empty( $opportunity_owner_col ) && ! empty( $opportunity_partner_col ) ) {
        $opportunity_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$opportunities_table} WHERE {$opportunity_owner_col} = %d OR {$opportunity_partner_col} = %d", $user_id, $user_id ) );
    } elseif ( ! empty( $opportunity_owner_col ) ) {
        $opportunity_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$opportunities_table} WHERE {$opportunity_owner_col} = %d", $user_id ) );
    } else {
        $opportunity_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$opportunities_table} WHERE {$opportunity_partner_col} = %d", $user_id ) );
    }
}
$deal_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$deals_table} WHERE owner_id = %d OR partner_id = %d", $user_id, $user_id ) );
$commission_total = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$commissions_table} WHERE beneficiary_id = %d", $user_id ) );

$recent_partnerships = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$partnerships_table} WHERE {$partnership_requester_col} = %d OR {$partnership_owner_col} = %d ORDER BY id DESC LIMIT 8",
        $user_id,
        $user_id
    )
);

$incoming_requests = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$partnerships_table} WHERE {$partnership_owner_col} = %d AND {$partnership_requester_col} <> %d AND status IN ('pending','solicitada') ORDER BY id DESC LIMIT 20",
        $user_id,
        $user_id
    )
);

$outgoing_requests = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$partnerships_table} WHERE {$partnership_requester_col} = %d AND {$partnership_owner_col} <> %d ORDER BY id DESC LIMIT 20",
        $user_id,
        $user_id
    )
);

$active_partnerships = array();
$historical_partnerships = array();
$active_statuses = array( 'pending', 'accepted', 'active', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal' );

foreach ( $recent_partnerships as $recent_partnership ) {
    $normalized_status = imovel_parceiro_canonical_status( isset( $recent_partnership->status ) ? $recent_partnership->status : '' );
    if ( in_array( $normalized_status, $active_statuses, true ) ) {
        $active_partnerships[] = $recent_partnership;
    } else {
        $historical_partnerships[] = $recent_partnership;
    }
}

$dashboard_base_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : get_permalink();
$all_partnerships_url = add_query_arg(
    array(
        'imovel_dashboard_area' => 'parcerias',
    ),
    $dashboard_base_url
);

$partnership_detail_base = add_query_arg( 'imovel-parceiro', 'dashboard', $dashboard_base_url );
$partnership_detail_url = function( $id ) use ( $partnership_detail_base ) {
    return add_query_arg( 'imovel_parceiro_parceria', absint( $id ), $partnership_detail_base );
};

$allowed_dashboard_tabs = array( 'ativas', 'recebidas', 'enviadas', 'historico' );
$requested_dashboard_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ativas';
$active_dashboard_tab = in_array( $requested_dashboard_tab, $allowed_dashboard_tabs, true ) ? $requested_dashboard_tab : 'ativas';
?>



<div class="imovel-parceiro-custom-dashboard">
    <header class="dashboard-header-main-wrap rounded-2xl border border-slate-100 bg-white px-5 sm:px-6 py-5 shadow-sm mb-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-slate-400" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'imovel-parceiro-core' ); ?>">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5">
                        <?php esc_html_e( 'Painel', 'imovel-parceiro-core' ); ?>
                    </span>
                    <span aria-hidden="true">/</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                        <?php esc_html_e( 'Minhas Parcerias', 'imovel-parceiro-core' ); ?>
                    </span>
                </nav>
                <h2 class="flex items-center gap-3 text-xl font-bold tracking-tight text-slate-900" style="margin:0 0 6px;">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <?php echo houzez_dash_icon( 'handshake', 'h-5 w-5' ); ?>
                    </span>
                    <?php esc_html_e( 'Minhas Parcerias', 'imovel-parceiro-core' ); ?>
                </h2>
                <p class="m-0 text-sm text-slate-500">
                    <?php esc_html_e( 'Acompanhe parcerias, oportunidades, negócios e comissões em um só lugar.', 'imovel-parceiro-core' ); ?>
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                <?php echo houzez_dash_icon( 'circle-check', 'h-3.5 w-3.5' ); ?>
                <?php echo esc_html( sprintf( __( '%d parcerias ativas', 'imovel-parceiro-core' ), (int) count( $active_partnerships ) ) ); ?>
            </span>
        </div>
    </header>

    <section class="dashboard-content-wrap" style="margin:0;">
        <div class="dashboard-content-inner-wrap">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-5">
                <div class="ipc-fade-up rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <p class="m-0 text-sm font-semibold text-slate-500"><?php esc_html_e( 'Parcerias', 'imovel-parceiro-core' ); ?></p>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <?php echo houzez_dash_icon( 'handshake', 'h-5 w-5' ); ?>
                        </span>
                    </div>
                    <p class="mt-4 mb-0 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( $partnership_count ) ); ?></p>
                </div>
                <div class="ipc-fade-up-delay-1 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <p class="m-0 text-sm font-semibold text-slate-500"><?php esc_html_e( 'Oportunidades', 'imovel-parceiro-core' ); ?></p>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                            <?php echo houzez_dash_icon( 'sparkles', 'h-5 w-5' ); ?>
                        </span>
                    </div>
                    <p class="mt-4 mb-0 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( $opportunity_count ) ); ?></p>
                </div>
                <div class="ipc-fade-up-delay-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <p class="m-0 text-sm font-semibold text-slate-500"><?php esc_html_e( 'Negócios', 'imovel-parceiro-core' ); ?></p>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <?php echo houzez_dash_icon( 'key-round', 'h-5 w-5' ); ?>
                        </span>
                    </div>
                    <p class="mt-4 mb-0 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( $deal_count ) ); ?></p>
                </div>
            </div>

            <div class="dashboard-content-block mt-4">
                <div class="imovel-parceiro-tabs mb-6" role="tablist" aria-label="<?php esc_attr_e( 'Navegação de parcerias', 'imovel-parceiro-core' ); ?>">
                    <button type="button" class="imovel-parceiro-tab <?php echo 'ativas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-tab="ativas">
                        <?php esc_html_e( 'Ativas', 'imovel-parceiro-core' ); ?>
                        <span class="ipc-tabs-count"><?php echo esc_html( number_format_i18n( (int) count( $active_partnerships ) ) ); ?></span>
                    </button>
                    <button type="button" class="imovel-parceiro-tab <?php echo 'recebidas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-tab="recebidas">
                        <?php esc_html_e( 'Recebidas', 'imovel-parceiro-core' ); ?>
                        <span class="ipc-tabs-count"><?php echo esc_html( number_format_i18n( (int) count( $incoming_requests ) ) ); ?></span>
                    </button>
                    <button type="button" class="imovel-parceiro-tab <?php echo 'enviadas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-tab="enviadas">
                        <?php esc_html_e( 'Enviadas', 'imovel-parceiro-core' ); ?>
                        <span class="ipc-tabs-count"><?php echo esc_html( number_format_i18n( (int) count( $outgoing_requests ) ) ); ?></span>
                    </button>
                    <button type="button" class="imovel-parceiro-tab <?php echo 'historico' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-tab="historico">
                        <?php esc_html_e( 'Histórico', 'imovel-parceiro-core' ); ?>
                        <span class="ipc-tabs-count"><?php echo esc_html( number_format_i18n( (int) count( $historical_partnerships ) ) ); ?></span>
                    </button>
                </div>

                    <div class="imovel-parceiro-tab-panel <?php echo 'ativas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-panel="ativas">
                        <?php if ( ! empty( $active_partnerships ) ) : ?>
                            <div class="ipc-card-list">
                                <?php foreach ( $active_partnerships as $partnership ) : ?>
                                    <?php
                                    $property_title = get_the_title( $partnership->property_id );
                                    if ( empty( $property_title ) ) {
                                        $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $partnership->property_id ) );
                                    }
                                    $current_status = imovel_parceiro_canonical_status( isset( $partnership->status ) ? $partnership->status : '' );
                                    $status_label = imovel_parceiro_status_label( $partnership->status );
                                    $status_badge_class = imovel_parceiro_status_badge_class( $partnership->status );
                                    $commission_split = ( $partnership_has_commission_split && ! empty( $partnership->commission_split ) ) ? $partnership->commission_split : '50/50';
                                    $requested_at_value = ! empty( $partnership->{$partnership_date_col} ) ? $partnership->{$partnership_date_col} : '';
                                    $requested_at = ! empty( $requested_at_value ) ? date_i18n( get_option( 'date_format' ), strtotime( $requested_at_value ) ) : '-';
                                    $notes_value = ! empty( $partnership_notes_col ) && ! empty( $partnership->{$partnership_notes_col} ) ? $partnership->{$partnership_notes_col} : '';
                                    $notes = ! empty( $notes_value ) ? $notes_value : __( 'Sem mensagem.', 'imovel-parceiro-core' );
                                    $property_thumb_url = get_the_post_thumbnail_url( $partnership->property_id, 'thumbnail' );
                                    $is_owner = isset( $partnership->{$partnership_owner_col} ) && (int) $partnership->{$partnership_owner_col} === (int) $user_id;
                                    $is_requester = isset( $partnership->{$partnership_requester_col} ) && (int) $partnership->{$partnership_requester_col} === (int) $user_id;
                                    $counterparty_user_id = $is_requester ? ( isset( $partnership->{$partnership_owner_col} ) ? (int) $partnership->{$partnership_owner_col} : 0 ) : ( isset( $partnership->{$partnership_requester_col} ) ? (int) $partnership->{$partnership_requester_col} : 0 );
                                    $counterparty_user = $counterparty_user_id ? get_userdata( $counterparty_user_id ) : false;
                                    $counterparty_name = $counterparty_user && ! empty( $counterparty_user->display_name ) ? $counterparty_user->display_name : '';
                                    $counterparty_email = $counterparty_user && ! empty( $counterparty_user->user_email ) ? $counterparty_user->user_email : '';
                                    $counterparty_phone = $counterparty_user_id ? get_user_meta( $counterparty_user_id, 'fave_author_phone', true ) : '';
                                    $counterparty_mobile = $counterparty_user_id ? get_user_meta( $counterparty_user_id, 'fave_author_mobile', true ) : '';
                                    $counterparty_whatsapp = $counterparty_user_id ? get_user_meta( $counterparty_user_id, 'fave_author_whatsapp', true ) : '';
                                    ?>
                                    <div class="ipc-card">
                                        <div class="ipc-card__head">
                                            <span class="ipc-card__thumb" aria-hidden="true">
                                                <?php if ( ! empty( $property_thumb_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $property_thumb_url ); ?>" alt="<?php echo esc_attr( $property_title ); ?>" />
                                                <?php else : ?>
                                                    <?php echo houzez_dash_icon( 'building-2', 'h-5 w-5' ); ?>
                                                <?php endif; ?>
                                            </span>
                                            <div class="ipc-card__body">
                                                <p class="ipc-card__title"><?php echo esc_html( $property_title ); ?></p>
                                                <span class="imovel-parceiro-status-badge <?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                            </div>
                                        </div>
                                        <div class="ipc-card__meta">
                                            <span><?php esc_html_e( 'Participação:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $commission_split ); ?></span>
                                            <span><?php esc_html_e( 'Data:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $requested_at ); ?></span>
                                            <?php
                                            $ipc_suspended = class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) && Imovel_Parceiro_Partnership_Integrity::is_contact_suspended( $partnership );
                                            $ipc_sla_due = ! empty( $partnership->sla_due_at ) ? strtotime( $partnership->sla_due_at ) : 0;
                                            $ipc_sla_late = $ipc_sla_due && $ipc_sla_due < current_time( 'timestamp' );
                                            ?>
                                            <?php if ( $ipc_suspended ) : ?><span style="color:#b91c1c;font-weight:700;"><?php esc_html_e( 'Contato suspenso', 'imovel-parceiro-core' ); ?></span><?php endif; ?>
                                            <?php if ( $ipc_sla_late ) : ?><span style="color:#b91c1c;font-weight:700;"><?php esc_html_e( 'Prazo vencido', 'imovel-parceiro-core' ); ?></span><?php endif; ?>
                                        </div>
                                        <div class="ipc-card__msg">
                                            <strong><?php echo houzez_dash_icon( 'message-circle-question', 'h-3.5 w-3.5 inline-block me-1' ); ?><?php esc_html_e( 'Mensagem:', 'imovel-parceiro-core' ); ?></strong>
                                            <div><?php echo esc_html( $notes ); ?></div>
                                        </div>
                                        <div class="ipc-card__actions">
                                            <?php if ( 'pending' === $current_status && $is_owner ) : ?>
                                                <button type="button" class="btn btn-success btn-sm imovel-parceiro-action" data-action="handle" data-status="accepted" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                <button type="button" class="btn btn-danger btn-sm imovel-parceiro-action" data-action="handle" data-status="rejected" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( 'accepted' === $current_status && ( $is_owner || $is_requester ) ) : ?>
                                                <button type="button" class="btn btn-primary btn-sm imovel-parceiro-action" data-action="transition" data-next-status="active" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Ativar parceria', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( 'active' === $current_status && ( $is_owner || $is_requester ) ) : ?>
                                                <button type="button" class="btn btn-warning btn-sm imovel-parceiro-action" data-action="transition" data-next-status="negotiating" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Iniciar negociacao', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( 'negotiating' === $current_status && ( $is_owner || $is_requester ) ) : ?>
                                                <button type="button" class="btn btn-success btn-sm imovel-parceiro-action" data-action="outcome" data-outcome="won" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Marcar ganho', 'imovel-parceiro-core' ); ?></button>
                                                <button type="button" class="btn btn-outline-danger btn-sm imovel-parceiro-action" data-action="outcome" data-outcome="lost" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Marcar perdido', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( 'pending' === $current_status && $is_requester ) : ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm imovel-parceiro-action" data-action="cancel" data-status="pending" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Cancelar solicitação', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( in_array( $current_status, array( 'accepted', 'active', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal' ), true ) && ( $is_owner || $is_requester ) ) : ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm imovel-parceiro-action" data-action="close" data-status="<?php echo esc_attr( $current_status ); ?>" data-partnership-id="<?php echo esc_attr( $partnership->id ); ?>"><?php esc_html_e( 'Encerrar parceria', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <?php if ( ( $is_owner || $is_requester ) && $counterparty_user_id && in_array( $current_status, array( 'accepted', 'active', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal' ), true ) ) : ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm imovel-parceiro-view-contact" data-partnership-status="<?php echo esc_attr( $current_status ); ?>" data-owner-name="<?php echo esc_attr( $counterparty_name ); ?>" data-owner-email="<?php echo esc_attr( $counterparty_email ); ?>" data-owner-phone="<?php echo esc_attr( $counterparty_phone ); ?>"><?php esc_html_e( 'Ver contato', 'imovel-parceiro-core' ); ?></button>
                                            <?php endif; ?>

                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url( $partnership_detail_url( $partnership->id ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'imovel-parceiro-core' ); ?></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 text-end">
                                <a class="imovel-parceiro-view-all-link" href="<?php echo esc_url( $all_partnerships_url ); ?>"><?php esc_html_e( 'Ver todas as parcerias', 'imovel-parceiro-core' ); ?></a>
                            </div>
                        <?php else : ?>
                            <div class="ipc-empty">
                                <span class="ipc-empty__icon"><?php echo houzez_dash_icon( 'handshake', 'h-6 w-6' ); ?></span>
                                <p><?php esc_html_e( 'Nenhuma parceria ativa no momento.', 'imovel-parceiro-core' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="imovel-parceiro-tab-panel <?php echo 'recebidas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-panel="recebidas">
                        <?php if ( ! empty( $incoming_requests ) ) : ?>
                            <div class="ipc-card-list mb-2">
                                <?php foreach ( $incoming_requests as $request ) : ?>
                                    <?php
                                    $incoming_title = get_the_title( $request->property_id );
                                    if ( empty( $incoming_title ) ) {
                                        $incoming_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $request->property_id ) );
                                    }
                                    $incoming_requester_id = isset( $request->{$partnership_requester_col} ) ? (int) $request->{$partnership_requester_col} : 0;
                                    $incoming_requester = $incoming_requester_id ? get_userdata( $incoming_requester_id ) : false;
                                    $incoming_requester_profile = $incoming_requester_id ? imovel_parceiro_requester_profile_data( $incoming_requester_id ) : array();
                                    $incoming_name = ! empty( $incoming_requester_profile['name'] ) ? $incoming_requester_profile['name'] : ( $incoming_requester && ! empty( $incoming_requester->display_name ) ? $incoming_requester->display_name : sprintf( __( 'Usuário #%d', 'imovel-parceiro-core' ), $incoming_requester_id ) );
                                    $incoming_notes = ! empty( $partnership_notes_col ) && ! empty( $request->{$partnership_notes_col} ) ? $request->{$partnership_notes_col} : __( 'Sem mensagem.', 'imovel-parceiro-core' );
                                    $incoming_requested_at_value = ! empty( $request->{$partnership_date_col} ) ? $request->{$partnership_date_col} : '';
                                    $incoming_requested_at = ! empty( $incoming_requested_at_value ) ? date_i18n( get_option( 'date_format' ), strtotime( $incoming_requested_at_value ) ) : '-';
                                    ?>
                                    <div class="ipc-card">
                                        <div class="ipc-card__head">
                                            <span class="ipc-card__thumb" aria-hidden="true">
                                                <img src="<?php echo esc_url( ! empty( $incoming_requester_profile['avatar'] ) ? $incoming_requester_profile['avatar'] : get_avatar_url( $incoming_requester_id, array( 'size' => 96 ) ) ); ?>" alt="<?php echo esc_attr( $incoming_name ); ?>" style="border-radius:50%;" />
                                            </span>
                                            <div class="ipc-card__body">
                                                <p class="ipc-card__title"><?php echo esc_html( $incoming_name ); ?></p>
                                                <?php if ( ! empty( $incoming_requester_profile['company'] ) ) : ?>
                                                    <div class="ipc-card__meta"><span><?php echo esc_html( $incoming_requester_profile['company'] ); ?></span></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $incoming_requester_profile['license'] ) ) : ?>
                                                    <div class="ipc-card__meta"><span><?php esc_html_e( 'CRECI:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $incoming_requester_profile['license'] ); ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ipc-card__meta">
                                            <span><?php esc_html_e( 'Imóvel:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $incoming_title ); ?></span>
                                            <span><?php esc_html_e( 'Data:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $incoming_requested_at ); ?></span>
                                        </div>
                                        <div class="ipc-card__msg">
                                            <strong><?php echo houzez_dash_icon( 'message-circle-question', 'h-3.5 w-3.5 inline-block me-1' ); ?><?php esc_html_e( 'Mensagem:', 'imovel-parceiro-core' ); ?></strong>
                                            <div><?php echo esc_html( $incoming_notes ); ?></div>
                                        </div>
                                        <div class="ipc-card__actions">
                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url( $partnership_detail_url( $request->id ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'imovel-parceiro-core' ); ?></a>
                                            <button type="button" class="btn btn-success btn-sm imovel-parceiro-action" data-action="handle" data-status="accepted" data-partnership-id="<?php echo esc_attr( $request->id ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                            <button type="button" class="btn btn-danger btn-sm imovel-parceiro-action" data-action="handle" data-status="rejected" data-partnership-id="<?php echo esc_attr( $request->id ); ?>"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="ipc-empty">
                                <span class="ipc-empty__icon"><?php echo houzez_dash_icon( 'inbox', 'h-6 w-6' ); ?></span>
                                <p><?php esc_html_e( 'Nenhuma solicitação pendente no momento.', 'imovel-parceiro-core' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="imovel-parceiro-tab-panel <?php echo 'enviadas' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-panel="enviadas">
                        <?php if ( ! empty( $outgoing_requests ) ) : ?>
                            <div class="ipc-card-list">
                                <?php foreach ( $outgoing_requests as $request ) : ?>
                                    <?php
                                    $outgoing_title = get_the_title( $request->property_id );
                                    if ( empty( $outgoing_title ) ) {
                                        $outgoing_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $request->property_id ) );
                                    }
                                    $outgoing_status_label = imovel_parceiro_status_label( $request->status );
                                    $outgoing_status_badge_class = imovel_parceiro_status_badge_class( $request->status );
                                    $outgoing_notes = ! empty( $partnership_notes_col ) && ! empty( $request->{$partnership_notes_col} ) ? $request->{$partnership_notes_col} : __( 'Sem mensagem.', 'imovel-parceiro-core' );
                                    $outgoing_requested_at_value = ! empty( $request->{$partnership_date_col} ) ? $request->{$partnership_date_col} : '';
                                    $outgoing_requested_at = ! empty( $outgoing_requested_at_value ) ? date_i18n( get_option( 'date_format' ), strtotime( $outgoing_requested_at_value ) ) : '-';
                                    $outgoing_thumb_url = get_the_post_thumbnail_url( $request->property_id, 'thumbnail' );
                                    ?>
                                    <div class="ipc-card">
                                        <div class="ipc-card__head">
                                            <span class="ipc-card__thumb" aria-hidden="true">
                                                <?php if ( ! empty( $outgoing_thumb_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $outgoing_thumb_url ); ?>" alt="<?php echo esc_attr( $outgoing_title ); ?>" />
                                                <?php else : ?>
                                                    <?php echo houzez_dash_icon( 'building-2', 'h-5 w-5' ); ?>
                                                <?php endif; ?>
                                            </span>
                                            <div class="ipc-card__body">
                                                <p class="ipc-card__title"><?php echo esc_html( $outgoing_title ); ?></p>
                                                <span class="imovel-parceiro-status-badge <?php echo esc_attr( $outgoing_status_badge_class ); ?>"><?php echo esc_html( $outgoing_status_label ); ?></span>
                                            </div>
                                        </div>
                                        <div class="ipc-card__meta">
                                            <span><?php esc_html_e( 'Data:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $outgoing_requested_at ); ?></span>
                                        </div>
                                        <div class="ipc-card__msg">
                                            <strong><?php echo houzez_dash_icon( 'message-circle-question', 'h-3.5 w-3.5 inline-block me-1' ); ?><?php esc_html_e( 'Mensagem:', 'imovel-parceiro-core' ); ?></strong>
                                            <div><?php echo esc_html( $outgoing_notes ); ?></div>
                                        </div>
                                        <div class="ipc-card__actions">
                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url( $partnership_detail_url( $request->id ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'imovel-parceiro-core' ); ?></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="ipc-empty">
                                <span class="ipc-empty__icon"><?php echo houzez_dash_icon( 'send', 'h-6 w-6' ); ?></span>
                                <p><?php esc_html_e( 'Nenhuma solicitação enviada ainda.', 'imovel-parceiro-core' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="imovel-parceiro-tab-panel <?php echo 'historico' === $active_dashboard_tab ? 'is-active' : ''; ?>" data-panel="historico">
                        <?php if ( ! empty( $historical_partnerships ) ) : ?>
                            <div class="ipc-card-list">
                                <?php foreach ( $historical_partnerships as $partnership ) : ?>
                                    <?php
                                    $history_title = get_the_title( $partnership->property_id );
                                    if ( empty( $history_title ) ) {
                                        $history_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $partnership->property_id ) );
                                    }
                                    $history_status_label = imovel_parceiro_status_label( $partnership->status );
                                    $history_status_badge_class = imovel_parceiro_status_badge_class( $partnership->status );
                                    $history_split = ( $partnership_has_commission_split && ! empty( $partnership->commission_split ) ) ? $partnership->commission_split : '50/50';
                                    $history_date_value = ! empty( $partnership->{$partnership_date_col} ) ? $partnership->{$partnership_date_col} : '';
                                    $history_date = ! empty( $history_date_value ) ? date_i18n( get_option( 'date_format' ), strtotime( $history_date_value ) ) : '-';
                                    $history_thumb_url = get_the_post_thumbnail_url( $partnership->property_id, 'thumbnail' );
                                    ?>
                                    <div class="ipc-card">
                                        <div class="ipc-card__head">
                                            <span class="ipc-card__thumb" aria-hidden="true">
                                                <?php if ( ! empty( $history_thumb_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $history_thumb_url ); ?>" alt="<?php echo esc_attr( $history_title ); ?>" />
                                                <?php else : ?>
                                                    <?php echo houzez_dash_icon( 'building-2', 'h-5 w-5' ); ?>
                                                <?php endif; ?>
                                            </span>
                                            <div class="ipc-card__body">
                                                <p class="ipc-card__title"><?php echo esc_html( $history_title ); ?></p>
                                                <span class="imovel-parceiro-status-badge <?php echo esc_attr( $history_status_badge_class ); ?>"><?php echo esc_html( $history_status_label ); ?></span>
                                            </div>
                                        </div>
                                        <div class="ipc-card__meta">
                                            <span><?php esc_html_e( 'Participação:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $history_split ); ?></span>
                                            <span><?php esc_html_e( 'Data:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $history_date ); ?></span>
                                        </div>
                                        <div class="ipc-card__actions">
                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url( $partnership_detail_url( $partnership->id ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'imovel-parceiro-core' ); ?></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="ipc-empty">
                                <span class="ipc-empty__icon"><?php echo houzez_dash_icon( 'calendar-clock', 'h-6 w-6' ); ?></span>
                                <p><?php esc_html_e( 'Nenhum registro no histórico.', 'imovel-parceiro-core' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            $ipc_reason_opts = array( 'won' => array(), 'lost' => array(), 'closed' => array() );
            if ( class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ) {
                $ipc_reason_opts = array(
                    'won'    => Imovel_Parceiro_Partnership_Workflow::outcome_reasons( 'won' ),
                    'lost'   => Imovel_Parceiro_Partnership_Workflow::outcome_reasons( 'lost' ),
                    'closed' => Imovel_Parceiro_Partnership_Workflow::outcome_reasons( 'closed' ),
                );
            }
            ?>
            <div id="imovel-parceiro-outcome-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imovel-parceiro-outcome-title"><?php esc_html_e( 'Registrar resultado', 'imovel-parceiro-core' ); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
                        </div>
                        <div class="modal-body">
                            <form id="imovel-parceiro-outcome-form">
                                <input type="hidden" name="partnership_id" value="" />
                                <input type="hidden" name="status" value="" />
                                <input type="hidden" name="mode" value="" />
                                <div class="form-group" style="margin-bottom:12px;">
                                    <label for="imovel-parceiro-outcome-reason" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Motivo', 'imovel-parceiro-core' ); ?> *</label>
                                    <select id="imovel-parceiro-outcome-reason" name="reason" class="form-control" required>
                                        <option value=""><?php esc_html_e( 'Selecione...', 'imovel-parceiro-core' ); ?></option>
                                    </select>
                                </div>
                                <div class="form-group" id="imovel-parceiro-outcome-other-wrap" style="display:none; margin-bottom:12px;">
                                    <label for="imovel-parceiro-outcome-other" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Descreva o motivo', 'imovel-parceiro-core' ); ?> *</label>
                                    <textarea id="imovel-parceiro-outcome-other" name="reason_other" rows="3" class="form-control"></textarea>
                                </div>
                                <div id="imovel-parceiro-outcome-won-fields" style="display:none;">
                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Valor final da venda', 'imovel-parceiro-core' ); ?></label>
                                        <div class="imovel-parceiro-money-wrap"><span class="imovel-parceiro-money-prefix">R$</span><input type="text" inputmode="decimal" class="form-control" id="imovel-parceiro-outcome-final-value" name="final_value" autocomplete="off" /></div>
                                    </div>
                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Data da venda', 'imovel-parceiro-core' ); ?></label>
                                        <input type="date" class="form-control" id="imovel-parceiro-outcome-sale-date" name="sale_date" />
                                    </div>
                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Cliente', 'imovel-parceiro-core' ); ?></label>
                                        <input type="text" class="form-control" id="imovel-parceiro-outcome-client" name="client_name" />
                                    </div>
                                    <div class="form-group" style="margin-bottom:12px;">
                                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Observações', 'imovel-parceiro-core' ); ?></label>
                                        <textarea class="form-control" id="imovel-parceiro-outcome-notes" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Voltar', 'imovel-parceiro-core' ); ?></button>
                                    <button type="submit" class="btn btn-danger"><?php esc_html_e( 'Confirmar', 'imovel-parceiro-core' ); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script>window.imovelParceiroReasons = <?php echo wp_json_encode( $ipc_reason_opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;</script>

            <div id="imovel-parceiro-partnership-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="imovel-parceiro-partnership-modal-label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imovel-parceiro-partnership-modal-label"><?php esc_html_e( 'Dados de contato do proprietário', 'imovel-parceiro-core' ); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
                        </div>
                        <div class="modal-body imovel-parceiro-modal-body">
                            <div class="imovel-parceiro-contact-box" style="padding:14px; border:1px solid #e6e6e6; border-radius:10px; background:#f8fafc; margin-bottom:16px;">
                                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Proprietário:', 'imovel-parceiro-core' ); ?></strong> <span class="imovel-parceiro-owner-name">-</span></p>
                                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'E-mail:', 'imovel-parceiro-core' ); ?></strong> <a class="imovel-parceiro-owner-email" href="#">-</a></p>
                                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Telefone:', 'imovel-parceiro-core' ); ?></strong> <a class="imovel-parceiro-owner-phone" href="#">-</a></p>
                                <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Celular:', 'imovel-parceiro-core' ); ?></strong> <a class="imovel-parceiro-owner-mobile" href="#">-</a></p>
                                <p style="margin:0;"><strong><?php esc_html_e( 'WhatsApp:', 'imovel-parceiro-core' ); ?></strong> <a class="imovel-parceiro-owner-whatsapp" href="#" target="_blank">-</a></p>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
jQuery(function($){
    function activatePartnershipTab(tabKey) {
        var $tabs = $('.imovel-parceiro-tab');
        var $panels = $('.imovel-parceiro-tab-panel');

        $tabs.removeClass('is-active');
        $panels.removeClass('is-active');

        $('.imovel-parceiro-tab[data-tab="' + tabKey + '"]').addClass('is-active');
        $('.imovel-parceiro-tab-panel[data-panel="' + tabKey + '"]').addClass('is-active');
    }

    $(document).on('click', '.imovel-parceiro-tab', function(e){
        e.preventDefault();
        var tabKey = $(this).data('tab') || 'ativas';
        activatePartnershipTab(tabKey);

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState({}, '', url.toString());
        }
    });

    function openOutcomeModal(mode, partnershipId, status) {
        var modal = document.getElementById('imovel-parceiro-outcome-modal');
        if (!modal) {
            return;
        }

        var titles = {
            won: '<?php echo esc_js( __( 'Registrar negócio ganho', 'imovel-parceiro-core' ) ); ?>',
            lost: '<?php echo esc_js( __( 'Registrar negócio perdido', 'imovel-parceiro-core' ) ); ?>',
            closed: '<?php echo esc_js( __( 'Encerrar parceria', 'imovel-parceiro-core' ) ); ?>'
        };
        var reasons = (window.imovelParceiroReasons && window.imovelParceiroReasons[mode]) || {};

        var form = modal.querySelector('#imovel-parceiro-outcome-form');
        if (form) {
            form.querySelector('[name="partnership_id"]').value = partnershipId || '';
            form.querySelector('[name="status"]').value = status || '';
            form.querySelector('[name="mode"]').value = mode || '';
            form.querySelector('[name="reason_other"]').value = '';
        }

        var titleEl = modal.querySelector('#imovel-parceiro-outcome-title');
        if (titleEl) {
            titleEl.textContent = titles[mode] || '';
        }

        var $sel = $(modal).find('#imovel-parceiro-outcome-reason');
        $sel.empty().append($('<option>').val('').text('<?php echo esc_js( __( 'Selecione...', 'imovel-parceiro-core' ) ); ?>'));
        $.each(reasons, function(val, label){
            $sel.append($('<option>').val(val).text(label));
        });

        $(modal).find('#imovel-parceiro-outcome-other-wrap').hide();
        $(modal).find('#imovel-parceiro-outcome-won-fields').toggle(mode === 'won');
        $(modal).find('#imovel-parceiro-outcome-final-value, #imovel-parceiro-outcome-sale-date, #imovel-parceiro-outcome-client, #imovel-parceiro-outcome-notes').val('');

        modal.style.display = 'block';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.zIndex = '1055';

        if (!document.querySelector('.modal-backdrop[data-imovel-parceiro-cancel-backdrop="1"]')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-imovel-parceiro-cancel-backdrop', '1');
            document.body.appendChild(backdrop);
        }

        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }

    function hideOutcomeModal() {
        var modal = document.getElementById('imovel-parceiro-outcome-modal');
        if (!modal) {
            return;
        }

        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.style.position = '';
        modal.style.inset = '';
        modal.style.zIndex = '';

        var backdrop = document.querySelector('.modal-backdrop[data-imovel-parceiro-cancel-backdrop="1"]');
        if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }

        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    $(document).on('click', '.imovel-parceiro-action', function(e){
        e.preventDefault();

        if (typeof imovelParceiroCore === 'undefined') {
            return;
        }

        var $btn = $(this);
        var partnershipId = parseInt($btn.data('partnership-id'), 10);
        var actionType = $btn.data('action');
        var status = $btn.data('status') || '';
        var nextStatus = $btn.data('next-status') || '';

        if (!partnershipId) {
            return;
        }

        if (actionType === 'outcome') {
            openOutcomeModal($btn.data('outcome') || '', partnershipId, status);
            return;
        }

        if (actionType === 'close') {
            openOutcomeModal('closed', partnershipId, status);
            return;
        }

        var action = 'imovel_parceiro_cancel_partnership';
        if (actionType === 'handle') {
            action = 'imovel_parceiro_handle_partnership';
        } else if (actionType === 'transition') {
            action = 'imovel_parceiro_transition_partnership';
        }
        var payload = {
            action: action,
            nonce: imovelParceiroCore.nonce,
            partnership_id: partnershipId
        };

        if (actionType === 'handle') {
            payload.status = status;
        }

        if (actionType === 'transition') {
            payload.next_status = nextStatus;
        }

        $btn.prop('disabled', true);

        $.post(imovelParceiroCore.ajax_url, payload, function(response){
            if (response && response.success) {
                window.location.reload();
                return;
            }

            var msg = (response && response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Não foi possível processar a ação.', 'imovel-parceiro-core' ) ); ?>';
            alert(msg);
        }).fail(function(){
            alert('<?php echo esc_js( __( 'Erro de comunicação com o servidor.', 'imovel-parceiro-core' ) ); ?>');
        }).always(function(){
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#imovel-parceiro-outcome-modal [data-bs-dismiss="modal"]', function(e){
        e.preventDefault();
        hideOutcomeModal();
    });

    $(document).on('change', '#imovel-parceiro-outcome-reason', function(){
        var $m = $(this).closest('#imovel-parceiro-outcome-modal');
        $m.find('#imovel-parceiro-outcome-other-wrap').toggle($(this).val() === 'outro');
    });

    $(document).on('submit', '#imovel-parceiro-outcome-form', function(e){
        e.preventDefault();

        if (typeof imovelParceiroCore === 'undefined') {
            return;
        }

        var $form = $(this);
        var mode = $form.find('[name="mode"]').val();
        var partnershipId = $form.find('[name="partnership_id"]').val();
        var status = $form.find('[name="status"]').val();
        var reason = $form.find('[name="reason"]').val();
        var other = ($form.find('[name="reason_other"]').val() || '').trim();

        if (!reason) {
            alert('<?php echo esc_js( __( 'Selecione um motivo para continuar.', 'imovel-parceiro-core' ) ); ?>');
            return;
        }

        if (reason === 'outro' && !other) {
            alert('<?php echo esc_js( __( 'Descreva o motivo para continuar.', 'imovel-parceiro-core' ) ); ?>');
            return;
        }

        var $button = $form.find('button[type="submit"]');
        if ($button.data('busy')) {
            return;
        }

        $button.data('busy', true).prop('disabled', true);

        var payload = {
            nonce: imovelParceiroCore.nonce,
            partnership_id: partnershipId
        };

        if (mode === 'closed') {
            payload.action = 'imovel_parceiro_cancel_partnership';
            payload.status = status;
            payload.reason = (reason === 'outro') ? other : reason;
        } else {
            payload.action = 'imovel_parceiro_funnel_transition';
            payload.to_status = mode;
            payload.payload = { reason: reason };
            if (reason === 'outro') {
                payload.payload.reason_detail = other;
            }
            if (mode === 'won') {
                payload.payload.final_value = $form.find('[name="final_value"]').val();
                payload.payload.sale_date = $form.find('[name="sale_date"]').val();
                payload.payload.client_name = $form.find('[name="client_name"]').val();
                payload.payload.notes = $form.find('[name="notes"]').val();
            }
        }

        $.post(imovelParceiroCore.ajax_url, payload, function(response){
            if (response && response.success) {
                window.location.reload();
                return;
            }

            var msg = (response && response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Não foi possível processar a ação.', 'imovel-parceiro-core' ) ); ?>';
            alert(msg);
        }).fail(function(){
            alert('<?php echo esc_js( __( 'Erro de comunicação com o servidor.', 'imovel-parceiro-core' ) ); ?>');
        }).always(function(){
            $button.data('busy', false).prop('disabled', false);
        });
    });
});
</script>

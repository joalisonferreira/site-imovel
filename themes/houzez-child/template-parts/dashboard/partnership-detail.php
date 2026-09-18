<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single partnership detail view (funnel).
 * Loaded from partnerships.php when ?imovel_parceiro_parceria=ID is present.
 */

if ( ! class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ) {
    echo '<div class="alert alert-danger">' . esc_html__( 'Módulo de parcerias indisponível.', 'imovel-parceiro-core' ) . '</div>';
    return;
}

$wf = 'Imovel_Parceiro_Partnership_Workflow';

$partnership_id = isset( $_GET['imovel_parceiro_parceria'] ) ? absint( $_GET['imovel_parceiro_parceria'] ) : 0;
$user_id = get_current_user_id();
$row = $wf::get_partnership( $partnership_id );

if ( ! $row ) {
    echo '<div class="alert alert-warning">' . esc_html__( 'Parceria não encontrada.', 'imovel-parceiro-core' ) . '</div>';
    return;
}

if ( ! $wf::can_access( $row, $user_id ) ) {
    echo '<div class="alert alert-danger">' . esc_html__( 'Você não tem permissão para acessar esta parceria.', 'imovel-parceiro-core' ) . '</div>';
    return;
}

$status           = $row->canonical;
$status_label     = $wf::status_label( $status );
$badge_class      = $wf::badge_class( $status );
$role             = $wf::user_role( $row, $user_id );
$contact_released = $wf::is_contact_released( $row );
$next_action      = $wf::next_action( $status );
$allowed_actions  = $wf::allowed_actions( $row, $user_id );
$timeline         = $wf::timeline_items( $row );
$events           = $wf::get_events( $partnership_id );
$opportunity      = $wf::get_opportunity( $partnership_id );
$split            = isset( $row->commission_split ) ? $row->commission_split : '50/50';

$property_id  = absint( $row->property_id );
$property_lk  = get_permalink( $property_id );
$property_title = get_the_title( $property_id );
if ( '' === $property_title ) {
    $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id );
}
$property_thumb = get_the_post_thumbnail_url( $property_id, 'thumbnail' );

$requester_id = isset( $row->{ $wf::requester_col() } ) ? (int) $row->{ $wf::requester_col() } : 0;
$owner_id     = isset( $row->{ $wf::owner_col() } ) ? (int) $row->{ $wf::owner_col() } : 0;
$requester    = $wf::contact_for_user( $requester_id );
$owner        = $wf::contact_for_user( $owner_id );
$requester_name = $requester && ! empty( $requester['name'] ) ? $requester['name'] : ( $requester_id ? '#' . $requester_id : '-' );
$owner_name     = $owner && ! empty( $owner['name'] ) ? $owner['name'] : ( $owner_id ? '#' . $owner_id : '-' );

// Partition events: interactions (typed records) vs timeline (status/actions).
$interactions = array();
$history      = array();
foreach ( $events as $ev ) {
    if ( in_array( $ev->kind, array( 'interaction', 'visit', 'proposal', 'opportunity' ), true ) ) {
        $interactions[] = $ev;
    } else {
        $history[] = $ev;
    }
}

$dashboard_back = add_query_arg( array( 'imovel-parceiro' => 'dashboard' ), home_url( '/' ) );
if ( function_exists( 'houzez_get_template_link_2' ) ) {
    $dashboard_back = add_query_arg( 'imovel-parceiro', 'dashboard', houzez_get_template_link_2( 'template/user_dashboard.php' ) );
}

// Build per-stage latest event data for the clickable progress modals (sections 35-41).
$ipc_steps = array();
$ipc_key_for_event = function( $ev ) {
    $kind = isset( $ev->kind ) ? $ev->kind : '';
    $type = isset( $ev->type ) ? $ev->type : '';
    if ( 'opportunity' === $kind ) { return 'opportunity'; }
    if ( 'visit' === $kind ) { return 'visit'; }
    if ( 'proposal' === $kind ) { return 'proposal'; }
    if ( 'status' === $kind && in_array( $type, array( 'won', 'lost' ), true ) ) { return $type; }
    if ( 'action' === $kind && in_array( $type, array( 'closed', 'cancelled', 'finalizada' ), true ) ) { return 'closed'; }
    if ( 'action' === $kind && 'accepted' === $type ) { return 'accepted'; }
    if ( 'action' === $kind && 'contact_released' === $type ) { return 'contact_released'; }
    return '';
};
foreach ( $events as $ev ) {
    $k = $ipc_key_for_event( $ev );
    if ( '' === $k ) {
        continue;
    }
    $actor = $ev->actor_user_id ? get_userdata( $ev->actor_user_id ) : false;
    $ipc_steps[ $k ] = array(
        'title'   => $ev->title,
        'note'    => $ev->note,
        'meta'    => isset( $ev->meta ) ? $ev->meta : array(),
        'actor'   => $actor ? $actor->display_name : '',
        'created' => $ev->created,
    );
}

$ipc_detail = array(
    'partnership_id' => $partnership_id,
    'property_title' => $property_title,
    'owner_name'     => $owner_name,
    'requester_name' => $requester_name,
    'dashboard_url'  => $dashboard_back,
    'steps'          => $ipc_steps,
);

$ipc_reason_options = array(
    'won'    => $wf::outcome_reasons( 'won' ),
    'lost'   => $wf::outcome_reasons( 'lost' ),
    'closed' => $wf::outcome_reasons( 'closed' ),
);

$visit_term_defaults = $wf::get_visit_term_defaults( $partnership_id, $user_id );
if ( ! is_array( $visit_term_defaults ) ) {
    $visit_term_defaults = array(
        'property_title'   => $property_title,
        'property_ref'     => $property_id,
        'property_address' => '',
        'broker_name'      => '',
        'broker_creci'     => '',
        'broker_company'   => '',
        'broker_phone'     => '',
    );
}

$my_role_label = 'owner' === $role ? __( 'Corretor dono', 'imovel-parceiro-core' ) : ( 'requester' === $role ? __( 'Corretor parceiro', 'imovel-parceiro-core' ) : __( 'Admin', 'imovel-parceiro-core' ) );
$next_title    = '';
$next_btn      = array();

// Map the single next action to a primary button.
foreach ( $allowed_actions as $a ) {
    if ( in_array( $a['kind'], array( 'accept', 'negotiate', 'release_contact', 'opportunity', 'proposal' ), true ) ) {
        $next_title = $a['label'];
        $next_btn   = $a;
        break;
    }
    if ( in_array( $a['kind'], array( 'reject', 'close' ), true ) ) {
        // Keep as fallback secondary.
    }
}
if ( empty( $next_btn ) && ! empty( $allowed_actions ) ) {
    $next_btn = $allowed_actions[0];
    $next_title = $next_btn['label'];
}

// Load contact button state.
$contact_loaded = false;
$contact_data = null;
if ( $contact_released ) {
    $contact_data = $wf::get_partner_contact( $partnership_id, $user_id );
    $contact_loaded = is_array( $contact_data );
}
?>
<style>
    .imovel-parceiro-money-wrap{position:relative}
    .imovel-parceiro-money-prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:#6b7280;pointer-events:none;z-index:2}
    .imovel-parceiro-money-wrap .form-control{padding-left:40px}
    .ipc-visit-fieldset{border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px 6px;margin:0 0 14px}
    .ipc-visit-fieldset legend{float:none;width:auto;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;padding:0 6px;margin:0 0 4px}
    #ipc-funnel-term-visit-body .ipc-term-head h2{font-size:20px;font-weight:800;text-align:center;margin:0 0 16px;letter-spacing:.06em}
    #ipc-funnel-term-visit-body .ipc-term-table{width:100%;border-collapse:collapse;margin-bottom:18px}
    #ipc-funnel-term-visit-body .ipc-term-table th{width:34%;text-align:left;vertical-align:top;padding:7px 10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600}
    #ipc-funnel-term-visit-body .ipc-term-table td{padding:7px 10px;border:1px solid #e5e7eb;vertical-align:top}
    #ipc-funnel-term-visit-body .ipc-term-sign p{margin:26px 0 0;line-height:1.6}
    #ipc-funnel-term-visit-body .ipc-term-foot{margin-top:20px;font-size:12px;color:#6b7280;text-align:right}
    .ipc-step-btn{align-items:flex-start}
    .ipc-step-label{display:block}
    .ipc-step-desc{display:block;font-size:11.5px;font-weight:400;line-height:1.35;color:#94a3b8;margin-top:1px}
    .ipc-progress__step.is-done .ipc-step-desc,.ipc-progress__step.is-current .ipc-step-desc{color:#64748b}
    .ipc-progress__step.is-skipped .ipc-progress__dot{background:#f1f5f9;border-color:#e2e8f0;color:#cbd5e1}
    .ipc-progress__step.is-skipped .ipc-step-label{color:#cbd5e1;text-decoration:line-through}
    .ipc-progress__step.is-skipped .ipc-step-desc{color:#cbd5e1}
    .ipc-outcome-hint{display:block;font-size:12px;color:#64748b;margin:-6px 0 12px}
</style>
<div class="ipc-funnel-wrap">
    <!-- HEADER -->
    <header class="mb-5 rounded-2xl border border-slate-100 bg-white px-5 sm:px-6 py-5 shadow-sm">
        <a class="ipc-funnel-back mb-3" href="<?php echo esc_url( $dashboard_back ); ?>">
            <?php echo houzez_dash_icon( 'arrow-left', 'h-4 w-4' ); ?>
            <?php esc_html_e( 'Voltar às parcerias', 'imovel-parceiro-core' ); ?>
        </a>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><?php echo houzez_dash_icon( 'handshake', 'h-5 w-5' ); ?></span>
                <h2 class="m-0 text-lg font-bold tracking-tight text-slate-900"><?php echo esc_html( $property_title ); ?></h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="imovel-parceiro-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                    <?php echo houzez_dash_icon( 'circle-user-round', 'h-3.5 w-3.5 text-slate-400' ); ?>
                    <?php esc_html_e( 'Seu papel:', 'imovel-parceiro-core' ); ?> <?php echo esc_html( $my_role_label ); ?>
                </span>
            </div>
        </div>
    </header>

    <div class="ipc-funnel-grid grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <!-- LEFT: partnership details + progress + next action -->
        <div class="ipc-funnel-col space-y-5">
            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Resumo da parceria', 'imovel-parceiro-core' ); ?></h3>
                <dl class="m-0">
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                        <dt class="shrink-0 text-[13px] font-semibold text-slate-500"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></dt>
                        <dd class="text-right text-[13px] font-medium text-slate-800"><a class="font-semibold text-indigo-600 hover:text-indigo-500" href="<?php echo esc_url( $property_lk ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $property_title ); ?></a></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                        <dt class="shrink-0 text-[13px] font-semibold text-slate-500"><?php esc_html_e( 'Corretor responsável', 'imovel-parceiro-core' ); ?></dt>
                        <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( $owner_name ); ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                        <dt class="shrink-0 text-[13px] font-semibold text-slate-500"><?php esc_html_e( 'Corretor parceiro', 'imovel-parceiro-core' ); ?></dt>
                        <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( $requester_name ); ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                        <dt class="shrink-0 text-[13px] font-semibold text-slate-500"><?php esc_html_e( 'Comissão', 'imovel-parceiro-core' ); ?></dt>
                        <dd class="text-right text-[13px] font-bold text-slate-900"><?php echo esc_html( $split ); ?></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-[13px] font-semibold text-slate-500"><?php esc_html_e( 'Status atual', 'imovel-parceiro-core' ); ?></dt>
                        <dd class="text-right"><span class="imovel-parceiro-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span></dd>
                    </div>
                </dl>
            </div>

            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Etapas da parceria', 'imovel-parceiro-core' ); ?></h3>
                <p class="ipc-funnel-muted" style="margin:-6px 0 12px;font-size:12px;"><?php esc_html_e( 'Acompanhe em que ponto a parceria está. Clique em uma etapa para ver os detalhes.', 'imovel-parceiro-core' ); ?></p>
                <ol class="ipc-progress">
                    <?php foreach ( $timeline as $step_index => $step ) : ?>
                        <li class="ipc-progress__step is-<?php echo esc_attr( $step['state'] ); ?>">
                            <span class="ipc-progress__rail">
                                <span class="ipc-progress__dot"><?php echo in_array( $step['state'], array( 'done', 'current' ), true ) ? '✓' : ''; ?></span>
                                <?php if ( $step_index < count( $timeline ) - 1 ) : ?><span class="ipc-progress__line"></span><?php endif; ?>
                            </span>
                            <button type="button" class="ipc-step-btn" data-ipc-step="<?php echo esc_attr( $step['key'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver detalhes de %s', 'imovel-parceiro-core' ), $step['label'] ) ); ?>">
                                <span class="ipc-step-label"><?php echo esc_html( $step['label'] ); ?></span>
                                <?php if ( ! empty( $step['desc'] ) ) : ?><span class="ipc-step-desc"><?php echo esc_html( $step['desc'] ); ?></span><?php endif; ?>
                                <span class="ipc-step-arrow"><?php echo houzez_dash_icon( 'chevron-down', 'h-4 w-4' ); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="ipc-funnel-card ipc-funnel-next">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Próximo passo', 'imovel-parceiro-core' ); ?></h3>
                <p class="ipc-funnel-next-text m-0 text-sm text-indigo-100"><?php echo esc_html( $next_action ); ?></p>
                <?php if ( ! empty( $next_btn ) ) : ?>
                    <button type="button" class="btn ipc-funnel-action mt-2" data-action="<?php echo esc_attr( $next_btn['kind'] ); ?>">
                        <?php echo houzez_dash_icon( 'arrow-right', 'h-4 w-4' ); ?>
                        <?php echo esc_html( $next_btn['label'] ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $allowed_actions ) ) : ?>
                <div class="ipc-funnel-card">
                    <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Ações disponíveis', 'imovel-parceiro-core' ); ?></h3>
                    <div class="ipc-funnel-actions flex flex-wrap gap-2">
                        <?php foreach ( $allowed_actions as $a ) : ?>
                            <?php
                            $btn_cls = 'btn-outline-secondary';
                            switch ( $a['kind'] ) {
                                case 'accept': $btn_cls = 'btn-success'; break;
                                case 'reject': $btn_cls = 'btn-danger'; break;
                                case 'negotiate': $btn_cls = 'btn-primary'; break;
                                case 'release_contact': $btn_cls = 'btn-primary'; break;
                                case 'opportunity': $btn_cls = 'btn-primary'; break;
                                case 'interaction': $btn_cls = 'btn-outline-primary'; break;
                                case 'visit': $btn_cls = 'btn-primary'; break;
                                case 'proposal': $btn_cls = 'btn-primary'; break;
                                case 'won': $btn_cls = 'btn-success'; break;
                                case 'lost': $btn_cls = 'btn-outline-danger'; break;
                            }
                            ?>
                            <button type="button" class="btn btn-sm <?php echo esc_attr( $btn_cls ); ?> ipc-funnel-action" data-action="<?php echo esc_attr( $a['kind'] ); ?>"><?php echo esc_html( $a['label'] ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: contact + opportunity + interactions + history -->
        <div class="ipc-funnel-col space-y-5">
            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Contato do parceiro', 'imovel-parceiro-core' ); ?></h3>
                <?php if ( $contact_loaded && $contact_data ) : ?>
                    <div class="ipc-funnel-contact">
                        <div class="mb-3 flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><?php echo houzez_dash_icon( 'circle-user-round', 'h-5 w-5' ); ?></span>
                            <div class="min-w-0">
                                <p class="m-0 text-sm font-bold text-slate-900"><?php echo esc_html( $contact_data['name'] ); ?></p>
                                <?php if ( ! empty( $contact_data['company'] ) ) : ?><p class="m-0 text-xs text-slate-500"><?php echo esc_html( $contact_data['company'] ); ?></p><?php endif; ?>
                            </div>
                        </div>
                        <ul class="m-0 space-y-2">
                            <?php if ( ! empty( $contact_data['phone'] ) ) : ?><li class="flex items-center gap-2 text-[13px]"><span class="text-slate-400"><?php echo houzez_dash_icon( 'phone', 'h-4 w-4' ); ?></span><a href="tel:<?php echo esc_attr( $contact_data['phone_call'] ); ?>"><?php echo esc_html( $contact_data['phone'] ); ?></a></li><?php endif; ?>
                            <?php if ( ! empty( $contact_data['mobile'] ) ) : ?><li class="flex items-center gap-2 text-[13px]"><span class="text-slate-400"><?php echo houzez_dash_icon( 'phone', 'h-4 w-4' ); ?></span><a href="tel:<?php echo esc_attr( $contact_data['mobile_call'] ); ?>"><?php echo esc_html( $contact_data['mobile'] ); ?></a></li><?php endif; ?>
                            <?php if ( ! empty( $contact_data['whatsapp'] ) ) : ?><li class="flex items-center gap-2 text-[13px]"><span class="text-slate-400"><?php echo houzez_dash_icon( 'message-circle', 'h-4 w-4' ); ?></span><a href="https://wa.me/<?php echo esc_attr( $contact_data['whatsapp_call'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $contact_data['whatsapp'] ); ?></a></li><?php endif; ?>
                            <?php if ( ! empty( $contact_data['email'] ) ) : ?><li class="flex items-center gap-2 text-[13px]"><span class="text-slate-400"><?php echo houzez_dash_icon( 'mail', 'h-4 w-4' ); ?></span><a href="mailto:<?php echo esc_attr( $contact_data['email'] ); ?>"><?php echo esc_html( $contact_data['email'] ); ?></a></li><?php endif; ?>
                        </ul>
                    </div>
                <?php elseif ( $contact_released ) : ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ipc-funnel-load-contact"><?php esc_html_e( 'Carregar contato', 'imovel-parceiro-core' ); ?></button>
                    <div id="ipc-funnel-contact-box" class="ipc-funnel-contact-box mt-2"></div>
                <?php else : ?>
                    <p class="ipc-funnel-muted m-0 flex items-center gap-2"><?php echo houzez_dash_icon( 'lock', 'h-4 w-4 text-slate-300' ); ?> <?php esc_html_e( 'O contato do parceiro ainda não foi liberado pelo corretor dono.', 'imovel-parceiro-core' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Oportunidade', 'imovel-parceiro-core' ); ?></h3>
                <?php if ( $opportunity && ! empty( $opportunity->meta ) ) : ?>
                    <?php $om = $opportunity->meta; ?>
                    <dl class="m-0">
                        <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                            <dt class="shrink-0 text-[12.5px] font-semibold text-slate-500"><?php esc_html_e( 'Cliente', 'imovel-parceiro-core' ); ?></dt>
                            <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( ! empty( $om['client_name'] ) ? $om['client_name'] : '-' ); ?></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                            <dt class="shrink-0 text-[12.5px] font-semibold text-slate-500"><?php esc_html_e( 'Telefone', 'imovel-parceiro-core' ); ?></dt>
                            <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( ! empty( $om['client_phone'] ) ? $om['client_phone'] : '-' ); ?></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                            <dt class="shrink-0 text-[12.5px] font-semibold text-slate-500"><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></dt>
                            <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( ! empty( $om['client_email'] ) ? $om['client_email'] : '-' ); ?></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-b border-slate-50 py-2.5 last:border-0">
                            <dt class="shrink-0 text-[12.5px] font-semibold text-slate-500"><?php esc_html_e( 'Interesse', 'imovel-parceiro-core' ); ?></dt>
                            <dd class="text-right text-[13px] font-medium text-slate-800"><?php echo esc_html( ! empty( $om['interest'] ) ? $om['interest'] : '-' ); ?></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-[12.5px] font-semibold text-slate-500"><?php esc_html_e( 'Valor estimado', 'imovel-parceiro-core' ); ?></dt>
                            <dd class="text-right text-[13px] font-bold text-slate-900"><?php echo esc_html( ! empty( $om['estimated_value'] ) ? $om['estimated_value'] : '-' ); ?></dd>
                        </div>
                    </dl>
                    <?php if ( ! empty( $om['notes'] ) ) : ?><p class="ipc-funnel-muted mt-3"><?php echo esc_html( $om['notes'] ); ?></p><?php endif; ?>
                <?php else : ?>
                    <p class="ipc-funnel-muted m-0"><?php esc_html_e( 'Nenhuma oportunidade registrada ainda.', 'imovel-parceiro-core' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Interações', 'imovel-parceiro-core' ); ?></h3>
                <?php if ( empty( $interactions ) ) : ?>
                    <p class="ipc-funnel-muted m-0"><?php esc_html_e( 'Nenhuma interação registrada.', 'imovel-parceiro-core' ); ?></p>
                <?php else : ?>
                    <ul class="ipc-funnel-events">
                        <?php foreach ( $interactions as $ev ) : ?>
                            <li>
                                <span class="ipc-ev-icon"><?php
                                    if ( 'visit' === $ev->kind ) { echo houzez_dash_icon( 'calendar-days', 'h-4 w-4' ); }
                                    elseif ( 'proposal' === $ev->kind ) { echo houzez_dash_icon( 'file-text', 'h-4 w-4' ); }
                                    elseif ( 'opportunity' === $ev->kind ) { echo houzez_dash_icon( 'sparkles', 'h-4 w-4' ); }
                                    else { echo houzez_dash_icon( 'message-circle', 'h-4 w-4' ); }
                                ?></span>
                                <span class="min-w-0">
                                    <span class="ipc-ev-meta"><?php echo esc_html( $ev->created ); ?></span>
                                    <span class="ipc-ev-title">
                                        <?php
                                        if ( 'visit' === $ev->kind ) {
                                            echo esc_html( __( 'Visita', 'imovel-parceiro-core' ) );
                                        } elseif ( 'proposal' === $ev->kind ) {
                                            echo esc_html( __( 'Proposta', 'imovel-parceiro-core' ) );
                                        } elseif ( 'opportunity' === $ev->kind ) {
                                            echo esc_html( __( 'Oportunidade', 'imovel-parceiro-core' ) );
                                        } else {
                                            echo esc_html( $ev->title );
                                        }
                                        ?>
                                        <?php if ( ! empty( $ev->note ) ) : ?> — <?php echo esc_html( $ev->note ); ?><?php endif; ?>
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="ipc-funnel-card">
                <h3 class="ipc-funnel-card-title"><?php esc_html_e( 'Histórico', 'imovel-parceiro-core' ); ?></h3>
                <?php if ( empty( $history ) ) : ?>
                    <p class="ipc-funnel-muted m-0"><?php esc_html_e( 'Sem eventos registrados.', 'imovel-parceiro-core' ); ?></p>
                <?php else : ?>
                    <ul class="ipc-funnel-events">
                        <?php foreach ( $history as $ev ) : ?>
                            <li>
                                <span class="ipc-ev-icon"><?php echo houzez_dash_icon( 'calendar-clock', 'h-4 w-4' ); ?></span>
                                <span class="min-w-0">
                                    <span class="ipc-ev-meta"><?php echo esc_html( $ev->created ); ?></span>
                                    <span class="ipc-ev-title"><?php echo esc_html( $ev->title ); ?><?php if ( ! empty( $ev->note ) ) : ?> — <?php echo esc_html( $ev->note ); ?><?php endif; ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    // Render a single generic "reason" modal reused for reject/lost/close.
    if ( ! function_exists( 'ipc_funnel_reason_modal' ) ) {
        function ipc_funnel_reason_modal( $id, $title, $label ) {
            ?>
            <div class="modal fade" id="<?php echo esc_attr( $id ); ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title"><?php echo esc_html( $title ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                        <div class="modal-body">
                            <label class="form-label"><?php echo esc_html( $title ); ?></label>
                            <textarea class="form-control ipc-modal-reason" rows="4" placeholder="<?php echo esc_attr( $label ); ?>"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                            <button type="button" class="btn btn-primary ipc-modal-confirm"><?php esc_html_e( 'Confirmar', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    $pn = __( 'Parceria #%d', 'imovel-parceiro-core' );
    ?>


    <!-- Transition modals -->
    <div class="modal fade" id="ipc-funnel-accept" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Aceitar parceria', 'imovel-parceiro-core' ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><?php esc_html_e( 'Confirmar o aceite desta parceria?', 'imovel-parceiro-core' ); ?></p></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button class="btn btn-success ipc-modal-go" data-to="accepted"><?php esc_html_e( 'Confirmar aceite', 'imovel-parceiro-core' ); ?></button></div></div></div></div>

    <div class="modal fade" id="ipc-funnel-negotiate" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Iniciar negociação', 'imovel-parceiro-core' ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><?php esc_html_e( 'A negociação desta parceria será iniciada. Continuar?', 'imovel-parceiro-core' ); ?></p></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button class="btn btn-primary ipc-modal-go" data-to="negotiating"><?php esc_html_e( 'Confirmar', 'imovel-parceiro-core' ); ?></button></div></div></div></div>

    <div class="modal fade" id="ipc-funnel-release" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Liberar contato', 'imovel-parceiro-core' ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><?php esc_html_e( 'Você está prestes a liberar os dados de contato para o corretor parceiro.', 'imovel-parceiro-core' ); ?></p></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Voltar', 'imovel-parceiro-core' ); ?></button><button class="btn btn-danger ipc-modal-go" data-to="contact_released"><?php esc_html_e( 'Confirmar liberação', 'imovel-parceiro-core' ); ?></button></div></div></div></div>

    <?php ipc_funnel_reason_modal( 'ipc-funnel-reject', __( 'Motivo da recusa', 'imovel-parceiro-core' ), __( 'Selecione/descreva o motivo', 'imovel-parceiro-core' ) ); ?>

    <!-- Unified outcome modal: ganho / perdido / encerramento -->
    <div class="modal fade" id="ipc-funnel-outcome" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ipc-funnel-outcome-title"><?php esc_html_e( 'Registrar resultado', 'imovel-parceiro-core' ); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ipc-funnel-outcome-to" value="" />
                    <div class="mb-3">
                        <label class="form-label" for="ipc-funnel-outcome-reason"><?php esc_html_e( 'Motivo *', 'imovel-parceiro-core' ); ?></label>
                        <select class="form-select" id="ipc-funnel-outcome-reason"></select>
                        <span class="ipc-outcome-hint" id="ipc-funnel-outcome-hint"></span>
                    </div>
                    <div class="mb-3" id="ipc-funnel-outcome-other-wrap" style="display:none;">
                        <label class="form-label" for="ipc-funnel-outcome-other"><?php esc_html_e( 'Descreva o motivo *', 'imovel-parceiro-core' ); ?></label>
                        <textarea class="form-control" id="ipc-funnel-outcome-other" rows="3"></textarea>
                    </div>
                    <div id="ipc-funnel-outcome-won-fields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label" for="ipc-funnel-outcome-final-value"><?php esc_html_e( 'Valor final da venda', 'imovel-parceiro-core' ); ?></label>
                            <div class="imovel-parceiro-money-wrap"><span class="imovel-parceiro-money-prefix">R$</span><input type="text" inputmode="decimal" class="form-control ipc-money" id="ipc-funnel-outcome-final-value" autocomplete="off" /></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ipc-funnel-outcome-sale-date"><?php esc_html_e( 'Data da venda', 'imovel-parceiro-core' ); ?></label>
                            <input type="date" class="form-control" id="ipc-funnel-outcome-sale-date" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ipc-funnel-outcome-client"><?php esc_html_e( 'Cliente', 'imovel-parceiro-core' ); ?></label>
                            <input type="text" class="form-control" id="ipc-funnel-outcome-client" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ipc-funnel-outcome-notes"><?php esc_html_e( 'Observações', 'imovel-parceiro-core' ); ?></label>
                            <textarea class="form-control" id="ipc-funnel-outcome-notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                    <button type="button" class="btn btn-primary" id="ipc-funnel-outcome-confirm"><?php esc_html_e( 'Confirmar', 'imovel-parceiro-core' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Opportunity modal -->
    <div class="modal fade" id="ipc-funnel-form-opportunity" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Registrar oportunidade', 'imovel-parceiro-core' ); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <form id="ipc-funnel-opportunity-form" class="ipc-funnel-form" data-kind="opportunity">
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Nome do cliente *', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_name" required /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Telefone do cliente *', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_phone" required /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'CPF/CNPJ (opcional)', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_doc" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></label><input type="email" class="form-control" name="client_email" /></div>
            <div class="alert alert-light border mb-0" role="alert" style="font-size:12px;"><?php esc_html_e( 'O registro do cliente é obrigatório para manter o acesso ao contato da parceria.', 'imovel-parceiro-core' ); ?></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Data do primeiro contato', 'imovel-parceiro-core' ); ?></label><input type="date" class="form-control" name="first_contact_at" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Interesse', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="interest" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Valor estimado', 'imovel-parceiro-core' ); ?></label><div class="imovel-parceiro-money-wrap"><span class="imovel-parceiro-money-prefix">R$</span><input type="text" inputmode="decimal" class="form-control ipc-money" name="estimated_value" autocomplete="off" /></div></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Observações', 'imovel-parceiro-core' ); ?></label><textarea class="form-control" name="notes" rows="3"></textarea></div>
            <div class="modal-footer px-0 pb-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button type="submit" class="btn btn-success"><?php esc_html_e( 'Salvar', 'imovel-parceiro-core' ); ?></button></div>
        </form>
    </div></div></div></div>

    <!-- Interaction modal -->
    <div class="modal fade" id="ipc-funnel-form-interaction" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Registrar interação', 'imovel-parceiro-core' ); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <form id="ipc-funnel-interaction-form" class="ipc-funnel-form" data-kind="interaction">
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Tipo', 'imovel-parceiro-core' ); ?></label><select class="form-select" name="type"><option value="contato"><?php esc_html_e( 'Contato', 'imovel-parceiro-core' ); ?></option><option value="ligacao"><?php esc_html_e( 'Ligação', 'imovel-parceiro-core' ); ?></option><option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'imovel-parceiro-core' ); ?></option><option value="email"><?php esc_html_e( 'E-mail', 'imovel-parceiro-core' ); ?></option><option value="observacao"><?php esc_html_e( 'Observação', 'imovel-parceiro-core' ); ?></option></select></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Descrição *', 'imovel-parceiro-core' ); ?></label><textarea class="form-control" name="desc" rows="3" required></textarea></div>
            <div class="modal-footer px-0 pb-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button type="submit" class="btn btn-success"><?php esc_html_e( 'Salvar', 'imovel-parceiro-core' ); ?></button></div>
        </form>
    </div></div></div></div>

    <!-- Visit modal -->
    <div class="modal fade" id="ipc-funnel-form-visit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Registrar visita', 'imovel-parceiro-core' ); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <form id="ipc-funnel-visit-form" class="ipc-funnel-form" data-kind="visit">
            <fieldset class="ipc-visit-fieldset">
                <legend><?php esc_html_e( 'Identificação do interessado', 'imovel-parceiro-core' ); ?></legend>
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><label class="form-label"><?php esc_html_e( 'Nome do interessado *', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_name" required /></div>
                    <div class="col-md-3"><label class="form-label"><?php esc_html_e( 'CPF/CNPJ', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_doc" /></div>
                    <div class="col-md-3"><label class="form-label"><?php esc_html_e( 'Telefone', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_phone" /></div>
                </div>
            </fieldset>
            <fieldset class="ipc-visit-fieldset">
                <legend><?php esc_html_e( 'Identificação do imóvel', 'imovel-parceiro-core' ); ?></legend>
                <div class="mb-2"><label class="form-label"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="property_title" value="<?php echo esc_attr( $visit_term_defaults['property_title'] ); ?>" readonly /></div>
                <div class="mb-2"><label class="form-label"><?php esc_html_e( 'Endereço', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="property_address" value="<?php echo esc_attr( $visit_term_defaults['property_address'] ); ?>" readonly /></div>
                <input type="hidden" name="property_ref" value="<?php echo esc_attr( $visit_term_defaults['property_ref'] ); ?>" />
            </fieldset>
            <fieldset class="ipc-visit-fieldset">
                <legend><?php esc_html_e( 'Identificação do corretor responsável', 'imovel-parceiro-core' ); ?></legend>
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><label class="form-label"><?php esc_html_e( 'Nome', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="broker_name" value="<?php echo esc_attr( $visit_term_defaults['broker_name'] ); ?>" /></div>
                    <div class="col-md-3"><label class="form-label"><?php esc_html_e( 'CRECI', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="broker_creci" value="<?php echo esc_attr( $visit_term_defaults['broker_creci'] ); ?>" /></div>
                    <div class="col-md-3"><label class="form-label"><?php esc_html_e( 'Imobiliária', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="broker_company" value="<?php echo esc_attr( $visit_term_defaults['broker_company'] ); ?>" /></div>
                </div>
            </fieldset>
            <fieldset class="ipc-visit-fieldset">
                <legend><?php esc_html_e( 'Data e horário da visita', 'imovel-parceiro-core' ); ?></legend>
                <div class="row g-2 mb-2"><div class="col"><label class="form-label"><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></label><input type="date" class="form-control" name="visit_date" /></div><div class="col"><label class="form-label"><?php esc_html_e( 'Hora', 'imovel-parceiro-core' ); ?></label><input type="time" class="form-control" name="visit_time" /></div></div>
            </fieldset>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Participantes', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="participants" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Resultado', 'imovel-parceiro-core' ); ?></label><select class="form-select" name="result"><option value="interessado"><?php esc_html_e( 'Interessado', 'imovel-parceiro-core' ); ?></option><option value="muito_interessado"><?php esc_html_e( 'Muito interessado', 'imovel-parceiro-core' ); ?></option><option value="sem_interesse"><?php esc_html_e( 'Sem interesse', 'imovel-parceiro-core' ); ?></option><option value="avaliar"><?php esc_html_e( 'Avaliar', 'imovel-parceiro-core' ); ?></option><option value="nova_visita"><?php esc_html_e( 'Nova visita', 'imovel-parceiro-core' ); ?></option></select></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Observações', 'imovel-parceiro-core' ); ?></label><textarea class="form-control" name="notes" rows="3"></textarea></div>
            <div class="modal-footer px-0 pb-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button type="submit" class="btn btn-success"><?php esc_html_e( 'Salvar e gerar termo', 'imovel-parceiro-core' ); ?></button></div>
        </form>
    </div></div></div></div>

    <!-- Visit term modal (shown after saving a visit) -->
    <div class="modal fade" id="ipc-funnel-contact-terms" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Termo de não-circunvenção', 'imovel-parceiro-core' ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<p><?php esc_html_e( 'Os dados de contato da contraparte são liberados exclusivamente para a condução desta parceria na plataforma.', 'imovel-parceiro-core' ); ?></p>
<ul class="mb-3 ps-3">
<li><?php esc_html_e( 'É vedado repassar o contato a terceiros.', 'imovel-parceiro-core' ); ?></li>
<li><?php esc_html_e( 'É vedado fechar negócio fora da plataforma sem registrar o resultado nesta parceria.', 'imovel-parceiro-core' ); ?></li>
<li><?php esc_html_e( 'O acesso ao contato fica registrado (data, hora e origem) para fins de auditoria.', 'imovel-parceiro-core' ); ?></li>
</ul>
<label class="d-flex align-items-start gap-2"><input type="checkbox" id="ipc-funnel-contact-terms-check" class="mt-1" /> <span><?php esc_html_e( 'Li e aceito o termo de não-circunvenção.', 'imovel-parceiro-core' ); ?></span></label>
</div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button class="btn btn-primary" id="ipc-funnel-contact-terms-confirm" disabled><?php esc_html_e( 'Aceitar e ver contato', 'imovel-parceiro-core' ); ?></button></div></div></div></div>

<div class="modal fade" id="ipc-funnel-term-visit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Termo de Visita', 'imovel-parceiro-core' ); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button></div><div class="modal-body" id="ipc-funnel-term-visit-body"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button><button type="button" class="btn btn-primary" id="ipc-funnel-term-print"><?php esc_html_e( 'Imprimir', 'imovel-parceiro-core' ); ?></button></div></div></div></div>

    <!-- Proposal modal -->
    <div class="modal fade" id="ipc-funnel-form-proposal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php esc_html_e( 'Registrar proposta', 'imovel-parceiro-core' ); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <form id="ipc-funnel-proposal-form" class="ipc-funnel-form" data-kind="proposal">
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Cliente *', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="client_name" required /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Valor da proposta', 'imovel-parceiro-core' ); ?></label><div class="imovel-parceiro-money-wrap"><span class="imovel-parceiro-money-prefix">R$</span><input type="text" inputmode="decimal" class="form-control ipc-money" name="amount" autocomplete="off" /></div></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></label><input type="date" class="form-control" name="proposal_date" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Condições', 'imovel-parceiro-core' ); ?></label><input type="text" class="form-control" name="conditions" /></div>
            <div class="mb-3"><label class="form-label"><?php esc_html_e( 'Observações', 'imovel-parceiro-core' ); ?></label><textarea class="form-control" name="notes" rows="3"></textarea></div>
            <div class="modal-footer px-0 pb-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button><button type="submit" class="btn btn-success"><?php esc_html_e( 'Salvar', 'imovel-parceiro-core' ); ?></button></div>
        </form>
    </div></div></div></div>

    <!-- Won modal removed: ganho/perdido/encerramento now use #ipc-funnel-outcome -->

    <div class="ipc-funnel-note"><?php echo esc_html( __( 'Parceria #', 'imovel-parceiro-core' ) . $partnership_id ); ?></div>
</div>

<script>
/* Funnel detail config: defined inline so the JS below does not depend on the
   plugin core script being enqueued/loaded before this body script runs. */
window.__ipcFunnel = window.__ipcFunnel || {};
window.__ipcFunnel.ajax_url = window.__ipcFunnel.ajax_url || '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
window.__ipcFunnel.nonce = window.__ipcFunnel.nonce || '<?php echo esc_js( wp_create_nonce( 'imovel_parceiro_core_nonce' ) ); ?>';
window.__ipcFunnel.pid = window.__ipcFunnel.pid || '<?php echo esc_js( $partnership_id ); ?>';
window.__ipcFunnel.reasons = window.__ipcFunnel.reasons || <?php echo wp_json_encode( $ipc_reason_options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
</script>

<script>
(function(){
    var CFG = window.__ipcFunnel || {};
    var AJAX = CFG.ajax_url || '';
    var NONCE = CFG.nonce || '';
    var PID = CFG.pid || '';
    var isDetailLoading = false;
    var tries = 0;

    function modalShow($, $m){
        if (!$m || !$m.length) { return; }
        if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance($m[0]).show(); }
        else if ($.fn.modal) { $m.modal('show'); }
    }
    function modalHide($, $m){
        if (!$m || !$m.length) { return; }
        if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance($m[0]).hide(); }
        else if ($.fn.modal) { $m.modal('hide'); }
    }

    function init($){
        var TOAST = function(msg){ if (window.alert) { alert(msg); } };

        function post(action, data){
            var payload = $.extend({ action: action, nonce: NONCE, partnership_id: PID }, data || {});
            return $.post(AJAX, payload);
        }
        function reload(){ window.location.reload(); }

        // Currency mask: R$ as prefix, "." thousands, "," decimals (BRL).
        function maskMoney(){
            $(this).off('input.money').on('input.money', function(){
                var el = this;
                var digitsOnly = function(s){ return (s || '').replace(/[^\d]/g, ''); };
                var value = el.value;
                var hasComma = value.indexOf(',') > -1;
                var intPart, decPart;
                if (hasComma) {
                    var parts = value.split(',');
                    intPart = digitsOnly(parts[0]);
                    decPart = digitsOnly(parts[1]).slice(0, 2);
                } else {
                    intPart = digitsOnly(value);
                    decPart = '';
                }
                intPart = (intPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                var formatted = decPart !== '' ? (intPart + ',' + decPart) : intPart;
                if (value.length && el.value.indexOf(',') !== value.indexOf(',')) { /* keep */ }
                el.value = formatted;
            });
        }
        $('.ipc-money').each(maskMoney);

        // Buttons -> open the correct modal
        $('.ipc-funnel-action').on('click', function(){
            var kind = $(this).data('action');
            var map = {
                accept:'#ipc-funnel-accept', negotiate:'#ipc-funnel-negotiate',
                release_contact:'#ipc-funnel-release', reject:'#ipc-funnel-reject',
                opportunity:'#ipc-funnel-form-opportunity', interaction:'#ipc-funnel-form-interaction',
                visit:'#ipc-funnel-form-visit', proposal:'#ipc-funnel-form-proposal'
            };
            if (kind === 'won' || kind === 'lost' || kind === 'close') { openOutcome(kind); return; }
            if (map[kind]) { modalShow($, $(map[kind])); } else { TOAST('Ação desconhecida'); }
        });

        // Unified outcome modal: ganho / perdido / encerramento
        function openOutcome(kind){
            var cfg = {
                won:   { to:'won',    title:'Registrar negócio ganho',   hint:'Selecione o motivo do ganho.' },
                lost:  { to:'lost',   title:'Registrar negócio perdido', hint:'Selecione o motivo da perda.' },
                close: { to:'closed', title:'Encerrar parceria',         hint:'Selecione o motivo do encerramento.' }
            }[kind];
            if (!cfg) { return; }
            var reasons = (window.__ipcFunnel && window.__ipcFunnel.reasons && window.__ipcFunnel.reasons[kind === 'close' ? 'closed' : kind]) || {};
            var $m = $('#ipc-funnel-outcome');
            $('#ipc-funnel-outcome-to').val(cfg.to);
            $('#ipc-funnel-outcome-title').text(cfg.title);
            $('#ipc-funnel-outcome-hint').text(cfg.hint);
            var $sel = $('#ipc-funnel-outcome-reason').empty();
            $sel.append($('<option>').val('').text('Selecione...'));
            $.each(reasons, function(val, label){ $sel.append($('<option>').val(val).text(label)); });
            $('#ipc-funnel-outcome-other-wrap').hide();
            $('#ipc-funnel-outcome-other').val('');
            $('#ipc-funnel-outcome-won-fields').toggle(kind === 'won');
            $('#ipc-funnel-outcome-final-value, #ipc-funnel-outcome-sale-date, #ipc-funnel-outcome-client, #ipc-funnel-outcome-notes').val('');
            modalShow($, $m);
        }
        $('#ipc-funnel-outcome-reason').on('change', function(){
            $('#ipc-funnel-outcome-other-wrap').toggle($(this).val() === 'outro');
        });
        $('#ipc-funnel-outcome-confirm').on('click', function(){
            var to = $('#ipc-funnel-outcome-to').val();
            var reason = $('#ipc-funnel-outcome-reason').val();
            if (!reason) { TOAST('Selecione o motivo.'); return; }
            var payload = { reason: reason };
            if (reason === 'outro') {
                var other = $.trim($('#ipc-funnel-outcome-other').val());
                if (!other) { TOAST('Descreva o motivo.'); return; }
                payload.reason_detail = other;
            }
            if (to === 'won') {
                payload.final_value = $.trim($('#ipc-funnel-outcome-final-value').val());
                payload.sale_date = $.trim($('#ipc-funnel-outcome-sale-date').val());
                payload.client_name = $.trim($('#ipc-funnel-outcome-client').val());
                payload.notes = $.trim($('#ipc-funnel-outcome-notes').val());
            }
            if (isDetailLoading) { return; }
            isDetailLoading = true;
            post('imovel_parceiro_funnel_transition', { to_status: to, payload: payload }).done(function(res){
                if (res && res.success) { modalHide($, $('#ipc-funnel-outcome')); reload(); }
                else { TOAST(res && res.data && res.data.message ? res.data.message : 'Erro'); }
            }).fail(function(){ TOAST('Erro de comunicação'); }).always(function(){ isDetailLoading = false; });
        });

        // Confirm-modals (transition endpoints): accept / negotiate / release
        $('.ipc-modal-go').on('click', function(){
            var to = $(this).data('to');
            if (isDetailLoading) { return; }
            isDetailLoading = true;
            post('imovel_parceiro_funnel_transition', { to_status: to }).done(function(res){
                if (res && res.success) { reload(); } else { TOAST(res && res.data && res.data.message ? res.data.message : 'Erro'); }
            }).fail(function(){ TOAST('Erro de comunicação'); }).always(function(){ isDetailLoading = false; });
        });

        // Reason modal: reject only
        $('.ipc-modal-confirm').on('click', function(){
            var $m = $(this).closest('.modal');
            var reason = $.trim($m.find('.ipc-modal-reason').val());
            if (!reason) { TOAST('Informe o motivo.'); return; }
            var id = $m.attr('id'), to = '';
            if (id === 'ipc-funnel-reject') { to = 'rejected'; }
            if (!to) { return; }
            if (isDetailLoading) { return; }
            isDetailLoading = true;
            post('imovel_parceiro_funnel_transition', { to_status: to, payload: { reason: reason } }).done(function(res){
                if (res && res.success) { reload(); } else { TOAST(res && res.data && res.data.message ? res.data.message : 'Erro'); }
            }).fail(function(){ TOAST('Erro de comunicação'); }).always(function(){ isDetailLoading = false; });
        });

        // Form modals: opportunity / interaction / visit / proposal / won
        $('.ipc-funnel-form').on('submit', function(e){
            e.preventDefault();
            var kind = $(this).data('kind');
            var data = $(this).serializeArray().reduce(function(o, kv){ o[kv.name] = kv.value; return o; }, {});
            var action = 'imovel_parceiro_funnel_' + kind;
            if (kind === 'opportunity') { action = 'imovel_parceiro_funnel_opportunity'; }
            if (kind === 'interaction') { action = 'imovel_parceiro_funnel_interaction'; }
            if (kind === 'visit') { action = 'imovel_parceiro_funnel_visit'; }
            if (kind === 'proposal') { action = 'imovel_parceiro_funnel_proposal'; }
            if (isDetailLoading) { return; }
            isDetailLoading = true;
            post(action, data).done(function(res){
                if (res && res.success) {
                    if (kind === 'visit' && res.data && res.data.term) {
                        showVisitTerm(res.data.term);
                    } else {
                        reload();
                    }
                } else { TOAST(res && res.data && res.data.message ? res.data.message : 'Erro'); }
            }).fail(function(){ TOAST('Erro de comunicação'); }).always(function(){ isDetailLoading = false; });
        });

        // Load released contact via secure endpoint (termo de não-circunvenção).
        function renderContact(c){
            var html = '';
            html += '<p><strong>' + c.name + '</strong></p>';
            if (c.company) { html += '<p>' + c.company + '</p>'; }
            if (c.phone) { html += '<p><a href="tel:' + c.phone_call + '">' + c.phone + '</a></p>'; }
            if (c.mobile) { html += '<p><a href="tel:' + c.mobile_call + '">' + c.mobile + '</a></p>'; }
            if (c.whatsapp) { html += '<p><a href="https://wa.me/' + c.whatsapp_call + '">' + c.whatsapp + '</a></p>'; }
            if (c.email) { html += '<p><a href="mailto:' + c.email + '">' + c.email + '</a></p>'; }
            if (c.notice) { html += '<p class="ipc-funnel-muted" style="font-size:11px;">' + c.notice + '</p>'; }
            return html;
        }
        function loadContact(acceptTerms){
            var btn = document.getElementById('ipc-funnel-load-contact');
            if (btn) { $(btn).prop('disabled', true); }
            var payload = acceptTerms ? { contact_terms: 1 } : {};
            post('imovel_parceiro_funnel_contact', payload).done(function(res){
                if (res && res.success && res.data && res.data.contact){
                    $('#ipc-funnel-contact-box').html(renderContact(res.data.contact));
                    if (btn) { $(btn).hide(); }
                } else if (res && res.data && res.data.code === 'contact_terms_required') {
                    modalShow($, $('#ipc-funnel-contact-terms'));
                } else {
                    TOAST(res && res.data && res.data.message ? res.data.message : 'Contato indisponível.');
                }
            }).fail(function(){ TOAST('Erro de comunicação'); }).always(function(){ if (btn) { $(btn).prop('disabled', false); } });
        }
        $('#ipc-funnel-load-contact').on('click', function(){ loadContact(false); });
        $('#ipc-funnel-contact-terms-check').on('change', function(){ $('#ipc-funnel-contact-terms-confirm').prop('disabled', !$(this).is(':checked')); });
        $('#ipc-funnel-contact-terms-confirm').on('click', function(){ modalHide($, $('#ipc-funnel-contact-terms')); loadContact(true); });

        // ---- Visit term: popup shown right after a visit is saved ----
        function termEsc(v){
            return $('<div>').text(v === null || v === undefined ? '' : String(v)).html();
        }
        function termResultLabel(v){
            var map = { interessado:'Interessado', muito_interessado:'Muito interessado', sem_interesse:'Sem interesse', avaliar:'Avaliar', nova_visita:'Nova visita' };
            return map[v] || v || '';
        }
        function termRows(term){
            return [
                ['Imóvel', term.property_title],
                ['Endereço', term.property_address],
                ['Referência', term.property_ref ? ('#' + term.property_ref) : ''],
                ['Interessado', term.client_name],
                ['CPF/CNPJ', term.client_doc],
                ['Telefone', term.client_phone],
                ['Corretor responsável', term.broker_name],
                ['CRECI', term.broker_creci],
                ['Imobiliária', term.broker_company],
                ['Data da visita', term.visit_date],
                ['Horário', term.visit_time],
                ['Participantes', term.participants],
                ['Resultado', termResultLabel(term.result)],
                ['Observações', term.notes]
            ];
        }
        function termHtml(term){
            var html = '<div class="ipc-term-head"><h2>TERMO DE VISITA</h2></div>';
            html += '<table class="ipc-term-table"><tbody>';
            termRows(term).forEach(function(pair){
                if (pair[1] === null || pair[1] === undefined || String(pair[1]).trim() === '') { return; }
                html += '<tr><th>' + termEsc(pair[0]) + '</th><td>' + termEsc(pair[1]) + '</td></tr>';
            });
            html += '</tbody></table>';
            html += '<div class="ipc-term-sign">';
            html += '<p>Local e data: ______________________________________, ____/____/________</p>';
            html += '<p>Assinatura do interessado: ______________________________________</p>';
            html += '<p>Assinatura do corretor responsável: ______________________________________</p>';
            html += '</div>';
            if (term.generated_at) { html += '<p class="ipc-term-foot">Emitido em ' + termEsc(term.generated_at) + '</p>'; }
            return html;
        }
        function showVisitTerm(term){
            window.__ipcLastVisitTerm = term || {};
            var body = document.getElementById('ipc-funnel-term-visit-body');
            if (!body) { reload(); return; }
            body.innerHTML = termHtml(term || {});
            modalHide($, $('#ipc-funnel-form-visit'));
            setTimeout(function(){ modalShow($, $('#ipc-funnel-term-visit')); }, 200);
        }
        function printVisitTerm(){
            var term = window.__ipcLastVisitTerm;
            if (!term) { return; }
            var w = window.open('', '_blank', 'width=820,height=920');
            if (!w) { TOAST('Permita pop-ups para imprimir o termo.'); return; }
            var css = 'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:32px}h2{font-size:20px;text-align:center;letter-spacing:.06em;margin:0 0 18px}table{width:100%;border-collapse:collapse;margin-bottom:18px}th{width:34%;text-align:left;vertical-align:top;padding:7px 10px;border:1px solid #ccc;background:#f4f4f4;font-weight:600}td{padding:7px 10px;border:1px solid #ccc;vertical-align:top}.ipc-term-sign p{margin:30px 0 0;line-height:1.6}.ipc-term-foot{margin-top:22px;font-size:12px;color:#666;text-align:right}';
            w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Termo de Visita</title><style>' + css + '</style></head><body>' + termHtml(term) + '</body></html>');
            w.document.close();
            w.focus();
            setTimeout(function(){ try { w.print(); } catch (e) {} }, 350);
        }
        $(document).on('click', '#ipc-funnel-term-print', printVisitTerm);
        $('#ipc-funnel-term-visit').on('hidden.bs.modal', function(){ reload(); });
    }

    function boot(){
        tries++;
        if (typeof window.jQuery === 'undefined') {
            if (tries < 300) { setTimeout(boot, 50); }
            return;
        }
        init(window.jQuery);
    }

    boot();
})();
</script>

<script id="ipc-funnel-step-modal" type="text/html">
<div id="ipc-funnel-detail-modal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ipc-funnel-detail-title"><?php esc_html_e( 'Detalhes da etapa', 'imovel-parceiro-core' ); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
            </div>
            <div class="modal-body" id="ipc-funnel-detail-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button>
            </div>
        </div>
    </div>
</div>
</script>

<script type="application/json" id="ipc-funnel-detail-data"><?php echo wp_json_encode( $ipc_detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<script>
(function ($) {
    'use strict';
    window.ipcFunnelDetailModal = function () {
        var el = document.getElementById('ipc-funnel-detail-modal');
        if (!el || typeof bootstrap === 'undefined') { return null; }
        return bootstrap.Modal.getOrCreateInstance(el);
    };

    var LABELS = {
        client_name: '<?php echo esc_js( __( 'Cliente', 'imovel-parceiro-core' ) ); ?>',
        client_phone: '<?php echo esc_js( __( 'Telefone', 'imovel-parceiro-core' ) ); ?>',
        client_email: '<?php echo esc_js( __( 'E-mail', 'imovel-parceiro-core' ) ); ?>',
        interest: '<?php echo esc_js( __( 'Interesse', 'imovel-parceiro-core' ) ); ?>',
        estimated_value: '<?php echo esc_js( __( 'Valor estimado', 'imovel-parceiro-core' ) ); ?>',
        amount: '<?php echo esc_js( __( 'Valor', 'imovel-parceiro-core' ) ); ?>',
        conditions: '<?php echo esc_js( __( 'Condições', 'imovel-parceiro-core' ) ); ?>',
        visit_date: '<?php echo esc_js( __( 'Data da visita', 'imovel-parceiro-core' ) ); ?>',
        visit_time: '<?php echo esc_js( __( 'Horário', 'imovel-parceiro-core' ) ); ?>',
        participants: '<?php echo esc_js( __( 'Participantes', 'imovel-parceiro-core' ) ); ?>',
        result: '<?php echo esc_js( __( 'Resultado', 'imovel-parceiro-core' ) ); ?>',
        reason: '<?php echo esc_js( __( 'Motivo', 'imovel-parceiro-core' ) ); ?>',
        notes: '<?php echo esc_js( __( 'Observações', 'imovel-parceiro-core' ) ); ?>',
        previous: '<?php echo esc_js( __( 'Status anterior', 'imovel-parceiro-core' ) ); ?>',
        final_value: '<?php echo esc_js( __( 'Valor da negociação', 'imovel-parceiro-core' ) ); ?>',
        commission: '<?php echo esc_js( __( 'Comissão', 'imovel-parceiro-core' ) ); ?>',
        sale_date: '<?php echo esc_js( __( 'Data do encerramento', 'imovel-parceiro-core' ) ); ?>',
        proposal_date: '<?php echo esc_js( __( 'Data da proposta', 'imovel-parceiro-core' ) ); ?>',
        first_contact_at: '<?php echo esc_js( __( 'Primeiro contato', 'imovel-parceiro-core' ) ); ?>',
        released_by: '<?php echo esc_js( __( 'Liberado por', 'imovel-parceiro-core' ) ); ?>',
        status: '<?php echo esc_js( __( 'Status', 'imovel-parceiro-core' ) ); ?>'
    };

    var FIXED_ROWS = [
        ['property_title', '<?php echo esc_js( __( 'Imóvel', 'imovel-parceiro-core' ) ); ?>'],
        ['owner_name', '<?php echo esc_js( __( 'Corretor responsável', 'imovel-parceiro-core' ) ); ?>'],
        ['requester_name', '<?php echo esc_js( __( 'Corretor parceiro', 'imovel-parceiro-core' ) ); ?>'],
        ['actor', '<?php echo esc_js( __( 'Usuário responsável', 'imovel-parceiro-core' ) ); ?>'],
        ['created', '<?php echo esc_js( __( 'Data/hora', 'imovel-parceiro-core' ) ); ?>']
    ];

    function readData() {
        var el = document.getElementById('ipc-funnel-detail-data');
        if (!el) { return null; }
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function detailRows(key, data) {
        var step = (data.steps && data.steps[key]) || null;
        var rows = [];

        if (key === 'won') {
            rows.push(['commission', '<b><?php echo esc_js( __( 'A comissão deve ser dividida conforme as regras da parceria.', 'imovel-parceiro-core' ) ); ?></b>']);
        }

        FIXED_ROWS.forEach(function (pair) {
            var val = pair[0] === 'property_title' ? data.property_title
                : pair[0] === 'owner_name' ? data.owner_name
                : pair[0] === 'requester_name' ? data.requester_name
                : (step ? step[pair[0]] : '');
            if (val && String(val).trim() !== '') {
                rows.push([pair[1], val]);
            }
        });

        if (step && step.meta) {
            Object.keys(LABELS).forEach(function (key) {
                if (step.meta[key] && String(step.meta[key]).trim() !== '') {
                    rows.push([LABELS[key], step.meta[key]]);
                }
            });
        }

        if (!rows.length) {
            return null;
        }
        return rows;
    }

    function open(key) {
        var data = readData();
        var modal = window.ipcFunnelDetailModal();
        if (!data || !modal) { return; }

        var step = (data.steps && data.steps[key]) || null;
        var title = step ? (step.title || key) : key;
        var rows = detailRows(key, data);
        var html = '';

        if (rows) {
            html += '<dl class="ipc-funnel-dl" style="margin:0;">';
            rows.forEach(function (pair) {
                html += '<div><dt>' + esc(pair[0]) + '</dt><dd>' + esc(pair[1]) + '</dd></div>';
            });
            html += '</dl>';
            if (step && step.note) { html += '<p class="ipc-funnel-muted mt-2">' + esc(step.note) + '</p>'; }
        } else {
            html += '<p class="ipc-funnel-muted"><?php echo esc_js( __( 'Esta etapa ainda não possui informações registradas.', 'imovel-parceiro-core' ) ); ?></p>';
        }

        $('#ipc-funnel-detail-title').text(title);
        $('#ipc-funnel-detail-body').html(html);
        modal.show();
    }

    function esc(v) { return $('<div>').text(v === null || v === undefined ? '' : String(v)).html(); }

    $(document).on('click', '.ipc-step-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        open($(this).data('ipc-step'));
    });

    $(function () {
        if (!window.ipcFunnelDetailModal) {
            window.ipcFunnelDetailModal = function () {
                var el = document.getElementById('ipc-funnel-detail-modal');
                if (!el || typeof bootstrap === 'undefined') { return null; }
                return bootstrap.Modal.getOrCreateInstance(el);
            };
        }
    });
})(jQuery);
</script>

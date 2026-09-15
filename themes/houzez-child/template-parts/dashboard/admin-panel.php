<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'imovel_parceiro_manage_owner_workflow' ) ) {
    return;
}

global $wpdb;

$dashboard_url = houzez_get_template_link_2( 'template/user_dashboard.php' );
$current_area = isset( $_GET['imovel_admin_area'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) : '';
if ( ! in_array( $current_area, array( 'auditoria', 'gestao', 'parcerias' ), true ) ) {
    $current_area = isset( $_GET['imovel_admin_section'] ) ? 'gestao' : '';
}

$current_management_section = isset( $_GET['imovel_admin_section'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_section'] ) ) : '';

$audit_table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
$partnership_table = $wpdb->prefix . 'imovel_parceiro_partnerships';
$broker_change_table = $wpdb->prefix . 'imovel_parceiro_broker_change_requests';
$documents_table = $wpdb->prefix . 'imovel_parceiro_owner_documents';
$audit_table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) );
$broker_change_table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $broker_change_table ) );
$documents_table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $documents_table ) );

$audit_user_filter = isset( $_GET['audit_user_search'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_user_search'] ) ) : '';
$audit_property_filter = isset( $_GET['audit_property_search'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_property_search'] ) ) : '';
$audit_event_filter = isset( $_GET['audit_event_type'] ) ? sanitize_key( wp_unslash( $_GET['audit_event_type'] ) ) : '';
$audit_start_filter = isset( $_GET['audit_start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_start_date'] ) ) : '';
$audit_end_filter = isset( $_GET['audit_end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_end_date'] ) ) : '';

$audit_user_suggestions = get_users(
    array(
        'number' => 20,
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => array( 'ID', 'display_name', 'user_email', 'user_login' ),
    )
);

$audit_property_suggestions = get_posts(
    array(
        'post_type' => 'property',
        'post_status' => array( 'publish', 'pending', 'draft', 'private', 'future' ),
        'posts_per_page' => 20,
        'orderby' => 'title',
        'order' => 'ASC',
    )
);

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $audit_start_filter ) ) {
    $audit_start_filter = '';
}

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $audit_end_filter ) ) {
    $audit_end_filter = '';
}

$summary_cards = array(
    array(
        'label' => __( 'Solicitações pendentes', 'imovel-parceiro-core' ),
        'value' => 0,
        'color' => '#856404',
        'background' => '#fff3cd',
    ),
    array(
        'label' => __( 'Parcerias aceitas', 'imovel-parceiro-core' ),
        'value' => 0,
        'color' => '#0f5132',
        'background' => '#d1e7dd',
    ),
    array(
        'label' => __( 'Parcerias canceladas', 'imovel-parceiro-core' ),
        'value' => 0,
        'color' => '#842029',
        'background' => '#f8d7da',
    ),
    array(
        'label' => __( 'Eventos auditados', 'imovel-parceiro-core' ),
        'value' => 0,
        'color' => '#084298',
        'background' => '#cfe2ff',
    ),
);

if ( $audit_table_exists ) {
    $summary_cards[3]['value'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$audit_table}" );
}

$pending_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$partnership_table} WHERE status = 'pending'" );
$accepted_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$partnership_table} WHERE status IN ('accepted','active','negotiating','won','lost')" );
$cancelled_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$partnership_table} WHERE status = 'cancelled'" );
$summary_cards[0]['value'] = $pending_count;
$summary_cards[1]['value'] = $accepted_count;
$summary_cards[2]['value'] = $cancelled_count;

$broker_change_pending_count = 0;
$broker_change_requests = array();
if ( $broker_change_table_exists ) {
    $broker_change_pending_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$broker_change_table} WHERE status = 'pendente'" );
    $broker_change_requests = $wpdb->get_results( "SELECT * FROM {$broker_change_table} ORDER BY created_at DESC, id DESC LIMIT 20" );
}

$doc_status_labels = array(
    'all' => __( 'Todos', 'imovel-parceiro-core' ),
    'enviado' => __( 'Pendente', 'imovel-parceiro-core' ),
    'aprovado' => __( 'Aprovado', 'imovel-parceiro-core' ),
    'rejeitado' => __( 'Rejeitado', 'imovel-parceiro-core' ),
    'aguardando_informacoes' => __( 'Aguardando informações adicionais', 'imovel-parceiro-core' ),
);
$doc_status_filter = isset( $_GET['imovel_doc_status'] ) ? sanitize_key( wp_unslash( $_GET['imovel_doc_status'] ) ) : 'all';
if ( ! isset( $doc_status_labels[ $doc_status_filter ] ) ) {
    $doc_status_filter = 'all';
}
$doc_search_owner = isset( $_GET['imovel_doc_owner'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_doc_owner'] ) ) : '';
$doc_search_property = isset( $_GET['imovel_doc_property'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_doc_property'] ) ) : '';
$doc_filter_type = isset( $_GET['imovel_doc_type'] ) ? sanitize_key( wp_unslash( $_GET['imovel_doc_type'] ) ) : '';
$doc_filter_start = isset( $_GET['imovel_doc_start'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_doc_start'] ) ) : '';
$doc_filter_end = isset( $_GET['imovel_doc_end'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_doc_end'] ) ) : '';
$doc_paged = max( 1, isset( $_GET['imovel_doc_paged'] ) ? absint( wp_unslash( $_GET['imovel_doc_paged'] ) ) : 1 );
$doc_per_page = max( 5, min( 50, isset( $_GET['imovel_doc_per_page'] ) ? absint( wp_unslash( $_GET['imovel_doc_per_page'] ) ) : 20 ) );
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $doc_filter_start ) ) {
    $doc_filter_start = '';
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $doc_filter_end ) ) {
    $doc_filter_end = '';
}

$doc_rows = array();
$doc_total = 0;
$doc_total_pages = 1;
$doc_counts = array(
    'enviado' => 0,
    'aprovado' => 0,
    'rejeitado' => 0,
    'aguardando_informacoes' => 0,
);
$doc_type_options = array();

if ( $documents_table_exists ) {
    $doc_counts['enviado'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$documents_table} WHERE status = 'enviado'" );
    $doc_counts['aprovado'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$documents_table} WHERE status = 'aprovado'" );
    $doc_counts['rejeitado'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$documents_table} WHERE status = 'rejeitado'" );
    $doc_counts['aguardando_informacoes'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$documents_table} WHERE status = 'aguardando_informacoes'" );

    $doc_type_options = $wpdb->get_col( "SELECT DISTINCT doc_type FROM {$documents_table} ORDER BY doc_type ASC" );

    $doc_where = array( '1=1' );
    $doc_params = array();

    if ( 'all' !== $doc_status_filter ) {
        $doc_where[] = 'd.status = %s';
        $doc_params[] = $doc_status_filter;
    }

    if ( '' !== $doc_search_owner ) {
        $like_owner = '%' . $wpdb->esc_like( $doc_search_owner ) . '%';
        $doc_where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
        $doc_params[] = $like_owner;
        $doc_params[] = $like_owner;
    }

    if ( '' !== $doc_search_property ) {
        $like_property = '%' . $wpdb->esc_like( $doc_search_property ) . '%';
        $doc_where[] = 'p.post_title LIKE %s';
        $doc_params[] = $like_property;
    }

    if ( '' !== $doc_filter_type ) {
        $doc_where[] = 'd.doc_type = %s';
        $doc_params[] = $doc_filter_type;
    }

    if ( '' !== $doc_filter_start ) {
        $doc_where[] = 'DATE(d.submitted_at) >= %s';
        $doc_params[] = $doc_filter_start;
    }

    if ( '' !== $doc_filter_end ) {
        $doc_where[] = 'DATE(d.submitted_at) <= %s';
        $doc_params[] = $doc_filter_end;
    }

    $doc_count_sql = 'SELECT COUNT(*)
        FROM ' . $documents_table . ' d
        LEFT JOIN ' . $wpdb->users . ' u ON u.ID = d.owner_user_id
        LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.property_id
        WHERE ' . implode( ' AND ', $doc_where );

    if ( ! empty( $doc_params ) ) {
        $doc_count_sql = $wpdb->prepare( $doc_count_sql, $doc_params );
    }

    $doc_total = (int) $wpdb->get_var( $doc_count_sql );
    $doc_total_pages = max( 1, (int) ceil( $doc_total / $doc_per_page ) );
    if ( $doc_paged > $doc_total_pages ) {
        $doc_paged = $doc_total_pages;
    }

    $doc_offset = ( $doc_paged - 1 ) * $doc_per_page;
    $doc_sql = 'SELECT d.*, u.display_name AS owner_name, u.user_email AS owner_email, p.post_title AS property_title
        FROM ' . $documents_table . ' d
        LEFT JOIN ' . $wpdb->users . ' u ON u.ID = d.owner_user_id
        LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.property_id
        WHERE ' . implode( ' AND ', $doc_where ) . '
        ORDER BY d.submitted_at DESC, d.id DESC
        LIMIT %d OFFSET %d';

    $doc_query_params = $doc_params;
    $doc_query_params[] = $doc_per_page;
    $doc_query_params[] = $doc_offset;
    $doc_sql = $wpdb->prepare( $doc_sql, $doc_query_params );
    $doc_rows = $wpdb->get_results( $doc_sql );
}

$audit_rows = array();
if ( $audit_table_exists ) {
    $audit_where = array( '1=1' );
    $audit_params = array();

    if ( '' !== $audit_user_filter ) {
        $audit_user_like = '%' . $wpdb->esc_like( $audit_user_filter ) . '%';
        $audit_where[] = '(
            u1.display_name LIKE %s
            OR u1.user_login LIKE %s
            OR u1.user_email LIKE %s
            OR u2.display_name LIKE %s
            OR u2.user_login LIKE %s
            OR u2.user_email LIKE %s
        )';
        for ( $i = 0; $i < 6; $i++ ) {
            $audit_params[] = $audit_user_like;
        }
    }

    if ( '' !== $audit_property_filter ) {
        $audit_property_like = '%' . $wpdb->esc_like( $audit_property_filter ) . '%';
        $audit_where[] = 'p.post_title LIKE %s';
        $audit_params[] = $audit_property_like;
    }

    if ( '' !== $audit_event_filter ) {
        $audit_where[] = 'al.event_type = %s';
        $audit_params[] = $audit_event_filter;
    }

    if ( '' !== $audit_start_filter ) {
        $audit_where[] = 'DATE(al.created_at) >= %s';
        $audit_params[] = $audit_start_filter;
    }

    if ( '' !== $audit_end_filter ) {
        $audit_where[] = 'DATE(al.created_at) <= %s';
        $audit_params[] = $audit_end_filter;
    }

    $audit_sql = "SELECT al.*, u1.display_name AS actor_name, u1.user_email AS actor_email, u2.display_name AS other_name, u2.user_email AS other_email, p.post_title AS property_title
         FROM {$audit_table} al
         LEFT JOIN {$wpdb->users} u1 ON u1.ID = al.actor_user_id
         LEFT JOIN {$wpdb->users} u2 ON u2.ID = al.other_user_id
         LEFT JOIN {$wpdb->posts} p ON p.ID = al.property_id
         WHERE " . implode( ' AND ', $audit_where ) . "
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT 50";

    if ( ! empty( $audit_params ) ) {
        $audit_sql = $wpdb->prepare( $audit_sql, $audit_params );
    }

    $audit_rows = $wpdb->get_results( $audit_sql );
}

$event_labels = array(
    // Parcerias.
    'partnership_requested' => __( 'Solicitação de parceria enviada (botão do imóvel)', 'imovel-parceiro-core' ),
    'partnership_status_updated' => __( 'Decisão da parceria (aceite/recusa pelo anunciante)', 'imovel-parceiro-core' ),
    'partnership_status_changed' => __( 'Etapa da parceria alterada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_cancelled' => __( 'Parceria encerrada (painel de parcerias)', 'imovel-parceiro-core' ),
    'partnership_accepted' => __( 'Parceria aceita (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_rejected' => __( 'Parceria recusada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_negotiating' => __( 'Negociação iniciada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_contact_released' => __( 'Contato liberado (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_opportunity' => __( 'Oportunidade registrada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_visit' => __( 'Visita registrada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_proposal' => __( 'Proposta registrada (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_won' => __( 'Negócio ganho (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_lost' => __( 'Oportunidade perdida (funil da parceria)', 'imovel-parceiro-core' ),
    'partnership_closed' => __( 'Parceria encerrada (funil da parceria)', 'imovel-parceiro-core' ),
    // Conta / cadastro.
    'user_terms_accepted' => __( 'Aceite do termo ao criar conta (aceite do termo de cadastro)', 'imovel-parceiro-core' ),
    // Assinaturas.
    'subscription_activated' => __( 'Assinatura ativada (pagamento via WooCommerce)', 'imovel-parceiro-core' ),
    'subscription_expiration_5_days' => __( 'Aviso de vencimento enviado (5 dias antes da assinatura)', 'imovel-parceiro-core' ),
    // Fluxo do proprietário.
    'owner_property_registered' => __( 'Imóvel cadastrado pelo proprietário (fluxo do proprietário)', 'imovel-parceiro-core' ),
    'owner_document_submitted' => __( 'Documento enviado pelo proprietário (análise de documentação)', 'imovel-parceiro-core' ),
    'owner_requested_broker_change' => __( 'Troca de corretor solicitada (painel do proprietário)', 'imovel-parceiro-core' ),
    'admin_processed_broker_change' => __( 'Troca de corretor analisada (gestão administrativa)', 'imovel-parceiro-core' ),
    'admin_reviewed_property_document' => __( 'Documento do imóvel analisado (gestão administrativa)', 'imovel-parceiro-core' ),
    'owner_requested_property_deletion' => __( 'Exclusão de imóvel solicitada (painel do proprietário)', 'imovel-parceiro-core' ),
    'admin_processed_property_deletion_request' => __( 'Exclusão de imóvel analisada (gestão administrativa)', 'imovel-parceiro-core' ),
);

$management_tabs = array(
    'agents' => array(
        'label' => __( 'Corretores', 'imovel-parceiro-core' ),
        'section' => 'agents',
        'icon' => 'dashicons-admin-users',
        'description' => __( 'Editar dados dos corretores cadastrados.', 'imovel-parceiro-core' ),
    ),
    'packages' => array(
        'label' => __( 'Planos', 'imovel-parceiro-core' ),
        'section' => 'packages',
        'icon' => 'dashicons-cart',
        'description' => __( 'Ajustar pacotes e condições de assinatura.', 'imovel-parceiro-core' ),
    ),
    'verification_requests' => array(
        'label' => __( 'Verificação', 'imovel-parceiro-core' ),
        'section' => 'verification_requests',
        'icon' => 'dashicons-yes-alt',
        'description' => __( 'Analisar solicitações de verificação de usuários.', 'imovel-parceiro-core' ),
    ),
    'owner_documents' => array(
        'label' => __( 'Análise de Documentação', 'imovel-parceiro-core' ),
        'section' => 'owner_documents',
        'icon' => 'dashicons-media-document',
        'description' => __( 'Visualizar e analisar documentação enviada pelos proprietários.', 'imovel-parceiro-core' ),
    ),
    'broker_changes' => array(
        'label' => __( 'Trocas de corretor', 'imovel-parceiro-core' ),
        'section' => 'broker_changes',
        'icon' => 'dashicons-randomize',
        'description' => __( 'Revisar solicitações de troca de corretor enviadas pelos proprietários.', 'imovel-parceiro-core' ),
    ),
    'testimonials' => array(
        'label' => __( 'Depoimentos', 'imovel-parceiro-core' ),
        'section' => 'testimonials',
        'icon' => 'dashicons-format-quote',
        'description' => __( 'Editar depoimentos publicados no site.', 'imovel-parceiro-core' ),
    ),
    'watermark' => array(
        'label' => __( 'Marca d\'água', 'imovel-parceiro-core' ),
        'section' => 'watermark',
        'icon' => 'dashicons-format-image',
        'description' => __( 'Configurar a marca d\'água automática para novas fotos de imóveis.', 'imovel-parceiro-core' ),
    ),
    'inatividade' => array(
        'label' => __( 'Inatividade', 'imovel-parceiro-core' ),
        'section' => 'inatividade',
        'icon' => 'dashicons-clock',
        'description' => __( 'Configurar aviso e desativação automática de imóveis sem atualização.', 'imovel-parceiro-core' ),
    ),
);

$admin_management_sections = array( 'agents', 'agencies', 'packages', 'reviews', 'testimonials', 'approval', 'verification_requests' );
$child_management_sections = array(
    'owner_documents' => __( 'Documentação', 'imovel-parceiro-core' ),
    'broker_changes' => __( 'Trocas de corretor', 'imovel-parceiro-core' ),
    'watermark' => __( 'Marca d\'água', 'imovel-parceiro-core' ),
    'inatividade' => __( 'Inatividade', 'imovel-parceiro-core' ),
);
$cadastros_active = ( '' === $current_management_section ) || in_array( $current_management_section, $admin_management_sections, true );

function imovel_parceiro_admin_decode_meta( $raw_meta ) {
    $meta = maybe_unserialize( $raw_meta );
    if ( ! is_array( $meta ) ) {
        return array();
    }

    if ( isset( $meta['meta'] ) && is_array( $meta['meta'] ) ) {
        $meta = array_merge( $meta, $meta['meta'] );
    }

    return $meta;
}

function imovel_parceiro_admin_status_label( $status ) {
    $labels = array(
        'pending' => __( 'Pendente', 'imovel-parceiro-core' ),
        'accepted' => __( 'Aceita', 'imovel-parceiro-core' ),
        'active' => __( 'Ativa', 'imovel-parceiro-core' ),
        'negotiating' => __( 'Em negociação', 'imovel-parceiro-core' ),
        'won' => __( 'Ganha', 'imovel-parceiro-core' ),
        'lost' => __( 'Perdida', 'imovel-parceiro-core' ),
        'cancelled' => __( 'Cancelada', 'imovel-parceiro-core' ),
        'rejected' => __( 'Recusada', 'imovel-parceiro-core' ),
    );

    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Turn raw audit meta into human-readable lines. Internal identifiers are kept
 * but explained: "Plano (package_id): Pro" instead of a bare numeric id.
 */
function imovel_parceiro_admin_humanize_details( $meta, $event_key = '' ) {
    $details = array();

    if ( ! empty( $meta['previous_status'] ) || ! empty( $meta['new_status'] ) ) {
        $details[] = sprintf(
            '%s: %s -> %s',
            __( 'Status', 'imovel-parceiro-core' ),
            imovel_parceiro_admin_status_label( isset( $meta['previous_status'] ) ? $meta['previous_status'] : '' ),
            imovel_parceiro_admin_status_label( isset( $meta['new_status'] ) ? $meta['new_status'] : '' )
        );
    }

    if ( ! empty( $meta['message'] ) ) {
        $details[] = $meta['message'];
    }

    if ( ! empty( $meta['reason'] ) ) {
        $details[] = sprintf( __( 'Motivo: %s', 'imovel-parceiro-core' ), $meta['reason'] );
    }

    if ( ! empty( $meta['package_id'] ) ) {
        $package_id = absint( $meta['package_id'] );
        $package_title = $package_id ? get_the_title( $package_id ) : '';
        $details[] = sprintf( __( 'Plano (package_id): %s', 'imovel-parceiro-core' ), $package_title ? $package_title : ( '#' . $package_id ) );
    }

    if ( ! empty( $meta['order_id'] ) ) {
        $details[] = sprintf( __( 'Pedido WooCommerce (order_id): #%s', 'imovel-parceiro-core' ), absint( $meta['order_id'] ) );
    }

    if ( ! empty( $meta['billing_type'] ) ) {
        $details[] = sprintf( __( 'Ciclo de cobrança (billing_type): %s', 'imovel-parceiro-core' ), $meta['billing_type'] );
    }

    if ( ! empty( $meta['payment_method'] ) ) {
        $details[] = sprintf( __( 'Forma de pagamento (payment_method): %s', 'imovel-parceiro-core' ), $meta['payment_method'] );
    }

    if ( ! empty( $meta['expiry_local'] ) ) {
        $details[] = sprintf( __( 'Vencimento (expiry_local): %s', 'imovel-parceiro-core' ), $meta['expiry_local'] );
    }

    if ( ! empty( $meta['person_type'] ) ) {
        $details[] = sprintf( __( 'Tipo de pessoa (person_type): %s', 'imovel-parceiro-core' ), strtoupper( (string) $meta['person_type'] ) );
    }

    if ( ! empty( $meta['document'] ) ) {
        $details[] = sprintf( __( 'Documento (document): %s', 'imovel-parceiro-core' ), $meta['document'] );
    }

    if ( ! empty( $meta['released_by'] ) ) {
        $released_user = get_userdata( absint( $meta['released_by'] ) );
        $details[] = sprintf(
            __( 'Contato liberado por (released_by): %s', 'imovel-parceiro-core' ),
            $released_user ? $released_user->display_name : ( '#' . absint( $meta['released_by'] ) )
        );
    }

    if ( empty( $details ) && '' !== $event_key ) {
        $details[] = __( 'Evento registrado pelo sistema.', 'imovel-parceiro-core' );
    }

    return $details;
}
?>

<style>
    .imovel-parceiro-admin-shell { max-width: 100%; }
    .imovel-parceiro-admin-header { min-width: 0; }
    .imovel-parceiro-admin-header h4 {
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #0f172a;
    }
    .imovel-parceiro-admin-header p { color: #64748b; font-size: 14px; }
    .imovel-parceiro-admin-areas {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 4px;
        margin: 0 0 24px;
        padding: 4px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }
    .imovel-parceiro-admin-areas__item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        white-space: nowrap;
        transition: all .15s ease;
    }
    .imovel-parceiro-admin-areas__item:hover { color: #0f172a; text-decoration: none; }
    .imovel-parceiro-admin-areas__item.is-active {
        background: #fff;
        color: #4338ca;
        box-shadow: 0 2px 8px rgba(15,23,42,.10);
    }
    .imovel-parceiro-admin-section-content { min-width: 0; }
    .imovel-parceiro-admin-section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .imovel-parceiro-admin-section-head h5 { margin: 0 0 4px; color: #0f172a; }
    .imovel-parceiro-admin-section-head p { margin: 0; color: #64748b; }
    .imovel-parceiro-admin-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 20px;
        min-width: 0;
    }
    .imovel-parceiro-admin-subnav__item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        background: #fff;
        text-decoration: none;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .imovel-parceiro-admin-subnav__item:hover { border-color: #cbd5e1; color: #0f172a; text-decoration: none; }
    .imovel-parceiro-admin-subnav__item.is-active {
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 12px -4px rgba(99,102,241,.5);
    }
    @media (max-width: 767px) {
        .imovel-parceiro-admin-areas { width: 100%; }
        .imovel-parceiro-admin-areas__item { flex: 1; justify-content: center; }
        .imovel-parceiro-admin-subnav { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 6px; margin-right: -4px; padding-right: 4px; }
    }
</style>
<div class="imovel-parceiro-admin-shell">
    <div class="imovel-parceiro-admin-header rounded-2xl border border-slate-100 bg-white px-5 py-4 shadow-sm mb-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h4 style="margin:0 0 4px;"><?php esc_html_e( 'Dashboard do Administrador', 'imovel-parceiro-core' ); ?></h4>
                <p style="margin:0;"><?php esc_html_e( 'Acompanhe a atividade de parcerias e mantenha o controle operacional do dashboard.', 'imovel-parceiro-core' ); ?></p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
                <?php echo houzez_dash_icon( 'shield-check', 'h-3.5 w-3.5' ); ?>
                <?php esc_html_e( 'Controle operacional', 'imovel-parceiro-core' ); ?>
            </span>
        </div>
    </div>

    <div class="imovel-parceiro-admin-areas" role="tablist" aria-label="<?php esc_attr_e( 'Áreas administrativas', 'imovel-parceiro-core' ); ?>">
        <a class="imovel-parceiro-admin-areas__item <?php echo 'parcerias' === $current_area ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'parcerias' ), $dashboard_url ) ); ?>">
            <?php echo houzez_dash_icon( 'handshake', 'h-4 w-4' ); ?>
            <?php esc_html_e( 'Parcerias', 'imovel-parceiro-core' ); ?>
            <?php if ( class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) : $ipc_open_alerts = Imovel_Parceiro_Partnership_Integrity::count_open_alerts(); ?>
                <?php if ( $ipc_open_alerts > 0 ) : ?><span class="rounded-full bg-rose-100 px-2 text-[11px] font-bold text-rose-700"><?php echo esc_html( number_format_i18n( $ipc_open_alerts ) ); ?></span><?php endif; ?>
            <?php endif; ?>
        </a>
        <a class="imovel-parceiro-admin-areas__item <?php echo 'auditoria' === $current_area ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'auditoria' ), $dashboard_url ) ); ?>">
            <?php echo houzez_dash_icon( 'shield-check', 'h-4 w-4' ); ?>
            <?php esc_html_e( 'Auditoria', 'imovel-parceiro-core' ); ?>
        </a>
        <a class="imovel-parceiro-admin-areas__item <?php echo 'gestao' === $current_area ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao' ), $dashboard_url ) ); ?>">
            <?php echo houzez_dash_icon( 'settings', 'h-4 w-4' ); ?>
            <?php esc_html_e( 'Gestão', 'imovel-parceiro-core' ); ?>
        </a>
    </div>

    <?php if ( 'gestao' === $current_area ) : ?>
        <?php
        $ipc_gestao_sections = array(
            '' => __( 'Cadastros', 'imovel-parceiro-core' ),
            'agents' => __( 'Corretores', 'imovel-parceiro-core' ),
            'agencies' => __( 'Imobiliárias', 'imovel-parceiro-core' ),
            'verification_requests' => __( 'Verificação', 'imovel-parceiro-core' ),
            'packages' => __( 'Planos', 'imovel-parceiro-core' ),
            'coupons' => __( 'Cupons', 'imovel-parceiro-core' ),
            'orcamentos' => __( 'Orçamentos', 'imovel-parceiro-core' ),
            'reviews' => __( 'Avaliações', 'imovel-parceiro-core' ),
            'testimonials' => __( 'Depoimentos', 'imovel-parceiro-core' ),
            'approval' => __( 'Aprovações', 'imovel-parceiro-core' ),
            'owner_documents' => __( 'Documentação', 'imovel-parceiro-core' ),
            'broker_changes' => __( 'Trocas de corretor', 'imovel-parceiro-core' ),
            'watermark' => __( 'Marca d\'água', 'imovel-parceiro-core' ),
            'inatividade' => __( 'Inatividade', 'imovel-parceiro-core' ),
            'duplicados' => __( 'Imóveis duplicados', 'imovel-parceiro-core' ),
        );

        $ipc_pending_property_count = (int) wp_count_posts( 'property' )->pending;

        $ipc_rail_groups = array(
            __( 'Cadastros', 'imovel-parceiro-core' ) => array(
                '' => array( 'layout-dashboard', 'is-active' ),
            ),
            __( 'Pessoas', 'imovel-parceiro-core' ) => array(
                'agents' => array( 'users' ),
                'agencies' => array( 'briefcase' ),
                'verification_requests' => array( 'badge-check' ),
            ),
            __( 'Planos & conteúdo', 'imovel-parceiro-core' ) => array(
                'packages' => array( 'package' ),
                'coupons' => array( 'percent' ),
                'orcamentos' => array( 'message-circle' ),
                'reviews' => array( 'star' ),
                'testimonials' => array( 'quote' ),
            ),
            __( 'Imóveis', 'imovel-parceiro-core' ) => array(
                'approval' => array( 'clipboard-check' ),
            ),
            __( 'Operação', 'imovel-parceiro-core' ) => array(
                'owner_documents' => array( 'file-text' ),
                'broker_changes' => array( 'arrow-left-right' ),
                'inatividade' => array( 'timer' ),
                'duplicados' => array( 'copy' ),
            ),
            __( 'Design', 'imovel-parceiro-core' ) => array(
                'watermark' => array( 'droplets' ),
            ),
        );

        $ipc_rail_badges = array(
            'owner_documents' => (int) $doc_counts['enviado'] + (int) $doc_counts['aguardando_informacoes'],
            'broker_changes' => $broker_change_pending_count,
            'approval' => $ipc_pending_property_count,
        );

        if ( class_exists( 'Imovel_Parceiro_Package_Extras' ) ) {
            $ipc_new_leads = get_posts(
                array(
                    'post_type'      => Imovel_Parceiro_Package_Extras::LEAD_CPT,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => Imovel_Parceiro_Package_Extras::LEAD_STATUS_META,
                            'value'   => 'atendido',
                            'compare' => '!=',
                        ),
                    ),
                )
            );
            $ipc_rail_badges['orcamentos'] = count( $ipc_new_leads );
        }
        ?>
        <div class="imovel-parceiro-admin-section-content">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <nav class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-slate-400" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'imovel-parceiro-core' ); ?>">
                        <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 transition-colors hover:bg-slate-100 hover:text-slate-600">
                            <?php esc_html_e( 'Gestão', 'imovel-parceiro-core' ); ?>
                        </a>
                        <span aria-hidden="true">/</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                            <?php echo esc_html( isset( $ipc_gestao_sections[ $current_management_section ] ) ? $ipc_gestao_sections[ $current_management_section ] : __( 'Cadastros', 'imovel-parceiro-core' ) ); ?>
                        </span>
                    </nav>
                    <h4 class="text-xl font-bold tracking-tight text-slate-900"><?php echo esc_html( isset( $ipc_gestao_sections[ $current_management_section ] ) ? $ipc_gestao_sections[ $current_management_section ] : __( 'Central de Cadastros', 'imovel-parceiro-core' ) ); ?></h4>
                    <p class="mt-1 text-sm text-slate-500">
                        <?php if ( 'owner_documents' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Visualize e analise a documentação enviada pelos proprietários, revise o status e decida com segurança.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'broker_changes' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Analise e processe as solicitações enviadas pelos proprietários antes de alterar o corretor do imóvel.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'watermark' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Configure a marca d\'água para aplicar automaticamente em novas fotos de imóveis.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'inatividade' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Configure o aviso e a desativação automática de imóveis sem atualização.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'duplicados' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Ative ou desative o bloqueio de cadastro de imóveis duplicados.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'orcamentos' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Acompanhe as solicitações de personalização enviadas pelos interessados em planos sob consulta.', 'imovel-parceiro-core' ); ?>
                        <?php elseif ( 'coupons' === $current_management_section ) : ?>
                            <?php esc_html_e( 'Crie e gerencie os cupons de desconto aplicáveis aos planos recorrentes.', 'imovel-parceiro-core' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Central de edição dos cadastros principais da plataforma.', 'imovel-parceiro-core' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'auditoria' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md">
                        <?php echo houzez_dash_icon( 'shield-check', 'h-4 w-4 text-slate-400' ); ?>
                        <?php esc_html_e( 'Ver auditoria', 'imovel-parceiro-core' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( (int) $doc_counts['enviado'] > 0 || $broker_change_pending_count > 0 ) : ?>
                <div class="mb-5 rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                            <?php echo houzez_dash_icon( 'triangle-alert', 'h-5 w-5' ); ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-amber-900"><?php esc_html_e( 'Atenções pendentes aguardando ação', 'imovel-parceiro-core' ); ?></p>
                            <p class="text-xs text-amber-700"><?php esc_html_e( 'Resolva as pendências de documentação e trocas de corretor.', 'imovel-parceiro-core' ); ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ( (int) $doc_counts['enviado'] > 0 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'owner_documents' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-amber-700 shadow-sm ring-1 ring-amber-200 transition-all hover:bg-amber-100">
                                    <?php echo houzez_dash_icon( 'file-text', 'h-3.5 w-3.5' ); ?>
                                    <?php echo esc_html( sprintf( __( 'Documentação: %d', 'imovel-parceiro-core' ), (int) $doc_counts['enviado'] ) ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $broker_change_pending_count > 0 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'broker_changes' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-amber-700 shadow-sm ring-1 ring-amber-200 transition-all hover:bg-amber-100">
                                    <?php echo houzez_dash_icon( 'arrow-left-right', 'h-3.5 w-3.5' ); ?>
                                    <?php echo esc_html( sprintf( __( 'Trocas: %d', 'imovel-parceiro-core' ), $broker_change_pending_count ) ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="lg:flex lg:items-start lg:gap-5">
                <nav class="ipc-admin-rail mb-5 lg:mb-0" aria-label="<?php esc_attr_e( 'Seções de gestão', 'imovel-parceiro-core' ); ?>">
                    <?php foreach ( $ipc_rail_groups as $ipc_rail_label => $ipc_rail_items ) : ?>
                        <div class="ipc-admin-rail-group">
                            <p class="ipc-admin-rail-label"><?php echo esc_html( $ipc_rail_label ); ?></p>
                            <?php foreach ( $ipc_rail_items as $ipc_rail_key => $ipc_rail_item_cfg ) : ?>
                                <?php
                                $ipc_rail_icon = $ipc_rail_item_cfg[0];
                                $ipc_is_current = ( 'is-active' === ( $ipc_rail_item_cfg[1] ?? '' ) );
                                if ( '' === $ipc_rail_key ) {
                                    $ipc_is_current = (bool) $cadastros_active;
                                } else {
                                    $ipc_is_current = $ipc_rail_key === $current_management_section;
                                }
                                $ipc_rail_url = '' === $ipc_rail_key
                                    ? add_query_arg( array( 'imovel_admin_area' => 'gestao' ), $dashboard_url )
                                    : add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => $ipc_rail_key ), $dashboard_url );
                                ?>
                                <a href="<?php echo esc_url( $ipc_rail_url ); ?>" class="ipc-admin-rail-item <?php echo $ipc_is_current ? 'is-active' : ''; ?>">
                                    <?php echo houzez_dash_icon( $ipc_rail_icon, 'ipc-nav-icon' ); ?>
                                    <span class="flex-1 truncate">
                                        <?php echo esc_html( isset( $ipc_gestao_sections[ $ipc_rail_key ] ) ? $ipc_gestao_sections[ $ipc_rail_key ] : '' ); ?>
                                    </span>
                                    <?php if ( isset( $ipc_rail_badges[ $ipc_rail_key ] ) && (int) $ipc_rail_badges[ $ipc_rail_key ] > 0 ) : ?>
                                        <span class="ipc-nav-badge"><?php echo esc_html( number_format_i18n( (int) $ipc_rail_badges[ $ipc_rail_key ] ) ); ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>

                <div class="min-w-0 flex-1">

            <?php if ( 'owner_documents' === $current_management_section ) : ?>
                <div class="dashboard-content-block rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h5 style="margin:0 0 4px;"><?php esc_html_e( 'Aprovação de Documentação', 'imovel-parceiro-core' ); ?></h5>
                            <p style="margin:0; color:#6b7280; font-size:14px;"><?php esc_html_e( 'Visualize e analise a documentação enviada pelos proprietários, revise o status e decida com segurança.', 'imovel-parceiro-core' ); ?></p>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-2"><?php echo esc_html( sprintf( __( '%d pendentes', 'imovel-parceiro-core' ), (int) $doc_counts['enviado'] ) ); ?></span>
                    </div>

                    <?php if ( ! $documents_table_exists ) : ?>
                        <div class="alert alert-warning" style="border-radius:10px;"><i class="houzez-icon icon-information-circle me-1"></i> <?php esc_html_e( 'A tabela de documentos do proprietário ainda não foi criada.', 'imovel-parceiro-core' ); ?></div>
                    <?php else : ?>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <span class="badge bg-warning text-dark"><?php echo esc_html( sprintf( __( '%s pendentes', 'imovel-parceiro-core' ), (int) $doc_counts['enviado'] ) ); ?></span>
                            <span class="badge bg-success"><?php echo esc_html( sprintf( __( '%s aprovados', 'imovel-parceiro-core' ), (int) $doc_counts['aprovado'] ) ); ?></span>
                            <span class="badge bg-danger"><?php echo esc_html( sprintf( __( '%s rejeitados', 'imovel-parceiro-core' ), (int) $doc_counts['rejeitado'] ) ); ?></span>
                            <span class="badge bg-info text-dark"><?php echo esc_html( sprintf( __( '%s aguardando info', 'imovel-parceiro-core' ), (int) $doc_counts['aguardando_informacoes'] ) ); ?></span>
                        </div>

                        <form method="get" style="background:#f8fafc; border:1px solid #e6e6e6; border-radius:12px; padding:16px; margin-bottom:20px;">
                            <input type="hidden" name="imovel_admin_area" value="gestao" />
                            <input type="hidden" name="imovel_admin_section" value="owner_documents" />
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3 col-sm-6">
                                    <label for="imovel_doc_status" class="form-label"><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_doc_status" name="imovel_doc_status" class="form-select">
                                        <?php foreach ( $doc_status_labels as $status_key => $status_label ) : ?>
                                            <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $doc_status_filter, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label for="imovel_doc_owner" class="form-label"><?php esc_html_e( 'Proprietário', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_doc_owner" name="imovel_doc_owner" class="form-control" value="<?php echo esc_attr( $doc_search_owner ); ?>" placeholder="<?php esc_attr_e( 'Nome ou e-mail', 'imovel-parceiro-core' ); ?>" />
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label for="imovel_doc_property" class="form-label"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></label>
                                    <input type="text" id="imovel_doc_property" name="imovel_doc_property" class="form-control" value="<?php echo esc_attr( $doc_search_property ); ?>" placeholder="<?php esc_attr_e( 'Título do imóvel', 'imovel-parceiro-core' ); ?>" />
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label for="imovel_doc_type" class="form-label"><?php esc_html_e( 'Tipo', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_doc_type" name="imovel_doc_type" class="form-select">
                                        <option value=""><?php esc_html_e( 'Todos', 'imovel-parceiro-core' ); ?></option>
                                        <?php foreach ( (array) $doc_type_options as $doc_type_option ) : ?>
                                            <?php $doc_type_option = sanitize_key( (string) $doc_type_option ); ?>
                                            <option value="<?php echo esc_attr( $doc_type_option ); ?>" <?php selected( $doc_filter_type, $doc_type_option ); ?>><?php echo esc_html( $doc_type_option ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label for="imovel_doc_start" class="form-label"><?php esc_html_e( 'Início', 'imovel-parceiro-core' ); ?></label>
                                    <input type="date" id="imovel_doc_start" name="imovel_doc_start" class="form-control" value="<?php echo esc_attr( $doc_filter_start ); ?>" />
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label for="imovel_doc_end" class="form-label"><?php esc_html_e( 'Fim', 'imovel-parceiro-core' ); ?></label>
                                    <input type="date" id="imovel_doc_end" name="imovel_doc_end" class="form-control" value="<?php echo esc_attr( $doc_filter_end ); ?>" />
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label for="imovel_doc_per_page" class="form-label"><?php esc_html_e( 'Itens', 'imovel-parceiro-core' ); ?></label>
                                    <select id="imovel_doc_per_page" name="imovel_doc_per_page" class="form-select">
                                        <?php foreach ( array( 10, 20, 30, 50 ) as $page_size ) : ?>
                                            <option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( $doc_per_page, $page_size ); ?>><?php echo esc_html( $page_size ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1"><?php esc_html_e( 'Filtrar', 'imovel-parceiro-core' ); ?></button>
                                    <a class="btn btn-outline-secondary" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'owner_documents' ), $dashboard_url ) ); ?>"><?php esc_html_e( 'Limpar', 'imovel-parceiro-core' ); ?></a>
                                </div>
                            </div>
                        </form>

                        <?php if ( empty( $doc_rows ) ) : ?>
                            <p class="mb-0 text-muted"><?php esc_html_e( 'Nenhum documento encontrado para os filtros informados.', 'imovel-parceiro-core' ); ?></p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Proprietário', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Documento', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Enviado em', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $doc_rows as $doc_row ) : ?>
                                        <?php
                                        $owner_label = ! empty( $doc_row->owner_name ) ? $doc_row->owner_name : sprintf( __( 'Usuário #%d', 'imovel-parceiro-core' ), absint( $doc_row->owner_user_id ) );
                                        $property_label = ! empty( $doc_row->property_title ) ? $doc_row->property_title : sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $doc_row->property_id ) );
                                        $doc_type = sanitize_key( (string) $doc_row->doc_type );
                                        $submitted_at = ! empty( $doc_row->submitted_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $doc_row->submitted_at ) ) : '-';
                                        $status_key = sanitize_key( (string) $doc_row->status );
                                        $status_label = isset( $doc_status_labels[ $status_key ] ) ? $doc_status_labels[ $status_key ] : ucfirst( str_replace( '_', ' ', $status_key ) );
                                        $status_badge = 'secondary';
                                        if ( 'enviado' === $status_key ) { $status_badge = 'warning text-dark'; }
                                        elseif ( 'aprovado' === $status_key ) { $status_badge = 'success'; }
                                        elseif ( 'rejeitado' === $status_key ) { $status_badge = 'danger'; }
                                        elseif ( 'aguardando_informacoes' === $status_key ) { $status_badge = 'info text-dark'; }
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $owner_label ); ?><br><small class="text-muted"><?php echo esc_html( ! empty( $doc_row->owner_email ) ? $doc_row->owner_email : '' ); ?></small></td>
                                            <td><?php echo esc_html( $property_label ); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-outline-primary btn-sm imovel-doc-open-modal" data-document-id="<?php echo esc_attr( $doc_row->id ); ?>">
                                                    <i class="houzez-icon icon-file-text me-1"></i><?php esc_html_e( 'Ver documento', 'imovel-parceiro-core' ); ?>
                                                </button>
                                                <small class="d-block text-muted mt-1"><?php echo esc_html( $doc_type ); ?></small>
                                            </td>
                                            <td><?php echo esc_html( $submitted_at ); ?></td>
                                            <td><span class="badge bg-<?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm imovel-doc-open-modal" data-document-id="<?php echo esc_attr( $doc_row->id ); ?>"><?php esc_html_e( 'Analisar', 'imovel-parceiro-core' ); ?></button>
                                                    <?php if ( in_array( $status_key, array( 'enviado', 'aguardando_informacoes' ), true ) ) : ?>
                                                        <button type="button" class="btn btn-success btn-sm imovel-doc-review-action" data-document-id="<?php echo esc_attr( $doc_row->id ); ?>" data-decision="approve"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-danger btn-sm imovel-doc-review-action" data-document-id="<?php echo esc_attr( $doc_row->id ); ?>" data-decision="reject"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-warning btn-sm imovel-doc-review-action" data-document-id="<?php echo esc_attr( $doc_row->id ); ?>" data-decision="request_info"><?php esc_html_e( 'Solicitar info', 'imovel-parceiro-core' ); ?></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                            <?php if ( $doc_total_pages > 1 ) : ?>
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; align-items:center;">
                                    <?php for ( $page_i = 1; $page_i <= $doc_total_pages; $page_i++ ) : ?>
                                        <a class="btn btn-sm <?php echo $page_i === $doc_paged ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'owner_documents', 'imovel_doc_status' => $doc_status_filter, 'imovel_doc_owner' => $doc_search_owner, 'imovel_doc_property' => $doc_search_property, 'imovel_doc_type' => $doc_filter_type, 'imovel_doc_start' => $doc_filter_start, 'imovel_doc_end' => $doc_filter_end, 'imovel_doc_per_page' => $doc_per_page, 'imovel_doc_paged' => $page_i ), $dashboard_url ) ); ?>"><?php echo esc_html( $page_i ); ?></a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>

                            <div id="imovel-doc-modal" class="imovel-doc-modal" aria-hidden="true">
                                <div class="imovel-doc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="imovel-doc-modal-title">
                                    <div class="imovel-doc-modal__header">
                                        <h5 id="imovel-doc-modal-title" class="mb-0"><?php esc_html_e( 'Documento', 'imovel-parceiro-core' ); ?></h5>
                                        <button type="button" class="btn btn-light btn-sm imovel-doc-modal-close" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>">&times;</button>
                                    </div>
                                    <div class="imovel-doc-modal__body">
                                        <div id="imovel-doc-modal-meta" class="imovel-doc-modal__meta"></div>
                                        <div id="imovel-doc-modal-viewer" class="imovel-doc-modal__viewer"></div>
                                    </div>
                                    <div class="imovel-doc-modal__footer">
                                        <a id="imovel-doc-modal-download" class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Baixar documento', 'imovel-parceiro-core' ); ?></a>
                                        <button type="button" class="btn btn-secondary imovel-doc-modal-close"><?php esc_html_e( 'Fechar', 'imovel-parceiro-core' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ( 'broker_changes' === $current_management_section ) : ?>
                <div class="rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                        <div>
                            <h5 style="margin:0 0 4px;"><?php esc_html_e( 'Trocas de corretor', 'imovel-parceiro-core' ); ?></h5>
                            <p style="margin:0; color:#666;"><?php esc_html_e( 'Analise e processe as solicitações enviadas pelos proprietários antes de alterar o corretor do imóvel.', 'imovel-parceiro-core' ); ?></p>
                        </div>
                        <div class="badge bg-warning text-dark" style="font-size:14px; padding:10px 12px;"><?php echo esc_html( sprintf( __( '%d pendentes', 'imovel-parceiro-core' ), $broker_change_pending_count ) ); ?></div>
                    </div>

                    <?php if ( ! $broker_change_table_exists ) : ?>
                        <div style="padding:12px 14px; border:1px solid #ffe08a; background:#fff8db; border-radius:8px; color:#6b5200;">
                            <?php esc_html_e( 'A tabela de solicitações de troca de corretor ainda não foi criada.', 'imovel-parceiro-core' ); ?>
                        </div>
                    <?php elseif ( empty( $broker_change_requests ) ) : ?>
                        <p class="mb-0 text-muted"><?php esc_html_e( 'Nenhuma solicitação de troca registrada ainda.', 'imovel-parceiro-core' ); ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table dashboard-table table-lined responsive-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Proprietário', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Corretor atual', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Corretor solicitado', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Motivo', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></th>
                                        <th><?php esc_html_e( 'Ações', 'imovel-parceiro-core' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $broker_change_requests as $request ) : ?>
                                        <?php
                                        $property_title = get_the_title( $request->property_id );
                                        if ( empty( $property_title ) ) {
                                            $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), absint( $request->property_id ) );
                                        }
                                        $owner_user = get_userdata( (int) $request->owner_user_id );
                                        $current_broker_user = ! empty( $request->current_broker_id ) ? get_userdata( (int) $request->current_broker_id ) : false;
                                        $requested_broker_user = ! empty( $request->requested_broker_id ) ? get_userdata( (int) $request->requested_broker_id ) : false;
                                        $owner_label = $owner_user && ! empty( $owner_user->display_name ) ? $owner_user->display_name : sprintf( __( 'Usuário #%d', 'imovel-parceiro-core' ), absint( $request->owner_user_id ) );
                                        $current_broker_label = $current_broker_user && ! empty( $current_broker_user->display_name ) ? $current_broker_user->display_name : '-';
                                        $requested_broker_label = $requested_broker_user && ! empty( $requested_broker_user->display_name ) ? $requested_broker_user->display_name : '-';
                                        $status_label = ! empty( $request->status ) ? ucfirst( str_replace( '_', ' ', $request->status ) ) : '-';
                                        $created_at = ! empty( $request->created_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->created_at ) ) : '-';
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $property_title ); ?></td>
                                            <td><?php echo esc_html( $owner_label ); ?></td>
                                            <td><?php echo esc_html( $current_broker_label ); ?></td>
                                            <td><?php echo esc_html( $requested_broker_label ); ?></td>
                                            <td><?php echo esc_html( ! empty( $request->reason ) ? $request->reason : __( 'Sem observações.', 'imovel-parceiro-core' ) ); ?></td>
                                            <td><?php echo esc_html( $status_label ); ?></td>
                                            <td><?php echo esc_html( $created_at ); ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if ( 'pendente' === $request->status ) : ?>
                                                        <button type="button" class="btn btn-success btn-sm imovel-broker-change-action" data-decision="approve" data-request-id="<?php echo esc_attr( $request->id ); ?>"><?php esc_html_e( 'Aprovar', 'imovel-parceiro-core' ); ?></button>
                                                        <button type="button" class="btn btn-danger btn-sm imovel-broker-change-action" data-decision="reject" data-request-id="<?php echo esc_attr( $request->id ); ?>"><?php esc_html_e( 'Rejeitar', 'imovel-parceiro-core' ); ?></button>
                                                    <?php else : ?>
                                                        <span class="text-muted"><?php esc_html_e( 'Processada', 'imovel-parceiro-core' ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ( 'watermark' === $current_management_section && class_exists( 'Imovel_Parceiro_Watermark' ) ) : ?>
                <?php get_template_part( 'template-parts/dashboard/admin-watermark' ); ?>
            <?php elseif ( 'inatividade' === $current_management_section && class_exists( 'Imovel_Parceiro_Property_Visibility' ) ) : ?>
                <?php Imovel_Parceiro_Property_Visibility::render_dashboard_section(); ?>
            <?php elseif ( 'duplicados' === $current_management_section && class_exists( 'Imovel_Parceiro_Property_Duplicates' ) ) : ?>
                <?php Imovel_Parceiro_Property_Duplicates::render_dashboard_section(); ?>
            <?php elseif ( 'orcamentos' === $current_management_section && class_exists( 'Imovel_Parceiro_Package_Extras' ) ) : ?>
                <?php Imovel_Parceiro_Package_Extras::render_dashboard_section(); ?>
            <?php else : ?>
                <?php get_template_part( 'template-parts/dashboard/admin-management' ); ?>
            <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif ( 'parcerias' === $current_area ) : ?>
        <?php get_template_part( 'template-parts/dashboard/admin-partnerships' ); ?>
    <?php else : ?>
        <?php
        $ipc_audit_nonce = wp_create_nonce( 'ipc_audit_action' );
        $ipc_audit_exported = (int) get_transient( 'ipc_audit_exported_' . get_current_user_id() );
        $ipc_audit_msg = isset( $_GET['imovel_audit_msg'] ) ? sanitize_key( wp_unslash( $_GET['imovel_audit_msg'] ) ) : '';
        ?>
        <div class="imovel-parceiro-admin-section rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <?php if ( '' !== $ipc_audit_msg ) : ?>
                <?php
                $ipc_audit_msg_text = '';
                $ipc_audit_msg_ok = false;
                if ( 'deleted' === $ipc_audit_msg ) {
                    $ipc_audit_msg_text = __( 'Todos os logs de auditoria foram excluídos.', 'imovel-parceiro-core' );
                    $ipc_audit_msg_ok = true;
                } elseif ( 'exported' === $ipc_audit_msg ) {
                    $ipc_audit_msg_text = __( 'Logs exportados com sucesso.', 'imovel-parceiro-core' );
                    $ipc_audit_msg_ok = true;
                } elseif ( 'must_export' === $ipc_audit_msg ) {
                    $ipc_audit_msg_text = __( 'Para excluir os logs, primeiro exporte-os (Excel).', 'imovel-parceiro-core' );
                } elseif ( 'notable' === $ipc_audit_msg ) {
                    $ipc_audit_msg_text = __( 'A tabela de auditoria ainda não existe.', 'imovel-parceiro-core' );
                } elseif ( 'invalid' === $ipc_audit_msg ) {
                    $ipc_audit_msg_text = __( 'Ação inválida ou expirada. Tente novamente.', 'imovel-parceiro-core' );
                }
                ?>
                <?php if ( '' !== $ipc_audit_msg_text ) : ?>
                    <div class="mb-4 inline-flex w-full items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium <?php echo $ipc_audit_msg_ok ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-amber-100 bg-amber-50 text-amber-700'; ?>">
                        <?php echo houzez_dash_icon( $ipc_audit_msg_ok ? 'circle-check' : 'triangle-alert', 'h-4 w-4 shrink-0' ); ?>
                        <?php echo esc_html( $ipc_audit_msg_text ); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h5 style="margin:0 0 4px;"><?php esc_html_e( 'Auditoria', 'imovel-parceiro-core' ); ?></h5>
                    <p style="margin:0; color:#64748b; font-size:14px;"><?php esc_html_e( 'Use filtros rápidos para localizar eventos e cruzar usuário, imóvel e período.', 'imovel-parceiro-core' ); ?></p>
                </div>
                <a class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:shadow-md" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao' ), $dashboard_url ) ); ?>">
                    <?php echo houzez_dash_icon( 'settings', 'h-4 w-4 text-slate-400' ); ?>
                    <?php esc_html_e( 'Abrir gestão', 'imovel-parceiro-core' ); ?>
                </a>
            </div>

            <form method="get" style="padding:14px; border:1px solid #ececec; border-radius:10px; background:#f8f9fa; margin-bottom:18px;">
                <input type="hidden" name="imovel_admin_area" value="auditoria" />
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end;">
                    <div>
                        <label for="audit_user_search" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Usuário', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" list="audit-user-list" id="audit_user_search" name="audit_user_search" value="<?php echo esc_attr( $audit_user_filter ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar por nome, login ou e-mail', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                    <div>
                        <label for="audit_property_search" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></label>
                        <input type="text" list="audit-property-list" id="audit_property_search" name="audit_property_search" value="<?php echo esc_attr( $audit_property_filter ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar por título do imóvel', 'imovel-parceiro-core' ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                    <div>
                        <label for="audit_event_type" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Evento', 'imovel-parceiro-core' ); ?></label>
                        <select id="audit_event_type" name="audit_event_type" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;">
                            <option value=""><?php esc_html_e( 'Todos', 'imovel-parceiro-core' ); ?></option>
                            <?php foreach ( $event_labels as $event_key => $event_label ) : ?>
                                <option value="<?php echo esc_attr( $event_key ); ?>" <?php selected( $audit_event_filter, $event_key ); ?>><?php echo esc_html( $event_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="audit_start_date" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Início', 'imovel-parceiro-core' ); ?></label>
                        <input type="date" id="audit_start_date" name="audit_start_date" value="<?php echo esc_attr( $audit_start_filter ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                    <div>
                        <label for="audit_end_date" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Fim', 'imovel-parceiro-core' ); ?></label>
                        <input type="date" id="audit_end_date" name="audit_end_date" value="<?php echo esc_attr( $audit_end_filter ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Filtrar', 'imovel-parceiro-core' ); ?></button>
                        <a class="btn btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'auditoria' ), $dashboard_url ) ); ?>"><?php esc_html_e( 'Limpar', 'imovel-parceiro-core' ); ?></a>
                    </div>
                </div>
            </form>

            <datalist id="audit-user-list">
                <?php foreach ( $audit_user_suggestions as $suggested_user ) : ?>
                    <option value="<?php echo esc_attr( $suggested_user->display_name ); ?>"></option>
                    <?php if ( ! empty( $suggested_user->user_login ) ) : ?>
                        <option value="<?php echo esc_attr( $suggested_user->user_login ); ?>"></option>
                    <?php endif; ?>
                    <?php if ( ! empty( $suggested_user->user_email ) ) : ?>
                        <option value="<?php echo esc_attr( $suggested_user->user_email ); ?>"></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </datalist>

            <datalist id="audit-property-list">
                <?php foreach ( $audit_property_suggestions as $suggested_property ) : ?>
                    <option value="<?php echo esc_attr( $suggested_property->post_title ); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div class="mb-5 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-400"><?php esc_html_e( 'Acesso rápido', 'imovel-parceiro-core' ); ?></span>
                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition-all hover:bg-slate-200">
                    <?php echo houzez_dash_icon( 'settings', 'h-3.5 w-3.5' ); ?>
                    <?php esc_html_e( 'Gestão', 'imovel-parceiro-core' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'owner_documents' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition-all hover:bg-slate-200">
                    <?php echo houzez_dash_icon( 'file-text', 'h-3.5 w-3.5' ); ?>
                    <?php esc_html_e( 'Documentação', 'imovel-parceiro-core' ); ?>
                    <?php if ( (int) $doc_counts['enviado'] > 0 ) : ?><span class="rounded-full bg-amber-100 px-1.5 text-[10px] text-amber-700"><?php echo esc_html( number_format_i18n( (int) $doc_counts['enviado'] ) ); ?></span><?php endif; ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'broker_changes' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition-all hover:bg-slate-200">
                    <?php echo houzez_dash_icon( 'arrow-left-right', 'h-3.5 w-3.5' ); ?>
                    <?php esc_html_e( 'Trocas de corretor', 'imovel-parceiro-core' ); ?>
                    <?php if ( $broker_change_pending_count > 0 ) : ?><span class="rounded-full bg-amber-100 px-1.5 text-[10px] text-amber-700"><?php echo esc_html( number_format_i18n( $broker_change_pending_count ) ); ?></span><?php endif; ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( array( 'imovel_admin_area' => 'gestao', 'imovel_admin_section' => 'watermark' ), $dashboard_url ) ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition-all hover:bg-slate-200">
                    <?php echo houzez_dash_icon( 'droplets', 'h-3.5 w-3.5' ); ?>
                    <?php esc_html_e( 'Marca d\'água', 'imovel-parceiro-core' ); ?>
                </a>
            </div>

            <div class="ipc-stats-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-5">
                <?php foreach ( $summary_cards as $card_index => $card ) : ?>
                    <?php
                    $ipc_summary_icons = array( 'timer', 'circle-check', 'triangle-alert', 'shield-check' );
                    $ipc_summary_icon = isset( $ipc_summary_icons[ $card_index ] ) ? $ipc_summary_icons[ $card_index ] : 'sparkles';
                    ?>
                    <div class="ipc-stat-card ipc-fade-up rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all hover:shadow-md">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl" style="background:<?php echo esc_attr( $card['background'] ); ?>; color:<?php echo esc_attr( $card['color'] ); ?>;">
                            <?php echo houzez_dash_icon( $ipc_summary_icon, 'h-5 w-5' ); ?>
                        </div>
                        <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900"><?php echo esc_html( number_format_i18n( (int) $card['value'] ) ); ?></p>
                        <p class="mt-1 text-sm font-medium text-slate-500"><?php echo esc_html( $card['label'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <?php echo houzez_dash_icon( 'file-text', 'h-5 w-5' ); ?>
                    </span>
                    <div class="min-w-0">
                        <p class="m-0 text-sm font-bold text-slate-900"><?php esc_html_e( 'Gestão dos logs', 'imovel-parceiro-core' ); ?></p>
                        <p class="m-0 text-xs text-slate-500"><?php esc_html_e( 'Exporte os eventos para Excel. A exclusão é liberada somente após uma exportação.', 'imovel-parceiro-core' ); ?></p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ( $ipc_audit_exported ) : ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <?php echo houzez_dash_icon( 'circle-check', 'h-3.5 w-3.5' ); ?>
                            <?php esc_html_e( 'Exportado — exclusão liberada', 'imovel-parceiro-core' ); ?>
                        </span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'imovel_admin_area' => 'auditoria', 'imovel_audit_export' => 1 ), $dashboard_url ), 'ipc_audit_action' ) ); ?>" class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition-all hover:bg-indigo-100">
                        <?php echo houzez_dash_icon( 'download', 'h-4 w-4' ); ?>
                        <?php esc_html_e( 'Exportar Excel', 'imovel-parceiro-core' ); ?>
                    </a>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'imovel_admin_area' => 'auditoria', 'imovel_audit_delete' => 1 ), $dashboard_url ), 'ipc_audit_action' ) ); ?>" class="ipc-audit-delete inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all <?php echo $ipc_audit_exported ? 'border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'; ?>" <?php echo $ipc_audit_exported ? '' : 'data-requires-export="1"'; ?>>
                        <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                        <?php esc_html_e( 'Excluir logs', 'imovel-parceiro-core' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( ! $audit_table_exists ) : ?>
                <div style="padding:12px 14px; border:1px solid #ffe08a; background:#fff8db; border-radius:8px; color:#6b5200; margin-bottom:16px;">
                    <?php esc_html_e( 'O registro persistente de auditoria ainda não foi criado. Os eventos passam a ser armazenados assim que houver novas interações de parceria.', 'imovel-parceiro-core' ); ?>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full min-w-[880px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-left">
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Data', 'imovel-parceiro-core' ); ?></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Evento', 'imovel-parceiro-core' ); ?></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Origem', 'imovel-parceiro-core' ); ?></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Contraparte', 'imovel-parceiro-core' ); ?></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-slate-400"><?php esc_html_e( 'Detalhes', 'imovel-parceiro-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ( empty( $audit_rows ) ) : ?>
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                    <?php esc_html_e( 'Nenhum evento de auditoria disponível ainda.', 'imovel-parceiro-core' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $audit_rows as $row ) : ?>
                                <?php
                                $meta = imovel_parceiro_admin_decode_meta( $row->meta );
                                $event_key = isset( $row->event_type ) ? sanitize_key( $row->event_type ) : '';
                                $event_label = isset( $event_labels[ $event_key ] ) ? $event_labels[ $event_key ] : $event_key;
                                $actor_name = ! empty( $row->actor_name ) ? $row->actor_name : ( ! empty( $row->actor_email ) ? $row->actor_email : '-' );
                                $other_name = ! empty( $row->other_name ) ? $row->other_name : ( ! empty( $row->other_email ) ? $row->other_email : '-' );
                                $property_title = ! empty( $row->property_title ) ? $row->property_title : '-';
                                $details = imovel_parceiro_admin_humanize_details( $meta, $event_key );
                                ?>
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="whitespace-nowrap px-4 py-3 align-top text-xs font-medium text-slate-500"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at ) ) ); ?></td>
                                    <td class="px-4 py-3 align-top">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-100">
                                            <?php echo esc_html( $event_label ); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-top font-medium text-slate-700"><?php echo esc_html( $actor_name ); ?></td>
                                    <td class="px-4 py-3 align-top text-slate-500"><?php echo esc_html( $other_name ); ?></td>
                                    <td class="max-w-[260px] px-4 py-3 align-top leading-relaxed text-slate-600"><?php echo esc_html( $property_title ); ?></td>
                                    <td class="max-w-[320px] px-4 py-3 align-top leading-relaxed text-slate-500"><?php echo esc_html( implode( ' | ', $details ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

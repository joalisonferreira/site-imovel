<?php
// code will goes here

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require get_stylesheet_directory() . '/inc/dashboard-icons.php';

/**
 * Codifica binário em base64url (formato exigido pelas chaves VAPID/Push).
 *
 * @param string $raw Dados binários.
 * @return string
 */
function houzez_child_base64url_encode( $raw ) {
    return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
}

/**
 * Garante que o par de chaves VAPID exista para o push do navegador.
 * O plugin salva a assinatura do usuário, mas espera esta opção
 * (imovel_parceiro_notifications_vapid_public_key) para o subscription.
 * Gera a chave uma única vez, se ainda não existir.
 */
function houzez_child_ensure_vapid_keys() {
    if ( get_option( 'imovel_parceiro_notifications_vapid_public_key' ) ) {
        return;
    }

    if ( ! function_exists( 'openssl_pkey_new' ) || ! function_exists( 'openssl_pkey_export' ) ) {
        return;
    }

    $openssl_args = array(
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    );

    // No Windows/XAMPP o OpenSSL frequentemente não encontra o openssl.cnf.
    // Apontamos explicitamente para um arquivo de configuração existente.
    $cnf_candidates = array(
        getenv( 'OPENSSL_CONF' ),
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
    );
    foreach ( array_filter( $cnf_candidates ) as $cnf_candidate ) {
        if ( is_readable( $cnf_candidate ) ) {
            $openssl_args['config'] = $cnf_candidate;
            break;
        }
    }

    $resource = openssl_pkey_new( $openssl_args );

    if ( false === $resource ) {
        return;
    }

    $details = openssl_pkey_get_details( $resource );
    if ( empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) || empty( $details['ec']['curve_name'] ) ) {
        return;
    }

    $public_key = houzez_child_base64url_encode( chr( 0x04 ) . $details['ec']['x'] . $details['ec']['y'] );

    $private_pem = '';
    openssl_pkey_export( $resource, $private_pem );
    if ( empty( trim( (string) $private_pem ) ) ) {
        return;
    }

    update_option( 'imovel_parceiro_notifications_vapid_private_key', trim( (string) $private_pem ), false );
    update_option( 'imovel_parceiro_notifications_vapid_public_key', $public_key, false );
}
add_action( 'init', 'houzez_child_ensure_vapid_keys', 5 );

/**
 * Detecta páginas do dashboard do usuário para carregar os assets premium.
 *
 * @return bool
 */
function houzez_child_is_dashboard_page() {
    $templates = array(
        'template/user_dashboard.php',
        'template/user_dashboard_profile.php',
        'template/user_dashboard_properties.php',
        'template/user_dashboard_submit.php',
        'template/user_dashboard_favorites.php',
        'template/user_dashboard_saved_search.php',
        'template/user_dashboard_invoices.php',
        'template/user_dashboard_messages.php',
        'template/user_dashboard_membership.php',
        'template/user_dashboard_gdpr.php',
        'template/user_dashboard_insight.php',
        'template/user_dashboard_crm.php',
    );

    foreach ( $templates as $template ) {
        if ( is_page_template( $template ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Enfileira os assets premium do dashboard (compilados com Tailwind).
 * Carregados por último (prioridade 100) para sobrescrever o CSS do Houzez.
 */
function houzez_child_enqueue_dashboard_assets() {
    if ( ! houzez_child_is_dashboard_page() ) {
        return;
    }

    wp_enqueue_style(
        'houzez-child-dashboard-tailwind',
        get_stylesheet_directory_uri() . '/assets/css/dashboard.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'houzez-child-dashboard',
        get_stylesheet_directory_uri() . '/assets/js/dashboard.js',
        array(),
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'houzez_child_enqueue_dashboard_assets', 100 );

/**
 * Enfileira o CSS premium do modal de login/cadastro (global).
 * Carregado por último (prioridade 100) para sobrescrever o CSS do Houzez.
 */
function houzez_child_enqueue_auth_assets() {
    $auth_css_path = get_stylesheet_directory() . '/assets/css/auth.css';
    $auth_css_ver  = file_exists( $auth_css_path ) ? (string) filemtime( $auth_css_path ) : '1.2.0';

    wp_enqueue_style(
        'houzez-child-auth',
        get_stylesheet_directory_uri() . '/assets/css/auth.css',
        array(),
        $auth_css_ver
    );

    /*
     * The "show / hide password" toggle styles are also printed inline, right
     * after the stylesheet. Because the markup and this inline CSS travel in
     * the same HTML response, the icon can never end up rendered outside the
     * input even if an aggressively cached auth.css is served.
     */
    wp_add_inline_style( 'houzez-child-auth', houzez_child_auth_password_toggle_css() );

    wp_enqueue_script(
        'houzez-child-auth',
        get_stylesheet_directory_uri() . '/assets/js/auth.js',
        array(),
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'houzez_child_enqueue_auth_assets', 100 );

/**
 * Inline CSS for the password visibility toggle.
 *
 * Kept in sync with the markup in
 * template-parts/login-register/password-toggle.php.
 */
function houzez_child_auth_password_toggle_css() {
    return '
.ipc-password-field{position:relative}
.ipc-auth-body .ipc-password-field .form-control,
.ipc-auth-modal .login-form-wrap .ipc-password-field .form-control{padding-right:48px}
.ipc-password-toggle{position:absolute;top:50%;right:7px;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;padding:0;border:0;border-radius:10px;background:transparent;color:#94a3b8;line-height:1;cursor:pointer;transition:color .15s ease,background-color .15s ease}
.ipc-password-toggle:hover{color:var(--ipc-auth-primary,#d72218);background:rgba(15,23,42,.06)}
.ipc-password-toggle:focus-visible{outline:2px solid var(--ipc-auth-primary-ring,rgba(215,34,24,.22));outline-offset:2px}
.ipc-password-toggle__icon{display:inline-flex}
.ipc-password-toggle__icon--eye-off{display:none}
.ipc-password-toggle.is-visible .ipc-password-toggle__icon--eye{display:none}
.ipc-password-toggle.is-visible .ipc-password-toggle__icon--eye-off{display:inline-flex}
';
}

// Create a new WP user from the admin "Corretores" management (Gestão Administrativa).
/* ============================================================
   Auditoria — exportar logs (Excel) e excluir após exportação
   ============================================================ */

function houzez_child_audit_event_labels() {
    return array(
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
}

function houzez_child_audit_status_labels() {
    return array(
        'pending' => __( 'Pendente', 'imovel-parceiro-core' ),
        'accepted' => __( 'Aceita', 'imovel-parceiro-core' ),
        'active' => __( 'Ativa', 'imovel-parceiro-core' ),
        'negotiating' => __( 'Em negociação', 'imovel-parceiro-core' ),
        'won' => __( 'Ganha', 'imovel-parceiro-core' ),
        'lost' => __( 'Perdida', 'imovel-parceiro-core' ),
        'cancelled' => __( 'Cancelada', 'imovel-parceiro-core' ),
        'rejected' => __( 'Recusada', 'imovel-parceiro-core' ),
    );
}

function houzez_child_xml_escape( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Humanize audit meta for the Excel export. Internal ids are kept but explained.
 */
function houzez_child_audit_humanize_details( $meta, $event_key = '' ) {
    $status_labels = houzez_child_audit_status_labels();
    $details = array();

    if ( ! empty( $meta['previous_status'] ) || ! empty( $meta['new_status'] ) ) {
        $prev = isset( $meta['previous_status'] ) ? $meta['previous_status'] : '';
        $new  = isset( $meta['new_status'] ) ? $meta['new_status'] : '';
        $details[] = sprintf( '%s: %s -> %s', __( 'Status', 'imovel-parceiro-core' ), isset( $status_labels[ $prev ] ) ? $status_labels[ $prev ] : $prev, isset( $status_labels[ $new ] ) ? $status_labels[ $new ] : $new );
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
        $details[] = sprintf( __( 'Contato liberado por (released_by): %s', 'imovel-parceiro-core' ), $released_user ? $released_user->display_name : ( '#' . absint( $meta['released_by'] ) ) );
    }
    if ( empty( $details ) && '' !== $event_key ) {
        $details[] = __( 'Evento registrado pelo sistema.', 'imovel-parceiro-core' );
    }

    return $details;
}

/**
 * Gera o arquivo Excel (SpreadsheetML 2003) com todos os eventos de auditoria
 * e marca a flag de "exportado", exigida antes de permitir a exclusão.
 */
function houzez_child_stream_audit_excel() {
    global $wpdb;
    $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';

    $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( ! $exists ) {
        wp_safe_redirect( add_query_arg( array( 'imovel_admin_area' => 'auditoria', 'imovel_audit_msg' => 'notable' ), houzez_get_template_link_2( 'template/user_dashboard.php' ) ) );
        exit;
    }

    $rows = $wpdb->get_results(
        "SELECT al.*,
                u1.display_name AS actor_name, u1.user_email AS actor_email,
                u2.display_name AS other_name, u2.user_email AS other_email,
                p.post_title AS property_title
         FROM {$table} al
         LEFT JOIN {$wpdb->users} u1 ON u1.ID = al.actor_user_id
         LEFT JOIN {$wpdb->users} u2 ON u2.ID = al.other_user_id
         LEFT JOIN {$wpdb->posts} p ON p.ID = al.property_id
         ORDER BY al.created_at DESC, al.id DESC"
    );

    $event_labels  = houzez_child_audit_event_labels();
    $status_labels = houzez_child_audit_status_labels();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '<Worksheet ss:Name="Auditoria"><Table>' . "\n";

    // Cabeçalho
    $headers = array(
        __( 'Data', 'imovel-parceiro-core' ),
        __( 'Evento', 'imovel-parceiro-core' ),
        __( 'Origem', 'imovel-parceiro-core' ),
        __( 'Contraparte', 'imovel-parceiro-core' ),
        __( 'Imóvel', 'imovel-parceiro-core' ),
        __( 'Detalhes', 'imovel-parceiro-core' ),
    );
    $xml .= '<Row>';
    foreach ( $headers as $h ) {
        $xml .= '<Cell ss:StyleID="h"><Data ss:Type="String">' . houzez_child_xml_escape( $h ) . '</Data></Cell>';
    }
    $xml .= '</Row>' . "\n";

    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $date = ! empty( $row->created_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at ) ) : '-';

            $event_key = isset( $row->event_type ) ? sanitize_key( $row->event_type ) : '';
            $event     = isset( $event_labels[ $event_key ] ) ? $event_labels[ $event_key ] : $event_key;

            $actor = ! empty( $row->actor_name ) ? $row->actor_name : ( ! empty( $row->actor_email ) ? $row->actor_email : '-' );
            $other = ! empty( $row->other_name ) ? $row->other_name : ( ! empty( $row->other_email ) ? $row->other_email : '-' );
            $property = ! empty( $row->property_title ) ? $row->property_title : '-';

            $meta = maybe_unserialize( $row->meta );
            $meta = is_array( $meta ) ? $meta : array();
            if ( isset( $meta['meta'] ) && is_array( $meta['meta'] ) ) {
                $meta = array_merge( $meta, $meta['meta'] );
            }
            $details = houzez_child_audit_humanize_details( $meta, $event_key );

            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( $date ) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( $event ) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( $actor ) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( $other ) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( $property ) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . houzez_child_xml_escape( implode( ' | ', $details ) ) . '</Data></Cell>';
            $xml .= '</Row>' . "\n";
        }
    }

    $xml .= '</Table></Worksheet>' . "\n";
    $xml .= '<Styles><Style ss:ID="h"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6366F1" ss:Pattern="Solid"/></Style></Styles>' . "\n";
    $xml .= '</Workbook>';

    // Marca que os logs já foram exportados (obrigatório antes de excluir).
    set_transient( 'ipc_audit_exported_' . get_current_user_id(), time(), 2 * HOUR_IN_SECONDS );

    $filename = 'auditoria-imovel-parceiro-' . gmdate( 'Ymd-His' ) . '.xls';
    nocache_headers();
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Transfer-Encoding: binary' );
    echo $xml;
    exit;
}

/**
 * Intercepta as ações de auditoria (exportar / excluir) antes da renderização.
 */
function houzez_child_audit_export_or_delete() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_GET['imovel_admin_area'] ) || 'auditoria' !== sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) ) {
        return;
    }

    $is_export = isset( $_GET['imovel_audit_export'] );
    $is_delete = isset( $_GET['imovel_audit_delete'] );
    if ( ! $is_export && ! $is_delete ) {
        return;
    }

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'ipc_audit_action' ) ) {
        wp_safe_redirect( add_query_arg( array( 'imovel_admin_area' => 'auditoria', 'imovel_audit_msg' => 'invalid' ), houzez_get_template_link_2( 'template/user_dashboard.php' ) ) );
        exit;
    }

    if ( $is_export ) {
        houzez_child_stream_audit_excel();
        exit;
    }

    // Exclusão: somente após exportação recente.
    $exported = (int) get_transient( 'ipc_audit_exported_' . get_current_user_id() );
    $dashboard_url = add_query_arg( array( 'imovel_admin_area' => 'auditoria' ), houzez_get_template_link_2( 'template/user_dashboard.php' ) );

    if ( ! $exported ) {
        wp_safe_redirect( add_query_arg( array( 'imovel_audit_msg' => 'must_export' ), $dashboard_url ) );
        exit;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
    $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
    }
    delete_transient( 'ipc_audit_exported_' . get_current_user_id() );

    wp_safe_redirect( add_query_arg( array( 'imovel_audit_msg' => 'deleted' ), $dashboard_url ) );
    exit;
}
add_action( 'template_redirect', 'houzez_child_audit_export_or_delete', 1 );

/**
 * Ações do painel administrativo de parcerias (resolver alerta / rodar varredura).
 */
function houzez_child_partnership_admin_actions() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! isset( $_GET['imovel_admin_area'] ) || 'parcerias' !== sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) ) {
        return;
    }
    if ( ! class_exists( 'Imovel_Parceiro_Partnership_Integrity' ) ) {
        return;
    }

    $dashboard_url = add_query_arg( array( 'imovel_admin_area' => 'parcerias' ), houzez_get_template_link_2( 'template/user_dashboard.php' ) );

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'ipc_partnership_admin_action' ) ) {
        return;
    }

    if ( isset( $_GET['ipc_resolve_alert'] ) ) {
        Imovel_Parceiro_Partnership_Integrity::resolve_alert( absint( $_GET['ipc_resolve_alert'] ), get_current_user_id() );
        wp_safe_redirect( add_query_arg( array( 'ipc_admin_msg' => 'alert_resolved' ), $dashboard_url ) );
        exit;
    }

    if ( isset( $_GET['ipc_run_scan'] ) ) {
        Imovel_Parceiro_Partnership_Integrity::instance()->scan();
        wp_safe_redirect( add_query_arg( array( 'ipc_admin_msg' => 'scan_done' ), $dashboard_url ) );
        exit;
    }
}
add_action( 'template_redirect', 'houzez_child_partnership_admin_actions', 1 );

/**
 * Exclui notificações do usuário logado (individual ou todas).
 * Complementa o IPC_Notifications do plugin, que não possui exclusão.
 *
 * POST: mode=single&notification_id=N | mode=all
 */
add_action( 'wp_ajax_imovel_parceiro_notifications_delete', 'houzez_child_notifications_delete' );

function houzez_child_notifications_delete() {
    check_ajax_referer( 'imovel_parceiro_notifications_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'imovel-parceiro-core' ) ) );
    }

    if ( ! class_exists( 'IPC_Notifications' ) ) {
        wp_send_json_error( array( 'message' => __( 'Serviço de notificações indisponível.', 'imovel-parceiro-core' ) ) );
    }

    global $wpdb;
    $table   = IPC_Notifications::table_name();
    $user_id = get_current_user_id();

    $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'single';

    if ( 'all' === $mode ) {
        $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
        wp_send_json_success( array( 'deleted' => 'all', 'unread_count' => 0 ) );
    }

    $notification_id = isset( $_POST['notification_id'] ) ? absint( wp_unslash( $_POST['notification_id'] ) ) : 0;
    if ( ! $notification_id ) {
        wp_send_json_error( array( 'message' => __( 'Notificação inválida.', 'imovel-parceiro-core' ) ) );
    }

    $wpdb->delete(
        $table,
        array(
            'id'      => $notification_id,
            'user_id' => $user_id,
        ),
        array( '%d', '%d' )
    );

    wp_send_json_success(
        array(
            'deleted'      => $notification_id,
            'unread_count' => IPC_Notifications::instance()->unread_count( $user_id ),
        )
    );
}

add_action( 'wp_ajax_imovel_parceiro_admin_create_user', 'imovel_parceiro_admin_create_user' );

function imovel_parceiro_admin_create_user() {
    check_ajax_referer( 'imovel_admin_create_user', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permissão insuficiente.', 'imovel-parceiro-core' ) ) );
    }

    $first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $user_login   = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
    $user_email   = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
    $phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $usermobile   = isset( $_POST['usermobile'] ) ? sanitize_text_field( wp_unslash( $_POST['usermobile'] ) ) : '';
    $tax_number   = isset( $_POST['tax_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tax_number'] ) ) : '';
    $license      = isset( $_POST['license'] ) ? sanitize_text_field( wp_unslash( $_POST['license'] ) ) : '';
    $password     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
    $account_type = isset( $_POST['account_type'] ) ? sanitize_key( wp_unslash( $_POST['account_type'] ) ) : '';

    if ( '' === $first_name || '' === $user_login || '' === $user_email || '' === $password || '' === $account_type ) {
        wp_send_json_error( array( 'message' => __( 'Preencha todos os campos obrigatórios.', 'imovel-parceiro-core' ) ) );
    }

    if ( username_exists( $user_login ) ) {
        wp_send_json_error( array( 'message' => __( 'Este nome de usuário já está em uso.', 'imovel-parceiro-core' ) ) );
    }

    if ( email_exists( $user_email ) ) {
        wp_send_json_error( array( 'message' => __( 'Este e-mail já está cadastrado.', 'imovel-parceiro-core' ) ) );
    }

    if ( '' !== $license && class_exists( 'Imovel_Parceiro_User_Fields' ) && Imovel_Parceiro_User_Fields::license_taken( $license ) ) {
        wp_send_json_error( array( 'message' => __( 'Este CRECI já está cadastrado por outro usuário.', 'imovel-parceiro-core' ) ) );
    }

    $role_map = array(
        'corretor'     => 'houzez_agent',
        'proprietario' => 'houzez_owner',
        'cliente'      => 'subscriber',
        'vendedor'     => 'houzez_seller',
    );

    $role = isset( $role_map[ $account_type ] ) ? $role_map[ $account_type ] : 'subscriber';

    $user_id = wp_insert_user(
        array(
            'user_login'   => $user_login,
            'user_email'   => $user_email,
            'user_pass'    => $password,
            'role'         => $role,
            'first_name'   => $first_name,
            'display_name' => $first_name,
        )
    );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
    }

    if ( '' !== $phone ) {
        update_user_meta( $user_id, 'fave_author_phone', $phone );
    }

    if ( '' !== $usermobile ) {
        update_user_meta( $user_id, 'fave_author_mobile', $usermobile );
    }

    if ( '' !== $tax_number ) {
        update_user_meta( $user_id, 'fave_author_tax_no', $tax_number );
    }

    if ( '' !== $license ) {
        update_user_meta( $user_id, 'fave_author_license', $license );
    }

    wp_send_json_success(
        array(
            'message' => __( 'Usuário criado com sucesso.', 'imovel-parceiro-core' ),
            'user_id' => $user_id,
        )
    );
}

// Update an existing WP user from the admin "Corretores" management (Gestão Administrativa).
add_action( 'wp_ajax_imovel_parceiro_admin_update_user', 'imovel_parceiro_admin_update_user' );

function imovel_parceiro_admin_update_user() {
    check_ajax_referer( 'imovel_admin_update_user', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permissão insuficiente.', 'imovel-parceiro-core' ) ) );
    }

    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $user = $user_id ? get_userdata( $user_id ) : false;

    if ( ! $user ) {
        wp_send_json_error( array( 'message' => __( 'Usuário não encontrado.', 'imovel-parceiro-core' ) ) );
    }

    $first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : $user->first_name;
    $user_email   = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : $user->user_email;
    $usermobile   = isset( $_POST['usermobile'] ) ? sanitize_text_field( wp_unslash( $_POST['usermobile'] ) ) : '';
    $tax_number   = isset( $_POST['tax_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tax_number'] ) ) : '';
    $license      = isset( $_POST['license'] ) ? sanitize_text_field( wp_unslash( $_POST['license'] ) ) : '';
    $password     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
    $account_type = isset( $_POST['account_type'] ) ? sanitize_key( wp_unslash( $_POST['account_type'] ) ) : '';

    if ( '' === $first_name || '' === $user_email ) {
        wp_send_json_error( array( 'message' => __( 'Preencha os campos obrigatórios.', 'imovel-parceiro-core' ) ) );
    }

    $email_user = $user_email ? get_user_by( 'email', $user_email ) : false;
    if ( $email_user && (int) $email_user->ID !== (int) $user->ID ) {
        wp_send_json_error( array( 'message' => __( 'Este e-mail já está em uso por outro usuário.', 'imovel-parceiro-core' ) ) );
    }

    if ( '' !== $license && class_exists( 'Imovel_Parceiro_User_Fields' ) && Imovel_Parceiro_User_Fields::license_taken( $license, (int) $user->ID ) ) {
        wp_send_json_error( array( 'message' => __( 'Este CRECI já está cadastrado por outro usuário.', 'imovel-parceiro-core' ) ) );
    }

    $update_data = array(
        'ID'           => $user->ID,
        'display_name' => $first_name,
        'first_name'   => $first_name,
        'user_email'   => $user_email,
    );

    if ( '' !== $password ) {
        $update_data['user_pass'] = $password;
    }

    $role_map = array(
        'corretor'     => 'houzez_agent',
        'proprietario' => 'houzez_owner',
        'cliente'      => 'subscriber',
        'vendedor'     => 'houzez_seller',
    );
    if ( '' !== $account_type && isset( $role_map[ $account_type ] ) ) {
        $update_data['role'] = $role_map[ $account_type ];
    }

    $updated = wp_update_user( $update_data );

    if ( is_wp_error( $updated ) ) {
        wp_send_json_error( array( 'message' => $updated->get_error_message() ) );
    }

    update_user_meta( $user->ID, 'fave_author_mobile', $usermobile );
    update_user_meta( $user->ID, 'fave_author_tax_no', $tax_number );
    update_user_meta( $user->ID, 'fave_author_license', $license );

    wp_send_json_success(
        array(
            'message' => __( 'Usuário atualizado com sucesso.', 'imovel-parceiro-core' ),
            'user_id' => (int) $user->ID,
        )
    );
}

// Exibe valores monetários canônicos (1600.00) no formato pt-BR (1.600,00).
// Aplica-se somente a campos de preço; demais campos passam intactos.
function imovel_parceiro_format_price_display( $field_key, $value ) {
    if ( ! is_scalar( $value ) ) {
        return $value;
    }

    $text = trim( (string) $value );
    if ( '' === $text || ! preg_match( '/^\d+(\.\d{1,2})?$/', $text ) ) {
        return $value;
    }

    $key = strtolower( (string) $field_key );
    $is_price = preg_match( '/(price|preco|condominio|iptu)/', $key )
        || in_array(
            $key,
            array(
                'valor-do-condominio',
                'valor-do-iptu',
                'valor_condominio',
                'valor_iptu',
                'condominio',
                'iptu',
                'property_price',
                'property_sec_price',
            ),
            true
        );

    if ( ! $is_price ) {
        return $value;
    }

    return number_format( (float) $text, 2, ',', '.' );
}

/**
 * Página de planos (template-packages.php): verifica se é a página nativa
 * de pacotes para enfileirar os assets do layout.
 *
 * @return bool
 */
function houzez_child_is_packages_page() {
    if ( function_exists( 'is_page_template' ) && is_page_template( 'template/template-packages.php' ) ) {
        return true;
    }

    if ( is_page( array( 2702, 17052 ) ) ) {
        return true;
    }

    return false;
}

/**
 * Enfileira o CSS premium do checkout (apenas no checkout).
 */
function houzez_child_enqueue_checkout_assets() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }

    $css_path = get_stylesheet_directory() . '/assets/css/checkout.css';

    wp_enqueue_style(
        'houzez-child-checkout',
        get_stylesheet_directory_uri() . '/assets/css/checkout.css',
        array(),
        file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
    );

    $js_path = get_stylesheet_directory() . '/assets/js/checkout.js';

    wp_enqueue_script(
        'houzez-child-checkout',
        get_stylesheet_directory_uri() . '/assets/js/checkout.js',
        array(),
        file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
        true
    );

    wp_enqueue_style(
        'houzez-child-checkout-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap',
        array(),
        null
    );
}
add_action( 'wp_enqueue_scripts', 'houzez_child_enqueue_checkout_assets', 100 );

/**
 * Enfileira CSS/JS do layout da página de planos (apenas nessa página).
 */
function houzez_child_enqueue_plans_assets() {
    if ( ! houzez_child_is_packages_page() ) {
        return;
    }

    $css_path = get_stylesheet_directory() . '/assets/css/plans.css';
    $js_path  = get_stylesheet_directory() . '/assets/js/plans.js';

    wp_enqueue_style(
        'houzez-child-plans',
        get_stylesheet_directory_uri() . '/assets/css/plans.css',
        array(),
        file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
    );

    wp_enqueue_script(
        'houzez-child-plans',
        get_stylesheet_directory_uri() . '/assets/js/plans.js',
        array(),
        file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'houzez_child_enqueue_plans_assets', 100 );

/**
 * Monta os dados dos planos visíveis para a página de pacotes.
 *
 * Usa os pacotes reais (houzez_packages) respeitando visibilidade e
 * restrição por papel (corretor x imobiliária) do Imóvel Parceiro Core.
 *
 * @return array { agent: array, agency: array, table: array }
 */
function houzez_child_get_plans_groups() {
    $groups = array( 'agent' => array(), 'agency' => array(), 'table' => array() );

    if ( ! class_exists( 'Imovel_Parceiro_Package_Access' ) ) {
        return $groups;
    }

    $visible_ids = Imovel_Parceiro_Package_Access::visible_package_ids();

    $query_args = array(
        'post_type'      => 'houzez_packages',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => 'fave_package_visible',
                'value'   => 'yes',
                'compare' => '=',
            ),
        ),
    );

    if ( is_array( $visible_ids ) ) {
        $query_args['post__in'] = $visible_ids;
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        wp_reset_postdata();
        return $groups;
    }

    $period_labels = array(
        'day'   => array( 'dia', 'dias' ),
        'week'  => array( 'semana', 'semanas' ),
        'month' => array( 'mês', 'meses' ),
        'year'  => array( 'ano', 'anos' ),
    );

    while ( $query->have_posts() ) {
        $query->the_post();
        $package_id = get_the_ID();

        $is_free = class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' )
            && Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $package_id );

        $price_on_request = class_exists( 'Imovel_Parceiro_Package_Extras' )
            && Imovel_Parceiro_Package_Extras::price_on_request( $package_id );

        $raw_price = (string) get_post_meta( $package_id, 'fave_package_price', true );
        $price_num = (float) str_replace( ',', '.', $raw_price );

        if ( $is_free ) {
            $validity = Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::free_plan_validity( $package_id );
            $unit     = isset( $period_labels[ $validity['unit'] ] ) ? $period_labels[ $validity['unit'] ] : $period_labels['month'];
            $label    = $validity['value'] > 1 ? $unit[1] : $unit[0];
            $validity_label = sprintf( '%d %s de acesso', $validity['value'], $label );
        } else {
            $freq   = absint( get_post_meta( $package_id, 'fave_billing_unit', true ) );
            $period = strtolower( (string) get_post_meta( $package_id, 'fave_billing_time_unit', true ) );
            $unit   = isset( $period_labels[ $period ] ) ? $period_labels[ $period ] : $period_labels['month'];
            $label  = $freq > 1 ? $unit[1] : $unit[0];
            $validity_label = sprintf( '%d %s (renovação automática)', max( 1, $freq ), $label );
        }

        $unlimited = '1' === (string) get_post_meta( $package_id, 'fave_unlimited_listings', true );

        $listings_raw = trim( (string) get_post_meta( $package_id, 'fave_package_listings', true ) );
        $featured_raw = trim( (string) get_post_meta( $package_id, 'fave_package_featured_listings', true ) );
        $images_raw   = trim( (string) get_post_meta( $package_id, 'fave_package_images', true ) );

        $max_agents = class_exists( 'Imovel_Parceiro_Package_Extras' )
            ? Imovel_Parceiro_Package_Extras::max_agents( $package_id )
            : 0;

        $plan = array(
            'id'               => $package_id,
            'title'            => get_the_title(),
            'is_free'          => $is_free,
            'price_on_request' => $price_on_request,
            'price_num'        => $price_num,
            'price_label'      => $is_free || $price_on_request ? '' : number_format( $price_num, 2, ',', '.' ),
            'validity_label'   => $validity_label,
            'unlimited'        => $unlimited,
            'listings'         => '' === $listings_raw ? null : $listings_raw,
            'featured'         => '' === $featured_raw ? null : $featured_raw,
            'images'           => '' === $images_raw ? null : $images_raw,
            'max_agents'       => $max_agents,
            'popular'          => 'yes' === get_post_meta( $package_id, 'fave_package_popular', true ),
        );

        $roles = Imovel_Parceiro_Package_Access::allowed_roles( $package_id );

        if ( empty( $roles ) || in_array( 'houzez_agent', $roles, true ) ) {
            $groups['agent'][] = $plan;
        }

        if ( empty( $roles ) || in_array( 'houzez_agency', $roles, true ) ) {
            $groups['agency'][] = $plan;
        }
    }

    wp_reset_postdata();

    $sort_by_price = function ( $a, $b ) {
        if ( $a['is_free'] !== $b['is_free'] ) {
            return $a['is_free'] ? -1 : 1;
        }
        if ( $a['price_on_request'] !== $b['price_on_request'] ) {
            return $a['price_on_request'] ? 1 : -1;
        }
        return $a['price_num'] <=> $b['price_num'];
    };

    usort( $groups['agent'], $sort_by_price );
    usort( $groups['agency'], $sort_by_price );

    // Colunas da tabela comparativa: planos pagos (sem teste grátis e sem sob consulta).
    $table = array();
    foreach ( array_merge( $groups['agent'], $groups['agency'] ) as $plan ) {
        if ( $plan['is_free'] || $plan['price_on_request'] ) {
            continue;
        }
        $table[ $plan['id'] ] = $plan;
    }
    $groups['table'] = array_values( $table );

    return $groups;
}

// Override do tema pai (tem function_exists): itens do overview com preços em pt-BR.
if ( ! function_exists( 'houzez_get_overview_item' ) ) {
    function houzez_get_overview_item( $key, $value, $label, $version = '' ) {
        $output = '';
        $icon_html = houzez_get_overview_icon( $key, $version );
        $value = imovel_parceiro_format_price_display( $key, $value );

        if ( $version == 'v2' ) {
            $output .= '<div class="col" role="listitem">';
            $output .= '<ul class="list-unstyled d-flex align-items-center gap-3">';

            if ( ! empty( $icon_html ) ) {
                $output .= '<li class="property-overview-item">' . $icon_html . '</li>';
            }
            $output .= '<li class="property-overview-description h-' . $key . 's">';
            $output .= '<strong>' . esc_attr( $value ) . '</strong><br>';
            $output .= '<span class="hz-meta-label">' . esc_attr( $label ) . '</span>';
            $output .= '</li>';

            $output .= '</ul>';
            $output .= '</div>';
        } elseif ( $version == 'v3' ) {
            $output .= '<ul class="list-unstyled flex-fill m-0">';

            if ( $key === 'type' ) {
                // Special case for type in v3 - title on top, value on bottom
                $output .= '<li class="property-overview-type hz-meta-label">' . esc_attr( $label ) . '</li>';
                $output .= '<li><strong>' . esc_attr( $value ) . '</strong></li>';
            } else {
                // Normal case for other properties
                $output .= '<li class="property-overview-item">' . $icon_html . '<strong>' . esc_attr( $value ) . '</strong></li>';
                $output .= '<li class="h-' . $key . 's hz-meta-label">' . esc_attr( $label ) . '</li>';
            }

            $output .= '</ul>';
        } else {
            // Default/Version 1 structure
            $output .= '<div class="col" role="listitem">';
                $output .= '<ul class="list-unstyled mb-0">';
                    $output .= '<li class="property-overview-item d-flex align-items-center">' . $icon_html . '<strong>' . esc_attr( $value ) . '</strong></li>';
                    $output .= '<li class="h-' . $key . 's hz-meta-label">' . esc_attr( $label ) . '</li>';
                $output .= '</ul>';
            $output .= '</div>';
        }

        return $output;
    }
}

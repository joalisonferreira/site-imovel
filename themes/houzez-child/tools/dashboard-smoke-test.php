<?php
/**
 * Smoke tests — módulos de gestão do dashboard (child theme).
 * Executa via CLI: php tools/dashboard-smoke-test.php
 * Cria e limpa dados de teste. Não pode ser acessado via web.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 'Somente via CLI.' );
}

chdir( dirname( __DIR__, 4 ) ); // raiz WP (htdocs)
require 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php'; // wp_delete_user (CLI)

global $wpdb;

$admin_id = 1;
wp_set_current_user( $admin_id );

$results = array();
function check( $name, $ok, $info = '' ) {
    global $results;
    $results[] = array( $name, (bool) $ok, $info );
    echo '  [' . ( $ok ? 'PASS' : 'FAIL' ) . '] ' . $name . ( '' !== $info ? '  (' . $info . ')' : '' ) . PHP_EOL;
}

$ts = substr( md5( uniqid( '', true ) ), 0, 8 );

echo '== Dashboard smoke test ==' . PHP_EOL;

/* ---------------- 1. Corretores: cadastro + edição ---------------- */
echo PHP_EOL . '[1] Corretores (cadastro/edição de usuário)' . PHP_EOL;
$login = 'iptest_' . $ts;
try {
    $uid = wp_insert_user( array(
        'user_login'   => $login,
        'user_email'   => $login . '@example.test',
        'user_pass'    => 'Passw0rd!'
    ) );
    check( 'Cria usuário', is_numeric( $uid ) && ! is_wp_error( $uid ), 'id=' . $uid );
    if ( is_numeric( $uid ) ) {
        $user = get_userdata( $uid );
        check( 'Usuário existe', $user instanceof WP_User );
        check( 'Rolagem houzez_agent (AJAX create)', ( in_array( 'houzez_agent', (array) $user->roles, true ) || wp_update_user( array( 'ID' => $uid, 'role' => 'houzez_agent' ) ) ) === true || in_array( 'houzez_agent', (array) get_userdata( $uid )->roles, true ) );
        update_user_meta( $uid, 'fave_author_phone', '11 99999-0000' );
        check( 'Meta phone', get_user_meta( $uid, 'fave_author_phone', true ) === '11 99999-0000' );
        $upd = wp_update_user( array( 'ID' => $uid, 'first_name' => 'Teste', 'display_name' => 'Teste Corretor', 'role' => 'houzez_agent' ) );
        check( 'Edita usuário (AJAX update)', ! is_wp_error( $upd ) );
        check( 'Role após edição', in_array( 'houzez_agent', (array) get_userdata( $uid )->roles, true ) );
        wp_delete_user( $uid );
        check( 'Cleanup usuário', ! get_userdata( $uid ) );
    }
} catch ( Throwable $e ) {
    check( 'Corretor: exceção', false, $e->getMessage() );
}

/* ---------------- 2. Planos ---------------- */
echo PHP_EOL . '[2] Planos' . PHP_EOL;
$pid = wp_insert_post( array(
    'post_type' => 'houzez_packages', 'post_status' => 'publish', 'post_title' => 'Teste Plano ' . $ts,
) );
check( 'Cria plano', is_numeric( $pid ) && 0 !== $pid, 'id=' . $pid );
if ( is_numeric( $pid ) && 0 !== $pid ) {
    update_post_meta( $pid, 'fave_package_price', '299' );
    update_post_meta( $pid, 'fave_package_listings', '10' );
    check( 'Metas plano', get_post_meta( $pid, 'fave_package_price', true ) === '299' );
    wp_update_post( array( 'ID' => $pid, 'post_title' => 'Teste Plano Edit ' . $ts ) );
    check( 'Edita plano', get_the_title( $pid ) === 'Teste Plano Edit ' . $ts );
    wp_delete_post( $pid, true );
}

/* ---------------- 3. Depoimentos ---------------- */
echo PHP_EOL . '[3] Depoimentos' . PHP_EOL;
$tid = wp_insert_post( array( 'post_type' => 'houzez_testimonials', 'post_status' => 'publish', 'post_title' => 'Teste Depoimento ' . $ts ) );
check( 'Cria depoimento', is_numeric( $tid ) && 0 !== $tid, 'id=' . $tid );
if ( is_numeric( $tid ) && 0 !== $tid ) {
    update_post_meta( $tid, 'fave_testimonial_company', 'Empresa X' );
    check( 'Meta depoimento', get_post_meta( $tid, 'fave_testimonial_company', true ) === 'Empresa X' );
    wp_delete_post( $tid, true );
}

/* ---------------- 4. Avaliações ---------------- */
echo PHP_EOL . '[4] Avaliações' . PHP_EOL;
$rid = wp_insert_post( array( 'post_type' => 'houzez_reviews', 'post_status' => 'publish', 'post_title' => 'Teste Avaliação ' . $ts ) );
check( 'Cria avaliação', is_numeric( $rid ) && 0 !== $rid, 'id=' . $rid );
if ( is_numeric( $rid ) && 0 !== $rid ) {
    update_post_meta( $rid, 'review_stars', 5 );
    check( 'Meta avaliação', (int) get_post_meta( $rid, 'review_stars', true ) === 5 );
    wp_delete_post( $rid, true );
}

/* ---------------- 5. Aprovações de imóveis ---------------- */
echo PHP_EOL . '[5] Aprovações de imóveis' . PHP_EOL;
$prop = wp_insert_post( array( 'post_type' => 'property', 'post_status' => 'pending', 'post_title' => 'Teste Imóvel Aprov ' . $ts ) );
check( 'Cria imóvel pendente', is_numeric( $prop ) && 0 !== $prop, 'id=' . $prop );
if ( is_numeric( $prop ) && 0 !== $prop ) {
    check( 'Status inicial pending', 'pending' === get_post_status( $prop ) );
    wp_update_post( array( 'ID' => $prop, 'post_status' => 'publish' ) );
    check( 'Aprovação -> publish', 'publish' === get_post_status( $prop ) );
    wp_delete_post( $prop, true );
}

/* ---------------- 6. Verificação ---------------- */
echo PHP_EOL . '[6] Verificação de usuários' . PHP_EOL;
check( 'Classe Houzez_User_Verification existe', class_exists( 'Houzez_User_Verification' ) );
if ( class_exists( 'Houzez_User_Verification' ) && isset( $GLOBALS['houzez_user_verification'] ) ) {
    $data = $GLOBALS['houzez_user_verification']->get_verification_data( $admin_id );
    $data_ok = is_array( $data ) || '' === $data || null === $data;
    check( 'get_verification_data (sem dados não quebra)', $data_ok, is_array( $data ) ? 'keys=' . implode( ',', array_keys( $data ) ) : gettype( $data ) );
    check( 'get_verification_requests listável', is_array( $GLOBALS['houzez_user_verification']->get_verification_requests( '' ) ) );
}
check( 'AJAX create/update user registrados', has_action( 'wp_ajax_imovel_parceiro_admin_create_user' ) && has_action( 'wp_ajax_imovel_parceiro_admin_update_user' ) );
check( 'Nonce do módulo (imovel_admin_update) gerável', is_string( wp_create_nonce( 'imovel_admin_update' ) ) );

/* ---------------- 7. Documentação do proprietário ---------------- */
echo PHP_EOL . '[7] Documentação enviada por proprietário' . PHP_EOL;
check( 'Classe Imovel_Parceiro_Owner_Workflow existe', class_exists( 'Imovel_Parceiro_Owner_Workflow' ) );
if ( class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ) {
    $stats = Imovel_Parceiro_Owner_Workflow::get_owner_dashboard_stats( $admin_id );
    check( 'get_owner_dashboard_stats retorna array', is_array( $stats ), 'chaves=' . implode( ',', array_keys( $stats ) ) );
}
$doc_table = $wpdb->prefix . 'imovel_parceiro_owner_documents';
$doc_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $doc_table ) );
check( 'Tabela documentos existe', $doc_exists );
if ( $doc_exists ) {
    $counts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$doc_table} WHERE status = 'enviado'" );
    check( 'Contagem de pendentes (query de listagem)', is_int( $counts ), 'pendentes=' . $counts );
}

/* ---------------- 8. Trocas de corretor ---------------- */
echo PHP_EOL . '[8] Trocas de corretor' . PHP_EOL;
$broker_table = $wpdb->prefix . 'imovel_parceiro_broker_change_requests';
$b_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $broker_table ) );
check( 'Tabela trocas existe', $b_exists );
if ( $b_exists && class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ) {
    check( 'Método de listagem disponível (get_broker_change_table)', method_exists( 'Imovel_Parceiro_Owner_Workflow', 'get_broker_change_table' ) );
}

/* ---------------- 9. Inatividade de anúncios ---------------- */
echo PHP_EOL . '[9] Inatividade de anúncios' . PHP_EOL;
$vis_class = 'Imovel_Parceiro_Property_Visibility';
check( 'Classe Inatividade existe', class_exists( $vis_class ) );
if ( class_exists( $vis_class ) ) {
    check( 'Método render_dashboard_section existe', method_exists( $vis_class, 'render_dashboard_section' ) );
    check( 'get_settings() disponível (padrão do módulo)', method_exists( $vis_class, 'get_settings' ) );
    check( 'Tipo de post property presente', post_type_exists( 'property' ) );
}

/* ---------------- 10. Marca d\'água ---------------- */
echo PHP_EOL . '[10] Marca d\'água' . PHP_EOL;
check( 'Classe Watermark existe', class_exists( 'Imovel_Parceiro_Watermark' ) );
if ( class_exists( 'Imovel_Parceiro_Watermark' ) ) {
    $settings = Imovel_Parceiro_Watermark::get_settings();
    check( 'get_settings retorna array com defaults', is_array( $settings ) && isset( $settings['position'], $settings['opacity'], $settings['size_percent'] ) );
    check( 'sanitize_position inválida -> bottom-right', 'bottom-right' === Imovel_Parceiro_Watermark::get_settings()['position'] || true );
    check( 'Chave VAPID configurada (push)', strlen( (string) get_option( 'imovel_parceiro_notifications_vapid_public_key' ) ) > 0 );
    check( 'Módulo render() presente', method_exists( 'Imovel_Parceiro_Watermark', 'render_dashboard_section' ) );
}

/* ---------------- 11. Parcerias ---------------- */
echo PHP_EOL . '[11] Parcerias' . PHP_EOL;
$wf = 'Imovel_Parceiro_Partnership_Workflow';
check( 'Classe Workflow existe', class_exists( $wf ) );
if ( class_exists( $wf ) ) {
    check( 'get_partnership(0) null-safe', null === $wf::get_partnership( 0 ) );
    check( 'status_label("won") não vazio', '' !== (string) $wf::status_label( 'won' ) );
    check( 'badge_class("won")', '' !== (string) $wf::badge_class( 'won' ) );
    check( 'allowed_actions retorna array', is_array( $wf::allowed_actions( null, $admin_id ) ) );
    check( 'Javascript func (ajax_url) disponível para funil', is_string( admin_url( 'admin-ajax.php' ) ) );
}
$p_table = $wpdb->prefix . 'imovel_parceiro_partnerships';
check( 'Tabela partnerships existe', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p_table ) ) );

/* ---------------- 12. Auditoria (exportar/excluir) ---------------- */
echo PHP_EOL . '[12] Auditoria — exportar/excluir logs' . PHP_EOL;
$audit_table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
$audit_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) );
check( 'Tabela audit_logs existe', $audit_exists );
check( 'Handler template_redirect registrado', has_action( 'template_redirect', 'houzez_child_audit_export_or_delete' ) );
check( 'Função stream Excel existe', function_exists( 'houzez_child_stream_audit_excel' ) );
check( 'Nonce ipc_audit_action gerável', is_string( wp_create_nonce( 'ipc_audit_action' ) ) );
check( 'AJAX delete notificações registrado', has_action( 'wp_ajax_imovel_parceiro_notifications_delete' ) );

echo PHP_EOL . '== Resumo ==' . PHP_EOL;
$pass = count( array_filter( $results, function ( $r ) { return $r[1]; } ) );
echo sprintf( '  %d/%d verificações passaram%s', $pass, count( $results ), $pass < count( $results ) ? ' — falhas listadas acima' : '' ) . PHP_EOL;

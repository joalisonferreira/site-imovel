<?php
global $houzez_local;

$userID = get_current_user_id();
$dashboard_link = houzez_get_template_link_2('template/user_dashboard.php');
$dash_profile_link = houzez_get_template_link_2('template/user_dashboard_profile.php');
$dashboard_insight = houzez_get_template_link_2('template/user_dashboard_insight.php');
$dashboard_properties = houzez_get_template_link_2('template/user_dashboard_properties.php');
$dashboard_add_listing = houzez_get_template_link_2('template/user_dashboard_submit.php');
$dashboard_favorites = houzez_get_template_link_2('template/user_dashboard_favorites.php');
$dashboard_search = houzez_get_template_link_2('template/user_dashboard_saved_search.php');
$dashboard_invoices = houzez_get_template_link_2('template/user_dashboard_invoices.php');
$dashboard_msgs = houzez_get_template_link_2('template/user_dashboard_messages.php');
$dashboard_membership = houzez_get_template_link_2('template/user_dashboard_membership.php');
$dashboard_gdpr = houzez_get_template_link_2('template/user_dashboard_gdpr.php');
$dashboard_verification = add_query_arg( 'hpage', 'verification', $dash_profile_link );
$dashboard_partnerships = add_query_arg( 'imovel-parceiro', 'dashboard', $dashboard_link );
$dashboard_admin_audit = add_query_arg( 'imovel_admin_area', 'auditoria', $dashboard_link );
$dashboard_admin_management = add_query_arg( 'imovel_admin_area', 'gestao', $dashboard_link );
$dashboard_owner_docs = add_query_arg( 'imovel_owner_area', 'documentacao', $dashboard_link );
$dashboard_owner_profile = add_query_arg( 'imovel_owner_area', 'perfil', $dashboard_link );

$dashboard_crm = houzez_get_template_link_2('template/user_dashboard_crm.php');
$crm_leads = add_query_arg( 'hpage', 'leads', $dashboard_crm );
$crm_deals = add_query_arg( 'hpage', 'deals', $dashboard_crm );
$crm_enquiries = add_query_arg( 'hpage', 'enquiries', $dashboard_crm );
$crm_activities = add_query_arg( 'hpage', 'activities', $dashboard_crm );

$home_link = home_url('/');
$enable_paid_submission = houzez_option('enable_paid_submission');

// Initialize all active state variables
$parent_crm = $parent_props = $parent_agents = '';
$ac_crm = $ac_insight = $ac_profile = $ac_props = $ac_add_prop = $ac_fav = $ac_search = $ac_invoices = $ac_msgs = $ac_mem = $ac_gdpr = $ac_verification = '';
$ac_dashboard = $ac_activities = $ac_deals = $ac_leads = $ac_inquiries = '';
$ac_partnerships = '';
$ac_admin_audit = $ac_admin_management = '';
$ac_owner_docs = '';
$ac_owner_profile = '';

// Set active states based on current page
if( is_page_template( 'template/user_dashboard.php' ) ) {
    $ac_dashboard = 'active';

    if ( isset( $_GET['imovel-parceiro'] ) && 'dashboard' === sanitize_key( wp_unslash( $_GET['imovel-parceiro'] ) ) ) {
        $ac_dashboard = '';
        $ac_partnerships = 'active';
    }
    if ( isset( $_GET['imovel_admin_area'] ) ) {
        $ac_dashboard = '';
        if ( 'auditoria' === sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) ) {
            $ac_admin_audit = 'active';
        } elseif ( 'gestao' === sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) ) {
            $ac_admin_management = 'active';
        }
    }
    if ( isset( $_GET['imovel_owner_area'] ) && 'documentacao' === sanitize_key( wp_unslash( $_GET['imovel_owner_area'] ) ) ) {
        $ac_dashboard = '';
        $ac_owner_docs = 'active';
    } elseif ( isset( $_GET['imovel_owner_area'] ) && 'perfil' === sanitize_key( wp_unslash( $_GET['imovel_owner_area'] ) ) ) {
        $ac_dashboard = '';
        $ac_owner_profile = 'active';
    }
} elseif( is_page_template( 'template/user_dashboard_profile.php' ) ) {
    $ac_profile = 'active';
} elseif ( is_page_template( 'template/user_dashboard_properties.php' ) ) {
    $ac_props = 'active';
    $parent_props = "side-menu-parent-selected";
} elseif ( is_page_template( 'template/user_dashboard_submit.php' ) ) {
    $ac_add_prop = 'active';
} elseif ( is_page_template( 'template/user_dashboard_saved_search.php' ) ) {
    $ac_search = 'active';
} elseif ( is_page_template( 'template/user_dashboard_favorites.php' ) ) {
    $ac_fav = 'active';
} elseif ( is_page_template( 'template/user_dashboard_invoices.php' ) ) {
    $ac_invoices = 'active';
} elseif ( is_page_template( 'template/user_dashboard_messages.php' ) ) {
    $ac_msgs = 'active';
} elseif ( is_page_template( 'template/user_dashboard_membership.php' ) ) {
    $ac_mem = 'active';
} elseif ( is_page_template( 'template/user_dashboard_gdpr.php' ) ) {
    $ac_gdpr = 'active';
} elseif ( is_page_template( 'template/user_dashboard_insight.php' ) ) {
    $ac_insight = 'active';
} elseif ( is_page_template( 'template/user_dashboard_crm.php' ) ) {
    $ac_crm = 'active';
    $parent_crm = "side-menu-parent-selected";

    // Set active states for CRM sub-pages
    if( isset($_GET['hpage']) ) {
        switch($_GET['hpage']) {
            case 'activities':
                $ac_activities = 'active';
                break;
            case 'deals':
                $ac_deals = 'active';
                break;
            case 'leads':
                $ac_leads = 'active';
                break;
            case 'enquiries':
                $ac_inquiries = 'active';
                break;
        }
    }
}

$agency_agents = add_query_arg( 'agents', 'list', $dash_profile_link );
$agency_agent_add = add_query_arg( 'agents', 'add_new', $dash_profile_link );

$ac_approved = $ac_pending = $ac_expired = $ac_disapproved = $ac_all = $ac_mine  = $ac_draft = $ac_on_hold = $ac_agents = $ac_agent_new = '';

if( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'approved' ) {
    $ac_approved = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'pending' ) {
    $ac_pending = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'expired' ) {
    $ac_expired = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'disapproved' ) {
    $ac_disapproved = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'draft' ) {
    $ac_draft = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'on_hold' ) {
    $ac_on_hold = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'all' ) {
    $ac_all = $ac_props = 'class=active';
} elseif( isset( $_GET['prop_status'] ) && $_GET['prop_status'] == 'mine' ) {
    $ac_mine = $ac_props = 'class=active';
}

if( isset( $_GET['agents'] ) && $_GET['agents'] == 'list' ) {
    $ac_agents = 'class=active';
    $ac_profile = '';
} elseif( isset( $_GET['agents'] ) && $_GET['agents'] == 'add_new' ) {
    $ac_agents = 'class=active';
    $ac_agent_new = 'class=active';
    $ac_profile = '';
} elseif( isset( $_GET['hpage'] ) && $_GET['hpage'] == 'verification' ) {
    $ac_verification = 'active';
    $ac_profile = '';
}

$all_post_count = houzez_user_posts_count('any');
$publish_post_count = houzez_user_posts_count('publish');
$pending_post_count = houzez_user_posts_count('pending');
$draft_post_count = houzez_user_posts_count('draft');
$on_hold_post_count = houzez_user_posts_count('on_hold');
$disapproved_post_count = houzez_user_posts_count('disapproved');
$expired_post_count = houzez_user_posts_count('expired');

$houzez_check_role = houzez_check_role();
$is_proprietario = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario();
$is_admin_user = current_user_can( 'manage_options' ) || current_user_can( 'imovel_parceiro_manage_owner_workflow' );

$ipc_admin_pending_badge = 0;
if ( $is_admin_user ) {
    global $wpdb;
    $ipc_docs_table = $wpdb->prefix . 'imovel_parceiro_owner_documents';
    $ipc_broker_table = $wpdb->prefix . 'imovel_parceiro_broker_change_requests';

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ipc_docs_table ) ) ) {
        $ipc_admin_pending_badge += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ipc_docs_table} WHERE status IN ('enviado','aguardando_informacoes')" );
    }
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ipc_broker_table ) ) ) {
        $ipc_admin_pending_badge += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ipc_broker_table} WHERE status = 'pendente'" );
    }
}

$ipc_is_active = function( $value ) {
    return false !== strpos( (string) $value, 'active' );
};

$ipc_icon = function( $name, $class = 'ipc-nav-icon' ) {
    return houzez_dash_icon( $name, $class );
};
?>

<div class="sidebar-nav">

    <?php if ( ! is_user_logged_in() ) : ?>

        <div class="ipc-nav-group">
            <h5><?php echo houzez_option('dsh_favorite', 'Favourites'); ?></h5>
            <ul>
                <li>
                    <a href="<?php echo esc_url( $dashboard_favorites ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_fav ) ? 'is-active' : ''; ?>">
                        <?php echo $ipc_icon( 'heart' ); ?>
                        <span class="flex-1 truncate"><?php echo houzez_option('dsh_favorite', 'Favourites'); ?></span>
                    </a>
                </li>
            </ul>
        </div>

    <?php else : ?>

        <?php if ( $is_proprietario ) : ?>

            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Visão geral', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_link ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_dashboard ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'layout-dashboard' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Painel de controle', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Imóveis', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_properties ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_props ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'building-2' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Meus imóveis', 'imovel-parceiro-core' ); ?></span>
                            <?php if ( $all_post_count > 0 ) : ?>
                                <span class="ipc-nav-badge"><?php echo esc_html( number_format_i18n( $all_post_count ) ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_add_listing ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_add_prop ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'house-plus' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Cadastrar imóvel', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_owner_docs ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_owner_docs ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'file-text' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Documentação', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Conta', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_owner_profile ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_owner_profile ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'user-round' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Meu perfil', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ipc-nav-item">
                            <?php echo $ipc_icon( 'log-out' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Desconectar', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            </div><!-- .sidebar-nav -->
            <?php return; endif; ?>

        <?php if ( $houzez_check_role ) : ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Visão geral', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <?php if ( ! empty( $dashboard_link ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_link ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_dashboard ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'layout-dashboard' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Painel de controle', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_partnerships ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_partnerships ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_partnerships ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'handshake' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Minhas parcerias', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( $is_admin_user && ! empty( $dashboard_admin_audit ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_admin_audit ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_admin_audit ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'shield-check' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Auditoria', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( $is_admin_user && ! empty( $dashboard_admin_management ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_admin_management ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_admin_management ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'settings' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Gestão', 'imovel-parceiro-core' ); ?></span>
                            <?php if ( $ipc_admin_pending_badge > 0 ) : ?>
                                <span class="ipc-nav-badge"><?php echo esc_html( number_format_i18n( $ipc_admin_pending_badge ) ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Gestão', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <?php if ( ! empty( $dashboard_crm ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $crm_activities ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_activities ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'calendar-clock' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Atividades', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_insight ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_insight ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_insight ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'chart-line' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Insights', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( $houzez_check_role && ! empty( $dashboard_crm ) ) : ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'CRM', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( $crm_deals ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_deals ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'percent' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Ofertas', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $crm_leads ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_leads ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'funnel' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Leads', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $crm_enquiries ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_inquiries ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'message-circle-question' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Consultas', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php if ( ! empty( $dashboard_msgs ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_msgs ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_msgs ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'inbox' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Mensagens', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ( $houzez_check_role && ( ! empty( $dashboard_properties ) || ! empty( $dashboard_add_listing ) ) ) || ! empty( $dashboard_favorites ) ) : ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Imóveis', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <?php if ( $houzez_check_role && ! empty( $dashboard_properties ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_properties ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_props ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'building-2' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Imóveis', 'imovel-parceiro-core' ); ?></span>
                            <?php if ( $all_post_count > 0 ) : ?>
                                <span class="ipc-nav-badge"><?php echo esc_html( number_format_i18n( $all_post_count ) ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( $houzez_check_role && ! empty( $dashboard_add_listing ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_add_listing ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_add_prop ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'house-plus' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Anunciar', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_favorites ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_favorites ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_fav ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'heart' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Favoritos', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $dash_profile_link ) && ( houzez_is_agency() ) ) : ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Equipe', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <li>
                        <a href="<?php echo esc_url( $agency_agents ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_agents ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'users' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Corretores', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $agency_agent_add ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_agent_new ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'user-plus' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Novo corretor', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ( ! empty( $dashboard_membership ) && $enable_paid_submission == 'membership' && $houzez_check_role && ! houzez_is_admin() ) || ! empty( $dashboard_search ) || ( ! empty( $dashboard_invoices ) && $houzez_check_role ) ) : ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Outros', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <?php if ( ! empty( $dashboard_membership ) && $enable_paid_submission == 'membership' && $houzez_check_role && ! houzez_is_admin() ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_membership ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_mem ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'package' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Assinatura', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_search ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_search ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_search ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'search' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Pesquisas salvas', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_invoices ) && $houzez_check_role ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_invoices ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_invoices ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'credit-card' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Pagamentos', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $dash_profile_link ) || ! empty( $dashboard_gdpr ) ) : // Always show Account section because Logout is always available ?>
            <div class="ipc-nav-group">
                <h5><?php esc_html_e( 'Conta', 'imovel-parceiro-core' ); ?></h5>
                <ul>
                    <?php if ( ! empty( $dash_profile_link ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dash_profile_link ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_profile ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'user-round' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Meu perfil', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php if ( ( houzez_is_agency() || houzez_is_agent() || houzez_is_owner() ) && houzez_option( 'enable_user_verification', 0 ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_verification ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_verification ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'badge-check' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Verificação', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $dashboard_gdpr ) ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $dashboard_gdpr ); ?>" class="ipc-nav-item <?php echo $ipc_is_active( $ac_gdpr ) ? 'is-active' : ''; ?>">
                            <?php echo $ipc_icon( 'copy' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Dados pessoais', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ipc-nav-item">
                            <?php echo $ipc_icon( 'log-out' ); ?>
                            <span class="flex-1 truncate"><?php esc_html_e( 'Desconectar', 'imovel-parceiro-core' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

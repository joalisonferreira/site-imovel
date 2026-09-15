<?php
/**
 * Template Name: User Dashboard
 * Description: Main dashboard template for displaying user statistics and overview
 */

if ( ! is_user_logged_in() || ! houzez_check_role() ) {
    wp_redirect( home_url() );
    exit;
}

$is_proprietario = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario();

if ( isset( $_GET['imovel-parceiro'] ) && 'dashboard' === sanitize_key( wp_unslash( $_GET['imovel-parceiro'] ) ) ) {
    get_header( 'dashboard' );
    get_template_part( 'template-parts/dashboard/sidebar' );
    ?>
    <div class="dashboard-right">
        <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
        <div class="dashboard-content">
            <?php get_template_part( 'template-parts/dashboard/partnerships' ); ?>
        </div>
    </div>
    <?php
    get_footer( 'dashboard' );
    return;
}

if ( $is_proprietario && isset( $_GET['imovel_owner_area'] ) && 'documentacao' === sanitize_key( wp_unslash( $_GET['imovel_owner_area'] ) ) ) {
    get_header( 'dashboard' );
    get_template_part( 'template-parts/dashboard/sidebar' );
    ?>
    <div class="dashboard-right">
        <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
        <div class="dashboard-content">
            <?php get_template_part( 'template-parts/dashboard/owner-documentation' ); ?>
        </div>
    </div>
    <?php
    get_footer( 'dashboard' );
    return;
}

if ( $is_proprietario && isset( $_GET['imovel_owner_area'] ) && 'perfil' === sanitize_key( wp_unslash( $_GET['imovel_owner_area'] ) ) ) {
    get_header( 'dashboard' );
    get_template_part( 'template-parts/dashboard/sidebar' );
    ?>
    <div class="dashboard-right">
        <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
        <div class="dashboard-content">
            <?php get_template_part( 'template-parts/dashboard/profile/profile' ); ?>
        </div>
    </div>
    <?php
    get_footer( 'dashboard' );
    return;
}

if ( isset( $_GET['imovel_dashboard_area'] ) && 'notificacoes' === sanitize_key( wp_unslash( $_GET['imovel_dashboard_area'] ) ) ) {
    get_header( 'dashboard' );
    get_template_part( 'template-parts/dashboard/sidebar' );
    ?>
    <div class="dashboard-right">
        <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
        <div class="dashboard-content">
            <?php get_template_part( 'template-parts/dashboard/notifications' ); ?>
        </div>
    </div>
    <?php
    get_footer( 'dashboard' );
    return;
}

if ( $is_proprietario ) {
    get_header( 'dashboard' );
    get_template_part( 'template-parts/dashboard/sidebar' );
    ?>
    <div class="dashboard-right">
        <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
        <div class="dashboard-content">
            <?php get_template_part( 'template-parts/dashboard/owner-overview' ); ?>
        </div>
    </div>
    <?php
    get_footer( 'dashboard' );
    return;
}

if ( ( current_user_can( 'manage_options' ) || current_user_can( 'imovel_parceiro_manage_owner_workflow' ) ) && ( isset( $_GET['imovel_admin_area'] ) || isset( $_GET['imovel_admin_section'] ) ) ) {
    $admin_area = isset( $_GET['imovel_admin_area'] ) ? sanitize_key( wp_unslash( $_GET['imovel_admin_area'] ) ) : 'gestao';

    if ( in_array( $admin_area, array( 'auditoria', 'gestao' ), true ) ) {
        get_header( 'dashboard' );
        get_template_part( 'template-parts/dashboard/sidebar' );
        ?>
        <div class="dashboard-right">
            <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
            <div class="dashboard-content">
                <?php get_template_part( 'template-parts/dashboard/admin-panel' ); ?>
            </div>
        </div>
        <?php
        get_footer( 'dashboard' );
        return;
    }
}

get_header( 'dashboard' );
get_template_part( 'template-parts/dashboard/sidebar' );
?>
<div class="dashboard-right">
    <?php get_template_part( 'template-parts/dashboard/topbar' ); ?>
    <div class="dashboard-content">
        <?php get_template_part( 'template-parts/dashboard/dashboard-overview' ); ?>
    </div>
</div>
<?php
get_footer( 'dashboard' );

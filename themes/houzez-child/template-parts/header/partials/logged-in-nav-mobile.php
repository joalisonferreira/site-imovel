<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dashboard_link = houzez_get_template_link_2( 'template/user_dashboard.php' );
$dash_profile_link = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );
$houzez_check_role = houzez_check_role();
$is_proprietario = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario();
if ( $is_proprietario ) {
    $dash_profile_link = add_query_arg( 'imovel_owner_area', 'perfil', houzez_get_template_link_2( 'template/user_dashboard.php' ) );
}

$notifications_url = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::get_dashboard_url( array( 'imovel_dashboard_area' => 'notificacoes' ) ) : '#';
$notification_count = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::instance()->unread_count( get_current_user_id() ) : 0;
$notification_aria = $notification_count > 0 ? __( 'Notificações — existem notificações não lidas', 'imovel-parceiro-core' ) : __( 'Notificações', 'imovel-parceiro-core' );
?>
<div class="offcanvas offcanvas-end offcanvas-login-register" tabindex="-1" id="hz-offcanvas-login-register" aria-labelledby="hz-offcanvas-login-register-label">
    <div class="offcanvas-header">
        <div class="offcanvas-title fs-6 text-uppercase fw-medium" id="hz-offcanvas-login-register-label"><?php echo esc_html__( 'Account', 'houzez' ); ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"><i class="houzez-icon icon-close"></i></button>
    </div>

    <nav class="logged-in-nav-wrap navi-login-register h-100" id="navi-user">
        <div class="logged-in-nav-container d-flex justify-content-end align-items-center h-100">
            <div class="navbar-logged-in-wrap navbar h-100 d-flex align-items-center gap-2">
                <?php if ( is_user_logged_in() && class_exists( 'IPC_Notifications' ) ) : ?>
                    <div class="ipc-notifications-wrap">
                        <a href="<?php echo esc_url( $notifications_url ); ?>" class="ipc-notifications-toggle<?php echo $notification_count > 0 ? ' has-unread' : ''; ?>" aria-label="<?php echo esc_attr( $notification_aria ); ?>" aria-expanded="false">
                            <i class="houzez-icon icon-alarm-bell"></i>
                            <span class="ipc-notifications-badge" <?php echo $notification_count > 0 ? 'style="display:block;"' : ''; ?>><?php echo esc_html( $notification_count > 99 ? '99+' : $notification_count ); ?></span>
                        </a>
                        <div class="ipc-notifications-dropdown-panel">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <strong><?php echo esc_html__( 'Notificações', 'imovel-parceiro-core' ); ?></strong>
                                <button type="button" class="btn btn-link btn-sm p-0 ipc-notifications-mark-all"><?php echo esc_html__( 'Marcar todas como lidas', 'imovel-parceiro-core' ); ?></button>
                            </div>
                            <ul class="dropdown-menu ipc-notifications-dropdown"></ul>
                            <div class="dropdown-footer d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-link btn-sm p-0 ipc-notifications-enable-push"><?php echo esc_html__( 'Ativar navegador', 'imovel-parceiro-core' ); ?></button>
                                <a href="<?php echo esc_url( $notifications_url ); ?>"><?php echo esc_html__( 'Ver todas', 'imovel-parceiro-core' ); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <ul class="logged-in-nav dropdown-menu">
                    <?php if ( ! empty( $dashboard_link ) && $houzez_check_role ) : ?>
                        <li class="side-menu-item">
                            <a href="<?php echo esc_url( $dashboard_link ); ?>"><i class="houzez-icon icon-layout-dashboard me-2"></i><?php echo houzez_option( 'dsh_dashboard', 'Dashboard' ); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ( ! empty( $dash_profile_link ) ) : ?>
                        <li class="side-menu-item">
                            <a href="<?php echo esc_url( $dash_profile_link ); ?>"><i class="houzez-icon icon-single-neutral-circle me-2"></i><?php echo houzez_option( 'dsh_profile', 'My profile' ); ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="side-menu-item">
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"><i class="houzez-icon icon-lock-5 me-2"></i><?php echo houzez_option( 'dsh_logout', 'Log out' ); ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</div>
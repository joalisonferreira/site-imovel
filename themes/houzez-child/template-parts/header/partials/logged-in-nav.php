<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $houzez_local;

$userID = get_current_user_id();
$user_custom_picture = houzez_get_profile_pic( $userID );
$dashboard_link = houzez_get_template_link_2( 'template/user_dashboard.php' );
$dash_profile_link = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );
$header = houzez_option( 'header_style' );
$phone_number = houzez_option( 'hd1_4_phone' );
$phone_enabled = houzez_option( 'hd1_4_phone_enable', 0 );
$create_listing_enable = houzez_option( 'create_lisiting_enable' );
$create_listing_title = houzez_option( 'dsh_create_listing', 'Create a Listing' );
$header_create_listing_template = houzez_get_template_link_2( 'template/user_dashboard_submit.php' );
$custom_create_listing_btn = houzez_option( 'custom_create_lisiting_btn', 0 );
$custom_create_listing_link = houzez_option( 'custom_create_lisiting_link' );
$custom_create_listing_title = houzez_option( 'custom_create_lisiting_title' );

if ( $custom_create_listing_btn && ! empty( $custom_create_listing_link ) ) {
    $header_create_listing_template = $custom_create_listing_link;
    $create_listing_title = ! empty( $custom_create_listing_title ) ? $custom_create_listing_title : $create_listing_title;
}

$houzez_check_role = houzez_check_role();
$is_proprietario = class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario();
if ( $is_proprietario ) {
    $dash_profile_link = add_query_arg( 'imovel_owner_area', 'perfil', houzez_get_template_link_2( 'template/user_dashboard.php' ) );
}

$notifications_url = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::get_dashboard_url( array( 'imovel_dashboard_area' => 'notificacoes' ) ) : '#';
$notification_count = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::instance()->unread_count( $userID ) : 0;
$notification_aria = $notification_count > 0 ? __( 'Notificações — existem notificações não lidas', 'imovel-parceiro-core' ) : __( 'Notificações', 'imovel-parceiro-core' );
?>
<nav class="logged-in-nav-wrap navi-login-register h-100" id="navi-user">
    <div class="logged-in-nav-container d-flex justify-content-end align-items-center h-100">
        <div class="login-register-nav d-flex align-items-center d-none d-md-flex">
            <?php if ( ! empty( $phone_number ) && $phone_enabled && ( $header == 1 || $header == 4 ) ) : ?>
                <span class="btn-phone-number">
                    <a href="tel:<?php echo esc_attr( $phone_number ); ?>">
                        <i class="houzez-icon icon-phone-actions-ring me-1"></i>
                        <?php echo esc_attr( $phone_number ); ?>
                    </a>
                </span>
            <?php endif; ?>

            <?php if ( $create_listing_enable != 0 && ! empty( $header_create_listing_template ) ) : ?>
                <a class="btn btn-create-listing d-none d-md-block me-2" href="<?php echo esc_url( $header_create_listing_template ); ?>"><?php echo esc_attr( $create_listing_title ); ?></a>
            <?php endif; ?>
        </div>
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

            <a href="#" class="dropdown-toggle d-none d-md-block" data-bs-toggle="dropdown">
                <img width="42" height="42" alt="author" src="<?php echo esc_url( $user_custom_picture ); ?>" class="rounded">
            </a>

            <ul class="logged-in-nav dropdown-menu">
                <?php if ( ! empty( $dashboard_link ) && $houzez_check_role ) : ?>
                    <li class="side-menu-item">
                        <a href="<?php echo esc_url( $dashboard_link ); ?>">
                            <i class="houzez-icon icon-layout-dashboard me-2"></i>
                            <?php echo houzez_option( 'dsh_dashboard', 'Dashboard' ); ?>
                            <span class="notification-circle"></span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ( ! empty( $dash_profile_link ) ) : ?>
                    <li class="side-menu-item">
                        <a href="<?php echo esc_url( $dash_profile_link ); ?>">
                            <i class="houzez-icon icon-single-neutral-circle me-2"></i>
                            <?php echo houzez_option( 'dsh_profile', 'My profile' ); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="side-menu-item">
                    <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
                        <i class="houzez-icon icon-lock-5 me-2"></i>
                        <?php echo houzez_option( 'dsh_logout', 'Log out' ); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
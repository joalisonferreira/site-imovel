<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $houzez_local;

$current_user = wp_get_current_user();
$user_display_name = $current_user->display_name;
$user_email = $current_user->user_email;
$user_id = get_current_user_id();
$user_custom_picture = houzez_get_profile_pic( $user_id );
$dash_profile_link = houzez_get_template_link_2( 'template/user_dashboard_profile.php' );

if ( class_exists( 'Imovel_Parceiro_Owner_Workflow' ) && Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario() ) {
    $dash_profile_link = add_query_arg( 'imovel_owner_area', 'perfil', houzez_get_template_link_2( 'template/user_dashboard.php' ) );
}

$notifications_url = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::get_dashboard_url( array( 'imovel_dashboard_area' => 'notificacoes' ) ) : '#';
$notification_count = class_exists( 'IPC_Notifications' ) ? IPC_Notifications::instance()->unread_count( $user_id ) : 0;
$notification_aria = $notification_count > 0
    ? __( 'Notificações — existem notificações não lidas', 'imovel-parceiro-core' )
    : __( 'Notificações', 'imovel-parceiro-core' );

$ipc_first_name = trim( explode( ' ', (string) $user_display_name )[0] );
$ipc_today_label = function () {
    return date_i18n( 'j \d\e F \d\e Y' ); // ex: 09 de setembro de 2026
};
?>
<div class="header">
    <div class="header-left flex items-center gap-3">
        <a href="javascript:void(0)" class="menu-btn" aria-label="<?php esc_attr_e( 'Abrir menu', 'houzez' ); ?>">
            <?php echo houzez_dash_icon( 'menu', 'h-5 w-5' ); ?>
        </a>

        <div class="ipc-hide-mobile">
            <p class="ipc-topbar-title leading-tight">
                <?php esc_html_e( 'Olá', 'houzez' ); ?>, <?php echo esc_html( $ipc_first_name ); ?>
            </p>
            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <?php echo houzez_dash_icon( 'calendar-days', 'h-3.5 w-3.5 text-slate-400' ); ?>
                <?php echo esc_html( $ipc_today_label() ); ?>
            </p>
        </div>
    </div>

    <div class="header-right d-flex align-items-center gap-2" style="gap: 10px;">
        <a href="<?php echo esc_url( home_url() ); ?>" target="_blank" class="ipc-visit-btn ipc-hide-mobile">
            <?php echo houzez_dash_icon( 'globe', 'h-4 w-4' ); ?>
            <span><?php esc_html_e( 'Ver site', 'houzez' ); ?></span>
        </a>

        <?php if ( is_user_logged_in() && class_exists( 'IPC_Notifications' ) ) : ?>
            <div class="ipc-notifications-wrap relative">
                <a href="<?php echo esc_url( $notifications_url ); ?>" class="ipc-notif-btn ipc-notifications-toggle<?php echo $notification_count > 0 ? ' has-unread' : ''; ?>" aria-label="<?php echo esc_attr( $notification_aria ); ?>" aria-expanded="false">
                    <?php echo houzez_dash_icon( 'bell', 'h-5 w-5' ); ?>
                    <span class="ipc-notifications-badge"><?php echo esc_html( $notification_count > 99 ? '99+' : $notification_count ); ?></span>
                </a>
                <div class="ipc-notifications-dropdown-panel">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <strong><?php echo esc_html__( 'Notificações', 'imovel-parceiro-core' ); ?></strong>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-link btn-sm p-0 ipc-notifications-mark-all"><?php echo esc_html__( 'Marcar como lidas', 'imovel-parceiro-core' ); ?></button>
                            <button type="button" class="btn btn-link btn-sm p-0 ipc-notifications-delete-all"><?php echo esc_html__( 'Excluir todas', 'imovel-parceiro-core' ); ?></button>
                        </div>
                    </div>
                    <ul class="dropdown-menu ipc-notifications-dropdown"></ul>
                    <div class="dropdown-footer d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link btn-sm p-0 ipc-notifications-enable-push"><?php echo esc_html__( 'Ativar notificações', 'imovel-parceiro-core' ); ?></button>
                        <a href="<?php echo esc_url( $notifications_url ); ?>"><?php echo esc_html__( 'Ver todas', 'imovel-parceiro-core' ); ?></a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( is_user_logged_in() ) : ?>
            <div class="dropdown relative">
                <button class="ipc-user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img width="34" height="34" alt="author" src="<?php echo esc_url( $user_custom_picture ); ?>" class="rounded-full h-[34px] w-[34px] object-cover">
                    <span class="ipc-hide-mobile max-w-[140px] truncate text-sm font-semibold text-slate-800"><?php echo esc_html( $user_display_name ); ?></span>
                    <?php echo houzez_dash_icon( 'chevron-down', 'ipc-hide-mobile h-4 w-4 text-slate-400' ); ?>
                </button>

                <ul class="dropdown-menu rounded-xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/5">
                    <li>
                        <div class="px-3 py-2.5">
                            <h6 class="mb-0.5 text-sm font-semibold text-slate-900"><?php echo esc_html( $user_display_name ); ?></h6>
                            <small class="text-slate-500"><?php echo esc_html( $user_email ); ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider border-slate-100"></li>
                    <?php if ( ! empty( $dash_profile_link ) ) : ?>
                        <li>
                            <a class="dropdown-item rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" href="<?php echo esc_url( $dash_profile_link ); ?>">
                                <?php echo houzez_dash_icon( 'user-round', 'me-2 h-4 w-4 inline-block text-slate-400' ); ?>
                                <span><?php esc_html_e( 'Meu perfil', 'imovel-parceiro-core' ); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
                            <?php echo houzez_dash_icon( 'log-out', 'me-2 h-4 w-4 inline-block text-slate-400' ); ?>
                            <span><?php esc_html_e( 'Sair', 'houzez' ); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

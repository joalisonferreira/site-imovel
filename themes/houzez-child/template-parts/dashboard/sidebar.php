<?php
$ipc_dashboard_logo = houzez_option( 'dashboard_logo', false, 'url' );
$ipc_site_logo      = get_theme_mod( 'logo', false );
$ipc_logo_url       = ! empty( $ipc_dashboard_logo ) ? $ipc_dashboard_logo : $ipc_site_logo;
?>
<div class="dashboard-sidebar">
    <div class="sidebar-logo">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
            <?php if ( ! empty( $ipc_logo_url ) ) : ?>
                <img src="<?php echo esc_url( $ipc_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
            <?php else : ?>
                <span class="ipc-sidebar-brand-text font-bold text-white text-lg tracking-tight"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
            <?php endif; ?>
        </a>
        <a href="javascript:void(0)" class="crose-btn d-xl-none" aria-label="<?php esc_attr_e( 'Fechar menu', 'imovel-parceiro-core' ); ?>">
            <?php echo houzez_dash_icon( 'x', 'ipc-nav-icon !text-slate-300 hover:!text-white' ); ?>
        </a>
    </div>

    <?php get_template_part( 'template-parts/dashboard/dashboard-menu' ); ?>
</div>
<div class="ipc-sidebar-overlay" aria-hidden="true"></div>

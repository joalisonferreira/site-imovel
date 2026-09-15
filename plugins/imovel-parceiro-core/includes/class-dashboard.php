<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Dashboard {
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_render_dashboard' ) );
        add_filter( 'houzez_is_dashboard_filter', array( $this, 'dashboard_page_filter' ) );
    }

    public function dashboard_page_filter( $files ) {
        $files[] = IMOVEL_PARCEIRO_CORE_DIR . 'templates/dashboard.php';
        return $files;
    }

    public function maybe_render_dashboard() {
        if ( ! function_exists( 'houzez_is_dashboard' ) || ! houzez_is_dashboard() ) {
            return;
        }
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }
        add_filter( 'the_content', array( $this, 'render_dashboard_content' ), 20 );
    }

    public function render_dashboard_content( $content ) {
        ob_start();
        include IMOVEL_PARCEIRO_CORE_DIR . 'templates/dashboard.php';
        return $content . ob_get_clean();
    }
}

new Imovel_Parceiro_Dashboard();

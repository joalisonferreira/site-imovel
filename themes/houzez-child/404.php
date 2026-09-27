<?php
/**
 * 404 error page — override Imóvel Parceiro (child theme).
 *
 * Mantém os textos configuráveis do customizer (404-title / 404-des)
 * com fallbacks em PT-BR e o respeito ao location do Elementor.
 *
 * @package Houzez Child
 */
global $houzez_local;
$title_404 = houzez_option( '404-title' );
if ( '' === trim( (string) $title_404 ) ) {
    $title_404 = __( 'Página não encontrada', 'houzez' );
}
$title_des = houzez_option( '404-des' );
if ( '' === trim( (string) $title_des ) ) {
    $title_des = __( 'O imóvel ou a página que você procura mudou de endereço ou não existe mais. Que tal começar uma nova busca?', 'houzez' );
}
$back_home = isset( $houzez_local['404_page'] ) && '' !== trim( (string) $houzez_local['404_page'] )
    ? $houzez_local['404_page']
    : __( 'Voltar ao início', 'houzez' );
get_header(); ?>

<?php if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'single' ) ) { ?>
<section class="ipc-404-wrap">
    <style>
        .ipc-404-wrap{background:linear-gradient(180deg,#fff1f2 0%,#ffffff 55%);padding:72px 0 88px}
        .ipc-404-card{max-width:640px;margin:0 auto;text-align:center;background:#fff;border:1px solid #ffe4e6;border-radius:24px;padding:48px 32px;box-shadow:0 20px 50px rgba(225,29,72,.10)}
        .ipc-404-code{font-size:96px;line-height:1;font-weight:800;letter-spacing:-4px;background:linear-gradient(135deg,#e11d48,#7a1e2b);-webkit-background-clip:text;background-clip:text;color:transparent;margin:0}
        .ipc-404-icon{width:72px;height:72px;margin:0 auto 16px;border-radius:20px;background:#fff1f2;display:flex;align-items:center;justify-content:center}
        .ipc-404-icon svg{width:38px;height:38px;stroke:#e11d48}
        .ipc-404-card h1{font-size:26px;font-weight:800;color:#0f172a;margin:18px 0 10px}
        .ipc-404-card p{font-size:15px;color:#64748b;margin:0 0 24px}
        .ipc-404-card .btn-primary{display:inline-block;border-radius:12px;padding:12px 28px;font-weight:700;text-decoration:none;background:#e11d48;color:#fff}
        .ipc-404-card .btn-primary:hover{background:#be123c;color:#fff}
        @media (max-width:576px){.ipc-404-code{font-size:72px}.ipc-404-card{padding:36px 20px;border-radius:18px}}
    </style>
    <div class="container">
        <div class="ipc-404-card" role="alert">
            <div class="ipc-404-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><circle cx="12" cy="14.5" r="2.2"/><path d="M12 16.7v2.3"/></svg>
            </div>
            <p class="ipc-404-code" aria-hidden="true">404</p>
            <h1><?php echo esc_html( $title_404 ); ?></h1>
            <p><?php echo wp_kses_post( $title_des ); ?></p>
            <a class="btn-primary" href="<?php echo esc_url( site_url() ); ?>"><?php echo esc_html( $back_home ); ?></a>
        </div>
    </div>
</section>
<?php } ?>

<?php get_footer(); ?>

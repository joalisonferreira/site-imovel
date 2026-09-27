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
$buscar_url = '';
if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) ) {
    $buscar_url = Imovel_Parceiro_First_Login_Redirect::buscar_url();
}
if ( '' === $buscar_url ) {
    $buscar_page = get_page_by_path( 'buscar' );
    $buscar_url  = $buscar_page ? get_permalink( $buscar_page->ID ) : home_url( '/buscar/' );
}
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
        .ipc-404-search{display:flex;gap:8px;max-width:420px;margin:0 auto 20px}
        .ipc-404-search input{flex:1;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;font-size:14px}
        .ipc-404-search input:focus{outline:2px solid #e11d48;border-color:#e11d48}
        .ipc-404-search button{background:#e11d48;color:#fff;border:0;border-radius:12px;padding:0 20px;font-weight:700;cursor:pointer}
        .ipc-404-search button:hover{background:#be123c}
        .ipc-404-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        .ipc-404-actions .btn{border-radius:12px;padding:12px 22px;font-weight:700;text-decoration:none}
        .ipc-404-actions .btn-primary{background:#e11d48;color:#fff}
        .ipc-404-actions .btn-primary:hover{background:#be123c;color:#fff}
        .ipc-404-actions .btn-outline{border:1.5px solid #e11d48;color:#e11d48;background:#fff}
        .ipc-404-actions .btn-outline:hover{background:#fff1f2}
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
            <form class="ipc-404-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <input type="search" name="s" placeholder="<?php esc_attr_e( 'Buscar no site…', 'houzez' ); ?>" aria-label="<?php esc_attr_e( 'Buscar no site', 'houzez' ); ?>" />
                <button type="submit"><?php esc_html_e( 'Buscar', 'houzez' ); ?></button>
            </form>
            <div class="ipc-404-actions">
                <a class="btn btn-primary" href="<?php echo esc_url( site_url() ); ?>"><?php echo esc_html( $back_home ); ?></a>
                <a class="btn btn-outline" href="<?php echo esc_url( $buscar_url ); ?>"><?php esc_html_e( 'Buscar imóveis', 'houzez' ); ?></a>
            </div>
        </div>
    </div>
</section>
<?php } ?>

<?php get_footer(); ?>

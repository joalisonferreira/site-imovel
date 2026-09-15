<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
$imovel_area = isset( $_GET['imovel_dashboard_area'] ) ? sanitize_key( wp_unslash( $_GET['imovel_dashboard_area'] ) ) : '';
$imovel_legacy = isset( $_GET['imovel-parceiro'] ) ? sanitize_key( wp_unslash( $_GET['imovel-parceiro'] ) ) : '';
?>
<?php if ( 'parcerias' === $imovel_area ) : ?>
    <?php
    if ( class_exists( 'Imovel_Parceiro_Partnerships' ) ) {
        Imovel_Parceiro_Partnerships::instance()->render_partnerships_panel();
    }
    ?>
<?php elseif ( 'dashboard' === $imovel_legacy ) : ?>
    <div class="imovel-parceiro-dashboard">
        <h3><?php esc_html_e( 'Minhas Parcerias', 'imovel-parceiro-core' ); ?></h3>
        <p><?php esc_html_e( 'Central de oportunidades, negócios e comissões.', 'imovel-parceiro-core' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Parcerias', 'imovel-parceiro-core' ); ?></li>
            <li><?php esc_html_e( 'Oportunidades', 'imovel-parceiro-core' ); ?></li>
            <li><?php esc_html_e( 'Negócios', 'imovel-parceiro-core' ); ?></li>
            <li><?php esc_html_e( 'Comissões', 'imovel-parceiro-core' ); ?></li>
        </ul>
    </div>
<?php endif; ?>

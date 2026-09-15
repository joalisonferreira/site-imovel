<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ) {
    echo '<div class="dashboard-notice"><p>' . esc_html__( 'Fluxo do proprietário indisponível no momento.', 'imovel-parceiro-core' ) . '</p></div>';
    return;
}

$current_user_id = get_current_user_id();
$property_ids = Imovel_Parceiro_Owner_Workflow::get_owner_property_ids( $current_user_id );
?>

<div class="heading d-flex align-items-center justify-content-between">
    <div class="heading-text">
        <h2><?php esc_html_e( 'Documentação dos Imóveis', 'imovel-parceiro-core' ); ?></h2>
        <p class="text-muted mb-0"><?php esc_html_e( 'Acompanhe o status da documentação de cada imóvel. Para enviar ou corrigir documentos, edite o imóvel.', 'imovel-parceiro-core' ); ?></p>
    </div>
</div>

<div class="dashboard-content-block-wrap mt-4">
    <div class="dashboard-content-block">
        <h4><?php esc_html_e( 'Status por imóvel', 'imovel-parceiro-core' ); ?></h4>
        <?php if ( empty( $property_ids ) ) : ?>
            <p class="text-muted mb-0"><?php esc_html_e( 'Nenhum imóvel encontrado.', 'imovel-parceiro-core' ); ?></p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="ipc-table table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Imóvel', 'imovel-parceiro-core' ); ?></th>
                            <th><?php esc_html_e( 'Corretor responsável', 'imovel-parceiro-core' ); ?></th>
                            <th><?php esc_html_e( 'Fluxo', 'imovel-parceiro-core' ); ?></th>
                            <th><?php esc_html_e( 'Documentação', 'imovel-parceiro-core' ); ?></th>
                            <th><?php esc_html_e( 'Aprovação', 'imovel-parceiro-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $property_ids as $property_id ) : ?>
                            <?php $context = Imovel_Parceiro_Owner_Workflow::get_property_owner_context( $property_id, $current_user_id ); ?>
                            <tr>
                                <td><?php echo esc_html( get_the_title( $property_id ) ); ?></td>
                                <td><?php echo esc_html( ! empty( $context['broker_name'] ) ? $context['broker_name'] : '-' ); ?></td>
                                <td><?php echo esc_html( ! empty( $context['workflow_status'] ) ? $context['workflow_status'] : '-' ); ?></td>
                                <td><?php echo esc_html( ! empty( $context['documentation_status'] ) ? $context['documentation_status'] : '-' ); ?></td>
                                <td><?php echo esc_html( ! empty( $context['approval_status'] ) ? $context['approval_status'] : '-' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

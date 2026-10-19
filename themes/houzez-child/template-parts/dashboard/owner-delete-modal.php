<?php
/**
 * Modal de exclusão do imóvel pelo proprietário.
 * Motivo opcional (não obrigatório). Impresso no rodapé do dashboard.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="modal fade" id="ipc-owner-delete-modal" tabindex="-1" aria-labelledby="ipc-owner-delete-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:20px;overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid #f1f5f9;padding:16px 24px;">
                <h5 class="modal-title fw-bold" id="ipc-owner-delete-label"><?php esc_html_e( 'Excluir imóvel', 'imovel-parceiro-core' ); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <p class="mb-3" data-ipc-owner-delete-text="1"><?php esc_html_e( 'Tem certeza de que deseja excluir este imóvel? Ele será movido para a lixeira.', 'imovel-parceiro-core' ); ?></p>
                <label class="form-label fw-semibold" for="ipc-owner-delete-reason"><?php esc_html_e( 'Motivo (opcional)', 'imovel-parceiro-core' ); ?></label>
                <textarea class="form-control" id="ipc-owner-delete-reason" rows="3" placeholder="<?php esc_attr_e( 'Ex.: vendi por fora, desisti de anunciar…', 'imovel-parceiro-core' ); ?>"></textarea>
                <input type="hidden" id="ipc-owner-delete-id" value="" />
            </div>
            <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:14px 24px;gap:8px;">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><?php esc_html_e( 'Cancelar', 'imovel-parceiro-core' ); ?></button>
                <button type="button" class="btn btn-success" data-ipc-owner-delete-confirm="1"><?php esc_html_e( 'Confirmar exclusão', 'imovel-parceiro-core' ); ?></button>
            </div>
        </div>
    </div>
</div>

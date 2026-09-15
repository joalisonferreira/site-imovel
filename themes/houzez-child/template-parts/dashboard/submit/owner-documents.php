<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Imovel_Parceiro_Owner_Workflow' ) || ! Imovel_Parceiro_Owner_Workflow::is_current_user_proprietario() ) {
    return;
}

global $property_data;

$required_types = Imovel_Parceiro_Owner_Workflow::owner_required_document_types();

$existing_by_type = array();
$editing_id       = 0;
if ( houzez_edit_property() && ! empty( $property_data->ID ) ) {
    $editing_id = (int) $property_data->ID;
    $docs       = Imovel_Parceiro_Owner_Workflow::get_property_documents( $editing_id );
    if ( is_array( $docs ) ) {
        foreach ( $docs as $doc ) {
            if ( ! isset( $existing_by_type[ $doc->doc_type ] ) ) {
                $existing_by_type[ $doc->doc_type ] = array(
                    'attachment_id' => (int) $doc->attachment_id,
                    'file_name'     => get_the_title( (int) $doc->attachment_id ),
                    'status'        => $doc->status,
                );
            }
        }
    }
}

$ajax_url = admin_url( 'admin-ajax.php' );
$nonce    = wp_create_nonce( 'imovel_parceiro_core_nonce' );

$status_labels = array(
    'enviado'   => __( 'Enviado — aguardando análise', 'imovel-parceiro-core' ),
    'em_analise' => __( 'Em análise', 'imovel-parceiro-core' ),
    'aprovado'  => __( 'Aprovado', 'imovel-parceiro-core' ),
    'rejeitado' => __( 'Rejeitado', 'imovel-parceiro-core' ),
);
?>
<div id="owner-documents-section" class="block-wrap">
    <div class="block-title-wrap d-flex justify-content-between align-items-center">
        <h2><?php esc_html_e( 'Documentação do imóvel (obrigatório)', 'imovel-parceiro-core' ); ?></h2>
    </div>
    <div class="block-content-wrap">
        <p class="text-muted mb-3"><?php esc_html_e( 'Envie abaixo os documentos do imóvel. É obrigatório anexar os três documentos para concluir o cadastro. Você poderá corrigi-los depois na edição do imóvel.', 'imovel-parceiro-core' ); ?></p>

        <div class="row g-3">
            <?php foreach ( $required_types as $doc_type => $doc_label ) : ?>
                <?php
                $existing = isset( $existing_by_type[ $doc_type ] ) ? $existing_by_type[ $doc_type ] : null;
                $has_file = ! empty( $existing['attachment_id'] );
                $file_name = $has_file ? $existing['file_name'] : '';
                $status_label = ( $has_file && isset( $status_labels[ $existing['status'] ] ) ) ? $status_labels[ $existing['status'] ] : $status_labels['enviado'];
                ?>
                <div class="col-md-4 ipc-owner-doc" data-type="<?php echo esc_attr( $doc_type ); ?>">
                    <label class="form-label"><?php echo esc_html( $doc_label ); ?> <span class="text-danger">*</span></label>
                    <div class="ipc-owner-doc-dropzone" tabindex="0" role="button" aria-label="<?php echo esc_attr( sprintf( __( 'Enviar %s', 'imovel-parceiro-core' ), $doc_label ) ); ?>">
                        <div class="ipc-owner-doc-drop-icon"><i class="houzez-icon icon-upload-button" aria-hidden="true"></i></div>
                        <div class="ipc-owner-doc-drop-cta"><?php esc_html_e( 'Arraste o arquivo aqui ou', 'imovel-parceiro-core' ); ?> <a href="javascript:;" class="ipc-owner-doc-browse"><?php esc_html_e( 'selecione', 'imovel-parceiro-core' ); ?></a></div>
                        <div class="ipc-owner-doc-drop-hint"><?php esc_html_e( 'PDF, JPG ou PNG', 'imovel-parceiro-core' ); ?></div>
                        <input type="file" class="ipc-owner-doc-input" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" hidden>
                    </div>
                    <div class="ipc-owner-doc-file <?php echo $has_file ? '' : 'd-none'; ?>" data-staged="<?php echo $has_file ? '0' : '1'; ?>">
                        <span class="ipc-owner-doc-file-icon"><i class="houzez-icon icon-attachment-2" aria-hidden="true"></i></span>
                        <span class="ipc-owner-doc-file-name"><?php echo esc_html( $file_name ); ?></span>
                        <span class="ipc-owner-doc-status"><?php echo esc_html( $status_label ); ?></span>
                        <button type="button" class="ipc-owner-doc-remove" aria-label="<?php esc_attr_e( 'Remover documento', 'imovel-parceiro-core' ); ?>"><i class="houzez-icon icon-remove-circle" aria-hidden="true"></i></button>
                    </div>
                    <input type="hidden" class="ipc-owner-doc-id" name="imovel_owner_documents[<?php echo esc_attr( $doc_type ); ?>]" value="<?php echo $has_file ? esc_attr( (int) $existing['attachment_id'] ) : ''; ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ipc-owner-doc-errors validate-errors-gal d-none" role="alert"></div>
    </div><!-- block-content-wrap -->
</div><!-- #owner-documents-section -->

<?php if ( empty( $GLOBALS['ipc_owner_documents_styles_rendered'] ) ) : $GLOBALS['ipc_owner_documents_styles_rendered'] = true; ?>
<style>
    .ipc-owner-doc-dropzone {
        border: 2px dashed #d0d5dd;
        border-radius: 10px;
        padding: 1.5rem 1rem;
        text-align: center;
        cursor: pointer;
        background: #fafbfc;
        transition: border-color .15s ease, background .15s ease;
        min-height: 148px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .35rem;
    }
    .ipc-owner-doc-dropzone:hover,
    .ipc-owner-doc-dropzone.ipc-owner-doc-dragover {
        border-color: #00aeef;
        background: #f0faff;
    }
    .ipc-owner-doc-dropzone.ipc-owner-doc-error {
        border-color: #dc3545;
        background: #fff7f7;
    }
    .ipc-owner-doc-drop-icon {
        font-size: 2rem;
        line-height: 1;
        color: #00aeef;
    }
    .ipc-owner-doc-drop-cta {
        font-size: .9rem;
        color: #475467;
    }
    .ipc-owner-doc-drop-cta a {
        color: #00aeef;
        font-weight: 600;
    }
    .ipc-owner-doc-drop-hint {
        font-size: .75rem;
        color: #98a2b3;
    }
    .ipc-owner-doc-file {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: .5rem;
        padding: .5rem .75rem;
        border: 1px solid #eaecf0;
        border-radius: 8px;
        background: #fff;
        font-size: .85rem;
    }
    .ipc-owner-doc-file-icon {
        color: #00aeef;
    }
    .ipc-owner-doc-file-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #344054;
    }
    .ipc-owner-doc-status {
        white-space: nowrap;
        font-size: .75rem;
        color: #667085;
    }
    .ipc-owner-doc-remove {
        border: 0;
        background: transparent;
        color: #dc3545;
        padding: 0;
        line-height: 1;
    }
    .ipc-owner-doc-errors {
        margin-top: 1rem;
        padding: .75rem 1rem;
        border-radius: 8px;
        background: #fff0f0;
        color: #b42318;
        font-size: .9rem;
    }
</style>
<?php endif; ?>

<script>
(function () {
    if (window.__ipcOwnerDocs) { return; }
    window.__ipcOwnerDocs = true;

    var AJAX = <?php echo wp_json_encode( esc_url_raw( $ajax_url ) ); ?>;
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
    var section = document.getElementById('owner-documents-section');
    if (!section) { return; }

    var MSG_REQUIRED = <?php echo wp_json_encode( __( 'Anexe os três documentos obrigatórios antes de continuar.', 'imovel-parceiro-core' ) ); ?>;
    var MSG_FAIL = <?php echo wp_json_encode( __( 'Falha no envio do documento.', 'imovel-parceiro-core' ) ); ?>;
    var MSG_SENT = <?php echo wp_json_encode( __( 'Enviado — aguardando análise', 'imovel-parceiro-core' ) ); ?>;

    function setError(message) {
        var errors = section.querySelector('.ipc-owner-doc-errors');
        if (!errors) { return; }
        errors.textContent = message;
        errors.classList.remove('d-none');
    }
    function clearError() {
        var errors = section.querySelector('.ipc-owner-doc-errors');
        if (!errors) { return; }
        errors.textContent = '';
        errors.classList.add('d-none');
    }

    function showFile(box, attachmentId, fileName, statusText, staged) {
        var fileRow = box.querySelector('.ipc-owner-doc-file');
        var nameEl = box.querySelector('.ipc-owner-doc-file-name');
        var statusEl = box.querySelector('.ipc-owner-doc-status');
        var hidden = box.querySelector('.ipc-owner-doc-id');
        if (hidden) { hidden.value = attachmentId; }
        if (nameEl) { nameEl.textContent = fileName; }
        if (statusEl) { statusEl.textContent = statusText; }
        if (fileRow) {
            fileRow.classList.remove('d-none');
            fileRow.setAttribute('data-staged', staged ? '1' : '0');
        }
        box.querySelector('.ipc-owner-doc-dropzone').classList.remove('ipc-owner-doc-error');
        clearError();
    }

    function hideFile(box) {
        var fileRow = box.querySelector('.ipc-owner-doc-file');
        var hidden = box.querySelector('.ipc-owner-doc-id');
        if (hidden) { hidden.value = ''; }
        if (fileRow) { fileRow.classList.add('d-none'); }
    }

    function uploadFile(box, file) {
        var docType = box.getAttribute('data-type');
        var fd = new FormData();
        fd.append('action', 'imovel_parceiro_owner_stage_document');
        fd.append('nonce', NONCE);
        fd.append('doc_type', docType);
        fd.append('document_file', file);

        var statusEl = box.querySelector('.ipc-owner-doc-status');
        if (statusEl) { statusEl.textContent = <?php echo wp_json_encode( __( 'Enviando...', 'imovel-parceiro-core' ) ); ?>; }

        fetch(AJAX, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result && result.success && result.data && result.data.attachment_id) {
                    showFile(box, result.data.attachment_id, result.data.file_name || file.name, MSG_SENT, true);
                    return;
                }
                var msg = (result && result.data && result.data.message) ? result.data.message : MSG_FAIL;
                setError(msg);
            })
            .catch(function () {
                setError(MSG_FAIL);
            });
    }

    function removeStaged(box) {
        var hidden = box.querySelector('.ipc-owner-doc-id');
        var attachmentId = hidden ? hidden.value : '';
        var staged = box.querySelector('.ipc-owner-doc-file') && box.querySelector('.ipc-owner-doc-file').getAttribute('data-staged') === '1';
        hideFile(box);
        if (staged && attachmentId) {
            var fd = new FormData();
            fd.append('action', 'imovel_parceiro_owner_remove_staged_document');
            fd.append('nonce', NONCE);
            fd.append('attachment_id', attachmentId);
            fetch(AJAX, { method: 'POST', body: fd, credentials: 'same-origin' });
        }
    }

    section.querySelectorAll('.ipc-owner-doc').forEach(function (box) {
        var dropzone = box.querySelector('.ipc-owner-doc-dropzone');
        var input = box.querySelector('.ipc-owner-doc-input');
        var browse = box.querySelector('.ipc-owner-doc-browse');
        var removeBtn = box.querySelector('.ipc-owner-doc-remove');

        function openPicker() { if (input) { input.click(); } }
        if (dropzone) { dropzone.addEventListener('click', openPicker); }
        if (browse) { browse.addEventListener('click', function (e) { e.stopPropagation(); openPicker(); }); }
        if (input) {
            input.addEventListener('change', function () {
                if (input.files && input.files.length) { uploadFile(box, input.files[0]); }
                input.value = '';
            });
        }
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) { e.preventDefault(); dropzone.classList.add('ipc-owner-doc-dragover'); });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) { e.preventDefault(); dropzone.classList.remove('ipc-owner-doc-dragover'); });
            });
            dropzone.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    uploadFile(box, e.dataTransfer.files[0]);
                }
            });
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', function () { removeStaged(box); });
        }
    });

    function validate() {
        var missing = [];
        section.querySelectorAll('.ipc-owner-doc').forEach(function (box) {
            var hidden = box.querySelector('.ipc-owner-doc-id');
            var dropzone = box.querySelector('.ipc-owner-doc-dropzone');
            if (!hidden || !hidden.value) {
                missing.push(box);
                if (dropzone) { dropzone.classList.add('ipc-owner-doc-error'); }
            }
        });
        if (missing.length) {
            setError(MSG_REQUIRED);
            section.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        clearError();
        return true;
    }

    var form = document.getElementById('submit_property_form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validate()) { e.preventDefault(); e.stopPropagation(); }
        }, true);
    }
    document.addEventListener('click', function (e) {
        var next = e.target.closest && e.target.closest('.btn-next');
        if (next && section.offsetParent !== null) {
            if (!validate()) { e.preventDefault(); e.stopPropagation(); }
        }
    }, true);
})();
</script>

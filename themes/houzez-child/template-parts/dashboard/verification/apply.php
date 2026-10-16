<?php
/**
 * Verification Apply Form — Child Override (ImobiSec: UF do CRECI + nascimento).
 *
 * Cópia do partial do tema pai + bloco "Dados do CRECI" exibido apenas para
 * corretor (houzez_agent) e imobiliária (houzez_agency). Os campos viajam no
 * FormData existente (verification.js) e são capturados em
 * Imovel_Parceiro_ImobiSec::capture_identity_fields().
 */
global $verification_data, $apply_verification_link, $cancel_verification_link, $document_types;

$ipc_user_id = get_current_user_id();
$ipc_user    = $ipc_user_id ? get_userdata( $ipc_user_id ) : null;
$ipc_is_broker = $ipc_user && (bool) array_intersect( (array) $ipc_user->roles, array( 'houzez_agent', 'houzez_agency' ) );
$ipc_uf_saved = $ipc_user_id ? get_user_meta( $ipc_user_id, 'imovel_parceiro_creci_uf', true ) : '';
$ipc_birth_saved = $ipc_user_id ? get_user_meta( $ipc_user_id, 'imovel_parceiro_birth_date', true ) : '';

if( !isset( $_GET['apply'] ) ): ?>
<p class="mb-3"><?php esc_html_e('Sua conta ainda não foi verificada. Solicite a verificação para exibir o selo no seu perfil e gerar confiança.', 'imovel-parceiro-core'); ?></p>
<p class="mb-3"><?php esc_html_e('O selo de verificado ajuda os usuários a reconhecer você como membro confiável da comunidade.', 'imovel-parceiro-core'); ?></p>
<div class="mt-3">
    <a href="<?php echo esc_url($apply_verification_link); ?>" class="btn btn-success">
        <?php esc_html_e('Solicitar verificação', 'imovel-parceiro-core'); ?>
    </a>
</div>

<?php else: ?>

<p class="text-muted mb-3"><?php esc_html_e('Preencha o formulário abaixo e envie um documento de identificação válido para verificar sua identidade.', 'imovel-parceiro-core'); ?></p>

<form id="houzez-verification-form" class="verification-form" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="full_name" class="form-label"><?php esc_html_e('Nome completo', 'imovel-parceiro-core'); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control form-control-lg" required aria-required="true"
                    value="<?php echo isset($verification_data['full_name']) ? esc_attr($verification_data['full_name']) : ''; ?>"
                    placeholder="<?php esc_attr_e('Digite seu nome completo como está no documento', 'imovel-parceiro-core'); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="document_type" class="form-label"><?php esc_html_e('Tipo de documento', 'imovel-parceiro-core'); ?> <span class="required" aria-hidden="true">*</span></label>
                <select id="document_type" name="document_type" class="selectpicker form-control bs-select-hidden" required aria-required="true">
                    <option value=""><?php esc_html_e('Selecione o tipo de documento', 'imovel-parceiro-core'); ?></option>
                    <?php foreach ($document_types as $value => $document): ?>
                        <option value="<?php echo esc_attr($value); ?>"
                            data-requires-back="<?php echo esc_attr($document['requires_back'] ? 'true' : 'false'); ?>"
                            <?php selected(isset($verification_data['document_type']) ? $verification_data['document_type'] : '', $value); ?>>
                            <?php echo esc_html($document['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if ( $ipc_is_broker ) : ?>
    <div class="row" id="imovel-creci-fields">
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="imovel_creci_uf" class="form-label"><?php esc_html_e( 'UF do CRECI', 'imovel-parceiro-core' ); ?> <span class="required">*</span></label>
                <select id="imovel_creci_uf" name="imovel_creci_uf" class="form-control form-control-lg" required>
                    <option value=""><?php esc_html_e( 'Selecione a UF', 'imovel-parceiro-core' ); ?></option>
                    <?php foreach ( Imovel_Parceiro_ImobiSec::states() as $ipc_uf ) : ?>
                        <option value="<?php echo esc_attr( $ipc_uf ); ?>" <?php selected( $ipc_uf_saved, $ipc_uf ); ?>><?php echo esc_html( $ipc_uf ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="file-help-text"><?php esc_html_e( 'Estado onde seu CRECI está registrado. Usado na validação automática.', 'imovel-parceiro-core' ); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="imovel_birth_date" class="form-label"><?php esc_html_e( 'Data de nascimento', 'imovel-parceiro-core' ); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="date" id="imovel_birth_date" name="imovel_birth_date" class="form-control form-control-lg" required aria-required="true"
                    value="<?php echo esc_attr( $ipc_birth_saved ); ?>" min="1900-01-01" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" aria-describedby="imovel-birth-help">
                <p class="file-help-text" id="imovel-birth-help"><?php esc_html_e( 'Obrigatória para validação automática do CRECI (pessoa física). Sem ela, seu pedido vai para fila manual.', 'imovel-parceiro-core' ); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="form-group mb-3">
                <label class="form-label"><?php esc_html_e('Frente do documento', 'imovel-parceiro-core'); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="file" id="verification_document" name="verification_document" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required aria-describedby="verification-front-help">
                <p class="file-help-text" id="verification-front-help"><?php esc_html_e('Envie a frente do documento. Formatos: JPG, PNG, PDF. Tamanho máximo: 10 MB', 'imovel-parceiro-core'); ?></p>
            </div>
        </div>
    </div>

    <div class="row document-back-upload" style="display: none;">
        <div class="col-12">
            <div class="form-group mb-3">
                <label class="form-label"><?php esc_html_e('Verso do documento', 'imovel-parceiro-core'); ?> <span class="required" aria-hidden="true">*</span></label>
                <input type="file" id="verification_document_back" name="verification_document_back" class="form-control" accept=".jpg,.jpeg,.png,.pdf" aria-describedby="verification-back-help">
                <p class="file-help-text" id="verification-back-help"><?php esc_html_e('Envie o verso do documento. Formatos: JPG, PNG, PDF. Tamanho máximo: 10 MB', 'imovel-parceiro-core'); ?></p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="form-group mb-4 mt-4">
            <div class="verification-agreement bg-light p-4 rounded border shadow-sm">
                <h4 class="mb-3"><?php esc_html_e('Termos da verificação', 'imovel-parceiro-core'); ?></h4>
                <div class="terms-content">
                    <p class="fw-bold mb-2"><?php esc_html_e('Ao enviar sua solicitação de verificação, você declara e concorda com os seguintes termos:', 'imovel-parceiro-core'); ?></p>
                    <ul class="mb-3 ps-4">
                        <li class="mb-2 text-muted"><?php esc_html_e('As informações pessoais e os documentos enviados são verdadeiros, autênticos e pertencem a você.', 'imovel-parceiro-core'); ?></li>
                        <li class="mb-2 text-muted"><?php esc_html_e('Todos os documentos estão válidos e dentro do prazo de validade.', 'imovel-parceiro-core'); ?></li>
                        <li class="mb-2 text-muted"><?php esc_html_e('Você autoriza o processamento e armazenamento dessas informações para verificação de identidade.', 'imovel-parceiro-core'); ?></li>
                        <li class="mb-2 text-muted"><?php esc_html_e('Podemos solicitar informações adicionais para concluir a verificação.', 'imovel-parceiro-core'); ?></li>
                        <li class="mb-2 text-muted"><?php esc_html_e('Informações falsas podem resultar em suspensão imediata da conta e medidas legais.', 'imovel-parceiro-core'); ?></li>
                        <li class="mb-2 text-muted"><?php esc_html_e('Seu status de verificação pode ser exibido publicamente no seu perfil.', 'imovel-parceiro-core'); ?></li>
                    </ul>
                    <p class="mb-1 text-muted small"><?php esc_html_e('Seus dados pessoais são protegidos conforme nossa Política de Privacidade e a LGPD.', 'imovel-parceiro-core'); ?></p>
                    <p class="mb-0 text-muted small"><?php esc_html_e('A verificação está sujeita a revisão e pode ser revogada em caso de violação dos termos.', 'imovel-parceiro-core'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div id="verification-messages" role="alert" aria-live="polite"></div>

    <div class="form-group d-flex justify-content-between gap-2">
        <button type="submit" id="submit-verification-btn" class="btn btn-success btn-lg">
        <?php esc_html_e('Enviar solicitação', 'imovel-parceiro-core'); ?>
        </button>

        <a href="<?php echo esc_url($cancel_verification_link); ?>" class="btn btn-danger btn-lg">
            <?php esc_html_e('Cancelar', 'imovel-parceiro-core'); ?>
        </a>
    </div>
</form>
<?php endif; ?>

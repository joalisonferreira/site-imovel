<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'Imovel_Parceiro_Watermark' ) ) {
    return;
}

$settings = Imovel_Parceiro_Watermark::get_settings();
$watermark_url = $settings['attachment_id'] ? wp_get_attachment_url( $settings['attachment_id'] ) : '';

$sample_property = get_posts(
    array(
        'post_type' => 'property',
        'posts_per_page' => 1,
        'post_status' => array( 'publish', 'pending', 'draft', 'private' ),
        'meta_key' => 'fave_property_images',
    )
);

$sample_image_url = '';
if ( ! empty( $sample_property ) ) {
    $sample_ids = get_post_meta( $sample_property[0]->ID, 'fave_property_images' );
    if ( ! empty( $sample_ids ) ) {
        $sample_image_url = wp_get_attachment_image_url( absint( $sample_ids[0] ), 'large' );
    }
}

$notice_type = isset( $_GET['imovel_wm_notice'] ) ? sanitize_key( wp_unslash( $_GET['imovel_wm_notice'] ) ) : '';
$notice_text = isset( $_GET['imovel_wm_message'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_wm_message'] ) ) : '';

$ipc_positions = array(
    'top-left' => array( __( 'Topo esquerda', 'imovel-parceiro-core' ), 'top-left' ),
    'top-center' => array( __( 'Topo centro', 'imovel-parceiro-core' ), 'top-center' ),
    'top-right' => array( __( 'Topo direita', 'imovel-parceiro-core' ), 'top-right' ),
    'center' => array( __( 'Centro', 'imovel-parceiro-core' ), 'center' ),
    'bottom-left' => array( __( 'Base esquerda', 'imovel-parceiro-core' ), 'bottom-left' ),
    'bottom-center' => array( __( 'Base centro', 'imovel-parceiro-core' ), 'bottom-center' ),
    'bottom-right' => array( __( 'Base direita', 'imovel-parceiro-core' ), 'bottom-right' ),
);
?>
<?php $ipc_wm_status = Imovel_Parceiro_Watermark::get_last_status(); ?>
<form method="post" enctype="multipart/form-data" class="ipc-watermark-form">
    <?php wp_nonce_field( 'imovel_watermark_update', '_imovel_watermark_nonce' ); ?>
    <input type="hidden" name="imovel_watermark_action" value="save" />

    <?php if ( ! empty( $ipc_wm_status ) ) : ?>
        <div class="mb-4 inline-flex w-full items-center gap-2 rounded-xl border <?php echo ! empty( $ipc_wm_status['ok'] ) ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-rose-100 bg-rose-50 text-rose-700'; ?> px-4 py-3 text-sm font-medium">
            <?php echo houzez_dash_icon( ! empty( $ipc_wm_status['ok'] ) ? 'circle-check' : 'triangle-alert', 'h-4 w-4 shrink-0' ); ?>
            <span>
                <?php
                if ( ! empty( $ipc_wm_status['ok'] ) ) {
                    echo esc_html( sprintf( __( 'Última aplicação: anexo #%d em %s.', 'imovel-parceiro-core' ), (int) $ipc_wm_status['attachment_id'], $ipc_wm_status['time'] ) );
                } else {
                    echo esc_html( $ipc_wm_status['message'] );
                    if ( ! empty( $ipc_wm_status['time'] ) ) {
                        echo esc_html( sprintf( __( ' (anexo #%d em %s)', 'imovel-parceiro-core' ), (int) $ipc_wm_status['attachment_id'], $ipc_wm_status['time'] ) );
                    }
                }
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ( $notice_type && $notice_text ) : ?>
        <div class="mb-4 inline-flex w-full items-center gap-2 rounded-xl border <?php echo 'updated' === $notice_type ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-rose-100 bg-rose-50 text-rose-700'; ?> px-4 py-3 text-sm font-medium">
            <?php echo houzez_dash_icon( 'circle-check', 'h-4 w-4 shrink-0' ); ?>
            <?php echo esc_html( $notice_text ); ?>
        </div>
    <?php endif; ?>

    <?php $ipc_wm_reqs = Imovel_Parceiro_Watermark::server_requirements(); ?>
    <div class="mb-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <h4 class="text-base font-bold text-slate-900"><?php esc_html_e( 'Requisitos do servidor', 'imovel-parceiro-core' ); ?></h4>
        <p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'A marca d\'água precisa de Imagick ou GD com suporte a JPEG/PNG. Itens em vermelho impedem o funcionamento.', 'imovel-parceiro-core' ); ?></p>
        <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <?php foreach ( $ipc_wm_reqs as $ipc_req ) : ?>
                <li class="flex items-start gap-2 rounded-xl border px-3 py-2 text-sm <?php echo ! empty( $ipc_req['ok'] ) ? 'border-emerald-100 bg-emerald-50/50 text-emerald-800' : 'border-rose-100 bg-rose-50/50 text-rose-700'; ?>">
                    <?php echo houzez_dash_icon( ! empty( $ipc_req['ok'] ) ? 'circle-check' : 'triangle-alert', 'h-4 w-4 shrink-0 mt-0.5' ); ?>
                    <span>
                        <strong class="font-semibold"><?php echo esc_html( $ipc_req['label'] ); ?></strong>
                        <span class="block text-xs opacity-80"><?php echo esc_html( $ipc_req['detail'] ); ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
        <!-- Controles -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between gap-3 flex-wrap border-b border-slate-100 pb-4 mb-5">
                <div>
                    <h4 class="text-base font-bold text-slate-900"><?php esc_html_e( 'Configuração da marca d\'água', 'imovel-parceiro-core' ); ?></h4>
                    <p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'Controlamos a aplicação de forma automática em novos uploads de fotos.', 'imovel-parceiro-core' ); ?></p>
                </div>
                <label class="ipc-switch" for="imovel_watermark_enabled">
                    <input type="checkbox" id="imovel_watermark_enabled" name="imovel_watermark_enabled" value="1" <?php checked( (int) $settings['enabled'], 1 ); ?> />
                    <span class="ipc-switch__track"><span class="ipc-switch__thumb"></span></span>
                    <span class="ipc-switch__label">
                        <span class="block text-sm font-semibold text-slate-800"><?php esc_html_e( 'Marcar d\'água ativa', 'imovel-parceiro-core' ); ?></span>
                        <span class="block text-xs text-slate-500"><?php esc_html_e( 'Aplicar em novos uploads', 'imovel-parceiro-core' ); ?></span>
                    </span>
                </label>
            </div>

            <!-- Posição -->
            <div class="mb-6">
                <label class="mb-2 block text-sm font-semibold text-slate-800"><?php esc_html_e( 'Posição na foto', 'imovel-parceiro-core' ); ?></label>
                <div class="ipc-wm-position" role="radiogroup" aria-label="<?php esc_attr_e( 'Posição da marca d\'água', 'imovel-parceiro-core' ); ?>">
                    <?php foreach ( $ipc_positions as $ipc_key => $ipc_data ) : ?>
                        <label class="ipc-wm-position__item">
                            <input type="radio" name="imovel_watermark_position" value="<?php echo esc_attr( $ipc_key ); ?>" <?php checked( $settings['position'], $ipc_key ); ?> />
                            <span class="ipc-wm-position__box">
                                <span class="ipc-wm-position__dot <?php echo esc_attr( 'dot-' . sanitize_html_class( $ipc_data[1] ) ); ?>" aria-hidden="true"></span>
                            </span>
                            <span class="ipc-wm-position__label"><?php echo esc_html( $ipc_data[0] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Opacidade -->
            <div class="mb-6">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <label for="imovel_watermark_opacity" class="font-semibold text-slate-800"><?php esc_html_e( 'Opacidade', 'imovel-parceiro-core' ); ?></label>
                    <span class="ipc-range-value rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                        <span data-ipc-wm-opacity-value><?php echo esc_html( $settings['opacity'] ); ?></span>%
                    </span>
                </div>
                <input type="range" id="imovel_watermark_opacity" name="imovel_watermark_opacity" min="5" max="100" step="1" value="<?php echo esc_attr( $settings['opacity'] ); ?>" class="ipc-range w-full" />
                <div class="mt-1 flex justify-between text-[11px] text-slate-400"><span>5%</span><span>100%</span></div>
            </div>

            <!-- Tamanho -->
            <div class="mb-6">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <label for="imovel_watermark_size" class="font-semibold text-slate-800"><?php esc_html_e( 'Tamanho', 'imovel-parceiro-core' ); ?></label>
                    <span class="ipc-range-value rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                        <span data-ipc-wm-size-value><?php echo esc_html( $settings['size_percent'] ); ?></span>%
                    </span>
                </div>
                <input type="range" id="imovel_watermark_size" name="imovel_watermark_size" min="5" max="80" step="1" value="<?php echo esc_attr( $settings['size_percent'] ); ?>" class="ipc-range w-full" />
                <div class="mt-1 flex justify-between text-[11px] text-slate-400"><span>5%</span><span>80%</span></div>
            </div>

            <!-- Upload -->
            <div class="mb-6">
                <label class="mb-2 block text-sm font-semibold text-slate-800"><?php esc_html_e( 'Imagem da marca d\'água', 'imovel-parceiro-core' ); ?></label>
                <label class="ipc-dropzone<?php echo $watermark_url ? ' has-image' : ''; ?>" for="imovel_watermark_file" data-ipc-wm-dropzone>
                    <input type="file" id="imovel_watermark_file" name="imovel_watermark_file" accept="image/png,image/jpeg,image/webp" class="sr-only" onchange="document.querySelector('[data-ipc-wm-filename]').textContent = this.files.length ? this.files[0].name : '<?php esc_attr_e( 'Nenhum arquivo selecionado', 'imovel-parceiro-core' ); ?>';" />
                    <span class="ipc-dropzone__icon"><?php echo houzez_dash_icon( 'upload', 'h-5 w-5' ); ?></span>
                    <span class="ipc-dropzone__text">
                        <span class="block text-sm font-semibold text-slate-700"><?php esc_html_e( 'Clique para enviar uma imagem', 'imovel-parceiro-core' ); ?></span>
                        <span class="block text-xs text-slate-400" data-ipc-wm-filename><?php esc_html_e( 'PNG com fundo transparente recomendado', 'imovel-parceiro-core' ); ?></span>
                    </span>
                </label>
            </div>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                <button type="submit" name="imovel_watermark_action" value="save" class="ipc-quick-add">
                    <?php echo houzez_dash_icon( 'check', 'h-4 w-4' ); ?>
                    <?php esc_html_e( 'Salvar configuração', 'imovel-parceiro-core' ); ?>
                </button>
                <?php if ( $settings['attachment_id'] ) : ?>
                    <button type="submit" name="imovel_watermark_action" value="remove" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-600 transition-all hover:bg-rose-100">
                        <?php echo houzez_dash_icon( 'trash-2', 'h-4 w-4' ); ?>
                        <?php esc_html_e( 'Remover imagem', 'imovel-parceiro-core' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php
            $ipc_wm_pending = Imovel_Parceiro_Watermark::count_pending();
            $ipc_wm_repair = Imovel_Parceiro_Watermark::count_repair();
            $ipc_wm_total = $ipc_wm_pending + $ipc_wm_repair;
            ?>
            <div class="mt-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h4 class="text-base font-bold text-slate-900"><?php esc_html_e( 'Fotos já cadastradas', 'imovel-parceiro-core' ); ?></h4>
                <p class="mt-0.5 text-sm text-slate-500">
                    <?php
                    echo esc_html(
                        sprintf(
                            _n( '%d foto de imóvel ainda sem marca d\'água.', '%d fotos de imóveis ainda sem marca d\'água.', $ipc_wm_pending, 'imovel-parceiro-core' ),
                            $ipc_wm_pending
                        )
                    );
                    ?>
                    <?php if ( $ipc_wm_repair > 0 ) : ?>
                        <?php
                        echo esc_html(
                            sprintf(
                                _n( 'Mais %d ampliação para reparar.', 'Mais %d ampliações para reparar.', $ipc_wm_repair, 'imovel-parceiro-core' ),
                                $ipc_wm_repair
                            )
                        );
                        ?>
                    <?php endif; ?>
                </p>
                <div class="mt-4 flex items-center gap-3 flex-wrap">
                    <button type="button" id="ipc-wm-bulk-btn" class="ipc-quick-add" <?php disabled( 0 === $ipc_wm_total ); ?>>
                        <?php echo houzez_dash_icon( 'droplets', 'h-4 w-4' ); ?>
                        <?php esc_html_e( 'Aplicar marca d\'água agora', 'imovel-parceiro-core' ); ?>
                    </button>
                    <span id="ipc-wm-bulk-status" class="text-sm text-slate-500"></span>
                </div>
                <div class="mt-3 hidden h-2.5 w-full overflow-hidden rounded-full bg-slate-200" id="ipc-wm-bulk-bar-wrap">
                    <div id="ipc-wm-bulk-bar" class="h-full w-0 rounded-full bg-emerald-500 transition-all"></div>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-slate-400"><?php esc_html_e( 'Processa em lotes de 5 fotos por vez. Fotos já marcadas são ignoradas; a marca é definitiva (não reaplica sobre foto já marcada).', 'imovel-parceiro-core' ); ?></p>
            </div>
            <script>
            (function(){
                var btn = document.getElementById('ipc-wm-bulk-btn');
                if (!btn || btn.__ipcBound) { return; }
                btn.__ipcBound = true;
                var total = <?php echo absint( $ipc_wm_total ); ?>;
                var done = 0;
                var mode = 'new';
                var nonce = '<?php echo esc_js( wp_create_nonce( 'imovel_watermark_bulk' ) ); ?>';
                var ajaxurl = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>';
                var statusEl = document.getElementById('ipc-wm-bulk-status');
                var barWrap = document.getElementById('ipc-wm-bulk-bar-wrap');
                var bar = document.getElementById('ipc-wm-bulk-bar');
                function setStatus(msg){ if (statusEl) { statusEl.textContent = msg; } }
                function setBar(){
                    if (!barWrap || !bar || !total) { return; }
                    barWrap.classList.remove('hidden');
                    bar.style.width = Math.min(100, Math.round(done / total * 100)) + '%';
                }
                btn.addEventListener('click', function(){
                    btn.disabled = true;
                    setStatus('<?php echo esc_js( __( 'Processando…', 'imovel-parceiro-core' ) ); ?>');
                    setBar();
                    function next(){
                        var data = new FormData();
                        data.append('action', 'imovel_parceiro_watermark_bulk');
                        data.append('nonce', nonce);
                        data.append('mode', mode);
                        fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                            .then(function(r){ return r.json(); })
                            .then(function(res){
                                if (!res || !res.success) {
                                    setStatus((res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Falha na aplicação.', 'imovel-parceiro-core' ) ); ?>');
                                    btn.disabled = false;
                                    return;
                                }
                                done += (res.data.batch || 0);
                                setBar();
                                if (res.data.remaining > 0) {
                                    setStatus(done + ' / ' + total + '…');
                                    next();
                                } else if (mode === 'new') {
                                    mode = 'repair';
                                    setStatus('<?php echo esc_js( __( 'Fotos novas OK. Reparando ampliações…', 'imovel-parceiro-core' ) ); ?>');
                                    next();
                                } else {
                                    setStatus('<?php echo esc_js( __( 'Concluído. Recarregue a página para atualizar.', 'imovel-parceiro-core' ) ); ?>');
                                    setTimeout(function(){ window.location.reload(); }, 1200);
                                }
                            })
                            .catch(function(){
                                setStatus('<?php echo esc_js( __( 'Erro de comunicação.', 'imovel-parceiro-core' ) ); ?>');
                                btn.disabled = false;
                            });
                    }
                    next();
                });
            })();
            </script>
        </div>

        <!-- Preview ao vivo -->
        <aside class="lg:sticky lg:top-[92px] self-start">
            <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h4 class="text-base font-bold text-slate-900"><?php esc_html_e( 'Prévia ao vivo', 'imovel-parceiro-core' ); ?></h4>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-600">
                        <?php echo houzez_dash_icon( 'sparkles', 'h-3.5 w-3.5' ); ?>
                        <?php esc_html_e( 'Atualização em tempo real', 'imovel-parceiro-core' ); ?>
                    </span>
                </div>

                <div class="ipc-wm-preview relative overflow-hidden rounded-xl border border-slate-200 bg-slate-100" style="min-height:220px;">
                    <?php if ( $sample_image_url ) : ?>
                        <img src="<?php echo esc_url( $sample_image_url ); ?>" alt="" class="block h-auto w-full" />
                    <?php else : ?>
                        <div class="flex h-[220px] items-center justify-center text-sm text-slate-400"><?php esc_html_e( 'Sem imagem de imóvel para prévia.', 'imovel-parceiro-core' ); ?></div>
                    <?php endif; ?>

                    <?php if ( $watermark_url ) : ?>
                        <img src="<?php echo esc_url( $watermark_url ); ?>" alt="" class="ipc-wm-preview__mark" data-ipc-wm-mark data-position="<?php echo esc_attr( $settings['position'] ); ?>" data-size="<?php echo esc_attr( (int) $settings['size_percent'] ); ?>" data-opacity="<?php echo esc_attr( (int) $settings['opacity'] ); ?>" />
                        <span class="ipc-wm-preview__pill"><?php echo esc_html( sprintf( __( 'Tamanho %d%% • Opacidade %d%%', 'imovel-parceiro-core' ), (int) $settings['size_percent'], (int) $settings['opacity'] ) ); ?></span>
                    <?php else : ?>
                        <div id="ipc-wm-preview-empty" class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                                <?php echo houzez_dash_icon( 'droplets', 'h-6 w-6' ); ?>
                            </span>
                            <p class="text-sm font-medium text-slate-400"><?php esc_html_e( 'Envie uma imagem para ativar a prévia', 'imovel-parceiro-core' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-slate-400">
                    <?php echo houzez_dash_icon( 'info', 'h-3.5 w-3.5 inline-block text-slate-300' ); ?>
                    <?php esc_html_e( 'A marca d\'água é aplicada automaticamente em novas fotos de imóveis, preservando as imagens já publicadas.', 'imovel-parceiro-core' ); ?>
                </p>
            </div>
        </aside>
    </div>
</form>

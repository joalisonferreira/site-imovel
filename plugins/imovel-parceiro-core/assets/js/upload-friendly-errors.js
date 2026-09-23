/**
 * Mensagens amigáveis (PT-BR) para erros de upload do formulário de imóveis.
 *
 * O Houzez exibe erros crus do plupload ("Error #-600: File size error.")
 * e motivos em inglês do servidor. Este script observa os containers de
 * erro da galeria/anexos e substitui por textos claros, mantendo o detalhe
 * técnico em <small> quando disponível.
 */
(function () {
    'use strict';

    function imageLimitLabel() {
        try {
            if (window.houzezProperty && houzezProperty.image_max_file_size) {
                return String(houzezProperty.image_max_file_size);
            }
        } catch (e) {}
        return '';
    }

    function attachmentLimitLabel() {
        try {
            if (window.houzezProperty && houzezProperty.attachment_max_file_size) {
                return String(houzezProperty.attachment_max_file_size);
            }
        } catch (e) {}
        return '';
    }

    function friendlyForCode(code, original) {
        var imgLimit = imageLimitLabel();
        var attLimit = attachmentLimitLabel();
        switch (Number(code)) {
            case -100: return 'Navegador não suportou o envio. Atualize o navegador ou tente em outro (Chrome/Edge).';
            case -200: return 'Erro de conexão durante o envio. Verifique sua internet e tente novamente.';
            case -300: return 'Não foi possível iniciar o envio. Recarregue a página e tente de novo.';
            case -400: return 'Erro de segurança no envio. Recarregue a página e tente novamente.';
            case -500: return 'Erro interno no envio. Tente de novo em alguns segundos.';
            case -600:
                return 'Foto muito grande para enviar.' +
                    (imgLimit ? ' O limite é de ' + imgLimit + '.' : '') +
                    ' Dica: reduza a foto (ex.: salve em qualidade menor ou tire print da imagem) e envie de novo.';
            case -601:
                return 'Formato de arquivo não aceito. Envie apenas JPG, PNG, GIF ou WebP.';
            case -602:
                return 'Esta foto já foi adicionada. Escolha outra imagem.';
            case -700:
                return 'Imagem corrompida ou inválida. Abra a foto no celular/computador e salve uma cópia antes de enviar.';
            default:
                return '';
        }
    }

    function friendlyForServerReason(reason) {
        if (!reason) { return ''; }
        var r = String(reason);
        var low = r.toLowerCase();
        if (low.indexOf('invalid file type') !== -1 || low.indexOf('allowed image types') !== -1 || low.indexOf('file content does not match') !== -1) {
            return 'Formato de arquivo não aceito. Envie apenas JPG, PNG, GIF ou WebP.';
        }
        if (low.indexOf('invalid image file') !== -1) {
            return 'Não conseguimos ler essa imagem. Salve uma cópia e tente de novo.';
        }
        if (low.indexOf('image upload failed') !== -1) {
            return 'Não foi possível enviar a imagem. Verifique sua conexão e tente novamente.';
        }
        if (low.indexOf('invalid nonce') !== -1) {
            return 'Sua sessão expirou. Recarregue a página e tente enviar de novo.';
        }
        if (low.indexOf('failed to remove attachment') !== -1) {
            return 'Não foi possível excluir a foto agora. Recarregue a página e tente de novo.';
        }
        if (low.indexOf('maximum file upload limit') !== -1 || low.indexOf('max-limit') !== -1) {
            return 'Você atingiu o número máximo de fotos. Exclua uma foto para enviar outra.';
        }
        return '';
    }

    function prettifyNode(container, isAttachment) {
        var html = container.innerHTML;
        if (!html || html.indexOf('ipc-friendly') !== -1) { return; }

        // Padrão plupload: "Error #-600: File size error."
        var codeMatch = html.match(/Error\s*#\s*(-?\d+)\s*:?\s*([^<]*)/i);
        if (codeMatch) {
            var friendly = friendlyForCode(codeMatch[1], codeMatch[2]);
            if (friendly) {
                var limit = isAttachment ? attachmentLimitLabel() : imageLimitLabel();
                container.innerHTML =
                    '<span class="ipc-friendly">' + escapeHtml(friendly) + '</span>' +
                    (limit ? ' <small class="text-muted">(limite: ' + escapeHtml(limit) + ')</small>' : '') +
                    ' <small class="text-muted">(código ' + escapeHtml(codeMatch[1]) + ')</small>';
                return;
            }
        }

        var serverFriendly = friendlyForServerReason(container.textContent || '');
        if (serverFriendly) {
            container.innerHTML = '<span class="ipc-friendly">' + escapeHtml(serverFriendly) + '</span>';
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function watch(id, isAttachment) {
        var el = document.getElementById(id);
        if (!el) { return; }
        prettifyNode(el, isAttachment);
        if (window.MutationObserver) {
            var obs = new MutationObserver(function () { prettifyNode(el, isAttachment); });
            obs.observe(el, { childList: true, subtree: true, characterData: true });
        }
    }

    function init() {
        watch('houzez_errors', false);
        watch('houzez_atach_errors', true);
        // Mensagem de "máximo atingido" da galeria
        var maxErr = document.querySelector('.max-limit-error');
        if (maxErr && !maxErr.dataset.ipcFriendly) {
            maxErr.dataset.ipcFriendly = '1';
            maxErr.textContent = 'Você atingiu o número máximo de fotos. Exclua uma foto para enviar outra.';
            maxErr.style.display = '';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    // A galeria é montada via JS; tenta de novo após o carregamento.
    setTimeout(init, 1500);
})();

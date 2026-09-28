/* ==========================================================================
   Imóvel Parceiro - Login / Register (premium)
   Show / hide password toggles. Bound once per button; safe to re-run on
   markup injected later (e.g. Bootstrap modal show events).
   ========================================================================== */
(function () {
    'use strict';

    function bind(toggle) {
        if (!toggle || toggle.dataset.ipcBound === '1') {
            return;
        }

        var field = toggle.closest('.ipc-password-field');
        var input = field ? field.querySelector('input') : null;
        if (!input) {
            return;
        }

        toggle.dataset.ipcBound = '1';

        toggle.addEventListener('click', function () {
            var reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            toggle.classList.toggle('is-visible', reveal);
            toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            toggle.setAttribute('aria-label', reveal ? (toggle.dataset.ipcHideLabel || 'Ocultar senha') : (toggle.dataset.ipcShowLabel || 'Mostrar senha'));

            input.focus();
        });
    }

    function init(scope) {
        var root = scope || document;
        var toggles = root.querySelectorAll('.ipc-password-toggle');
        Array.prototype.forEach.call(toggles, bind);
    }

    document.addEventListener('DOMContentLoaded', function () {
        init(document);
    });

    document.addEventListener('shown.bs.modal', function (event) {
        init(event.target);
    });

    window.ipcInitPasswordToggles = init;
})();

/* ==========================================================================
   Imóvel Parceiro - Wizard de cadastro (padrão stitch)
   Passos: 1 perfil+dados | 2 documento (+preferências p/ comprador) |
   3 senha+termos (submit AJAX próprio) | 4 sucesso.
   ========================================================================== */
(function () {
    'use strict';

    var ROLE_PARAM_MAP = {
        corretor: 'houzez_agent',
        proprietario: 'houzez_owner',
        proprietaria: 'houzez_owner',
        cliente: 'houzez_buyer',
        comprar: 'houzez_buyer',
        alugar: 'houzez_buyer',
        imobiliaria: 'houzez_agency'
    };
    var AGENT_ROLES = ['houzez_agent', 'houzez_agency'];
    var ROLE_LABELS = {
        houzez_agent: 'Corretor',
        houzez_owner: 'Proprietário',
        houzez_buyer: 'Comprador / Locatário',
        houzez_agency: 'Imobiliária (PJ)'
    };
    var GOAL_LABELS = { comprar: 'Comprar', alugar: 'Alugar', investir: 'Investir' };

    function getForm() {
        var form = document.getElementById('houzez_register_form');
        return form && form.classList.contains('ipc-wizard') ? form : null;
    }

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function checkedRole(form) {
        var checked = form.querySelector('input[name="role"]:checked');
        return checked ? checked.value : '';
    }

    /* ---------- máscaras ---------- */
    function maskPhone(value) {
        value = digits(value).slice(0, 11);
        if (value.length > 10) {
            return value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
        return value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    }

    function maskDoc(value, type) {
        value = digits(value).slice(0, type === 'cnpj' ? 14 : 11);
        if (type === 'cnpj') {
            return value
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        }
        if (value.length > 9) {
            return value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        }
        if (value.length > 6) {
            return value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        }
        if (value.length > 3) {
            return value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        }
        return value;
    }

    /* ---------- validadores ---------- */
    function validCPF(value) {
        value = digits(value);
        if (value.length !== 11 || /^(\d)\1{10}$/.test(value)) {
            return false;
        }
        for (var t = 9; t < 11; t++) {
            var sum = 0;
            for (var i = 0; i < t; i++) {
                sum += parseInt(value.charAt(i), 10) * ((t + 1) - i);
            }
            var digit = ((10 * sum) % 11) % 10;
            if (parseInt(value.charAt(t), 10) !== digit) {
                return false;
            }
        }
        return true;
    }

    function validCNPJ(value) {
        value = digits(value);
        if (value.length !== 14 || /^(\d)\1{13}$/.test(value)) {
            return false;
        }
        var weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        ];
        for (var k = 0; k < 2; k++) {
            var sum = 0;
            for (var i = 0; i < weights[k].length; i++) {
                sum += parseInt(value.charAt(i), 10) * weights[k][i];
            }
            var rest = sum % 11;
            var digit = rest < 2 ? 0 : 11 - rest;
            if (parseInt(value.charAt(12 + k), 10) !== digit) {
                return false;
            }
        }
        return true;
    }

    function validEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value || '').trim());
    }

    function usernameFromEmail(email) {
        var local = String(email || '').split('@')[0].toLowerCase();
        local = local.replace(/[^0-9a-z_]/g, '_').replace(/^_+|_+$/g, '');
        if (local.length < 3) {
            local = (local + 'usuario').slice(0, 20);
        }
        return local.slice(0, 40);
    }

    /* ---------- UI do wizard ---------- */
    function showStep(form, step) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-ipc-wz-panel]'), function (panel) {
            panel.hidden = panel.getAttribute('data-ipc-wz-panel') !== String(step);
        });
        var bar = form.querySelector('[data-ipc-wz-bar]');
        if (bar) {
            bar.style.width = step >= 4 ? '100%' : (step * 33.333) + '%';
        }
        Array.prototype.forEach.call(form.querySelectorAll('[data-ipc-wz-dot]'), function (dot) {
            var num = parseInt(dot.getAttribute('data-ipc-wz-dot'), 10);
            dot.classList.toggle('is-done', num < step);
            dot.classList.toggle('is-active', num === step);
        });
        var card = form.closest('.modal-body');
        if (card) {
            card.scrollTop = 0;
        }
        var first = form.querySelector('[data-ipc-wz-panel="' + step + '"] input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])');
        if (first) {
            try {
                first.focus({ preventScroll: true });
            } catch (e) {
                /* noop */
            }
        }
    }

    function showError(form, step, message) {
        var box = form.querySelector('[data-ipc-wz-error="' + step + '"]');
        if (!box) {
            return;
        }
        if (!message) {
            box.hidden = true;
            box.textContent = '';
            return;
        }
        box.textContent = message;
        box.hidden = false;
    }

    // Passo 2 existe só para quem tem algo a preencher: docs (corretor/PJ)
    // ou preferências (comprador). Proprietário pula direto para o passo 3.
    function needsStep2(form) {
        return checkedRole(form) !== 'houzez_owner';
    }

    function docType(form) {
        var hidden = form.querySelector('[data-ipc-wz-person-type]');
        return hidden && hidden.value === 'cnpj' ? 'cnpj' : 'cpf';
    }

    function syncProfileUI(form) {
        var role = checkedRole(form);
        var agency = role === 'houzez_agency';
        var isAgent = AGENT_ROLES.indexOf(role) !== -1;
        var personType = form.querySelector('[data-ipc-wz-person-type]');
        var doctypeSel = form.querySelector('[data-ipc-wz-doctype]');
        var doctypeWrap = form.querySelector('[data-ipc-wz-doctype-wrap]');
        if (agency) {
            // PJ/imobiliária: sempre CNPJ.
            if (personType) {
                personType.value = 'cnpj';
            }
            if (doctypeWrap) {
                doctypeWrap.hidden = true;
            }
        } else if (role === 'houzez_agent') {
            // Corretor: pode ser PF (CPF) ou PJ (CNPJ).
            if (doctypeWrap) {
                doctypeWrap.hidden = false;
            }
            if (personType && doctypeSel) {
                personType.value = doctypeSel.value === 'cnpj' ? 'cnpj' : 'cpf';
            }
        } else {
            if (personType) {
                personType.value = 'cpf';
            }
            if (doctypeWrap) {
                doctypeWrap.hidden = true;
            }
        }
        var type = docType(form);
        var docLabel = form.querySelector('[data-ipc-wz-doc-label]');
        if (docLabel) {
            docLabel.textContent = type === 'cnpj' ? 'CNPJ' : 'CPF';
        }
        var doc = form.querySelector('input[name="person_document"]');
        if (doc) {
            doc.placeholder = type === 'cnpj' ? '00.000.000/0001-00' : '000.000.000-00';
            doc.value = maskDoc(doc.value, type);
        }
        var docs = form.querySelector('[data-ipc-wz-docs]');
        if (docs) {
            docs.style.display = isAgent ? '' : 'none';
        }
        var creciWrap = form.querySelector('[data-ipc-wz-creci-wrap]');
        if (creciWrap) {
            creciWrap.hidden = AGENT_ROLES.indexOf(role) === -1;
        }
        var prefs = form.querySelector('[data-ipc-wz-prefs]');
        if (prefs) {
            prefs.style.display = role === 'houzez_buyer' ? '' : 'none';
        }
        var pj = form.querySelector('[data-ipc-wz-pj]');
        if (pj) {
            pj.classList.toggle('is-on', agency);
        }
    }

    /* ---------- validação por passo ---------- */
    function validateStep1(form) {
        var role = checkedRole(form);
        if (!role) {
            return 'Escolha um perfil para continuar.';
        }
        var name = form.querySelector('input[name="ipc_full_name"]');
        if (!name || name.value.trim().replace(/\s+/g, ' ').length < 3) {
            if (name) {
                name.classList.add('is-invalid');
            }
            return 'Informe seu nome completo.';
        }
        var email = form.querySelector('input[name="useremail"]');
        if (!email || !validEmail(email.value)) {
            if (email) {
                email.classList.add('is-invalid');
            }
            return 'Informe um e-mail válido.';
        }
        var phone = form.querySelector('input[name="phone_number"]');
        if (!phone || digits(phone.value).length < 10) {
            if (phone) {
                phone.classList.add('is-invalid');
            }
            return 'Informe um celular com DDD válido.';
        }
        return '';
    }

    function validateStep2(form) {
        // Conta mínima: documento só para corretor/PJ; preferências são opcionais.
        if (AGENT_ROLES.indexOf(checkedRole(form)) === -1) {
            return '';
        }
        var isCnpj = docType(form) === 'cnpj';
        var doc = form.querySelector('input[name="person_document"]');
        var docDigits = doc ? digits(doc.value) : '';
        if (isCnpj ? !validCNPJ(docDigits) : !validCPF(docDigits)) {
            if (doc) {
                doc.classList.add('is-invalid');
            }
            return isCnpj ? 'Informe um CNPJ válido.' : 'Informe um CPF válido.';
        }
        if (AGENT_ROLES.indexOf(checkedRole(form)) !== -1) {
            var creci = form.querySelector('input[name="creci"]');
            if (!creci || creci.value.trim().length < 3) {
                if (creci) {
                    creci.classList.add('is-invalid');
                }
                return 'Informe seu CRECI.';
            }
        }
        return '';
    }

    function passwordScore(value) {
        var score = 0;
        if (value.length >= 8) {
            score += 1;
        }
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) {
            score += 1;
        }
        if (/\d/.test(value)) {
            score += 1;
        }
        if (/[^A-Za-z0-9]/.test(value)) {
            score += 1;
        }
        return score;
    }

    function paintStrength(form) {
        var pass = form.querySelector('input[name="register_pass"]');
        var wrap = form.querySelector('[data-ipc-wz-strength]');
        if (!pass || !wrap) {
            return 0;
        }
        var score = passwordScore(pass.value);
        var bars = wrap.querySelectorAll('.ipc-wz-strength__bars span');
        Array.prototype.forEach.call(bars, function (bar, index) {
            bar.classList.toggle('is-on', index < score);
        });
        var labels = ['', 'Muito fraca', 'Fraca', 'Boa', 'Muito forte'];
        var label = wrap.querySelector('[data-ipc-wz-strength-label]');
        if (label) {
            label.textContent = pass.value ? labels[score] : '';
        }
        var criteria = form.querySelector('[data-ipc-wz-criteria]');
        if (criteria) {
            var checks = {
                len: pass.value.length >= 8,
                upper: /[A-Z]/.test(pass.value),
                symbol: /[^A-Za-z0-9]/.test(pass.value)
            };
            Array.prototype.forEach.call(criteria.querySelectorAll('li[data-criterion]'), function (li) {
                li.classList.toggle('is-ok', !!checks[li.getAttribute('data-criterion')]);
            });
        }
        return score;
    }

    function validateStep3(form) {
        var pass = form.querySelector('input[name="register_pass"]');
        var value = pass ? pass.value : '';
        if (value.length < 8 || !/[A-Z]/.test(value) || !/[^A-Za-z0-9]/.test(value)) {
            if (pass) {
                pass.classList.add('is-invalid');
            }
            return 'A senha precisa de 8 caracteres, 1 maiúscula e 1 símbolo.';
        }
        var terms = form.querySelector('input[name="term_condition"]');
        if (!terms || !terms.checked) {
            return 'Você precisa concordar com os Termos de Uso.';
        }
        return '';
    }

    /* ---------- submit próprio (substitui o handler do tema) ---------- */
    function ajaxUrl() {
        if (window.houzez && houzez.Core && houzez.Core.config && houzez.Core.config.ajaxurl) {
            return houzez.Core.config.ajaxurl;
        }
        if (window.ajaxurl) {
            return window.ajaxurl;
        }
        return '/wp-admin/admin-ajax.php';
    }

    function setLoading(form, loading) {
        var btn = form.querySelector('.btn-register');
        if (!btn) {
            return;
        }
        if (loading) {
            btn.setAttribute('disabled', 'disabled');
        } else {
            btn.removeAttribute('disabled');
        }
        var loader = btn.querySelector('.houzez-loader-js');
        if (loader) {
            loader.classList.toggle('loader-show', loading);
        }
    }

    function formMessage(form, type, text) {
        var box = document.getElementById('hz-register-messages');
        if (!box) {
            return;
        }
        box.className = 'hz-social-messages alert ' + (type === 'success' ? 'alert-success' : 'alert-danger');
        box.textContent = text;
    }

    function resetCaptcha(form) {
        try {
            if (window.houzez && houzez.Core && houzez.Core.util && typeof houzez.Core.util.resetCaptcha === 'function') {
                houzez.Core.util.resetCaptcha(window.jQuery ? window.jQuery(form) : form);
            }
        } catch (e) {
            /* noop */
        }
    }

    function prepareSubmit(form) {
        var email = form.querySelector('input[name="useremail"]');
        var user = form.querySelector('input[name="username"]');
        if (user && email && !user.value) {
            user.value = usernameFromEmail(email.value);
        }
        var pass = form.querySelector('input[name="register_pass"]');
        var retype = form.querySelector('input[name="register_pass_retype"]');
        if (pass && retype) {
            retype.value = pass.value;
        }
        var full = form.querySelector('input[name="ipc_full_name"]');
        var first = form.querySelector('input[name="first_name"]');
        var last = form.querySelector('input[name="last_name"]');
        if (full && (first || last)) {
            var parts = full.value.trim().replace(/\s+/g, ' ').split(' ');
            if (first) {
                first.value = parts.shift() || '';
            }
            if (last) {
                last.value = parts.join(' ');
            }
        }
    }

    function buildSummary(form, role) {
        var name = form.querySelector('input[name="ipc_full_name"]');
        var email = form.querySelector('input[name="useremail"]');
        var goal = form.querySelector('input[name="ipc_goal"]:checked');
        var items = [
            ['Perfil', ROLE_LABELS[role] || role],
            ['Nome', name ? name.value.trim() : ''],
            ['E-mail', email ? email.value.trim() : '']
        ];
        if (role === 'houzez_buyer' && goal) {
            items.push(['Objetivo', GOAL_LABELS[goal.value] || goal.value]);
            var types = form.querySelector('[data-ipc-wz-types]');
            if (types && types.value) {
                items.push(['Imóveis', types.value]);
            }
        }
        var html = '';
        items.forEach(function (item) {
            html += '<div class="ipc-wz-summary__item">' + item[0] + '<strong>' + String(item[1]).replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</strong></div>';
        });
        return html;
    }

    function submitWizard(form, retry) {
        retry = retry || 0;
        prepareSubmit(form);
        setLoading(form, true);
        formMessage(form, 'success', 'Criando sua conta...');

        var data = window.jQuery ? window.jQuery(form).serialize() : new URLSearchParams(new FormData(form)).toString();
        window.jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajaxUrl(),
            data: data,
            success: function (response) {
                if (response && response.success) {
                    var role = checkedRole(form);
                    var summary = form.querySelector('[data-ipc-wz-summary]');
                    if (summary) {
                        summary.innerHTML = buildSummary(form, role);
                    }
                    var redirects = (window.ipcAuth && window.ipcAuth.redirects) || {};
                    var cta = form.querySelector('[data-ipc-wz-cta]');
                    if (cta && redirects[role]) {
                        cta.setAttribute('href', redirects[role]);
                    }
                    showError(form, 3, '');
                    showStep(form, 4);
                    var box = document.getElementById('hz-register-messages');
                    if (box) {
                        box.className = 'hz-social-messages';
                        box.textContent = '';
                    }
                } else {
                    var msg = String((response && response.msg) || 'Não foi possível concluir o cadastro.');
                    if (/username/i.test(msg) && retry < 3) {
                        var email = form.querySelector('input[name="useremail"]');
                        var user = form.querySelector('input[name="username"]');
                        if (user && email) {
                            user.value = (usernameFromEmail(email.value) + Math.floor(100 + Math.random() * 900)).slice(0, 40);
                        }
                        submitWizard(form, retry + 1);
                        return;
                    }
                    formMessage(form, 'error', msg);
                }
                resetCaptcha(form);
                setLoading(form, false);
            },
            error: function () {
                formMessage(form, 'error', 'Erro de conexão. Tente novamente.');
                resetCaptcha(form);
                setLoading(form, false);
            }
        });
    }

    /* ---------- binds ---------- */
    function bindWizard(form) {
        if (!form || form.dataset.ipcWzBound === '1') {
            return;
        }
        form.dataset.ipcWzBound = '1';

        // Perfil via ?perfil=
        try {
            var perfil = (new URLSearchParams(window.location.search).get('perfil') || '').toLowerCase().trim();
            var mapped = ROLE_PARAM_MAP[perfil] || '';
            if (mapped) {
                var radio = form.querySelector('input[name="role"][value="' + mapped + '"]');
                if (radio) {
                    radio.checked = true;
                }
            }
        } catch (e) {
            /* noop */
        }
        syncProfileUI(form);
        showStep(form, 1);

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }
            if (target.matches('input[name="role"]')) {
                syncProfileUI(form);
                showError(form, 1, '');
            }
            if (target.matches('[data-ipc-wz-doctype]')) {
                var hidden = form.querySelector('[data-ipc-wz-person-type]');
                if (hidden) {
                    hidden.value = target.value === 'cnpj' ? 'cnpj' : 'cpf';
                }
                syncProfileUI(form);
            }
        });

        form.addEventListener('click', function (event) {
            var pj = event.target && event.target.closest ? event.target.closest('[data-ipc-wz-pj]') : null;
            if (pj) {
                var agency = form.querySelector('[data-ipc-wz-agency]');
                if (agency) {
                    agency.checked = true;
                    syncProfileUI(form);
                }
                return;
            }
            var chip = event.target && event.target.closest ? event.target.closest('[data-ipc-wz-chips] .ipc-wz-chip') : null;
            if (chip) {
                chip.classList.toggle('is-on');
                var selected = [];
                Array.prototype.forEach.call(form.querySelectorAll('[data-ipc-wz-chips] .ipc-wz-chip.is-on'), function (on) {
                    selected.push(on.getAttribute('data-value'));
                });
                var hidden = form.querySelector('[data-ipc-wz-types]');
                if (hidden) {
                    hidden.value = selected.join(', ');
                }
                return;
            }
            var next = event.target && event.target.closest ? event.target.closest('[data-ipc-wz-next]') : null;
            if (next) {
                var to = parseInt(next.getAttribute('data-ipc-wz-next'), 10);
                if (to === 2 && !needsStep2(form)) {
                    to = 3;
                }
                var error = (to === 3 && needsStep2(form)) ? validateStep2(form) : validateStep1(form);
                var current = to === 3 && !needsStep2(form) ? 1 : (to === 3 ? 2 : 1);
                if (error) {
                    showError(form, current, error);
                    return;
                }
                showError(form, current, '');
                showStep(form, to);
                return;
            }
            var prev = event.target && event.target.closest ? event.target.closest('[data-ipc-wz-prev]') : null;
            if (prev) {
                var back = parseInt(prev.getAttribute('data-ipc-wz-prev'), 10);
                if (back === 2 && !needsStep2(form)) {
                    back = 1;
                }
                showStep(form, back);
            }
        });

        form.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }
            target.classList.remove('is-invalid');
            if (target.matches('input[name="phone_number"]')) {
                var maskedPhone = maskPhone(target.value);
                if (maskedPhone !== target.value) {
                    target.value = maskedPhone;
                }
            }
            if (target.matches('input[name="person_document"]')) {
                var maskedDoc = maskDoc(target.value, docType(form));
                if (maskedDoc !== target.value) {
                    target.value = maskedDoc;
                }
            }
            if (target.matches('input[name="register_pass"]')) {
                paintStrength(form);
            }
        });

        // Submit próprio: desliga o handler do tema e assume o POST.
        if (window.jQuery) {
            window.jQuery(form).off('submit');
            window.jQuery(form).on('submit', function (event) {
                event.preventDefault();
                var error = validateStep1(form) || validateStep2(form) || validateStep3(form);
                if (error) {
                    var step = validateStep1(form) ? 1 : (validateStep2(form) ? 2 : 3);
                    showStep(form, step);
                    showError(form, step, error);
                    return false;
                }
                showError(form, 3, '');
                submitWizard(form, 0);
                return false;
            });
        }
    }

    function init() {
        bindWizard(getForm());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Reforço tardio: se o JS do tema vinculou o submit depois de nós,
    // remove de novo e mantém só o submit do wizard.
    window.addEventListener('load', function () {
        var form = getForm();
        if (form && window.jQuery) {
            form.dataset.ipcWzBound = '';
            bindWizard(form);
        }
    });

    document.addEventListener('shown.bs.modal', function (event) {
        var form = event.target && event.target.querySelector ? event.target.querySelector('#houzez_register_form') : null;
        if (form && form.classList.contains('ipc-wizard')) {
            form.dataset.ipcWzBound = '';
            bindWizard(form);
        }
    });
})();

/* ==========================================================================
   Imóvel Parceiro - Cadastro simplificado (itens 1, 2, 3, 6)
   - Username gerado do e-mail (campo oculto, com retry em colisão).
   - Confirmação de senha preenchida automaticamente (senha única).
   - Cards de perfil (?perfil=corretor|proprietario|cliente|imobiliaria).
   - Documento/CRECI visíveis só para corretor/imobiliária (conta mínima).
   - Redirect pós-cadastro por perfil (via window.ipcAuth.redirects).
   ========================================================================== */
(function () {
    'use strict';

    var ROLE_PARAM_MAP = {
        corretor: 'houzez_agent',
        corretoras: 'houzez_agent',
        proprietario: 'houzez_owner',
        proprietaria: 'houzez_owner',
        cliente: 'houzez_buyer',
        comprar: 'houzez_buyer',
        alugar: 'houzez_buyer',
        imobiliaria: 'houzez_agency'
    };

    var AGENT_ROLES = ['houzez_agent', 'houzez_agency'];
    var retryCount = 0;
    var submittedRole = '';

    function getForm() {
        return document.getElementById('houzez_register_form');
    }

    function checkedRole(form) {
        var checked = form.querySelector('input[name="role"]:checked');
        if (checked) {
            return checked.value;
        }
        var legacy = form.querySelector('select[name="role"]');
        return legacy ? legacy.value : '';
    }

    function applyProfileFromUrl(form) {
        var params;
        try {
            params = new URLSearchParams(window.location.search);
        } catch (e) {
            return;
        }
        var perfil = (params.get('perfil') || '').toLowerCase().trim();
        var role = ROLE_PARAM_MAP[perfil] || '';
        if (role) {
            var radio = form.querySelector('input[name="role"][value="' + role + '"]');
            if (radio) {
                radio.checked = true;
            }
        }
        // Fallback: nenhum perfil marcado -> pré-seleciona "quero comprar ou alugar".
        if (!form.querySelector('input[name="role"]:checked')) {
            var fallback = form.querySelector('input[name="role"][value="houzez_buyer"]');
            if (fallback) {
                fallback.checked = true;
            }
        }
    }

    function toggleDocsByRole(form) {
        var grids = form.querySelectorAll('.ipc-register-grid');
        if (!grids.length) {
            return;
        }
        var show = AGENT_ROLES.indexOf(checkedRole(form)) !== -1;
        Array.prototype.forEach.call(grids, function (grid) {
            grid.style.display = show ? '' : 'none';
        });
    }

    function usernameFromEmail(email) {
        var local = String(email || '').split('@')[0].toLowerCase();
        local = local.replace(/[^0-9a-z_]/g, '_').replace(/^_+|_+$/g, '');
        if (local.length < 3) {
            local = (local + 'usuario').slice(0, 20);
        }
        return local.slice(0, 40);
    }

    function prepareSubmit(form) {
        var emailInput = form.querySelector('input[name="useremail"]');
        var userInput = form.querySelector('input[name="username"]');
        if (userInput && emailInput && !userInput.value) {
            userInput.value = usernameFromEmail(emailInput.value);
        }
        var pass = form.querySelector('input[name="register_pass"]');
        var retype = form.querySelector('input[name="register_pass_retype"]');
        if (pass && retype) {
            retype.value = pass.value;
        }
        submittedRole = checkedRole(form);
        toggleDocsByRole(form);
    }

    function bindForm(form) {
        if (!form || form.dataset.ipcRegBound === '1') {
            return;
        }
        form.dataset.ipcRegBound = '1';

        applyProfileFromUrl(form);
        toggleDocsByRole(form);

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches && event.target.matches('input[name="role"]')) {
                toggleDocsByRole(form);
            }
        });

        // Capture: roda antes do handler do tema (jQuery) e da máscara do plugin.
        form.addEventListener('submit', function () {
            prepareSubmit(form);
        }, true);
    }

    function jQueryAjaxHooks() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        window.jQuery(document).ajaxComplete(function (event, xhr, settings) {
            var data = settings && settings.data ? String(settings.data) : '';
            if (data.indexOf('action=houzez_register') === -1) {
                return;
            }
            var response = null;
            try {
                response = window.jQuery.parseJSON(xhr.responseText);
            } catch (e) {
                return;
            }
            var form = getForm();
            if (!form) {
                return;
            }
            if (!response.success) {
                // Colisão de username gerado: adiciona sufixo e reenvia (invisível).
                var msg = String((response && response.msg) || '').toLowerCase();
                if (msg.indexOf('username') !== -1 && retryCount < 3) {
                    retryCount += 1;
                    var userInput = form.querySelector('input[name="username"]');
                    if (userInput) {
                        var base = usernameFromEmail(form.querySelector('input[name="useremail"]').value);
                        userInput.value = (base + Math.floor(100 + Math.random() * 900)).slice(0, 40);
                    }
                    form.querySelector('.btn-register').click();
                }
                return;
            }
            retryCount = 0;
            var redirects = (window.ipcAuth && window.ipcAuth.redirects) || {};
            var target = redirects[submittedRole] || '';
            if (target) {
                window.setTimeout(function () {
                    window.location.href = target;
                }, 1800);
            }
        });
    }

    function init() {
        bindForm(getForm());
        jQueryAjaxHooks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('shown.bs.modal', function (event) {
        var form = (event.target && event.target.querySelector)
            ? event.target.querySelector('#houzez_register_form')
            : null;
        if (form) {
            form.dataset.ipcRegBound = '';
            bindForm(form);
        }
    });
})();

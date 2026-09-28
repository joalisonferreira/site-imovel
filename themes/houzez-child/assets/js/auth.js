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

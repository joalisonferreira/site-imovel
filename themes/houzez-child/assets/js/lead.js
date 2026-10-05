/* Extras do drawer mobile (banner boas-vindas + rodapé). Idempotente. */
(function () {
    'use strict';

    function esc(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function injectDrawerExtras() {
        var body = document.querySelector('.offcanvas-mobile-menu-body');
        if (!body || body.dataset.ipcDrawerExtra === '1') {
            return;
        }
        body.dataset.ipcDrawerExtra = '1';

        var loggedIn = window.ipcLead && window.ipcLead.is_logged_in;
        var userName = (window.ipcLead && window.ipcLead.user_name) || '';
        var dashboardUrl = (window.ipcLead && window.ipcLead.dashboard_url) || '/dashboard/';

        var banner = document.createElement('div');
        banner.className = 'ipc-drawer-welcome';
        if (loggedIn) {
            banner.innerHTML = '<span class="ipc-drawer-welcome__hello">Olá, ' + esc(userName.split(' ')[0] || 'bem-vindo') + '!</span>' +
                '<a class="ipc-drawer-welcome__link" href="' + esc(dashboardUrl) + '">Ir para o painel →</a>';
        } else {
            banner.innerHTML = '<span class="ipc-drawer-welcome__hello">Olá, bem-vindo!</span>' +
                '<a class="ipc-drawer-welcome__link" href="#" data-bs-toggle="modal" data-bs-target="#login-register-form">Entre ou cadastre-se →</a>';
        }
        body.insertBefore(banner, body.firstChild);

        var footer = document.createElement('div');
        footer.className = 'ipc-drawer-footer';
        footer.innerHTML = '<span>Imóvel Parceiro © 2025</span>' +
            '<span class="ipc-drawer-footer__links"><a href="/termos-de-uso/">Termos</a> · <a href="/politica-de-privacidade/">Privacidade</a></span>';
        body.appendChild(footer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectDrawerExtras);
    } else {
        injectDrawerExtras();
    }
})();
(function () {
    'use strict';

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function maskPhone(value) {
        value = digits(value).slice(0, 11);
        if (value.length > 10) {
            return value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
        return value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    }

    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) {
            return false;
        }
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
            return true;
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#' + id).modal('show');
            return true;
        }
        return false;
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(el);
            if (instance) {
                instance.hide();
            }
            return;
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#' + id).modal('hide');
        }
    }

    function openRegister(role) {
        if (!openModal('login-register-form')) {
            return;
        }
        window.setTimeout(function () {
            var tab = document.getElementById('register-tab');
            if (tab) {
                tab.click();
            }
            if (role) {
                var radio = document.querySelector('#houzez_register_form input[name="role"][value="' + role + '"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }, 150);
    }

    function collectLead(form) {
        var goal = form.querySelector('input[name="lead_goal"]:checked');
        var types = [];
        Array.prototype.forEach.call(form.querySelectorAll('[data-ipc-lead-chips] .ipc-wz-chip.is-on'), function (chip) {
            types.push(chip.getAttribute('data-value'));
        });
        return {
            action: 'ipc_save_buyer_lead',
            nonce: (window.ipcLead && window.ipcLead.nonce) || '',
            goal: goal ? goal.value : 'comprar',
            types: types.join(', '),
            where: form.querySelector('[name="lead_where"]').value.trim(),
            name: form.querySelector('[name="lead_name"]').value.trim(),
            phone: form.querySelector('[name="lead_phone"]').value,
            email: form.querySelector('[name="lead_email"]').value.trim(),
            consent: form.querySelector('[name="lead_consent"]').checked ? '1' : ''
        };
    }

    function validateLead(form, data) {
        if (data.where.length < 2) {
            return 'Informe a cidade ou bairro de interesse.';
        }
        if (data.name.replace(/\s+/g, ' ').length < 3) {
            return 'Informe seu nome.';
        }
        if (digits(data.phone).length < 10) {
            return 'Informe um WhatsApp válido com DDD.';
        }
        if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.email)) {
            return 'Informe um e-mail válido ou deixe em branco.';
        }
        if (!data.consent) {
            return 'É preciso autorizar o contato para continuar.';
        }
        return '';
    }

    function showLeadError(form, message) {
        var box = form.querySelector('[data-ipc-lead-error]');
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

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target : null;

        var ownerBtn = target && target.closest ? target.closest('[data-ipc-register-role]') : null;
        if (ownerBtn) {
            event.preventDefault();
            openRegister(ownerBtn.getAttribute('data-ipc-register-role') || 'houzez_owner');
            return;
        }

        var toRegister = target && target.closest ? target.closest('[data-ipc-lead-to-register]') : null;
        if (toRegister) {
            try {
                var form = document.getElementById('ipc-lead-form');
                if (form) {
                    var goal = form.querySelector('input[name="lead_goal"]:checked');
                    var types = [];
                    Array.prototype.forEach.call(form.querySelectorAll('[data-ipc-lead-chips] .ipc-wz-chip.is-on'), function (chip) {
                        types.push(chip.getAttribute('data-value'));
                    });
                    window.localStorage.setItem('ipcLead', JSON.stringify({
                        name: form.querySelector('[name="lead_name"]').value,
                        phone: form.querySelector('[name="lead_phone"]').value,
                        where: form.querySelector('[name="lead_where"]').value,
                        goal: goal ? goal.value : 'comprar',
                        types: types.join(', ')
                    }));
                }
            } catch (e) {
                /* noop */
            }
            closeModal('ipc-lead-modal');
            window.setTimeout(function () {
                openRegister('houzez_buyer');
            }, 200);
            return;
        }

        var chip = target && target.closest ? target.closest('[data-ipc-lead-chips] .ipc-wz-chip') : null;
        if (chip) {
            chip.classList.toggle('is-on');
        }
    });

    document.addEventListener('input', function (event) {
        var target = event.target;
        if (target && target.matches && target.matches('#ipc_lead_phone')) {
            var masked = maskPhone(target.value);
            if (masked !== target.value) {
                target.value = masked;
            }
            target.classList.remove('is-invalid');
        }
        if (target && (target.matches('#ipc_lead_where') || target.matches('#ipc_lead_name'))) {
            target.classList.remove('is-invalid');
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.id !== 'ipc-lead-form') {
            return;
        }
        event.preventDefault();
        var data = collectLead(form);
        var error = validateLead(form, data);
        if (error) {
            showLeadError(form, error);
            return;
        }
        showLeadError(form, '');
        var btn = form.querySelector('[data-ipc-lead-submit]');
        if (btn) {
            btn.setAttribute('disabled', 'disabled');
        }
        var ajaxurl = (window.ipcLead && window.ipcLead.ajaxurl) || '/wp-admin/admin-ajax.php';
        var body = Object.keys(data).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
        }).join('&');
        window.fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (btn) {
                btn.removeAttribute('disabled');
            }
            if (json && json.success) {
                var wrap = document.querySelector('[data-ipc-lead-form-wrap]');
                var success = document.querySelector('[data-ipc-lead-success]');
                var msg = document.querySelector('[data-ipc-lead-success-msg]');
                if (msg) {
                    msg.textContent = (json.data && json.data.message) || 'Pedido recebido!';
                }
                if (wrap) {
                    wrap.hidden = true;
                }
                if (success) {
                    success.hidden = false;
                }
            } else {
                showLeadError(form, (json && json.data && json.data.message) || 'Não foi possível salvar. Tente novamente.');
            }
        }).catch(function () {
            if (btn) {
                btn.removeAttribute('disabled');
            }
            showLeadError(form, 'Erro de conexão. Tente novamente.');
        });
    });
})();

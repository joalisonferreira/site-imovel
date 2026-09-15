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

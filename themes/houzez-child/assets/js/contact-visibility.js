(function($) {
    'use strict';

    $(document).ready(function() {
        var ipcAuth = window.ipcLead || {};

        if (!ipcAuth.is_logged_in || !ipcAuth.has_membership) {
            // Bloquear formulário de Agendar Visita
            $('.property-schedule-tour-form-wrap form, #houzez_schedule_send_message_form, form[name="houzez_schedule_send_message"]').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                ipcShowContactToast();
                return false;
            });

            // Bloquear formulário de Mensagem/Correio
            $('form[name="houzez_property_agent_contact"], .property-form-wrap form, form[action*="houzez_property_agent_contact"]').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                ipcShowContactToast();
                return false;
            });
        }
    });

    function ipcShowContactToast() {
        var toast = document.createElement('div');
        toast.className = 'ipc-contact-toast';
        toast.innerHTML = '<div class="ipc-toast-body"><i class="houzez-icon icon-info-circle me-2"></i><strong>Atenção:</strong> Você precisa estar logado e ter um plano ativo para entrar em contato ou agendar uma visita.</div>';
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.classList.add('ipc-toast-show');
        }, 10);

        setTimeout(function() {
            toast.classList.remove('ipc-toast-show');
            setTimeout(function() {
                if (toast.parentElement) toast.parentElement.removeChild(toast);
            }, 300);
        }, 4000);
    }
})(jQuery);
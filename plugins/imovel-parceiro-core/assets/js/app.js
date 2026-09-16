jQuery(function($){
    function escapeHtml(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function acceptanceSectionHtml() {
        return '' +
            '<div class="imovel-parceiro-acceptance-block block-wrap">' +
                '<div class="block-title-wrap d-flex justify-content-between align-items-center">' +
                    '<h2>Declaracoes e Termos para Publicacao do Imovel *</h2>' +
                '</div>' +
                '<div class="block-content-wrap">' +
                    '<div class="form-group mb-2">' +
                        '<label class="control control--checkbox" style="font-weight:400;">' +
                            '<input type="checkbox" name="imovel_parceiro_acceptance[authorization]" value="1" class="imovel-parceiro-acceptance-checkbox" required> Declaro que sou proprietário ou representante legal do imóvel e autorizo a plataforma e seus corretores parceiros a divulgar e intermediar oportunidades relacionadas a este imóvel, conforme os termos da plataforma.' +
                            '<span class="control__indicator"></span>' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-group mb-2">' +
                        '<label class="control control--checkbox" style="font-weight:400;">' +
                            '<input type="checkbox" name="imovel_parceiro_acceptance[partnership]" value="1" class="imovel-parceiro-acceptance-checkbox" required> Autorizo que este imóvel seja disponibilizado para parceria com outros corretores cadastrados na plataforma.' +
                            '<span class="control__indicator"></span>' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-group mb-2">' +
                        '<label class="control control--checkbox" style="font-weight:400;">' +
                            '<input type="checkbox" name="imovel_parceiro_acceptance[commission]" value="1" class="imovel-parceiro-acceptance-checkbox" required> Aceito a divisão de comissão de 50% | 50% em parceria direta.' +
                            '<span class="control__indicator"></span>' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-group mb-1">' +
                        '<label class="control control--checkbox" style="font-weight:400;">' +
                            '<input type="checkbox" name="imovel_parceiro_acceptance[terms]" value="1" class="imovel-parceiro-acceptance-checkbox" required> Aceito os Termos de Uso e as regras da plataforma.' +
                            '<span class="control__indicator"></span>' +
                        '</label>' +
                    '</div>' +
                '</div>' +
            '</div>';
    }

    function ensureAcceptanceFields() {
        var $form = $('#submit_property_form');
        if (!$form.length || $form.find('.imovel-parceiro-acceptance-block').length) {
            return;
        }

        var $target = $form.find('.add-new-listing-bottom-nav-wrap').first();
        if ($target.length) {
            $(acceptanceSectionHtml()).insertBefore($target);
        } else {
            $form.append(acceptanceSectionHtml());
        }
    }

    function isPropertyEditForm($form) {
        if (!$form || !$form.length) {
            return false;
        }

        var actionValue = (($form.find('input[name="action"]').val() || '') + '').toLowerCase();
        if (actionValue === 'update_property') {
            return true;
        }

        return $form.find('input[name="prop_id"]').length > 0 || $form.find('input[name="draft_prop_id"]').length > 0;
    }

    function precheckAcceptanceOnEdit() {
        var $form = $('#submit_property_form');
        if (!$form.length || !isPropertyEditForm($form)) {
            return;
        }

        if ($form.data('imovel-parceiro-acceptance-prefilled')) {
            return;
        }

        $form.find('.imovel-parceiro-acceptance-checkbox').prop('checked', true);
        $form.data('imovel-parceiro-acceptance-prefilled', true);
    }

    function hasAllAcceptances($scope) {
        var allChecked = true;
        $scope.find('.imovel-parceiro-acceptance-checkbox').each(function(){
            if (!$(this).is(':checked')) {
                allChecked = false;
                return false;
            }
        });

        return allChecked;
    }

    function bindAcceptanceValidation() {
        var $form = $('#submit_property_form');
        if (!$form.length) {
            return;
        }

        var validationMessage = (window.imovelParceiroCore && imovelParceiroCore.messages && imovelParceiroCore.messages.acceptances_required)
            ? imovelParceiroCore.messages.acceptances_required
            : 'E obrigatorio aceitar todos os termos para publicar ou editar este imovel.';

        $form.find('.imovel-parceiro-acceptance-checkbox').each(function(){
            var $checkbox = $(this);
            if ($checkbox.data('imovel-parceiro-bound')) {
                return;
            }

            $checkbox.data('imovel-parceiro-bound', true);
            if ($checkbox.rules) {
                $checkbox.rules('add', {
                    required: true,
                    messages: {
                        required: validationMessage
                    }
                });
            }
        });
    }

    function syncAcceptanceSubmitState() {
        var $form = $('#submit_property_form');
        if (!$form.length) {
            return;
        }

        var allAccepted = hasAllAcceptances($form);
        var $submit = $form.find('.btn-step-submit .houzez-submit-js');

        $submit.prop('disabled', !allAccepted);
        $submit.toggleClass('is-disabled', !allAccepted);
    }

    function getPriceFieldSelectors() {
        return [
            '#property_price',
            'input[name="property_price"]',
            'input[name="prop_price"]',
            '#property_sec_price',
            'input[name="property_sec_price"]',
            'input[name="prop_sec_price"]',
            '#fave_valor-do-condominio',
            'input[name="fave_valor-do-condominio"]',
            '#fave_valor-do-iptu',
            'input[name="fave_valor-do-iptu"]'
        ];
    }

    function normalizePriceInput(value) {
        return (value || '').toString().replace(/\D+/g, '');
    }

    function splitCentsDigits(value) {
        var clean = normalizePriceInput(value);
        if (!clean) {
            return null;
        }

        clean = clean.replace(/^0+(?=\d)/, '') || '0';
        while (clean.length < 3) {
            clean = '0' + clean;
        }

        return {
            intPart: clean.slice(0, -2).replace(/^0+(?=\d)/, '') || '0',
            decimalPart: clean.slice(-2)
        };
    }

    function parsePriceToNormalized(value) {
        var parts = splitCentsDigits(value);
        return parts ? parts.intPart + '.' + parts.decimalPart : '';
    }

    function formatNormalizedPrice(value) {
        var parts = splitCentsDigits(value);
        if (!parts) {
            return '';
        }

        return parts.intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + parts.decimalPart;
    }

    function formatPriceInput(input) {
        var value = input.value;
        var caret = typeof input.selectionStart === 'number' ? input.selectionStart : value.length;
        var digitsBeforeCaret = normalizePriceInput(value.slice(0, caret)).length;
        var digits = normalizePriceInput(value);
        var formattedValue = formatNormalizedPrice(value);
        var formattedCaret = formattedValue.length;

        if (digitsBeforeCaret < digits.length) {
            var digitsSeen = 0;
            formattedCaret = 0;
            while (formattedCaret < formattedValue.length && digitsSeen < digitsBeforeCaret) {
                if (/\d/.test(formattedValue.charAt(formattedCaret))) {
                    digitsSeen++;
                }
                formattedCaret++;
            }
        }

        input.value = formattedValue;
        if (document.activeElement === input && input.setSelectionRange) {
            input.setSelectionRange(formattedCaret, formattedCaret);
        }
    }

    function getSelectedStatusText() {
        var $status = $('#property_status');
        if (!$status.length) {
            return '';
        }

        var selectedText = $status.find('option:selected').map(function() {
            return $(this).text();
        }).get().join(' ');

        if (!selectedText) {
            selectedText = $status.find(':selected').text() || $status.val() || '';
        }

        return selectedText.toString().toLowerCase();
    }

    function getSelectedStatusValue() {
        var $status = $('#property_status');
        if (!$status.length) {
            return '';
        }

        var values = $status.val();
        if (Array.isArray(values)) {
            values = values.join(' ');
        }

        return (values || '').toString().toLowerCase();
    }

    function isRentalStatusText(statusText) {
        return /(aluguel|loca[cç][aã]o|rent|rental|arrendamento|lease)/i.test(statusText || '');
    }

    function isRentalOperationSelected() {
        return isRentalStatusText(getSelectedStatusText() + ' ' + getSelectedStatusValue());
    }

    function hidePriceField(selector) {
        var $field = $(selector);
        if (!$field.length) {
            return;
        }

        var $group = $field.closest('.form-group');
        if ($group.length) {
            $group.addClass('imovel-parceiro-hidden-price-field').hide();
        } else {
            $field.addClass('imovel-parceiro-hidden-price-field').hide();
        }
    }

    function hideUnsupportedPriceFields() {
        hidePriceField('#property_price_prefix');
        hidePriceField('#property_price_postfix');
        hidePriceField('#property_price_placeholder');
        hidePriceField('#show_price_placeholder');
        hidePriceField('[for="property_price_prefix"]');
        hidePriceField('[for="property_price_postfix"]');
        hidePriceField('[for="property_price_placeholder"]');
        hidePriceField('[for="show_price_placeholder"]');

        $('#price-plac-js').hide();
        $('.hz-price-placeholder').hide();
    }

    function bindPriceMask() {
        var $priceInputs = $(getPriceFieldSelectors().join(','));
        if (!$priceInputs.length) {
            return;
        }

        $priceInputs.each(function() {
            var $input = $(this);
            if ($input.data('imovel-parceiro-price-bound')) {
                return;
            }

            $input.data('imovel-parceiro-price-bound', true);

            $input.attr('inputmode', 'decimal');
            formatPriceInput($input[0]);
            $input.on('keydown.imovelParceiroPrice', function(e) {
                if (e.ctrlKey || e.metaKey || e.altKey) {
                    return;
                }
                if (e.key && e.key.length === 1 && !/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            $input.on('input.imovelParceiroPrice', function() {
                formatPriceInput(this);
            });
            $input.on('blur.imovelParceiroPrice change.imovelParceiroPrice', function() {
                formatPriceInput(this);
            });
        });
    }

    function normalizePricesBeforeSubmit() {
        var $form = $('#submit_property_form');
        if (!$form.length || $form.data('imovel-parceiro-price-submit-bound')) {
            return;
        }

        $form.data('imovel-parceiro-price-submit-bound', true);

        $form.on('submit.imovelParceiroPrice', function() {
            $form.find(getPriceFieldSelectors().join(',')).each(function() {
                var $field = $(this);
                $field.val(parsePriceToNormalized($field.val()));
            });
        });

        document.addEventListener('click', function(event) {
            if (!event.target.closest || !event.target.closest('#save_as_draft')) {
                return;
            }

            $form.find(getPriceFieldSelectors().join(',')).each(function() {
                this.value = parsePriceToNormalized(this.value);
            });

            window.setTimeout(function() {
                $form.find(getPriceFieldSelectors().join(',')).each(function() {
                    this.value = formatNormalizedPrice(this.value);
                });
            }, 0);
        }, true);
    }

    function updateRentalBadge() {
        var $priceGroup = $('#property_price').closest('.form-group');
        if (!$priceGroup.length) {
            return;
        }

        var $label = $priceGroup.find('label.form-label').first();
        var $badge = $priceGroup.find('.imovel-parceiro-rental-badge');
        var rental = isRentalOperationSelected();

        if (rental) {
            if (!$badge.length) {
                $badge = $('<small class="imovel-parceiro-rental-badge ms-2 text-muted">/Mês</small>');
                if ($label.length) {
                    $label.append($badge);
                } else {
                    $priceGroup.prepend($badge);
                }
            }
        } else if ($badge.length) {
            $badge.remove();
        }
    }

    function bindRentalPriceToggle() {
        var $status = $('#property_status');
        if (!$status.length) {
            return;
        }

        if ($status.data('imovel-parceiro-rental-bound')) {
            return;
        }

        $status.data('imovel-parceiro-rental-bound', true);
        $status.on('change.imovelParceiroPrice', updateRentalBadge);
        $status.on('changed.bs.select.imovelParceiroPrice', updateRentalBadge);
        $(document).on('change.imovelParceiroPrice', '#property_status', updateRentalBadge);
        $(document).on('changed.bs.select.imovelParceiroPrice', '#property_status', updateRentalBadge);
        updateRentalBadge();
    }

    function applyFrontendRentalPriceSuffix() {
        if (!(window.imovelParceiroCore && imovelParceiroCore.is_rental_property)) {
            return;
        }

        var $priceNodes = $('.item-price, .hz-ele-price .price');
        if (!$priceNodes.length) {
            return;
        }

        $priceNodes.each(function() {
            var $node = $(this);
            if ($node.data('imovel-parceiro-monthly-applied')) {
                return;
            }

            if ($node.find('.imovel-parceiro-monthly-suffix').length) {
                $node.data('imovel-parceiro-monthly-applied', true);
                return;
            }

            $node.append('<span class="imovel-parceiro-monthly-suffix"> /Mês</span>');
            $node.data('imovel-parceiro-monthly-applied', true);
        });
    }

    function initPriceUi() {
        hideUnsupportedPriceFields();
        bindRentalPriceToggle();
        applyFrontendRentalPriceSuffix();

        setTimeout(function() {
            updateRentalBadge();
            applyFrontendRentalPriceSuffix();
        }, 0);
    }

    function validateAcceptanceBeforeSubmit() {
        $(document).on('submit', '#submit_property_form', function(e){
            var $form = $(this);
            ensureAcceptanceFields();

            if (!hasAllAcceptances($form)) {
                e.preventDefault();
                var msg = (window.imovelParceiroCore && imovelParceiroCore.messages && imovelParceiroCore.messages.acceptances_required)
                    ? imovelParceiroCore.messages.acceptances_required
                    : 'E obrigatorio aceitar todos os termos para publicar ou editar este imovel.';
                showFeedback(msg, 'error');
                var $errorBox = $form.find('.validate-errors').first();
                if ($errorBox.length) {
                    $errorBox.removeClass('houzez-hidden').show();
                }
            }
        });

        $(document).on('change', '#submit_property_form .imovel-parceiro-acceptance-checkbox', function(){
            syncAcceptanceSubmitState();
        });
    }

    function showPartnershipModal() {
        var modalEl = document.getElementById('imovel-parceiro-partnership-modal');
        if (!modalEl) {
            return;
        }

        modalEl.style.display = 'block';
        modalEl.style.position = 'fixed';
        modalEl.style.inset = '0';
        modalEl.style.zIndex = '1055';
        modalEl.style.overflowY = 'auto';
        modalEl.classList.add('show');
        modalEl.setAttribute('aria-hidden', 'false');
        modalEl.setAttribute('aria-modal', 'true');

        var dialogEl = modalEl.querySelector('.modal-dialog');
        if (dialogEl) {
            dialogEl.style.zIndex = '1056';
        }

        if (!document.querySelector('.modal-backdrop')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-imovel-parceiro-backdrop', '1');
            document.body.appendChild(backdrop);
        }

        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }

    function hidePartnershipModal() {
        var modalEl = document.getElementById('imovel-parceiro-partnership-modal');
        if (!modalEl) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modalEl);
            if (!instance) {
                instance = new window.bootstrap.Modal(modalEl);
            }
            instance.hide();
            instance.dispose();
        }

        modalEl.style.display = 'none';
        modalEl.style.position = '';
        modalEl.style.inset = '';
        modalEl.style.zIndex = '';
        modalEl.style.overflowY = '';
        modalEl.classList.remove('show');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');

        var dialogEl = modalEl.querySelector('.modal-dialog');
        if (dialogEl) {
            dialogEl.style.zIndex = '';
        }

        var backdrop = document.querySelector('.modal-backdrop[data-imovel-parceiro-backdrop="1"]');
        if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }

        var bootstrapBackdrops = document.querySelectorAll('.modal-backdrop');
        bootstrapBackdrops.forEach(function(node){
            if (node && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        var offcanvasBackdrops = document.querySelectorAll('.offcanvas-backdrop');
        offcanvasBackdrops.forEach(function(node){
            if (node && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        document.body.classList.remove('modal-open');
        document.body.classList.remove('offcanvas-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    function setContactHref(selector, value, prefix) {
        var $element = $(selector);
        if (!$element.length) {
            return;
        }

        if (!value) {
            $element.removeAttr('href').removeAttr('target');
            return;
        }

        $element.attr('href', prefix + value);
    }

    function populateContactModal(contact) {
        var contactData = contact || (window.imovelParceiroCoreOwnerContact || {});

        $('.imovel-parceiro-owner-name').text(contactData.owner_name || '-');
        $('.imovel-parceiro-owner-email').text(contactData.owner_email || '-');
        $('.imovel-parceiro-owner-phone').text(contactData.owner_phone || '-');
        $('.imovel-parceiro-owner-mobile').text(contactData.owner_mobile || '-');
        $('.imovel-parceiro-owner-whatsapp').text(contactData.owner_whatsapp || '-');

        setContactHref('.imovel-parceiro-owner-email', contactData.owner_email || '', 'mailto:');
        setContactHref('.imovel-parceiro-owner-phone', contactData.owner_phone_call || '', 'tel:');
        setContactHref('.imovel-parceiro-owner-mobile', contactData.owner_mobile_call || '', 'tel:');

        var $whatsapp = $('.imovel-parceiro-owner-whatsapp');
        if ($whatsapp.length) {
            if (contactData.owner_whatsapp_call) {
                var propertyTitle = contactData.property_title || '';
                var propertyUrl = contactData.property_permalink || window.location.href;
                var whatsappText = encodeURIComponent('Olá, tenho interesse no imóvel ' + propertyTitle + ' - ' + propertyUrl);
                $whatsapp.attr('href', 'https://api.whatsapp.com/send?phone=' + contactData.owner_whatsapp_call + '&text=' + whatsappText);
                $whatsapp.attr('target', '_blank');
            } else {
                $whatsapp.removeAttr('href').removeAttr('target');
            }
        }

        var $propertyField = $('#imovel-parceiro-partnership-form [name="property_id"]');
        if ($propertyField.length && contactData.property_id) {
            $propertyField.val(contactData.property_id);
        }
    }

    function openContactModal(contact) {
        populateContactModal(contact);
        showPartnershipModal();
    }

    function injectPartnershipButtonInAgentCard() {
        syncPartnershipButtonState();
    }

    function showFeedback(message, type) {
        var $toast = $('<div class="imovel-parceiro-toast" role="status" aria-live="polite"></div>');
        $toast.css({
            position: 'fixed',
            right: '20px',
            bottom: '20px',
            zIndex: '999999',
            padding: '12px 16px',
            borderRadius: '8px',
            color: '#fff',
            background: type === 'error' ? '#b91c1c' : '#15803d',
            boxShadow: '0 8px 24px rgba(0,0,0,0.2)',
            maxWidth: '320px',
            fontSize: '14px',
            lineHeight: '1.4'
        }).text(message);
        $('body').append($toast);
        setTimeout(function(){
            $toast.fadeOut(250, function(){ $toast.remove(); });
        }, 4000);
    }

    function partnershipRequestExists() {
        return !!(window.imovelParceiroCore && imovelParceiroCore.request_exists === true);
    }

    function syncPartnershipButtonState() {
        if (!partnershipRequestExists()) {
            return;
        }

        $('.imovel-parceiro-request-partnership').each(function(){
            var $button = $(this);
            $button.prop('disabled', true);
            $button.addClass('disabled');
            $button.attr('aria-disabled', 'true');
            if (!$button.text().match(/solicita[cç][aã]o ja enviada/i) && !$button.text().match(/solicita[cç][aã]o já enviada/i)) {
                $button.text('Solicitacao ja enviada');
            }
        });
    }

    var brokerChangeSearchTimer = null;
    var brokerChangeSearchXhr = null;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getBrokerChangeModalHtml() {
        return '' +
            '<div id="imovel-parceiro-broker-change-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="imovel-parceiro-broker-change-modal-label" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered modal-lg" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="imovel-parceiro-broker-change-modal-label">Solicitar troca de corretor</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="text-muted mb-3 imovel-parceiro-broker-change-property">Selecione o corretor desejado e confirme a solicitação.</p>' +
                            '<input type="hidden" name="property_id" value="" />' +
                            '<input type="hidden" name="selected_broker_id" value="" />' +
                            '<div class="form-group mb-3">' +
                                '<label for="imovel-parceiro-broker-search" style="display:block; margin-bottom:6px; font-weight:600;">Buscar corretor</label>' +
                                '<input type="text" id="imovel-parceiro-broker-search" class="form-control imovel-parceiro-broker-search" placeholder="Digite nome, e-mail ou login" autocomplete="off" />' +
                            '</div>' +
                            '<div class="imovel-parceiro-broker-search-results list-group mb-3" style="max-height:240px; overflow:auto;"></div>' +
                            '<div class="imovel-parceiro-broker-selected" style="display:none; padding:12px; border:1px solid #dbeafe; background:#eff6ff; border-radius:10px; margin-bottom:14px;"></div>' +
                            '<div class="form-group">' +
                                '<label for="imovel-parceiro-broker-reason" style="display:block; margin-bottom:6px; font-weight:600;">Motivo da troca</label>' +
                                '<textarea id="imovel-parceiro-broker-reason" class="form-control imovel-parceiro-broker-reason" rows="4" placeholder="Explique por que deseja solicitar a troca."></textarea>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-footer d-flex justify-content-between align-items-center">' +
                            '<small class="text-muted imovel-parceiro-broker-help">A busca retorna apenas perfis comerciais válidos.</small>' +
                            '<div class="d-flex gap-2">' +
                                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>' +
                                '<button type="button" class="btn btn-primary imovel-parceiro-broker-confirm" disabled>Confirmar solicitação</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
    }

    function ensureBrokerChangeModal() {
        var modalEl = document.getElementById('imovel-parceiro-broker-change-modal');
        if (!modalEl) {
            $('body').append(getBrokerChangeModalHtml());
            modalEl = document.getElementById('imovel-parceiro-broker-change-modal');
        }

        return modalEl;
    }

    function showBrokerChangeModal() {
        var modalEl = ensureBrokerChangeModal();
        if (!modalEl) {
            return;
        }

        modalEl.style.display = 'block';
        modalEl.style.position = 'fixed';
        modalEl.style.inset = '0';
        modalEl.style.zIndex = '1055';
        modalEl.style.overflowY = 'auto';
        modalEl.classList.add('show');
        modalEl.setAttribute('aria-hidden', 'false');
        modalEl.setAttribute('aria-modal', 'true');

        var dialogEl = modalEl.querySelector('.modal-dialog');
        if (dialogEl) {
            dialogEl.style.zIndex = '1056';
        }

        if (!document.querySelector('.modal-backdrop[data-imovel-parceiro-backdrop="2"]')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-imovel-parceiro-backdrop', '2');
            document.body.appendChild(backdrop);
        }

        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }

    function hideBrokerChangeModal() {
        var modalEl = document.getElementById('imovel-parceiro-broker-change-modal');
        if (!modalEl) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modalEl);
            if (!instance) {
                instance = new window.bootstrap.Modal(modalEl);
            }
            instance.hide();
            instance.dispose();
        }

        modalEl.style.display = 'none';
        modalEl.style.position = '';
        modalEl.style.inset = '';
        modalEl.style.zIndex = '';
        modalEl.style.overflowY = '';
        modalEl.classList.remove('show');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');

        var dialogEl = modalEl.querySelector('.modal-dialog');
        if (dialogEl) {
            dialogEl.style.zIndex = '';
        }

        var backdrop = document.querySelector('.modal-backdrop[data-imovel-parceiro-backdrop="2"]');
        if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }

        var bootstrapBackdrops = document.querySelectorAll('.modal-backdrop');
        bootstrapBackdrops.forEach(function(node){
            if (node && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        var offcanvasBackdrops = document.querySelectorAll('.offcanvas-backdrop');
        offcanvasBackdrops.forEach(function(node){
            if (node && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        document.body.classList.remove('modal-open');
        document.body.classList.remove('offcanvas-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    function renderBrokerSearchResults($modal, results) {
        var $results = $modal.find('.imovel-parceiro-broker-search-results');
        if (!$results.length) {
            return;
        }

        if (!results || !results.length) {
            $results.html('<div class="list-group-item text-muted">Nenhum corretor encontrado.</div>');
            return;
        }

        var html = '';
        results.forEach(function(broker){
            html += '<button type="button" class="list-group-item list-group-item-action imovel-parceiro-broker-result" data-broker-id="' + escapeHtml(broker.id) + '" data-broker-name="' + escapeHtml(broker.name) + '" data-broker-email="' + escapeHtml(broker.email) + '" data-broker-company="' + escapeHtml(broker.company || '') + '" data-broker-license="' + escapeHtml(broker.license || '') + '" data-broker-phone="' + escapeHtml(broker.phone || '') + '" data-broker-mobile="' + escapeHtml(broker.mobile || '') + '" data-broker-whatsapp="' + escapeHtml(broker.whatsapp || '') + '" data-broker-avatar="' + escapeHtml(broker.avatar || '') + '">' +
                '<div class="d-flex align-items-start gap-3">' +
                    '<div style="width:44px; height:44px; border-radius:50%; overflow:hidden; background:#f3f4f6; flex:0 0 auto;">' +
                        '<img src="' + escapeHtml(broker.avatar || '') + '" alt="' + escapeHtml(broker.name || '') + '" style="width:100%; height:100%; object-fit:cover; display:block;" />' +
                    '</div>' +
                    '<div class="text-start">' +
                        '<strong class="d-block">' + escapeHtml(broker.name || '') + '</strong>' +
                        (broker.company ? '<small class="d-block text-muted">' + escapeHtml(broker.company) + '</small>' : '') +
                        (broker.license ? '<small class="d-block text-muted">CRECI: ' + escapeHtml(broker.license) + '</small>' : '') +
                        (broker.email ? '<small class="d-block text-muted">' + escapeHtml(broker.email) + '</small>' : '') +
                    '</div>' +
                '</div>' +
            '</button>';
        });

        $results.html(html);
    }

    function updateBrokerSelection($modal, broker) {
        var $selected = $modal.find('.imovel-parceiro-broker-selected');
        var $confirm = $modal.find('.imovel-parceiro-broker-confirm');
        var $selectedId = $modal.find('[name="selected_broker_id"]');

        if (!broker) {
            $selected.hide().empty();
            $confirm.prop('disabled', true);
            $selectedId.val('');
            return;
        }

        var summary = '' +
            '<div class="d-flex align-items-start gap-3">' +
                '<div style="width:56px; height:56px; border-radius:50%; overflow:hidden; background:#fff; flex:0 0 auto; border:1px solid #cfe2ff;">' +
                    '<img src="' + escapeHtml(broker.avatar || '') + '" alt="' + escapeHtml(broker.name || '') + '" style="width:100%; height:100%; object-fit:cover; display:block;" />' +
                '</div>' +
                '<div>' +
                    '<strong class="d-block">' + escapeHtml(broker.name || '') + '</strong>' +
                    (broker.company ? '<span class="d-block text-muted">' + escapeHtml(broker.company) + '</span>' : '') +
                    (broker.license ? '<span class="d-block text-muted">CRECI: ' + escapeHtml(broker.license) + '</span>' : '') +
                '</div>' +
            '</div>';

        $selected.html(summary).show();
        $confirm.prop('disabled', false);
        $selectedId.val(broker.id || '');
    }

    function fetchBrokerSearchResults($modal, propertyId, term) {
        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            renderBrokerSearchResults($modal, []);
            return;
        }

        if (brokerChangeSearchXhr && brokerChangeSearchXhr.readyState !== 4) {
            brokerChangeSearchXhr.abort();
        }

        var $results = $modal.find('.imovel-parceiro-broker-search-results');
        if ($results.length) {
            $results.html('<div class="list-group-item text-muted">Buscando corretores...</div>');
        }

        brokerChangeSearchXhr = $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_search_brokers',
            nonce: imovelParceiroCore.nonce,
            property_id: propertyId,
            search: term || ''
        }, function(response){
            if (response && response.success && response.data && response.data.results) {
                renderBrokerSearchResults($modal, response.data.results);
            } else {
                renderBrokerSearchResults($modal, []);
            }
        }).fail(function(){
            renderBrokerSearchResults($modal, []);
        });
    }

    function openBrokerChangeModal(propertyId, propertyTitle) {
        var modalEl = ensureBrokerChangeModal();
        if (!modalEl) {
            return;
        }

        var $modal = $(modalEl);
        $modal.find('[name="property_id"]').val(propertyId || '');
        $modal.find('[name="selected_broker_id"]').val('');
        $modal.find('.imovel-parceiro-broker-search').val('');
        $modal.find('.imovel-parceiro-broker-reason').val('');
        $modal.find('.imovel-parceiro-broker-change-property').text(propertyTitle ? ('Imóvel: ' + propertyTitle) : 'Selecione o corretor desejado e confirme a solicitação.');
        $modal.find('.imovel-parceiro-broker-selected').hide().empty();
        $modal.find('.imovel-parceiro-broker-confirm').prop('disabled', true);
        renderBrokerSearchResults($modal, []);
        showBrokerChangeModal();
        fetchBrokerSearchResults($modal, propertyId, '');
    }

    $(document).on('input', '#imovel-parceiro-broker-change-modal .imovel-parceiro-broker-search', function(){
        var $modal = $(this).closest('#imovel-parceiro-broker-change-modal');
        var propertyId = parseInt($modal.find('[name="property_id"]').val(), 10) || 0;
        var term = $.trim($(this).val() || '');

        updateBrokerSelection($modal, null);

        if (brokerChangeSearchTimer) {
            clearTimeout(brokerChangeSearchTimer);
        }

        brokerChangeSearchTimer = setTimeout(function(){
            fetchBrokerSearchResults($modal, propertyId, term);
        }, term.length ? 250 : 0);
    });

    $(document).on('click', '#imovel-parceiro-broker-change-modal .imovel-parceiro-broker-result', function(){
        var $button = $(this);
        var $modal = $button.closest('#imovel-parceiro-broker-change-modal');
        updateBrokerSelection($modal, {
            id: $button.data('broker-id') || '',
            name: $button.data('broker-name') || '',
            email: $button.data('broker-email') || '',
            company: $button.data('broker-company') || '',
            license: $button.data('broker-license') || '',
            phone: $button.data('broker-phone') || '',
            mobile: $button.data('broker-mobile') || '',
            whatsapp: $button.data('broker-whatsapp') || '',
            avatar: $button.data('broker-avatar') || ''
        });
    });

    $(document).on('click', '#imovel-parceiro-broker-change-modal .imovel-parceiro-broker-confirm', function(){
        var $button = $(this);
        var $modal = $button.closest('#imovel-parceiro-broker-change-modal');
        var propertyId = parseInt($modal.find('[name="property_id"]').val(), 10) || 0;
        var selectedBrokerId = parseInt($modal.find('[name="selected_broker_id"]').val(), 10) || 0;
        var reason = $.trim($modal.find('.imovel-parceiro-broker-reason').val() || '');
        var selectedName = $.trim(($modal.find('.imovel-parceiro-broker-selected strong').first().text() || ''));

        if (!propertyId || !selectedBrokerId) {
            showFeedback('Selecione um corretor para continuar.', 'error');
            return;
        }

        if (!window.confirm('Confirmar troca de corretor para ' + (selectedName || 'o corretor selecionado') + '?')) {
            return;
        }

        if ($button.data('busy')) {
            return;
        }

        $button.data('busy', true);
        $button.prop('disabled', true).text('Enviando...');

        $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_request_broker_change',
            nonce: imovelParceiroCore.nonce,
            property_id: propertyId,
            requested_broker_id: selectedBrokerId,
            reason: reason
        }, function(response){
            if (response && response.success) {
                showFeedback((response.data && response.data.message) ? response.data.message : 'Solicitacao de troca enviada.', 'success');
                hideBrokerChangeModal();
            } else {
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Nao foi possivel enviar a solicitacao.', 'error');
            }
        }).fail(function(){
            showFeedback('Falha de comunicacao ao solicitar troca de corretor.', 'error');
        }).always(function(){
            $button.data('busy', false);
            $button.prop('disabled', false).text('Confirmar solicitação');
        });
    });

    $(document).on('click', '.imovel-parceiro-save-acceptance', function(e){
        e.preventDefault();
        if ($(this).data('busy')) {
            return;
        }
        var $button = $(this);
        $button.data('busy', true);
        var propertyId = $button.data('property-id');
        var $scope = $button.closest('.imovel-parceiro-acceptance, .imovel-parceiro-acceptance-block, form');
        var acceptance = {
            authorization: !!$scope.find('[name="imovel_parceiro_acceptance[authorization]"]').is(':checked'),
            partnership: !!$scope.find('[name="imovel_parceiro_acceptance[partnership]"]').is(':checked'),
            commission: !!$scope.find('[name="imovel_parceiro_acceptance[commission]"]').is(':checked'),
            terms: !!$scope.find('[name="imovel_parceiro_acceptance[terms]"]').is(':checked')
        };
        var payload = { action: 'imovel_parceiro_accept_terms', nonce: imovelParceiroCore.nonce, property_id: propertyId, acceptance: acceptance };
        $.post(imovelParceiroCore.ajax_url, payload, function(response){
            if(response.success){
                showFeedback(response.data.message || 'Aceites registrados.', 'success');
            } else {
                showFeedback(response.data.message || 'Erro.', 'error');
            }
        }).always(function(){
            $button.data('busy', false);
        });
    });

    $(document).on('click', '.imovel-parceiro-request-partnership, .imovel-parceiro-request-btn', function(e){
        e.preventDefault();
        e.stopImmediatePropagation();

        if (partnershipRequestExists()) {
            showFeedback((window.imovelParceiroCore && imovelParceiroCore.messages && imovelParceiroCore.messages.request_exists)
                ? imovelParceiroCore.messages.request_exists
                : 'Você já enviou uma solicitação para este imóvel e não pode pedir novamente.', 'error');
            return;
        }

        openContactModal(window.imovelParceiroCoreOwnerContact || {});
    });

    $(document).on('click', '.imovel-parceiro-view-contact', function(e){
        e.preventDefault();
        var partnershipStatus = $(this).data('partnership-status');
        if ($.inArray(partnershipStatus, ['accepted', 'active', 'negotiating', 'contact_released', 'opportunity', 'visit', 'proposal']) === -1) {
            showFeedback('O contato ficará disponível após a aprovação da parceria.', 'error');
            return;
        }

        var contact = {
            property_id: $(this).data('property-id') || '',
            owner_name: $(this).data('owner-name') || '',
            owner_email: $(this).data('owner-email') || '',
            owner_phone: $(this).data('owner-phone') || '',
            owner_phone_call: $(this).data('owner-phone-call') || '',
            owner_mobile: $(this).data('owner-mobile') || '',
            owner_mobile_call: $(this).data('owner-mobile-call') || '',
            owner_whatsapp: $(this).data('owner-whatsapp') || '',
            owner_whatsapp_call: $(this).data('owner-whatsapp-call') || '',
            property_title: $(this).data('property-title') || '',
            property_permalink: $(this).data('property-permalink') || ''
        };

        openContactModal(contact);
    });

    $(document).on('click', '#imovel-parceiro-partnership-modal [data-bs-dismiss="modal"]', function(e){
        e.preventDefault();
        hidePartnershipModal();
    });

    $(document).on('click', '.imovel-owner-request-broker-change', function(e){
        e.preventDefault();

        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            showFeedback('Configuracao de seguranca indisponivel para esta acao.', 'error');
            return;
        }

        var propertyId = parseInt($(this).data('property-id'), 10) || 0;
        if (!propertyId) {
            showFeedback('Imovel invalido para solicitacao.', 'error');
            return;
        }

        openBrokerChangeModal(propertyId, $(this).data('property-title') || '');
    });

    $(document).on('click', '.imovel-broker-change-action', function(e){
        e.preventDefault();

        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            showFeedback('Configuracao de seguranca indisponivel para esta acao.', 'error');
            return;
        }

        var requestId = parseInt($(this).data('request-id'), 10) || 0;
        var decision = ($(this).data('decision') || '').toString();

        if (!requestId || !decision) {
            showFeedback('Solicitacao invalida.', 'error');
            return;
        }

        if (!window.confirm(decision === 'approve' ? 'Aprovar esta troca de corretor?' : 'Rejeitar esta troca de corretor?')) {
            return;
        }

        var adminNote = '';
        if (decision === 'reject') {
            adminNote = window.prompt('Informe um motivo para a rejeicao (opcional):') || '';
        } else if (window.confirm('Deseja adicionar uma observacao interna?')) {
            adminNote = window.prompt('Observacao interna (opcional):') || '';
        }

        $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_process_broker_change',
            nonce: imovelParceiroCore.nonce,
            request_id: requestId,
            decision: decision,
            admin_note: adminNote
        }, function(response){
            if (response && response.success) {
                showFeedback((response.data && response.data.message) ? response.data.message : 'Solicitacao processada.', 'success');
                setTimeout(function(){
                    window.location.reload();
                }, 250);
            } else {
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Nao foi possivel processar a solicitacao.', 'error');
            }
        }).fail(function(){
            showFeedback('Falha de comunicacao ao processar troca de corretor.', 'error');
        });
    });

    function closeDocumentModal() {
        var $modal = $('#imovel-doc-modal');
        if (!$modal.length) {
            return;
        }

        $modal.css({
            display: '',
            position: '',
            inset: '',
            zIndex: '',
            padding: '',
            alignItems: '',
            justifyContent: '',
            background: ''
        });
        $modal.attr('aria-hidden', 'true');
        $modal.removeClass('is-open');
        $('#imovel-doc-modal-viewer').empty();
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
    }

    function renderDocumentModal(documentData) {
        var $modal = $('#imovel-doc-modal');
        if (!$modal.length) {
            return;
        }

        var metaHtml = '' +
            '<div class="imovel-doc-modal__meta-grid">' +
                '<div><strong>Documento</strong><div>' + escapeHtml(documentData.document_type || '-') + '</div></div>' +
                '<div><strong>Proprietário</strong><div>' + escapeHtml(documentData.owner_name || '-') + '</div></div>' +
                '<div><strong>Imóvel</strong><div>' + escapeHtml(documentData.property_title || '-') + '</div></div>' +
                '<div><strong>Enviado em</strong><div>' + escapeHtml(documentData.submitted_at || '-') + '</div></div>' +
                '<div><strong>Status</strong><div>' + escapeHtml(documentData.status || '-') + '</div></div>' +
                '<div><strong>Arquivo</strong><div>' + escapeHtml(documentData.file_name || '-') + '</div></div>' +
            '</div>';

        if (documentData.review_note) {
            metaHtml += '<div class="imovel-doc-modal__note"><strong>Observação:</strong> ' + escapeHtml(documentData.review_note) + '</div>';
        }

        $('#imovel-doc-modal-title').text('Documento - ' + (documentData.document_type || ''));
        $('#imovel-doc-modal-meta').html(metaHtml);

        var viewerHtml = '';
        if (documentData.is_previewable && documentData.stream_url) {
            if ((documentData.mime_type || '').indexOf('image/') === 0) {
                viewerHtml = '<img class="imovel-doc-modal__image" src="' + encodeURI(documentData.stream_url) + '" alt="Documento" />';
            } else {
                viewerHtml = '<iframe class="imovel-doc-modal__iframe" src="' + encodeURI(documentData.stream_url) + '" title="Visualização do documento"></iframe>';
            }
        } else {
            viewerHtml = '<div class="imovel-doc-modal__unavailable">Este formato não pode ser visualizado diretamente. Use o botão "Baixar documento".</div>';
        }

        $('#imovel-doc-modal-viewer').html(viewerHtml);
        if (documentData.download_url) {
            $('#imovel-doc-modal-download').attr('href', documentData.download_url).show();
        } else {
            $('#imovel-doc-modal-download').hide();
        }

        // Fallback to force popup behavior if theme/plugins failed to load modal CSS.
        $modal.css({
            display: 'flex',
            position: 'fixed',
            inset: '0',
            zIndex: '99999',
            padding: '16px',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(15,23,42,.55)'
        });
        $modal.attr('aria-hidden', 'false');
        $modal.addClass('is-open');
        $('body').addClass('modal-open');
        $('body').css('overflow', 'hidden');
    }

    function ensureDocumentNoteModal() {
        if ($('#imovel-doc-note-modal').length) {
            return $('#imovel-doc-note-modal');
        }

        var html = '' +
            '<div id="imovel-doc-note-modal" class="imovel-doc-note-modal" aria-hidden="true">' +
                '<div class="imovel-doc-note-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="imovel-doc-note-modal-title">' +
                    '<div class="imovel-doc-note-modal__header">' +
                        '<h5 id="imovel-doc-note-modal-title" class="mb-0">Observação da análise</h5>' +
                        '<button type="button" class="btn btn-light btn-sm imovel-doc-note-modal-close" aria-label="Fechar">&times;</button>' +
                    '</div>' +
                    '<div class="imovel-doc-note-modal__body">' +
                        '<label for="imovel-doc-note-input" class="d-block mb-2" id="imovel-doc-note-label">Informe a observação:</label>' +
                        '<textarea id="imovel-doc-note-input" class="form-control" rows="5" placeholder="Descreva aqui..."></textarea>' +
                        '<small id="imovel-doc-note-hint" class="text-muted d-block mt-2"></small>' +
                    '</div>' +
                    '<div class="imovel-doc-note-modal__footer">' +
                        '<button type="button" class="btn btn-secondary imovel-doc-note-modal-close">Cancelar</button>' +
                        '<button type="button" class="btn btn-primary" id="imovel-doc-note-confirm">Confirmar</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('body').append(html);
        return $('#imovel-doc-note-modal');
    }

    function closeDocumentNoteModal() {
        var $modal = $('#imovel-doc-note-modal');
        if (!$modal.length) {
            return;
        }

        $modal.attr('aria-hidden', 'true').removeClass('is-open');
        $modal.css({
            display: '',
            position: '',
            inset: '',
            zIndex: '',
            padding: '',
            alignItems: '',
            justifyContent: '',
            background: ''
        });
        $modal.removeData('required').removeData('resolve').removeData('decision');
        $('#imovel-doc-note-input').val('');
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
    }

    function askDocumentReviewNote(options, onConfirm) {
        var settings = options || {};
        var $modal = ensureDocumentNoteModal();
        var required = !!settings.required;
        var title = settings.title || 'Observação da análise';
        var label = settings.label || 'Informe a observação:';
        var hint = settings.hint || '';
        var placeholder = settings.placeholder || 'Descreva aqui...';

        $('#imovel-doc-note-modal-title').text(title);
        $('#imovel-doc-note-label').text(label);
        $('#imovel-doc-note-hint').text(hint);
        $('#imovel-doc-note-input').attr('placeholder', placeholder).val(settings.initialValue || '');

        $modal.data('required', required);
        $modal.data('resolve', onConfirm || null);

        // Fallback to force popup behavior even if CSS did not load on the page.
        $modal.css({
            display: 'flex',
            position: 'fixed',
            inset: '0',
            zIndex: '100000',
            padding: '16px',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(15,23,42,.55)'
        });
        $modal.attr('aria-hidden', 'false').addClass('is-open');
        $('body').addClass('modal-open');
        $('body').css('overflow', 'hidden');

        setTimeout(function(){
            $('#imovel-doc-note-input').trigger('focus');
        }, 20);
    }

    $(document).on('click', '.imovel-doc-note-modal-close', function(e){
        e.preventDefault();
        closeDocumentNoteModal();
    });

    $(document).on('click', '#imovel-doc-note-modal', function(e){
        if ($(e.target).is('#imovel-doc-note-modal')) {
            closeDocumentNoteModal();
        }
    });

    $(document).on('click', '#imovel-doc-note-confirm', function(e){
        e.preventDefault();

        var $modal = $('#imovel-doc-note-modal');
        if (!$modal.length) {
            return;
        }

        var required = !!$modal.data('required');
        var note = $.trim($('#imovel-doc-note-input').val() || '');
        if (required && !note) {
            showFeedback('E obrigatorio informar um motivo.', 'error');
            return;
        }

        var resolver = $modal.data('resolve');
        closeDocumentNoteModal();
        if (typeof resolver === 'function') {
            resolver(note);
        }
    });

    $(document).on('keydown', function(e){
        if (e.key !== 'Escape') {
            return;
        }

        if ($('#imovel-doc-note-modal').hasClass('is-open')) {
            closeDocumentNoteModal();
            return;
        }

        if ($('#imovel-doc-modal').hasClass('is-open')) {
            closeDocumentModal();
        }
    });

    function submitDocumentReview(documentId, decision, reviewNote) {
        $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_review_document',
            nonce: imovelParceiroCore.nonce,
            document_id: documentId,
            decision: decision,
            review_note: reviewNote || ''
        }, function(response){
            if (response && response.success) {
                showFeedback((response.data && response.data.message) ? response.data.message : 'Analise registrada.', 'success');
                setTimeout(function(){
                    window.location.reload();
                }, 250);
                return;
            }

            showFeedback((response && response.data && response.data.message) ? response.data.message : 'Nao foi possivel processar a analise.', 'error');
        }).fail(function(){
            showFeedback('Falha de comunicacao ao processar analise documental.', 'error');
        });
    }

    $(document).on('click', '.imovel-doc-open-modal', function(e){
        e.preventDefault();

        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            showFeedback('Configuracao de seguranca indisponivel para esta acao.', 'error');
            return;
        }

        var documentId = parseInt($(this).data('document-id'), 10) || 0;
        if (!documentId) {
            showFeedback('Documento invalido.', 'error');
            return;
        }

        $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_document_preview',
            nonce: imovelParceiroCore.nonce,
            document_id: documentId
        }, function(response){
            if (response && response.success && response.data && response.data.document) {
                renderDocumentModal(response.data.document);
                return;
            }

            showFeedback((response && response.data && response.data.message) ? response.data.message : 'Nao foi possivel carregar o documento.', 'error');
        }).fail(function(){
            showFeedback('Falha de comunicacao ao abrir o documento.', 'error');
        });
    });

    $(document).on('click', '.imovel-doc-modal-close', function(e){
        e.preventDefault();
        closeDocumentModal();
    });

    $(document).on('click', '#imovel-doc-modal', function(e){
        if ($(e.target).is('#imovel-doc-modal')) {
            closeDocumentModal();
        }
    });

    $(document).on('click', '.imovel-doc-review-action', function(e){
        e.preventDefault();

        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            showFeedback('Configuracao de seguranca indisponivel para esta acao.', 'error');
            return;
        }

        var documentId = parseInt($(this).data('document-id'), 10) || 0;
        var decision = String($(this).data('decision') || '');
        if (!documentId || !decision) {
            showFeedback('Dados invalidos para analise documental.', 'error');
            return;
        }

        var actionLabel = decision === 'approve' ? 'aprovar' : (decision === 'reject' ? 'rejeitar' : 'solicitar informações adicionais para');
        if (!window.confirm('Deseja ' + actionLabel + ' este documento?')) {
            return;
        }

        if (decision === 'reject' || decision === 'request_info') {
            askDocumentReviewNote({
                required: true,
                title: decision === 'reject' ? 'Motivo da rejeicao' : 'Solicitar informacoes adicionais',
                label: decision === 'reject' ? 'Informe o motivo da rejeicao:' : 'Informe o que deve ser corrigido/enviado:',
                hint: 'Essa mensagem sera registrada na analise e enviada ao proprietario.',
                placeholder: decision === 'reject' ? 'Ex.: documento ilegivel ou invalido...' : 'Ex.: enviar RG frente e verso com melhor qualidade...'
            }, function(reviewNote){
                submitDocumentReview(documentId, decision, reviewNote);
            });
            return;
        }

        if (window.confirm('Deseja registrar uma observacao interna?')) {
            askDocumentReviewNote({
                required: false,
                title: 'Observacao interna (opcional)',
                label: 'Escreva uma observacao interna (opcional):',
                hint: 'Se nao quiser adicionar nada, clique em Confirmar com o campo vazio.',
                placeholder: 'Observacao opcional...'
            }, function(reviewNote){
                submitDocumentReview(documentId, decision, reviewNote);
            });
            return;
        }

        submitDocumentReview(documentId, decision, '');
    });

    $(document).on('click', '.imovel-owner-request-delete', function(e){
        e.preventDefault();

        if (!(window.imovelParceiroCore && imovelParceiroCore.ajax_url && imovelParceiroCore.nonce)) {
            showFeedback('Configuracao de seguranca indisponivel para esta acao.', 'error');
            return;
        }

        var propertyId = parseInt($(this).data('property-id'), 10) || 0;
        if (!propertyId) {
            showFeedback('Imovel invalido para solicitacao.', 'error');
            return;
        }

        var reason = window.prompt('Informe o motivo da solicitacao de exclusao:');
        if (reason === null) {
            return;
        }

        $.post(imovelParceiroCore.ajax_url, {
            action: 'imovel_parceiro_owner_request_property_delete',
            nonce: imovelParceiroCore.nonce,
            property_id: propertyId,
            reason: reason
        }, function(response){
            if (response && response.success) {
                showFeedback((response.data && response.data.message) ? response.data.message : 'Solicitacao de exclusao enviada.', 'success');
            } else {
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Nao foi possivel enviar a solicitacao.', 'error');
            }
        }).fail(function(){
            showFeedback('Falha de comunicacao ao solicitar exclusao.', 'error');
        });
    });

    injectPartnershipButtonInAgentCard();
    $(window).on('load', injectPartnershipButtonInAgentCard);

    $(document).on('elementor/frontend/init', function(){
        injectPartnershipButtonInAgentCard();

        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', injectPartnershipButtonInAgentCard);
        }
    });

    $(document).on('submit', '#imovel-parceiro-partnership-form', function(e){
        e.preventDefault();
        var $form = $(this);

        var $button = $form.find('.imovel-parceiro-submit-request');
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var propertyId = $form.find('[name="property_id"]').val();
        var message = $form.find('[name="message"]').val();
        $button.prop('disabled', true).text('Enviando...');
        var payload = { action: 'imovel_parceiro_request_partnership', nonce: imovelParceiroCore.nonce, property_id: propertyId, message: message, partnership_terms: 1 };
        $.post(imovelParceiroCore.ajax_url, payload, function(response){
            if(response.success){
                showFeedback(response.data.message || 'Solicitação enviada.', 'success');
                $form.get(0).reset();
                setTimeout(function(){
                    hidePartnershipModal();
                    if (imovelParceiroCore.partnerships_url) {
                        window.location.href = imovelParceiroCore.partnerships_url;
                    }
                }, 800);
            } else {
                showFeedback(response.data.message || 'Erro.', 'error');
            }
        }).always(function(){
            $button.prop('disabled', false).text('Solicitar parceria');
            $button.data('busy', false);
        });
    });

    function postPartnershipAction(action, data) {
        return $.post(imovelParceiroCore.ajax_url, $.extend({}, data, { action: action, nonce: imovelParceiroCore.nonce }));
    }

    function handlePartnershipDecision($button, status) {
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true).prop('disabled', true);
        var partnershipId = $button.data('partnership-id');
        postPartnershipAction('imovel_parceiro_handle_partnership', { partnership_id: partnershipId, status: status })
            .done(function(response){
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Parceria atualizada.', (response && response.success) ? 'success' : 'error');
                if (response && response.success) {
                    setTimeout(function(){ window.location.reload(); }, 400);
                }
            })
            .fail(function(){
                showFeedback('Falha de comunicação ao atualizar a parceria.', 'error');
            })
            .always(function(){
                $button.data('busy', false).prop('disabled', false);
            });
    }

    function handlePartnershipTransition($button) {
        if ($button.data('busy')) {
            return;
        }
        var nextStatus = $button.data('next-status') || '';
        if (!nextStatus) {
            return;
        }
        $button.data('busy', true).prop('disabled', true);
        var partnershipId = $button.data('partnership-id');
        postPartnershipAction('imovel_parceiro_transition_partnership', { partnership_id: partnershipId, next_status: nextStatus })
            .done(function(response){
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Status atualizado.', (response && response.success) ? 'success' : 'error');
                if (response && response.success) {
                    setTimeout(function(){ window.location.reload(); }, 400);
                }
            })
            .fail(function(){
                showFeedback('Falha de comunicação ao atualizar o status.', 'error');
            })
            .always(function(){
                $button.data('busy', false).prop('disabled', false);
            });
    }

    function handlePartnershipCancel($button) {
        if ($button.data('busy')) {
            return;
        }
        var partnershipId = $button.data('partnership-id');
        var reason = window.prompt('Informe o motivo do cancelamento (obrigatório antes da negociação):', '');
        if (reason === null) {
            return;
        }
        $button.data('busy', true).prop('disabled', true);
        postPartnershipAction('imovel_parceiro_cancel_partnership', { partnership_id: partnershipId, reason: reason })
            .done(function(response){
                showFeedback((response && response.data && response.data.message) ? response.data.message : 'Parceria cancelada.', (response && response.success) ? 'success' : 'error');
                if (response && response.success) {
                    setTimeout(function(){ window.location.reload(); }, 400);
                }
            })
            .fail(function(){
                showFeedback('Falha de comunicação ao cancelar a parceria.', 'error');
            })
            .always(function(){
                $button.data('busy', false).prop('disabled', false);
            });
    }

    $(document).on('click', '.imovel-parceiro-partnership-accept', function(e){
        e.preventDefault();
        handlePartnershipDecision($(this), 'aceita');
    });

    $(document).on('click', '.imovel-parceiro-partnership-reject', function(e){
        e.preventDefault();
        handlePartnershipDecision($(this), 'recusada');
    });

    $(document).on('click', '.imovel-parceiro-partnership-transition', function(e){
        e.preventDefault();
        handlePartnershipTransition($(this));
    });

    $(document).on('click', '.imovel-parceiro-partnership-cancel', function(e){
        e.preventDefault();
        handlePartnershipCancel($(this));
    });

    ensureAcceptanceFields();
    precheckAcceptanceOnEdit();
    bindAcceptanceValidation();
    syncAcceptanceSubmitState();
    initPriceUi();
    validateAcceptanceBeforeSubmit();
    $(window).on('load', function(){
        ensureAcceptanceFields();
        precheckAcceptanceOnEdit();
        bindAcceptanceValidation();
        syncAcceptanceSubmitState();
        initPriceUi();
    });
    syncPartnershipButtonState();
});
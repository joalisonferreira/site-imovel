(function ($) {
    'use strict';

    if (typeof window.ipcwData === 'undefined') {
        return;
    }

    var IPCW = window.ipcwData;
    var $root, $fab, $overlay, $panel, $body, $close;
    var open = false;
    var lastFocus = null;

    function esc(s) {
        return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
    }

    function statusBadgeClass(status) {
        var map = {
            won: 'is-won',
            lost: 'is-lost',
            closed: 'is-closed',
            pending: 'is-pending',
            accepted: 'is-accepted',
            negotiating: 'is-negotiating',
            contact_released: 'is-released',
            opportunity: 'is-opportunity',
            visit: 'is-visit',
            proposal: 'is-proposal',
            rejected: 'is-lost'
        };
        return map[status] || '';
    }

    function str(key, fallback) {
        return (IPCW.strings && IPCW.strings[key]) ? IPCW.strings[key] : fallback;
    }

    function fillName(template, name) {
        return String(template || '').replace('{name}', name || '');
    }

    function init() {
        $root = $('#ipcw-root');
        if (!$root.length) {
            return;
        }
        $root.removeClass('ipcw-hidden');
        $fab = $('#ipcw-fab');
        $overlay = $('#ipcw-overlay');
        $panel = $('#ipcw-panel');
        $body = $('#ipcw-body');
        $close = $('#ipcw-close');

        var config = null;
        try {
            var cfgEl = document.getElementById('ipcw-config');
            if (cfgEl) {
                config = JSON.parse(cfgEl.textContent);
            }
        } catch (e) { config = null; }

        if (config) {
            lastConfig = config;
            renderPanel(config);
        }

        bindEvents();
        maybeInjectListingButtons();
    }

    function bindEvents() {
        if ($fab && $fab.length) {
            $fab.on('click', function () { openPanel(lastConfig); });
        }

        $close.on('click', closePanel);
        $overlay.on('click', closePanel);

        $body.on('click', '.ipcw-wa-login', function (e) {
            e.preventDefault();
            openHouzezLogin();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && open) {
                closePanel();
            }
        });

        $(document).on('click', '.ipcw-panel, .ipcw-fab', function (e) {
            e.stopPropagation();
        });

        $(document).on('click', function (e) {
            if (open && !$(e.target).closest('.ipcw-panel, .ipcw-fab').length) {
                closePanel();
            }
        });
    }

    var lastConfig = null;

    function openPanel(config) {
        if (!config) {
            return;
        }
        lastConfig = config;
        renderPanel(config);
        open = true;
        lastFocus = document.activeElement;
        $panel.addClass('is-open');
        $overlay.css({ opacity: 1, visibility: 'visible' });
        setTimeout(function () {
            var first = $panel.find('a[href], button:not(.ipcw-close), textarea').first();
            if (first.length) { first.trigger('focus'); }
        }, 60);
    }

    function closePanel() {
        open = false;
        $panel.removeClass('is-open');
        $overlay.css({ opacity: 0, visibility: 'hidden' });
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }

    function whatsappIcon() { return '&#128172;'; }
    function handshakeIcon() { return '&#129309;'; }
    function homeIcon() { return '&#127968;'; }
    function lockIcon() { return '&#128274;'; }
    function checkIcon() { return '&#10003;'; }

    function brokerInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) { return 'IP'; }
        var first = parts[0].charAt(0);
        var last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    function renderPanel(config) {
        if (!config) {
            $body.empty();
            return;
        }

        var p = config.property || {};
        var b = config.broker || {};
        var ps = config.partnership || {};
        var wa = config.whatsapp || {};
        var req = config.request || {};

        var brokerMetaParts = [];
        if (b.creci) { brokerMetaParts.push('CRECI ' + b.creci); }
        if (b.company) { brokerMetaParts.push(b.company); }
        var brokerMeta = brokerMetaParts.join(' · ');
        if (!brokerMeta) { brokerMeta = 'Corretor de imóveis'; }

        var html = '';
        html += '<div class="ipcw-broker">';
        if (b.avatar) {
            html += '<img src="' + esc(b.avatar) + '" alt="' + esc(b.name) + '" />';
        } else {
            html += '<span class="ipcw-broker__avatar" aria-hidden="true">' + esc(brokerInitials(b.name)) + '</span>';
        }
        html += '<div class="ipcw-broker__info">';
        html += '<p class="ipcw-broker__name">' + esc(b.name || 'Corretor') + '</p>';
        html += '<p class="ipcw-broker__meta">' + esc(brokerMeta) + '</p>';
        html += '</div></div>';

        if (p.title || p.location || p.price) {
            html += '<div class="ipcw-property">';
            if (p.thumb) {
                html += '<span class="ipcw-property__thumb"><img src="' + esc(p.thumb) + '" alt="' + esc(p.title) + '" /></span>';
            } else {
                html += '<span class="ipcw-property__thumb"><i class="houzez-icon icon-building-cloudy"></i></span>';
            }
            html += '<div class="ipcw-property__info">';
            html += '<p class="ipcw-property__title">' + esc(p.title || ('Imóvel #' + p.id)) + '</p>';
            if (p.location) { html += '<div class="ipcw-property__loc">' + esc(p.location) + '</div>'; }
            if (p.price) { html += '<div class="ipcw-property__price">' + esc(p.price) + '</div>'; }
            html += '</div></div>';
        }

        html += '<div class="ipcw-actions">';

        // WhatsApp action. Disabled for the property owner (cannot contact self).
        if (req.is_owner) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--whatsapp" disabled>';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + lockIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + (IPCW.strings ? IPCW.strings.wa_owner : 'WhatsApp indisponível') + '</span>';
            html += '<span class="ipcw-btn__sub">' + (IPCW.strings ? IPCW.strings.wa_owner_sub : 'Você é o proprietário deste imóvel.') + '</span>';
            html += '</span></button>';
        } else if (wa.available && wa.link) {
            html += '<a class="ipcw-btn ipcw-btn--whatsapp" href="' + esc(wa.link) + '" target="_blank" rel="noopener nofollow">';
            html += '<span class="ipcw-btn__icon">' + whatsappIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">Falar pelo WhatsApp</span>';
            html += '<span class="ipcw-btn__sub">Entrar em contato com o corretor</span>';
            html += '</span></a>';
        } else if (wa.needs_login) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--whatsapp ipcw-wa-login">';
            html += '<span class="ipcw-btn__icon">' + whatsappIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + (IPCW.strings ? IPCW.strings.wa_login : 'Faça login para ver o contato') + '</span>';
            html += '<span class="ipcw-btn__sub">' + (IPCW.strings ? IPCW.strings.wa_login_sub : 'Entre na sua conta para falar com o corretor.') + '</span>';
            html += '</span></button>';
        } else {
            html += '<button type="button" class="ipcw-btn ipcw-btn--whatsapp" disabled>';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + lockIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + (IPCW.strings ? IPCW.strings.wa_unavailable : 'WhatsApp indisponível') + '</span>';
            html += '<span class="ipcw-btn__sub">' + (IPCW.strings ? IPCW.strings.wa_unavailable_sub : 'O contato do corretor ainda não está liberado.') + '</span>';
            html += '</span></button>';
        }

        // Partnership action. Hidden for the property owner (cannot partner with self) and for clients (houzez_buyer).
        // Cada estado tem rótulo explícito: o corretor sabe antes do clique.
        if (!req.is_owner && !req.is_client) {
            var brokerName = b.name || '';
            var commissionLabel = (req.commission && req.commission.label) ? req.commission.label : '';
            if (ps.exists) {
            var badgeCls = statusBadgeClass(ps.status);
            var isPending = (ps.status === 'pending');
            var existTitle = isPending
                ? fillName(str('pending_title', 'Aguardando {name}'), brokerName)
                : ((IPCW.strings ? IPCW.strings.partnership : 'Parceria') + (ps.label ? ' · ' + ps.label : ''));
            html += '<div class="ipcw-btn ipcw-btn--partnership">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + esc(existTitle) + ' <span class="ipcw-status-badge ' + esc(badgeCls) + '">' + esc(ps.label || '') + '</span></span>';
            html += '<span class="ipcw-btn__sub">';
            if (ps.detail_url) {
                html += '<a href="' + esc(ps.detail_url) + '">' + esc(str('open_partnership', 'Acompanhar')) + '</a>';
            }
            html += '</span>';
            html += '</span></div>';
        } else if (req.allowed) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + esc(str('partnership_cta', 'Solicitar parceria')) + '</span>';
            html += '<span class="ipcw-btn__sub">' + esc(str('partnership_commission_sub', 'Dividir comissão') + (commissionLabel ? ' ' + commissionLabel : '')) + '</span>';
            html += '</span></button>';
        } else if (req.needs_subscription) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn ipcw-needs-subscription">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + esc(str('plans_cta', 'Ver planos')) + '</span>';
            html += '<span class="ipcw-btn__sub">' + esc(str('needs_subscription', 'Seu plano acabou ou não está ativo.')) + '</span>';
            html += '</span></button>';
        } else {
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn ipcw-needs-login">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + esc(str('login_cta', 'Fazer login')) + '</span>';
            html += '<span class="ipcw-btn__sub">' + esc(str('login_required', 'Faça login para solicitar uma parceria.')) + '</span>';
            html += '</span></button>';
        }
        }

        html += '</div>';

        // Client interest flow ("Tenho interesse neste imóvel"). Clients never
        // get direct WhatsApp — the lead is registered and the broker reaches out.
        var it = config.interest || {};
        if (!req.is_owner && req.is_client && (it.allowed || it.has_open_lead)) {
            html += '<div class="ipcw-actions">';
            if (it.has_open_lead) {
                html += '<button type="button" class="ipcw-btn ipcw-btn--interest" disabled>';
                html += '<span class="ipcw-btn__icon" aria-hidden="true">' + checkIcon() + '</span>';
                html += '<span class="ipcw-btn__text">';
                html += '<span class="ipcw-btn__title">' + esc(IPCW.strings ? IPCW.strings.interest_done : 'Interesse já registrado') + '</span>';
                html += '<span class="ipcw-btn__sub">' + esc(IPCW.strings ? IPCW.strings.interest_done_sub : 'O corretor responsável já foi avisado.') + '</span>';
                html += '</span></button>';
            } else {
                html += '<button type="button" class="ipcw-btn ipcw-btn--interest ipcw-interest-btn">';
                html += '<span class="ipcw-btn__icon" aria-hidden="true">' + homeIcon() + '</span>';
                html += '<span class="ipcw-btn__text">';
                html += '<span class="ipcw-btn__title">' + esc(IPCW.strings ? IPCW.strings.interest : 'Tenho interesse neste imóvel') + '</span>';
                html += '<span class="ipcw-btn__sub">' + esc(IPCW.strings ? IPCW.strings.interest_sub : 'O corretor responsável entrará em contato') + '</span>';
                html += '</span></button>';
            }
            html += '</div>';
        }

        $body.html(html);

        var $req = $body.find('.ipcw-request-btn');
        if ($req.length) {
            $req.on('click', function () {
                if ($req.hasClass('ipcw-needs-login')) {
                    openHouzezLogin();
                    return;
                }
                if ($req.hasClass('ipcw-needs-subscription')) {
                    var plansUrl = (config.request && config.request.plans_url) || IPCW.plans_url || '';
                    if (plansUrl) { window.location.href = plansUrl; }
                    return;
                }
                renderRequestForm(config);
            });
        }

        var $it = $body.find('.ipcw-interest-btn');
        if ($it.length) {
            $it.on('click', function () { renderInterestForm(config); });
        }
    }

    function renderInterestForm(config) {
        var p = config.property || {};
        var max = 500;

        var html = '<div class="ipcw-form">';
        html += '<label for="ipcw-interest-message">' + esc(IPCW.strings ? IPCW.strings.interest : 'Tenho interesse neste imóvel') + '</label>';
        html += '<textarea id="ipcw-interest-message" maxlength="' + max + '" placeholder="' + esc(IPCW.strings ? IPCW.strings.interest_message_ph : 'Mensagem opcional para o corretor (máx. 500 caracteres)') + '"></textarea>';
        html += '<div class="ipcw-char-count"><span>0</span>/' + max + '</div>';
        html += '<button type="button" class="ipcw-submit">' + esc(IPCW.strings ? IPCW.strings.interest_send : 'Enviar interesse') + '</button>';
        html += '<div class="ipcw-char-count ipcw-feedback" aria-live="polite"></div>';
        html += '</div>';

        $body.append(html);

        var $area = $('#ipcw-interest-message');
        var $count = $body.find('.ipcw-form .ipcw-char-count span').first();
        var $submit = $body.find('.ipcw-submit');
        var $feedback = $body.find('.ipcw-form .ipcw-feedback').first();

        $area.on('input', function () { $count.text($area.val().length); });

        $submit.on('click', function () {
            var payload = {
                action: 'imovel_parceiro_property_interest',
                nonce: IPCW.nonce,
                property_id: String(p.id || ''),
                message: $area.val()
            };
            $submit.prop('disabled', true).text(IPCW.strings ? IPCW.strings.interest_sending : 'Enviando…');

            $.post(IPCW.ajax_url, payload, function (response) {
                if (response && response.success) {
                    if (lastConfig && lastConfig.interest) { lastConfig.interest.has_open_lead = true; }
                    renderInterestConfirmed(config, response.data && response.data.message);
                } else {
                    var msg = (response && response.data && response.data.message) ? response.data.message : (IPCW.strings ? IPCW.strings.error : 'Erro');
                    $feedback.text(msg).removeClass('is-success').addClass('is-error');
                    $submit.prop('disabled', false).text(IPCW.strings ? IPCW.strings.interest_send : 'Enviar interesse');
                }
            }).fail(function () {
                $feedback.text(IPCW.strings ? IPCW.strings.error : 'Erro').removeClass('is-success').addClass('is-error');
                $submit.prop('disabled', false).text(IPCW.strings ? IPCW.strings.interest_send : 'Enviar interesse');
            });
        });
    }

    function renderInterestConfirmed(config, message) {
        var b = (config && config.broker) || {};
        var html = '<div class="ipcw-confirm">';
        html += '<div class="ipcw-confirm__check"><span aria-hidden="true">' + checkIcon() + '</span></div>';
        html += '<h4>' + esc(IPCW.strings ? IPCW.strings.interest_ok : 'Interesse registrado!') + '</h4>';
        html += '<p>' + esc(message || 'O corretor responsável foi avisado e entrará em contato com você.') + '</p>';
        html += '<p>' + esc(b.name ? ('Corretor responsável: ' + b.name) : '') + '</p>';
        html += '</div>';
        $body.html(html);
    }

    /**
     * Open the native Houzez login modal instead of redirecting to wp-login.php.
     * Falls back to the Houzez login URL when the modal is not present.
     */
    function openHouzezLogin() {
        var selector = IPCW.login_modal || '#login-register-form';
        var modalEl = document.querySelector(selector);

        if (modalEl) {
            closePanel();
            try {
                if (window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    return true;
                }
            } catch (e) {}
            if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
                jQuery(modalEl).modal('show');
                return true;
            }
        }

        if (IPCW.login_url) {
            window.location.href = IPCW.login_url;
        }
        return false;
    }

    function requestAllowedUser() {
        var c = lastConfig;
        return c && c.request && c.request.allowed;
    }

    function renderRequestForm(config) {
        var p = config.property || {};
        var b = config.broker || {};
        var req = config.request || {};
        var message = req.message || (IPCW.partnership_message_default || '');
        var max = req.max_chars || 500;
        var brokerName = b.name || '';
        var commissionLabel = (req.commission && req.commission.label) ? req.commission.label : '';
        var chips = (IPCW.partnership_chips && IPCW.partnership_chips.length) ? IPCW.partnership_chips : [];
        var termsUrl = IPCW.terms_url || '';
        var termsLabel = termsUrl
            ? str('request_terms_prefix', 'Concordo com os') + ' <a href="' + esc(termsUrl) + '" target="_blank" rel="noopener">' + esc(str('request_terms_link', 'Termos da parceria')) + '</a>'
            : esc(str('request_terms', 'Concordo com os Termos da parceria'));

        var html = '<div class="ipcw-form">';
        // Resumo: miniatura + título + comissão explícita.
        html += '<div class="ipcw-req-summary">';
        if (p.thumb) {
            html += '<span class="ipcw-req-summary__thumb"><img src="' + esc(p.thumb) + '" alt="" /></span>';
        }
        html += '<div class="ipcw-req-summary__info">';
        html += '<p class="ipcw-req-summary__title">' + esc(p.title || '') + '</p>';
        if (commissionLabel) {
            html += '<span class="ipcw-commission-chip" aria-label="' + esc(str('partnership_commission_sub', 'Dividir comissão') + ' ' + commissionLabel) + '">' + esc(commissionLabel) + '</span>';
        }
        html += '</div></div>';
        html += '<label for="ipcw-partnership-message">' + esc(fillName(str('request_title', 'Mensagem para {name}'), brokerName)) + '</label>';
        if (chips.length) {
            html += '<div class="ipcw-chips" role="group" aria-label="' + esc(str('partnership_ask', 'Dividir este imóvel?')) + '">';
            for (var ci = 0; ci < chips.length; ci++) {
                html += '<button type="button" class="ipcw-chip" data-chip="' + esc(chips[ci]) + '">' + esc(chips[ci]) + '</button>';
            }
            html += '</div>';
        }
        html += '<textarea id="ipcw-partnership-message" maxlength="' + max + '" placeholder="' + esc(str('request_ph', 'Ex: "Tenho cliente aprovado para visita sábado."')) + '" aria-describedby="ipcw-partnership-count">' + esc(message) + '</textarea>';
        html += '<div class="ipcw-char-count" id="ipcw-partnership-count"><span>0</span>/' + max + '</div>';
        html += '<div class="ipcw-terms">';
        html += '<input type="checkbox" id="ipcw-terms" />';
        html += '<label for="ipcw-terms">' + termsLabel + '</label>';
        html += '</div>';
        html += '<button type="button" class="ipcw-submit" disabled>' + esc(str('request_send', 'Enviar solicitação')) + '</button>';
        html += '<div class="ipcw-char-count ipcw-feedback" aria-live="polite"></div>';
        html += '</div>';

        $body.append(html);

        var $area = $('#ipcw-partnership-message');
        var $count = $body.find('.ipcw-form .ipcw-char-count span').first();
        var $terms = $('#ipcw-terms');
        var $submit = $body.find('.ipcw-submit');
        var $feedback = $body.find('.ipcw-form .ipcw-feedback').first();

        function updateCount() {
            var len = $area.val().length;
            $count.text(len);
        }

        $area.on('input', updateCount);
        $body.find('.ipcw-chip').on('click', function () {
            var preset = $(this).data('chip') || '';
            var current = $area.val().trim();
            $area.val(current ? (current + ' ' + preset) : preset);
            $area.trigger('input');
            $area.trigger('focus');
        });

        function sync() {
            var ok = $terms.is(':checked') && $area.val().trim().length > 0;
            $submit.prop('disabled', !ok);
        }

        $area.on('input', sync);
        $terms.on('change', sync);

        $submit.on('click', function () {
            var payload = {
                action: 'imovel_parceiro_request_partnership',
                nonce: IPCW.nonce,
                property_id: String(config.property.id),
                partner_user_id: String(b.id || ''),
                message: $area.val(),
                partnership_terms: 1
            };
            $submit.prop('disabled', true).text(str('request_sending', 'Enviando…'));

            $.post(IPCW.ajax_url, payload, function (response) {
                if (response && response.success) {
                    renderConfirmed(config, (response && response.data) || {});
                } else {
                    var msg = (response && response.data && response.data.message) ? response.data.message : str('error', 'Erro');
                    if (response && response.data && response.data.code === 'subscription_required') {
                        var plansUrl = (response.data.plans_url) || (config.request && config.request.plans_url) || IPCW.plans_url || '';
                        $feedback.text(msg).removeClass('is-success').addClass('is-error');
                        if (plansUrl) { $feedback.append(' <a href="' + esc(plansUrl) + '" target="_blank" rel="noopener">' + esc(str('plans_cta', 'Ver planos')) + '</a>'); }
                        $submit.prop('disabled', false).text(str('request_send', 'Enviar solicitação'));
                        return;
                    }
                    $feedback.text(msg).removeClass('is-success').addClass('is-error');
                    if (/j[aá] (enviou|possui)/i.test(msg)) {
                        $feedback.text(str('already_requested', 'Você já solicitou este imóvel.'));
                        if (IPCW.partnerships_url) {
                            $feedback.append(' <a href="' + esc(IPCW.partnerships_url) + '">' + esc(str('sent_track', 'Acompanhar no dashboard')) + '</a>');
                        }
                    }
                    $submit.prop('disabled', false).text(str('request_send', 'Enviar solicitação'));
                }
            }).fail(function () {
                $feedback.text(str('error', 'Erro')).removeClass('is-success').addClass('is-error');
                $submit.prop('disabled', false).text(str('request_send', 'Enviar solicitação'));
            });
        });
    }

    function renderConfirmed(config, data) {
        var b = config.broker || {};
        var brokerName = b.name || '';
        var trackUrl = (data && data.detail_url) || IPCW.partnerships_url || '';
        var html = '<div class="ipcw-confirm">';
        html += '<div class="ipcw-confirm__check"><span aria-hidden="true">' + checkIcon() + '</span></div>';
        html += '<h4>' + esc(fillName(str('sent_title', 'Solicitação enviada para {name}!'), brokerName)) + '</h4>';
        html += '<p>' + esc(str('sent_body', 'Avisaremos aqui e por e-mail quando ele responder.')) + '</p>';
        html += '<div class="ipcw-confirm__actions">';
        if (trackUrl) {
            html += '<a class="ipcw-confirm__btn ipcw-confirm__btn--primary" href="' + esc(trackUrl) + '">' + esc(str('sent_track', 'Acompanhar no dashboard')) + '</a>';
        }
        html += '<button type="button" class="ipcw-confirm__btn ipcw-confirm__btn--ghost ipcw-confirm__back">' + esc(str('sent_back', 'Voltar ao imóvel')) + '</button>';
        html += '</div></div>';
        $body.html(html);

        $body.find('.ipcw-confirm__back').on('click', function () {
            closePanel();
        });
    }

    /* ---------------------------------------------------------------------
     * Listing injection (non-invasive): add a per-card "Parceria" button.
     * ------------------------------------------------------------------ */

    function propertyAnchorBase() {
        return (IPCW.property_base || '/property/');
    }

    function maybeInjectListingButtons() {
        if (IPCW.is_single || !IPCW.is_listing) {
            return;
        }
        if (IPCW.is_client) {
            return;
        }
        setTimeout(function () {
            var injected = 0;
            $('a[href*="' + propertyAnchorBase() + '"]').each(function () {
                var $a = $(this);
                if ($a.closest('#ipcw-root').length) { return; }
                var href = $a.attr('href') || '';
                if (!href) { return; }
                if ($a.closest('.ipcw-card-btn').length) { return; }
                if ($a.siblings('.ipcw-card-btn').length) { return; }
                if ($a.closest('.ipcw-injected').length) { return; }

                var $card = $a.closest('.item-wrap, .property-listing-wrap, .property-item-wrap, .houzez-property-item, .property-card, article, .listing-card').first();
                var $target = $card.length ? $card : $a.parent();

                if ($target.find('.ipcw-injected').length) { return; }

                var $btn = $('<button type="button" class="ipcw-card-btn ipcw-injected">&#129309; <span>Parceria</span></button>');
                $btn.data('property-permalink', $a.attr('href'));
                $btn.data('property-title', $a.text().trim());

                $target.append($btn);
                injected++;
            });

            $(document).on('click', '.ipcw-card-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $this = $(this);
                var permalink = $this.data('property-permalink') || '';
                if (!permalink) { return; }
                $this.prop('disabled', true).text('Carregando…');
                $.get(IPCW.ajax_url, {
                    action: 'imovel_parceiro_widget_context',
                    nonce: IPCW.nonce,
                    property_permalink: permalink
                }, function (response) {
                    $this.prop('disabled', false).html('&#129309; <span>Parceria</span>');
                    if (response && response.success && response.data && response.data.config) {
                        openPanel(response.data.config);
                    }
                }).fail(function () {
                    $this.prop('disabled', false).html('&#129309; <span>Parceria</span>');
                });
            });
        }, 800);
    }

    $(function () { init(); });
})(jQuery);

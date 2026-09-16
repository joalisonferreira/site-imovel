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
            rejected: 'is-lost'
        };
        return map[status] || '';
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
    function lockIcon() { return '&#128274;'; }
    function checkIcon() { return '&#10003;'; }

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

        var brokerAvatar = b.avatar
            ? 'src="' + esc(b.avatar) + '" alt="' + esc(b.name) + '"'
            : 'class="ipcw-broker__avatar"';

        var html = '';
        html += '<div class="ipcw-broker">';
        if (b.avatar) {
            html += '<img ' + brokerAvatar + ' />';
        } else {
            html += '<span ' + brokerAvatar + '>??</span>';
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

        // Partnership action. Hidden for the property owner (cannot partner with self).
        if (!req.is_owner) {
            if (ps.exists) {
            var badgeCls = statusBadgeClass(ps.status);
            html += '<div class="ipcw-btn ipcw-btn--partnership">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">' + (IPCW.strings ? IPCW.strings.partnership : 'Parceria') + ' <span class="ipcw-status-badge ' + esc(badgeCls) + '">' + esc(ps.label || '') + '</span></span>';
            html += '<span class="ipcw-btn__sub">';
            if (ps.detail_url) {
                html += '<a href="' + esc(ps.detail_url) + '">' + (IPCW.strings ? IPCW.strings.open_partnership : 'Abrir parceria') + '</a>';
            }
            html += '</span>';
            html += '</span></div>';
        } else if (req.allowed) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">Solicitar parceria</span>';
            html += '<span class="ipcw-btn__sub">Trabalhar este imóvel em conjunto</span>';
            html += '</span></button>';
        } else if (req.needs_subscription) {
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn ipcw-needs-subscription">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">Solicitar parceria</span>';
            html += '<span class="ipcw-btn__sub">' + esc(IPCW.strings ? IPCW.strings.needs_subscription : 'Para solicitar uma parceria, você precisa ter um plano ativo.') + '</span>';
            html += '</span></button>';
        } else {
            var sub = 'Trabalhar este imóvel em conjunto';
            if (!requestAllowedUser()) {
                sub = (IPCW.strings ? IPCW.strings.login_required : 'Faça login para solicitar uma parceria.');
            }
            html += '<button type="button" class="ipcw-btn ipcw-btn--partnership ipcw-request-btn ipcw-needs-login">';
            html += '<span class="ipcw-btn__icon" aria-hidden="true">' + handshakeIcon() + '</span>';
            html += '<span class="ipcw-btn__text">';
            html += '<span class="ipcw-btn__title">Solicitar parceria</span>';
            html += '<span class="ipcw-btn__sub">' + esc(sub) + '</span>';
            html += '</span></button>';
        }
        }

        html += '</div>';

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

        var html = '<div class="ipcw-form">';
        html += '<label for="ipcw-partnership-message">Mensagem</label>';
        html += '<textarea id="ipcw-partnership-message" maxlength="' + max + '">' + esc(message) + '</textarea>';
        html += '<div class="ipcw-char-count"><span>0</span>/' + max + '</div>';
        html += '<div class="ipcw-terms">';
        html += '<input type="checkbox" id="ipcw-terms" />';
        html += '<label for="ipcw-terms">Li e concordo com os termos da parceria.</label>';
        html += '</div>';
        html += '<button type="button" class="ipcw-submit" disabled>Enviar solicitação</button>';
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
            $submit.prop('disabled', true).text('Enviando…');

            $.post(IPCW.ajax_url, payload, function (response) {
                if (response && response.success) {
                    renderConfirmed(config);
                } else {
                    var msg = (response && response.data && response.data.message) ? response.data.message : (IPCW.strings ? IPCW.strings.error : 'Erro');
                    if (response && response.data && response.data.code === 'subscription_required') {
                        var plansUrl = (response.data.plans_url) || (config.request && config.request.plans_url) || IPCW.plans_url || '';
                        $feedback.text(msg).removeClass('is-success').addClass('is-error');
                        if (plansUrl) { $feedback.append(' <a href="' + esc(plansUrl) + '" target="_blank" rel="noopener">' + (IPCW.strings ? IPCW.strings.view_plans : 'Ver planos') + '</a>'); }
                        $submit.prop('disabled', false).text('Enviar solicitação');
                        return;
                    }
                    $feedback.text(msg).removeClass('is-success').addClass('is-error');
                    $submit.prop('disabled', false).text('Enviar solicitação');
                }
            }).fail(function () {
                $feedback.text(IPCW.strings ? IPCW.strings.error : 'Erro').removeClass('is-success').addClass('is-error');
                $submit.prop('disabled', false).text('Enviar solicitação');
            });
        });
    }

    function renderConfirmed(config) {
        var b = config.broker || {};
        var html = '<div class="ipcw-confirm">';
        html += '<div class="ipcw-confirm__check"><span aria-hidden="true">' + checkIcon() + '</span></div>';
        html += '<h4>' + (IPCW.strings ? IPCW.strings.sent : 'Solicitação enviada!') + '</h4>';
        html += '<p>Sua solicitação foi enviada para ' + esc(b.name || 'o corretor') + '.</p>';
        html += '<p>Aguarde a aprovação do corretor responsável.</p>';
        html += '<p class="ipcw-confirm__redirect">Redirecionando para suas parcerias…</p>';
        html += '</div>';
        $body.html(html);

        if (IPCW.partnerships_url) {
            setTimeout(function () { window.location.href = IPCW.partnerships_url; }, 1500);
        }
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

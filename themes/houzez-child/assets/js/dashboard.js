/* Houzez Child — Dashboard Premium
 * Sidebar mobile (hamburger + overlay) e ajustes de campo de navegação.
 */
(function () {
  'use strict';

  function isMobile() {
    return window.innerWidth < 1024;
  }

  function openSidebar() {
    document.body.classList.add('ipc-sidebar-open');
  }

  function closeSidebar() {
    document.body.classList.remove('ipc-sidebar-open');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var menuBtn = document.querySelector('.menu-btn');
    var closeBtn = document.querySelector('.crose-btn');
    var overlay = document.querySelector('.ipc-sidebar-overlay');

    if (menuBtn) {
      menuBtn.addEventListener('click', function (event) {
        if (isMobile()) {
          event.preventDefault();
          openSidebar();
        }
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        closeSidebar();
      });
    }

    if (overlay) {
      overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keyup', function (event) {
      if (event.key === 'Escape') {
        closeSidebar();
      }
    });

    window.addEventListener('resize', function () {
      if (!isMobile()) {
        closeSidebar();
      }
    });

    document.addEventListener('click', function (event) {
      var viaSidebarLink = event.target.closest && event.target.closest('.dashboard-sidebar a');
      if (viaSidebarLink && isMobile()) {
        closeSidebar();
      }
    });

    initWatermarkPreview();
    initNotificationPush();
    initNotificationDeletes();
    initAuditDelete();
    initResponsiveTables(document);

    document.addEventListener('shown.bs.modal', function (event) {
      initResponsiveTables(event.target);
    });
  });

  function initResponsiveTables(scope) {
    var root = scope || document;
    var tables = root.querySelectorAll('table.ipc-table');

    Array.prototype.forEach.call(tables, function (table) {
      var headers = table.querySelectorAll('thead th');
      if (!headers.length) {
        return;
      }

      var labels = [];
      Array.prototype.forEach.call(headers, function (th) {
        labels.push((th.textContent || '').trim());
      });

      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
        Array.prototype.forEach.call(row.children, function (cell) {
          if (cell.tagName !== 'TD' || cell.hasAttribute('colspan')) {
            return;
          }
          var label = labels[cell.cellIndex];
          if (label) {
            cell.setAttribute('data-label', label);
          }
        });
      });
    });
  }

  function initAuditDelete() {
    document.querySelectorAll('.ipc-audit-delete').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        if (btn.getAttribute('data-requires-export') === '1') {
          event.preventDefault();
          toast('Exporte os logs para Excel antes de excluí-los.', 'error');
          return;
        }
        if (!window.confirm('Excluir TODOS os logs de auditoria? Esta ação não pode ser desfeita.')) {
          event.preventDefault();
        }
      });
    });
  }

  var IPC_TRASH_SVG =
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
    '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>' +
    '<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';

  function initNotificationDeletes() {
    if (typeof window.ipcNotifications === 'undefined' || !window.ipcNotifications.ajax_url) {
      return;
    }

    var deleting = false;

    function postDelete(payload) {
      var formData = new FormData();
      formData.append('action', 'imovel_parceiro_notifications_delete');
      formData.append('nonce', window.ipcNotifications.nonce);
      Object.keys(payload).forEach(function (key) {
        formData.append(key, String(payload[key]));
      });
      return fetch(window.ipcNotifications.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      }).then(function (response) {
        return response.json();
      });
    }

    function updateUnreadCount(count) {
      var unread = Math.max(0, parseInt(count, 10) || 0);
      document.querySelectorAll('.ipc-notifications-badge').forEach(function (el) {
        el.style.display = unread > 0 ? 'flex' : 'none';
        el.textContent = unread > 99 ? '99+' : String(unread);
      });
      document.querySelectorAll('.ipc-notifications-toggle').forEach(function (el) {
        el.classList.toggle('has-unread', unread > 0);
      });
    }

    function attachDeleteButton(btn) {
      if (btn.__ipcDeleteAttached) {
        return;
      }
      btn.__ipcDeleteAttached = true;

      btn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (deleting) {
          return;
        }

        var notificationId = parseInt(btn.getAttribute('data-notification-id'), 10) || 0;
        if (!notificationId) {
          return;
        }

        if (!window.confirm('Excluir esta notificação?')) {
          return;
        }

        deleting = true;
        postDelete({ notification_id: notificationId })
          .then(function (result) {
            if (!(result && result.success)) {
              toast((result && result.data && result.data.message) || 'Falha ao excluir a notificação.', 'error');
              return;
            }

            var item = btn.closest('.ipc-notification-item') || btn.closest('.ipc-notif-row');
            var host = (item && item.closest('li')) || item;

            if (host && host.parentNode) {
              if (btn.closest('.ipc-notifications-dropdown-panel')) {
                host.parentNode.removeChild(host);
                updateUnreadCount(result.data && result.data.unread_count);
                toast('Notificação excluída.', 'success');
              } else if (item && item.classList.contains('ipc-notif-row')) {
                host.parentNode.removeChild(host);
                updateUnreadCount(result.data && result.data.unread_count);
                toast('Notificação excluída.', 'success');
              } else {
                window.location.reload();
              }
            }
          })
          .catch(function () {
            toast('Erro de comunicação com o servidor.', 'error');
          })
          .finally(function () {
            deleting = false;
          });
      });
    }

    function injectDropdownDeletes() {
      var menu = document.querySelector('.ipc-notifications-dropdown');
      if (!menu) {
        return;
      }
      menu.querySelectorAll('.ipc-notification-item').forEach(function (item) {
        if (item.querySelector('.ipc-notification-delete')) {
          return;
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ipc-notification-delete ipc-notification-delete--small';
        btn.setAttribute('data-notification-id', item.getAttribute('data-notification-id') || '');
        btn.setAttribute('aria-label', 'Excluir');
        btn.innerHTML = IPC_TRASH_SVG;
        item.appendChild(btn);
        attachDeleteButton(btn);
      });
    }

    var dropdownMenu = document.querySelector('.ipc-notifications-dropdown');
    if (dropdownMenu && window.MutationObserver) {
      new MutationObserver(injectDropdownDeletes).observe(dropdownMenu, { childList: true, subtree: true });
    }

    document.querySelectorAll('.ipc-notification-delete').forEach(attachDeleteButton);
    injectDropdownDeletes();

    document.querySelectorAll('.ipc-notifications-delete-all').forEach(function (btn) {
      if (btn.__ipcDeleteAllAttached) {
        return;
      }
      btn.__ipcDeleteAllAttached = true;

      btn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (deleting) {
          return;
        }

        if (!window.confirm('Excluir TODAS as notificações? Esta ação não pode ser desfeita.')) {
          return;
        }

        deleting = true;
        postDelete({ mode: 'all' })
          .then(function (result) {
            if (!(result && result.success)) {
              toast((result && result.data && result.data.message) || 'Falha ao excluir as notificações.', 'error');
              return;
            }

            if (document.querySelector('.ipc-notif-panel__body') || document.querySelector('.ipc-notifs-list')) {
              window.location.reload();
              return;
            }

            var menu = document.querySelector('.ipc-notifications-dropdown');
            if (menu) {
              menu.innerHTML =
                '<li><span class="dropdown-item-text text-muted" style="display:block;text-align:center;padding:10px;">Sem notificações recentes.</span></li>';
            }
            updateUnreadCount(0);
            toast('Todas as notificações foram excluídas.', 'success');
          })
          .catch(function () {
            toast('Erro de comunicação com o servidor.', 'error');
          })
          .finally(function () {
            deleting = false;
          });
      });
    });
  }

  function ipcToast(message, type) {
    type = type || 'info';
    var toast = document.createElement('div');
    toast.className = 'ipc-toast ipc-toast--' + (type || '');
    toast.setAttribute('role', 'status');
    toast.textContent = String(message || '');
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add('ipc-toast--show');
    });
    setTimeout(function () {
      toast.classList.remove('ipc-toast--show');
      setTimeout(function () {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 250);
    }, 5200);
  }

  /* ==========================================================================
     Página de notificações — "marcar todas como lidas" reflete na lista
     e clique no card marca a notificação como lida.
     ========================================================================== */
  (function () {
    'use strict';

    function list() {
      return document.querySelector('.ipc-notif-panel__body');
    }

    // Reflete no DOM que todas as notificações foram lidas.
    function markAllReadDom() {
      var rows = document.querySelectorAll('.ipc-notif-row.is-unread');
      Array.prototype.forEach.call(rows, function (row) {
        row.classList.remove('is-unread');
        row.classList.add('is-read');
        var badge = row.querySelector('.ipc-notif-new');
        if (badge && badge.parentNode) {
          badge.parentNode.removeChild(badge);
        }
      });

      var stat = document.querySelector('.ipc-notif-stat.is-alert .ipc-notif-stat__value');
      if (stat) {
        stat.textContent = '0';
      }

      var alertStat = document.querySelector('.ipc-notif-stat.is-alert');
      if (alertStat) {
        alertStat.classList.remove('is-alert');
      }

      var badgeTop = document.querySelector('.ipc-notifications-badge');
      if (badgeTop) {
        badgeTop.style.display = 'none';
        badgeTop.textContent = '0';
      }

      var toggle = document.querySelector('.ipc-notifications-toggle');
      if (toggle) {
        toggle.classList.remove('has-unread');
      }
    }

    // Reflete no DOM que uma notificação foi lida.
    function markRowAsRead(row) {
      if (!row || !row.classList.contains('is-unread')) {
        return;
      }

      row.classList.remove('is-unread');
      row.classList.add('is-read');

      var badge = row.querySelector('.ipc-notif-new');
      if (badge && badge.parentNode) {
        badge.parentNode.removeChild(badge);
      }

      var stat = document.querySelector('.ipc-notif-stat.is-alert .ipc-notif-stat__value');
      if (stat) {
        var current = parseInt(stat.textContent, 10) || 0;
        stat.textContent = String(Math.max(0, current - 1));
      }
    }

    function markAllRead() {
      var btn = document.querySelector('.ipc-notif-mark-all');
      if (!btn || !window.ipcNotifications || !window.ipcNotifications.ajax_url) {
        return;
      }

      btn.disabled = true;

      var formData = new FormData();
      formData.append('action', 'imovel_parceiro_notifications_mark_all_read');
      formData.append('nonce', window.ipcNotifications.nonce);

      fetch(window.ipcNotifications.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          if (!(result && result.success)) {
            btn.disabled = false;
            ipcToast((result && result.data && result.data.message) || 'Não foi possível marcar as notificações como lidas.', 'error');
            return;
          }

          markAllReadDom();
          ipcToast('Todas as notificações foram marcadas como lidas.', 'success');
        })
        .catch(function () {
          btn.disabled = false;
          ipcToast('Erro de comunicação com o servidor.', 'error');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
      document.addEventListener('click', function (event) {
        var btn = event.target.closest && event.target.closest('.ipc-notif-mark-all');
        if (btn) {
          event.preventDefault();
          event.stopPropagation();
          markAllRead();
          return;
        }

        if (!list()) {
          return;
        }

        // "Ler mensagem completa": expande o texto truncado do card.
        var more = event.target.closest && event.target.closest('[data-ipc-notif-more]');
        if (more) {
          event.preventDefault();
          event.stopPropagation();
          var row = more.closest('.ipc-notif-row');
          if (row) {
            var expanded = row.classList.toggle('is-expanded');
            more.textContent = expanded ? 'Mostrar menos' : 'Ler mensagem completa';
          }
          return;
        }

        // Clique no corpo do card (fora do link, da lixeira e do botão) navega.
        var card = event.target.closest && event.target.closest('.ipc-notif-row');
        if (!card) {
          return;
        }

        // Clique no título: o plugin (notifications.js) marca como lida e navega.
        // Aqui só refletimos o estado "lida" no card.
        if (event.target.closest('.ipc-notif-row__link')) {
          markRowAsRead(card);
          return;
        }

        if (event.target.closest('a') || event.target.closest('.ipc-notification-delete')) {
          return;
        }

        var link = card.querySelector('.ipc-notif-row__link');
        if (link) {
          link.click();
          return;
        }

        var href = card.getAttribute('data-href');
        if (href) {
          window.location.href = href;
        }
      });

      // O dropdown do sino (plugin) também marca tudo como lido: refletiu aqui.
      document.addEventListener('ipc:notifications-marked-all', function () {
        markAllReadDom();
      });

      // Mostra o botão "Ler mensagem completa" só quando o texto foi truncado.
      var texts = document.querySelectorAll('[data-ipc-notif-text]');
      Array.prototype.forEach.call(texts, function (text) {
        var row = text.closest ? text.closest('.ipc-notif-row') : null;
        if (!row) {
          return;
        }

        row.classList.add('is-measuring');
        var fullHeight = text.scrollHeight;
        row.classList.remove('is-measuring');
        var clampedHeight = text.offsetHeight;

        if (fullHeight - clampedHeight > 4) {
          var more = text.parentNode.querySelector('[data-ipc-notif-more]');
          if (more) {
            more.hidden = false;
          }
        }
      });
    });
  })();


  function base64UrlToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function initNotificationPush() {
    document.addEventListener(
      'click',
      function (event) {
        var btn = event.target.closest && event.target.closest('.ipc-notifications-enable-push, .ipc-notif-enable-push');
        if (!btn) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (btn.getAttribute('data-busy') === '1') {
          return;
        }

        if (typeof window.ipcNotifications === 'undefined' || !window.ipcNotifications.ajax_url) {
          toast('Serviço de notificações indisponível. Recarregue a página.', 'error');
          return;
        }

        if (!('Notification' in window)) {
          toast('Seu navegador não suporta notificações.', 'error');
          return;
        }

        if (!window.isSecureContext) {
          toast('Ative HTTPS (ou use localhost) para habilitar notificações.', 'error');
          return;
        }

        if (!('PushManager' in window) || !('serviceWorker' in navigator)) {
          toast('Push não suportado neste navegador.', 'error');
          return;
        }

        if (Notification.permission === 'denied') {
          toast('Permissão bloqueada. Libere as notificações no ícone de cadeado da barra de endereço.', 'error');
          return;
        }

        btn.setAttribute('data-busy', '1');
        toast('Verificando os serviços de notificação do navegador…', 'info');

        navigator.serviceWorker
          .register(window.ipcNotifications.service_worker_url)
          .then(function (registration) {
            return Notification.requestPermission().then(function (permission) {
              if (permission !== 'granted') {
                toast('Permissão negada para notificações.', 'error');
                throw new Error('permission-denied');
              }

              return registration.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                  return existing;
                }

                if (!window.ipcNotifications.vapid_public_key) {
                  toast('Configuração de push (VAPID) pendente no servidor. Tente novamente em instantes.', 'error');
                  throw new Error('vapid-missing');
                }

                return registration.pushManager.subscribe({
                  userVisibleOnly: true,
                  applicationServerKey: base64UrlToUint8Array(window.ipcNotifications.vapid_public_key)
                });
              });
            });
          })
          .then(function (subscription) {
            var formData = new FormData();
            formData.append('action', 'imovel_parceiro_notifications_save_subscription');
            formData.append('nonce', window.ipcNotifications.nonce);
            formData.append('subscription', JSON.stringify(subscription.toJSON ? subscription.toJSON() : subscription));
            formData.append('device_label', navigator.userAgent || '');

            return fetch(window.ipcNotifications.ajax_url, {
              method: 'POST',
              body: formData,
              credentials: 'same-origin'
            }).then(function (response) {
              return response.json();
            }).then(function (result) {
              if (result && result.success) {
                toast('Notificações do navegador ativadas com sucesso.', 'success');
                var btnLabel = btn.querySelector('span');
                if (btnLabel) {
                  btnLabel.textContent = 'Ativado';
                } else {
                  btn.textContent = 'Ativado';
                }
                btn.classList.add('is-activated');
                btn.setAttribute('aria-pressed', 'true');
                return;
              }
              toast((result && result.data && result.data.message) || 'Falha ao salvar a assinatura de push.', 'error');
            });
          })
          .catch(function (err) {
            if (err && err.message === 'permission-denied') {
              return;
            }
            if (err && err.message === 'vapid-missing') {
              return;
            }
            toast('Não foi possível configurar o push neste dispositivo.', 'error');
          })
          .finally(function () {
            btn.setAttribute('data-busy', '0');
          });
      },
      true
    );
  }

  function initWatermarkPreview() {
    var mark = document.querySelector('[data-ipc-wm-mark]');
    if (!mark) {
      return;
    }

    var opacityInput = document.getElementById('imovel_watermark_opacity');
    var sizeInput = document.getElementById('imovel_watermark_size');
    var positionRadios = document.querySelectorAll('input[name="imovel_watermark_position"]');
    var opacityValue = document.querySelector('[data-ipc-wm-opacity-value]');
    var sizeValue = document.querySelector('[data-ipc-wm-size-value]');
    var pill = document.querySelector('.ipc-wm-preview__pill');

    function apply(opacity, size) {
      opacity = Math.max(5, Math.min(100, parseInt(opacity, 10) || 60));
      size = Math.max(5, Math.min(80, parseInt(size, 10) || 20));

      mark.style.setProperty('--ipc-wm-opacity', String(opacity));
      mark.style.setProperty('--ipc-wm-size', size + '%');

      if (opacityValue) {
        opacityValue.textContent = String(opacity);
      }
      if (sizeValue) {
        sizeValue.textContent = String(size);
      }
      if (pill) {
        pill.textContent = 'Tamanho ' + size + '% • Opacidade ' + opacity + '%';
      }
    }

    if (opacityInput) {
      opacityInput.addEventListener('input', function () {
        apply(opacityInput.value, sizeInput ? sizeInput.value : mark.dataset.size);
      });
    }
    if (sizeInput) {
      sizeInput.addEventListener('input', function () {
        apply(opacityInput ? opacityInput.value : mark.dataset.opacity, sizeInput.value);
      });
    }

    positionRadios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        mark.setAttribute('data-position', radio.value);
      });
    });

    apply(mark.dataset.opacity, mark.dataset.size);
  }
})();

/* ==========================================================================
   Imóvel Parceiro - Exclusão de imóvel pelo proprietário.
   Popup com motivo opcional; sem histórico comercial exclui direto (lixeira),
   com histórico vira solicitação para análise. Tudo com log em auditoria.
   ========================================================================== */
(function () {
  'use strict';

  function core() {
    return window.imovelParceiroCore || {};
  }

  function toast(message, type) {
    if (typeof window.ipcToast === 'function') {
      window.ipcToast(message, type);
      return;
    }
    if (typeof ipcToast === 'function') {
      try {
        toast(message, type);
        return;
      } catch (e) {
        /* cai para o fallback abaixo */
      }
    }
    var el = document.createElement('div');
    el.className = 'ipc-toast ipc-toast--' + (type || '');
    el.textContent = message;
    document.body.appendChild(el);
    window.setTimeout(function () {
      el.classList.add('ipc-toast--show');
    }, 30);
    window.setTimeout(function () {
      if (el.parentElement) {
        el.parentElement.removeChild(el);
      }
    }, 4000);
  }

  function openDeleteModal(propertyId, propertyTitle) {
    var modalEl = document.getElementById('ipc-owner-delete-modal');
    if (!modalEl) {
      return;
    }
    var idInput = modalEl.querySelector('#ipc-owner-delete-id');
    var reasonInput = modalEl.querySelector('#ipc-owner-delete-reason');
    var text = modalEl.querySelector('[data-ipc-owner-delete-text]');
    if (idInput) {
      idInput.value = propertyId;
    }
    if (reasonInput) {
      reasonInput.value = '';
    }
    if (text && propertyTitle) {
      text.textContent = 'Tem certeza de que deseja excluir "' + propertyTitle + '"? Ele será movido para a lixeira.';
    }
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function closeDeleteModal() {
    var modalEl = document.getElementById('ipc-owner-delete-modal');
    if (!modalEl) {
      return;
    }
    if (window.bootstrap && window.bootstrap.Modal) {
      var instance = window.bootstrap.Modal.getInstance(modalEl);
      if (instance) {
        instance.hide();
      }
    }
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target && event.target.closest ? event.target.closest('.imovel-owner-delete') : null;
    if (trigger) {
      event.preventDefault();
      openDeleteModal(
        trigger.getAttribute('data-property-id'),
        trigger.getAttribute('data-property-title')
      );
      return;
    }

    var confirmBtn = event.target && event.target.closest ? event.target.closest('[data-ipc-owner-delete-confirm]') : null;
    if (!confirmBtn) {
      return;
    }
    var modalEl = document.getElementById('ipc-owner-delete-modal');
    var propertyId = modalEl ? (modalEl.querySelector('#ipc-owner-delete-id') || {}).value : '';
    var reasonEl = modalEl ? modalEl.querySelector('#ipc-owner-delete-reason') : null;
    if (!propertyId || !core().ajax_url || !core().nonce) {
      return;
    }

    confirmBtn.setAttribute('disabled', 'disabled');
    var body = new URLSearchParams({
      action: 'imovel_parceiro_owner_delete_property',
      nonce: core().nonce,
      property_id: propertyId,
      reason: reasonEl ? reasonEl.value : ''
    });

    window.fetch(core().ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    }).then(function (json) {
      confirmBtn.removeAttribute('disabled');
      if (json && json.success) {
        closeDeleteModal();
        var row = document.querySelector('.imovel-owner-delete[data-property-id="' + propertyId + '"]');
        var tr = row && row.closest ? row.closest('tr') : null;
        if (tr) {
          tr.parentElement.removeChild(tr);
        }
        toast((json.data && json.data.message) || 'Imóvel excluído com sucesso.', 'success');
      } else {
        toast((json && json.data && json.data.message) || 'Não foi possível excluir o imóvel.', 'error');
      }
    }).catch(function () {
      confirmBtn.removeAttribute('disabled');
      toast('Erro de conexão. Tente novamente.', 'error');
    });
  });
})();

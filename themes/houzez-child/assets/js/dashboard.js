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
  });

  function initAuditDelete() {
    document.querySelectorAll('.ipc-audit-delete').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        if (btn.getAttribute('data-requires-export') === '1') {
          event.preventDefault();
          ipcToast('Exporte os logs para Excel antes de excluí-los.', 'error');
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
              ipcToast((result && result.data && result.data.message) || 'Falha ao excluir a notificação.', 'error');
              return;
            }

            var item = btn.closest('.ipc-notification-item');
            var host = (item && item.closest('li')) || item;

            if (host && host.parentNode) {
              if (btn.closest('.ipc-notifications-dropdown-panel')) {
                host.parentNode.removeChild(host);
                updateUnreadCount(result.data && result.data.unread_count);
                ipcToast('Notificação excluída.', 'success');
              } else {
                window.location.reload();
              }
            }
          })
          .catch(function () {
            ipcToast('Erro de comunicação com o servidor.', 'error');
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
              ipcToast((result && result.data && result.data.message) || 'Falha ao excluir as notificações.', 'error');
              return;
            }

            if (document.querySelector('.ipc-notifs-list')) {
              window.location.reload();
              return;
            }

            var menu = document.querySelector('.ipc-notifications-dropdown');
            if (menu) {
              menu.innerHTML =
                '<li><span class="dropdown-item-text text-muted" style="display:block;text-align:center;padding:10px;">Sem notificações recentes.</span></li>';
            }
            updateUnreadCount(0);
            ipcToast('Todas as notificações foram excluídas.', 'success');
          })
          .catch(function () {
            ipcToast('Erro de comunicação com o servidor.', 'error');
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
    toast.className = 'ipc-toast ipc-toast--' + type;
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
        var btn = event.target.closest && event.target.closest('.ipc-notifications-enable-push');
        if (!btn) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (btn.getAttribute('data-busy') === '1') {
          return;
        }

        if (typeof window.ipcNotifications === 'undefined' || !window.ipcNotifications.ajax_url) {
          ipcToast('Serviço de notificações indisponível. Recarregue a página.', 'error');
          return;
        }

        if (!('Notification' in window)) {
          ipcToast('Seu navegador não suporta notificações.', 'error');
          return;
        }

        if (!window.isSecureContext) {
          ipcToast('Ative HTTPS (ou use localhost) para habilitar notificações.', 'error');
          return;
        }

        if (!('PushManager' in window) || !('serviceWorker' in navigator)) {
          ipcToast('Push não suportado neste navegador.', 'error');
          return;
        }

        if (Notification.permission === 'denied') {
          ipcToast('Permissão bloqueada. Libere as notificações no ícone de cadeado da barra de endereço.', 'error');
          return;
        }

        btn.setAttribute('data-busy', '1');
        ipcToast('Verificando os serviços de notificação do navegador…', 'info');

        navigator.serviceWorker
          .register(window.ipcNotifications.service_worker_url)
          .then(function (registration) {
            return Notification.requestPermission().then(function (permission) {
              if (permission !== 'granted') {
                ipcToast('Permissão negada para notificações.', 'error');
                throw new Error('permission-denied');
              }

              return registration.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                  return existing;
                }

                if (!window.ipcNotifications.vapid_public_key) {
                  ipcToast('Configuração de push (VAPID) pendente no servidor. Tente novamente em instantes.', 'error');
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
                ipcToast('Notificações do navegador ativadas com sucesso.', 'success');
                btn.textContent = 'Ativado';
                btn.classList.add('is-activated');
                btn.setAttribute('aria-pressed', 'true');
                return;
              }
              ipcToast((result && result.data && result.data.message) || 'Falha ao salvar a assinatura de push.', 'error');
            });
          })
          .catch(function (err) {
            if (err && err.message === 'permission-denied') {
              return;
            }
            if (err && err.message === 'vapid-missing') {
              return;
            }
            ipcToast('Não foi possível configurar o push neste dispositivo.', 'error');
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

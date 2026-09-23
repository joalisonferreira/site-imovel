/* Checkout: controle segmentado PF/PJ espelha o select nativo. */
(function () {
  'use strict';

  function syncToggle() {
    var select = document.getElementById('billing_persontype');
    if (!select) {
      return;
    }
    var value = select.value === '2' ? '2' : '1';
    document.querySelectorAll('.ipc-co-persontype__btn').forEach(function (btn) {
      var active = btn.getAttribute('data-person-type') === value;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function bind() {
    var select = document.getElementById('billing_persontype');
    if (!select || select.dataset.ipcBound) {
      syncToggle();
      return;
    }
    select.dataset.ipcBound = '1';

    document.querySelectorAll('.ipc-co-persontype__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        select.value = btn.getAttribute('data-person-type');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncToggle();
      });
    });

    select.addEventListener('change', syncToggle);
    syncToggle();
  }

  document.addEventListener('DOMContentLoaded', bind);
  document.addEventListener('updated_checkout', bind);
  bind();
})();

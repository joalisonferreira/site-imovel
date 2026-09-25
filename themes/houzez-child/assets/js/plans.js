/* Página de planos — abas de segmento e sanfona do FAQ. */
(function () {
  'use strict';

  function switchPlansTab(audience) {
    var tabAgent = document.getElementById('ipc-tab-agent');
    var tabAgency = document.getElementById('ipc-tab-agency');
    var viewAgent = document.getElementById('ipc-view-agent');
    var viewAgency = document.getElementById('ipc-view-agency');

    if (!tabAgent || !tabAgency || !viewAgent || !viewAgency) {
      return;
    }

    var isAgent = audience !== 'agency';

    tabAgent.classList.toggle('is-active', isAgent);
    tabAgency.classList.toggle('is-active', !isAgent);
    tabAgent.setAttribute('aria-selected', isAgent ? 'true' : 'false');
    tabAgency.setAttribute('aria-selected', !isAgent ? 'true' : 'false');

    viewAgent.hidden = !isAgent;
    viewAgency.hidden = isAgent;

    var shown = isAgent ? viewAgent : viewAgency;
    shown.classList.remove('ipc-plans-view');
    void shown.offsetWidth;
    shown.classList.add('ipc-plans-view');
  }

  function togglePlansFaq(button) {
    var item = button.closest('.ipc-faq-item');
    if (!item) {
      return;
    }

    var content = item.querySelector('.ipc-faq-content');
    var wasOpen = item.classList.contains('is-open');

    document.querySelectorAll('.ipc-faq-item.is-open').forEach(function (other) {
      other.classList.remove('is-open');
      var otherContent = other.querySelector('.ipc-faq-content');
      if (otherContent) {
        otherContent.hidden = true;
      }
      var otherButton = other.querySelector('.ipc-faq-toggle');
      if (otherButton) {
        otherButton.setAttribute('aria-expanded', 'false');
      }
    });

    if (!wasOpen) {
      item.classList.add('is-open');
      if (content) {
        content.hidden = false;
      }
      button.setAttribute('aria-expanded', 'true');
    }
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-ipc-plans-tab]');
    if (tab) {
      switchPlansTab(tab.getAttribute('data-ipc-plans-tab'));
      return;
    }

    var faq = event.target.closest('.ipc-faq-toggle');
    if (faq) {
      togglePlansFaq(faq);
    }
  });

  window.ipcPlansTab = switchPlansTab;
})();

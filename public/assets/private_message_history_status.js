(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const sync = window.ForumPrivateMessageHistorySync;
    if (!sync) return;
    document.querySelectorAll('[data-private-message-history-status]').forEach(function (root) {
      const status = root.querySelector('[data-role="sync-status"]');
      const retry = root.querySelector('[data-action="retry-history-sync"]');
      function render(state) {
        const verified = state.verified ? state.verified + ' older message' + (state.verified === 1 ? '' : 's') + ' verified on this visit. ' : '';
        const descriptions = {
          working: 'Checking older message access…',
          waiting: 'Some older messages are not available on this device yet. You can retry after another device visits.',
          ready: 'History check finished. More history may become available after another device visits.',
          error: 'History check could not finish. Try again; you can continue messaging.',
          'identity-changed': 'Your saved identity changed. Reload this page to check history with that identity.',
        };
        status.textContent = verified + (descriptions[state.state] || descriptions.waiting);
        retry.disabled = state.state === 'identity-changed';
        retry.setAttribute('aria-disabled', String(state.state === 'working' || retry.disabled));
      }
      document.addEventListener('private-message-history-sync-state', event => render(event.detail));
      retry.addEventListener('click', async function () {
        if (retry.getAttribute('aria-disabled') === 'true') return;
        await sync.refresh();
        document.dispatchEvent(new CustomEvent('private-message-history-retry-readers'));
      });
      render(sync.state());
    });
  });
})();

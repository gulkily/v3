(function () {
  'use strict';
  function bind(root) {
    if (!root || root.dataset.seenBound) return;
    root.dataset.seenBound = '1';
    const viewer = root.dataset.viewerUsernameToken, counterpart = root.dataset.counterpartUsernameToken;
    const feedback = root.querySelector('[data-role="read-feedback"]');
    const text = root.querySelector('[data-role="read-status"]');
    const retry = root.querySelector('[data-role="read-retry"]');
    const reopen = root.querySelector('[data-role="read-reopen"]');
    let token = root.querySelector('[data-role="private-message-read-token"]').value;
    let latest = Array.from(root.querySelectorAll('[data-private-message-id]')).at(-1);
    let settled = root.dataset.privateMessageReaderSettled === '1';
    let pending = false, acknowledged = '', failed = '', generation = 0, scheduled, request;
    function invalidate() {
      generation++;
      if (request) request.abort();
      pending = false;
      retry.disabled = false;
      root.dataset.readState = token === acknowledged ? 'acknowledged' : 'waiting';
    }
    function visible() {
      if (document.hidden || !document.hasFocus() || !latest || !latest.isConnected || root.dataset.historyLoading === '1') return false;
      const presentation = window.ForumPrivateMessageConversation.presentationFor(root, latest);
      const rect = presentation.getBoundingClientRect();
      let bottom = window.visualViewport ? window.visualViewport.height : window.innerHeight;
      const composer = root.querySelector('[data-private-message-composer]');
      if (!root.classList.contains('composer-inline')) bottom = Math.min(bottom, composer.getBoundingClientRect().top);
      return rect.height > 0 && bottom > 0 && rect.bottom > 0 && rect.top < bottom;
    }
    async function acknowledge(explicitRetry) {
      if (!root.isConnected || !window.ForumPrivateMessageUnread.sameIdentity()) return;
      if (pending || !settled || !token || token === acknowledged || (!explicitRetry && (token === failed || !visible()))) return;
      const candidate = token, current = generation;
      pending = true;
      request = new AbortController();
      root.dataset.readState = 'pending';
      retry.disabled = true;
      try {
        const response = await fetch('/api/private_messages/read', {
          method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: request.signal,
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumPrivateMessages', Accept: 'application/json' },
          body: JSON.stringify({ counterpart: counterpart, read_token: candidate }),
        });
        const state = await response.json();
        if (!root.isConnected || current !== generation || !window.ForumPrivateMessageUnread.sameIdentity()) return;
        if (!response.ok || !window.ForumPrivateMessageUnread.acceptsState(state, [counterpart]) || state.viewer !== viewer) {
          const error = new Error(state && state.error || 'Unable to confirm seen status.');
          error.reopen = state && state.reopen === true;
          throw error;
        }
        acknowledged = candidate;
        failed = '';
        feedback.hidden = true;
        root.dataset.readState = 'acknowledged';
        await window.ForumPrivateMessageUnread.refresh();
      } catch (error) {
        if (!root.isConnected || current !== generation || !window.ForumPrivateMessageUnread.sameIdentity()) return;
        failed = candidate;
        feedback.hidden = false;
        text.textContent = error.reopen ? 'Seen status could not be confirmed. Reopen for a fresh read position. Copy your draft first as a precaution.'
          : 'Seen status could not be confirmed. Retry to check it; your messages and draft are unchanged.';
        reopen.hidden = !error.reopen;
        retry.hidden = !!error.reopen;
        root.dataset.readState = 'failed';
      } finally {
        if (current === generation) { pending = false; retry.disabled = false; }
        schedule();
      }
    }
    function schedule() {
      if (scheduled) return;
      scheduled = requestAnimationFrame(function () { scheduled = 0; acknowledge(false); });
    }
    root.addEventListener('private-message-reader-settled', function () { settled = true; schedule(); });
    root.addEventListener('private-message-window-settled', function (event) {
      invalidate();
      token = event.detail.readToken || '';
      latest = Array.from(root.querySelectorAll('[data-private-message-id]')).find(card => card.dataset.privateMessageId === event.detail.latestId);
      settled = true; failed = ''; feedback.hidden = true;
      root.dataset.readState = token === acknowledged ? 'acknowledged' : 'waiting';
      schedule();
    });
    retry.addEventListener('click', function () { acknowledge(true); });
    document.addEventListener('private-message-identity-invalidated', invalidate);
    window.addEventListener('pagehide', invalidate);
    window.addEventListener('pageshow', schedule);
    ['scroll', 'resize', 'focus'].forEach(type => window.addEventListener(type, schedule, { passive: true }));
    document.addEventListener('visibilitychange', schedule);
    if (window.ResizeObserver) new ResizeObserver(schedule).observe(root);
    schedule();
  }
  window.ForumPrivateMessageSeen = { bind: bind };
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('[data-mailbox="conversation"]').forEach(bind); });
})();

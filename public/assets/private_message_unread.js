(function () {
  'use strict';
  let historyEligibility = null;
  let nav, viewer, originalIdentity, generation = 0, scheduled, listening = false;
  function identity() {
    try { return JSON.stringify([localStorage.getItem('forum_pki_public_key'), localStorage.getItem('forum_pki_username')]); }
    catch (error) { return ''; }
  }
  function sameIdentity() { return originalIdentity === identity(); }
  function rows() { return Array.from(document.querySelectorAll('[data-conversation-row]')); }
  function unavailable(message) {
    nav.querySelector('#private-message-unread-status').textContent = message;
    nav.title = message;
    nav.dataset.unreadState = 'unavailable';
    delete nav.dataset.unreadCount;
    rows().forEach(function (row) {
      const marker = row.querySelector('[data-role="unread-indicator"]');
      marker.textContent = '?'; marker.title = message;
      row.classList.remove('is-unread');
    });
    document.querySelectorAll('[data-role="unread-retry"]').forEach(button => { button.hidden = false; });
  }
  // Shared pages carry no viewer; the first valid response binds this page to
  // the session's viewer, and later responses for another viewer are rejected.
  function valid(state, names) {
    return state && state.status === 'ok' && typeof state.viewer === 'string' && state.viewer !== '' && (!viewer || state.viewer === viewer) && Number.isSafeInteger(state.unread_count) && state.unread_count >= 0 &&
      typeof state.revision === 'string' && state.revision && state.conversations &&
      names.every(name => typeof state.conversations[name] === 'boolean');
  }
  async function refresh() {
    if (!nav || !nav.isConnected || window.ForumPrivateMessageAvailability.isDisabled()) return;
    const current = ++generation;
    if (!sameIdentity()) { unavailable('Identity changed. Reload Messages before checking unread status.'); return; }
    const names = Array.from(new Set(rows().map(row => row.dataset.counterpart).filter(Boolean)));
    try {
      let combined, states;
      for (let attempt = 0; attempt < 2; attempt++) {
        combined = null; states = Object.create(null);
        let changed = false;
        for (let index = 0; index < Math.max(names.length, 1); index += 25) {
          const batch = names.slice(index, index + 25);
          const query = new URLSearchParams();
          batch.forEach(name => query.append('counterparts[]', name));
          const response = await window.ForumPrivateMessageAvailability.request('/api/private_messages/unread?' + query, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
          const state = await response.json();
          if (current !== generation || !nav.isConnected) return;
          if (!sameIdentity()) throw new Error('Identity changed. Reload Messages before checking unread status.');
          if (!response.ok || !valid(state, batch)) throw new Error('Unread status unavailable. Retry on Messages.');
          if (combined && combined.revision !== state.revision) { changed = true; break; }
          combined = state;
          Object.assign(states, state.conversations);
        }
        if (!changed) break;
        if (attempt === 1) throw new Error('Unread status changed during refresh. Retry on Messages.');
      }
      if (current !== generation) return;
      if (!viewer) {
        viewer = combined.viewer;
        nav.dataset.viewer = viewer;
        document.dispatchEvent(new CustomEvent('private-message-viewer-known'));
      }
      historyEligibility = typeof combined.has_history === 'boolean' ? combined.has_history : true;
      document.dispatchEvent(new CustomEvent('private-message-history-eligibility'));
      const description = combined.unread_count + ' unread conversation' + (combined.unread_count === 1 ? '' : 's');
      nav.querySelector('#private-message-unread-status').textContent = description;
      nav.title = description;
      nav.dataset.unreadState = 'ready';
      nav.dataset.unreadCount = String(combined.unread_count);
      rows().forEach(function (row) {
        const unread = states[row.dataset.counterpart];
        const marker = row.querySelector('[data-role="unread-indicator"]');
        marker.textContent = unread === true ? 'Unread' : unread === false ? '' : '…';
        marker.title = unread === undefined ? 'Unread status loading' : '';
        row.classList.toggle('is-unread', unread === true);
      });
      document.querySelectorAll('[data-role="unread-retry"]').forEach(button => { button.hidden = true; });
    } catch (error) {
      if (current === generation) unavailable(error.message || 'Unread status unavailable. Retry on Messages.');
    }
  }
  function schedule() {
    if (!nav || window.ForumPrivateMessageAvailability.isDisabled()) return;
    generation++;
    clearTimeout(scheduled);
    scheduled = setTimeout(refresh, 0);
  }
  function stop() {
    generation++;
    clearTimeout(scheduled);
    nav = null;
    historyEligibility = null;
  }
  function start(navElement) {
    if (nav === navElement && nav && nav.isConnected) return;
    stop();
    nav = navElement;
    if (!nav) return;
    // Messages pages name their viewer in their own content; elsewhere the first response does.
    const page = document.querySelector('[data-viewer-username-token], [data-conversation-list][data-viewer]');
    if (!nav.dataset.viewer && page) nav.dataset.viewer = page.dataset.viewerUsernameToken || page.dataset.viewer || '';
    viewer = nav.dataset.viewer;
    originalIdentity = identity();
    if (!listening) {
      listening = true;
      document.querySelectorAll('[data-role="unread-retry"]').forEach(button => button.addEventListener('click', schedule));
      document.addEventListener('private-messaging-disabled', function () {
        if (nav) unavailable(window.ForumPrivateMessageAvailability.message);
        stop();
      });
      document.addEventListener('private-message-rows-changed', schedule);
      window.addEventListener('focus', schedule);
      // A shared page can start without a session; retry once sign-in completes.
      window.addEventListener('forum:authenticated-in-place', schedule);
      window.addEventListener('pageshow', schedule);
      window.addEventListener('pagehide', function () { generation++; clearTimeout(scheduled); });
      window.addEventListener('storage', function (event) {
        if (event.key === null || ['forum_pki_public_key', 'forum_pki_username'].includes(event.key)) {
          generation++;
          document.dispatchEvent(new CustomEvent('private-message-identity-invalidated'));
          schedule();
        }
      });
      document.addEventListener('visibilitychange', function () { if (!document.hidden) schedule(); });
    }
    schedule();
  }
  window.ForumPrivateMessageUnread = { historyEligibility: () => nav && sameIdentity() ? historyEligibility : null, refresh, sameIdentity, acceptsState: valid, start, stop };
  document.addEventListener('DOMContentLoaded', () => start(document.querySelector('[data-private-message-unread]')));
})();

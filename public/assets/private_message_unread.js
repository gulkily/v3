(function () {
  'use strict';
  let nav, viewer, generation = 0, scheduled;
  function rows() { return Array.from(document.querySelectorAll('[data-conversation-row]')); }
  function unavailable(message) {
    nav.querySelector('[data-role="unread-count"]').textContent = '?';
    nav.querySelector('#private-message-unread-status').textContent = message;
    nav.title = message;
    nav.dataset.unreadState = 'unavailable';
    rows().forEach(function (row) {
      const marker = row.querySelector('[data-role="unread-indicator"]');
      marker.textContent = '?'; marker.title = message;
      row.classList.remove('is-unread');
    });
    document.querySelectorAll('[data-role="unread-retry"]').forEach(button => { button.hidden = false; });
  }
  function valid(state, names) {
    return state && state.status === 'ok' && state.viewer === viewer && Number.isSafeInteger(state.unread_count) && state.unread_count >= 0 &&
      typeof state.revision === 'string' && state.revision && state.conversations &&
      names.every(name => typeof state.conversations[name] === 'boolean');
  }
  async function refresh() {
    if (!nav || !nav.isConnected) return;
    const current = ++generation;
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
          const response = await fetch('/api/private_messages/unread?' + query, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
          const state = await response.json();
          if (current !== generation) return;
          if (!response.ok || !valid(state, batch)) throw new Error('Unread status unavailable. Retry on Messages.');
          if (combined && combined.revision !== state.revision) { changed = true; break; }
          combined = state;
          Object.assign(states, state.conversations);
        }
        if (!changed) break;
        if (attempt === 1) throw new Error('Unread status changed during refresh. Retry on Messages.');
      }
      if (current !== generation) return;
      const description = combined.unread_count + ' unread conversation' + (combined.unread_count === 1 ? '' : 's');
      nav.querySelector('[data-role="unread-count"]').textContent = combined.unread_count ? '(' + combined.unread_count + ')' : '';
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
    clearTimeout(scheduled);
    scheduled = setTimeout(refresh, 0);
  }
  window.ForumPrivateMessageUnread = { refresh: refresh };
  document.addEventListener('DOMContentLoaded', function () {
    nav = document.querySelector('[data-private-message-unread]');
    if (!nav) return;
    viewer = nav.dataset.viewer;
    document.querySelectorAll('[data-role="unread-retry"]').forEach(button => button.addEventListener('click', schedule));
    document.addEventListener('private-message-rows-changed', schedule);
    window.addEventListener('focus', schedule);
    window.addEventListener('pageshow', schedule);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) schedule(); });
    schedule();
  });
})();

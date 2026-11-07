(function () {
  'use strict';
  // The Inbox link ships without a count, so every viewer gets the same HTML.
  // For a browser remembered as approved, this sets data-unread-count on the link
  // and its mirrors; CSS draws an outline, which never changes layout.
  let generation = 0, scheduled;
  function approved() { return document.documentElement.getAttribute('data-member') === 'approved'; }
  function link() { return document.querySelector('[data-inbox-unread]'); }
  function targets() {
    return [link(), ...document.querySelectorAll('[data-inbox-unread-mirror]')].filter(Boolean);
  }
  function show(count, description) {
    targets().forEach(function (element) {
      if (count === null) delete element.dataset.unreadCount; else element.dataset.unreadCount = String(count);
      if (description) element.title = description; else element.removeAttribute('title');
    });
    const status = document.getElementById('inbox-unread-status');
    if (status) status.textContent = description || '';
  }
  async function refresh() {
    if (!approved() || !targets().length) return;
    const current = ++generation;
    try {
      const response = await fetch('/api/inbox/unread', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      const state = await response.json();
      if (current !== generation) return;
      if (!response.ok || state.status !== 'ok' || !Number.isSafeInteger(state.unread_count) || state.unread_count < 0) throw new Error();
      show(state.unread_count, state.unread_count === 0 ? 'Nothing new' : state.unread_count + ' unread');
    } catch (error) {
      if (current === generation) show(null, 'Inbox status unavailable');
    }
  }
  function schedule() {
    if (document.hidden) return;
    clearTimeout(scheduled);
    scheduled = setTimeout(refresh, 0);
  }
  document.addEventListener('DOMContentLoaded', schedule);
  window.addEventListener('focus', schedule);
  window.addEventListener('pageshow', schedule);
  window.addEventListener('forum:authenticated-in-place', schedule);
  window.addEventListener('pagehide', function () { generation++; clearTimeout(scheduled); });
  document.addEventListener('visibilitychange', schedule);
  window.ForumInboxUnread = { refresh };
})();

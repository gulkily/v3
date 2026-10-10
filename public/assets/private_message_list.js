(function () {
  "use strict";

  function bind(root) {
    if (!root || root.dataset.listBound === "1") return null;
    root.dataset.listBound = "1";
    const rows = root.querySelector('[data-role="rows"]');
    const template = root.querySelector('[data-role="row-template"]');
    const more = root.querySelector('[data-action="load-more"]');
    const retry = root.querySelector('[data-action="retry-list"]');
    const restart = root.querySelector('[data-action="restart-list"]');
    const status = root.querySelector('[data-role="list-status"]');
    const empty = root.querySelector('[data-role="empty"]');
    const rendered = new Map(Array.from(rows.querySelectorAll('[data-conversation-row]')).map(function (row) {
      return [row.dataset.counterpart, row];
    }));
    let cursor = root.dataset.pageCursor;
    let loading = false;

    function render(message) {
      let row = rendered.get(message.counterpart);
      if (!row) {
        row = template.content.firstElementChild.cloneNode(true);
        rendered.set(message.counterpart, row);
        rows.appendChild(row);
      }
      row.dataset.counterpart = message.counterpart;
      row.dataset.messageId = message.message_id;
      row.querySelector('a').href = '/messages/conversation/' + encodeURIComponent(message.counterpart);
      row.querySelector('[data-role="counterpart"]').textContent = message.counterpart;
      const time = row.querySelector('[data-role="time"]');
      time.dateTime = message.created_at;
      time.textContent = message.created_at;
    }

    async function load() {
      if (loading || cursor === null) return;
      loading = true;
      more.disabled = true;
      retry.hidden = true;
      restart.hidden = true;
      rows.setAttribute('aria-busy', 'true');
      status.textContent = 'Loading conversations…';
      try {
        const response = await fetch('/api/private_messages/conversations?cursor=' + encodeURIComponent(cursor), {
          credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok || !payload || payload.status !== 'ok' || !Array.isArray(payload.conversations)) {
          if (payload && payload.restart) restart.hidden = false;
          throw new Error(String(payload && payload.error || 'Unable to load conversations. Try again.'));
        }
        payload.conversations.forEach(render);
        cursor = payload.next_cursor;
        more.hidden = cursor === null;
        empty.hidden = rendered.size !== 0;
        status.textContent = cursor === null
          ? (rendered.size ? 'All conversations loaded.' : '')
          : rendered.size + ' conversations loaded.';
      } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Unable to load conversations. Try again.';
        retry.hidden = !restart.hidden;
      } finally {
        loading = false;
        more.disabled = false;
        rows.setAttribute('aria-busy', 'false');
      }
    }

    more.addEventListener('click', load);
    retry.addEventListener('click', load);
    const ready = load();
    return { load: load, ready: ready };
  }

  window.ForumPrivateMessageList = { bind: bind };
  document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.querySelectorAll('[data-conversation-list]')).forEach(bind);
  });
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
})();

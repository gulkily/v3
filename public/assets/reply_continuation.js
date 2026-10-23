(function () {
  'use strict';
  const prefix = 'forum-reply-continuation:';
  function save(item) {
    try { localStorage.setItem(prefix + item.id, JSON.stringify(item)); }
    catch { throw new Error('Reply recovery storage is unavailable or full. Keep this page open, free browser storage and retry.'); }
  }
  function load(id) { return JSON.parse(localStorage.getItem(prefix + id) || 'null'); }
  function bind(form, root, api) {
    if (!root.querySelector('[data-inline-reply-compose]')) return null;
    const body = form.querySelector('[name="body"]');
    const threadId = form.querySelector('[name="thread_id"]').value;
    const parentId = form.querySelector('[name="parent_id"]').value;
    const views = new Map();
    function matches(item) { return item?.fields?.thread_id === threadId && item.fields.parent_id === parentId; }
    function render(item) {
      if (views.has(item.id)) { views.get(item.id).update(item); return views.get(item.id); }
      if (item.result && document.getElementById('post-' + item.result.postId)) return null;
      const card = api.card({pendingId:item.id, parentId:item.fields.parent_id, body:item.fields.body, status:'Posting…'});
      card.dataset.replyOperation = item.id;
      const status = card.querySelector('[data-role="pending-reply-status"]');
      status.setAttribute('role', 'status');
      const retry = document.createElement('button');
      retry.type = 'button'; retry.textContent = 'Retry';
      card.append(retry);
      api.insert(root, card);
      let running = false;
      function update(next) {
        item = next;
        card.dataset.replyState = item.state;
        retry.hidden = running || item.state === 'accepted';
        if (item.state === 'accepted') {
          status.textContent = '';
          const link = document.createElement('a');
          link.href = '/posts/' + encodeURIComponent(item.result.postId);
          link.textContent = 'Open reply'; status.append(link);
          const canonical = document.getElementById('post-' + item.result.postId);
          if (canonical && canonical !== card) { card.remove(); }
          card.removeAttribute('data-pending-reply-id');
          card.classList.remove('pending-reply-card');
        } else {
          status.textContent = item.error || (running ? 'Posting…' : 'Not confirmed. Check and retry.');
        }
      }
      async function transmit() {
        // A second tab must reload the winning operation while holding the same lock.
        item = load(item.id) || item;
        if (item.state === 'accepted') { update(item); return; }
        if (item.delivery && await api.confirm(item.delivery)) {
          item.result = {postId:item.delivery.prepared.postId};
        } else {
          if (!item.delivery) {
            item.delivery = await api.prepare(item.fields);
            save(item); // Persist exact target/token before any finalization attempt.
          }
          let result = await api.finalize(item.delivery);
          if (!result.ok) {
            if (await api.confirm(item.delivery)) result = {ok:true, postId:item.delivery.prepared.postId};
            else if (/Prepared post has expired\./i.test(result.rawError || result.error)) {
              // A definitive expiry response under the server write lock rules out
              // a later successful use of this token; confirm absence before renewing.
              item.delivery = null; save(item);
              throw new Error('Reply preparation expired. Retry to prepare it again.');
            } else throw new Error(result.error);
          }
          item.result = result;
        }
        item.state = 'accepted'; item.error = ''; save(item);
        update(item);
      }
      async function deliver() {
        if (running) return;
        running = true; update(item);
        try {
          if (!navigator.locks) throw new Error('Safe reply recovery requires browser locks. Keep this reply and retry in a supported browser.');
          await navigator.locks.request(prefix + item.id, async () => {
            try { await transmit(); }
            catch (error) {
              item.state = 'failed'; item.error = error.message;
              try { save(item); } catch (storageError) { item.error += ' ' + storageError.message; }
            }
          });
        } catch (error) { item.error = error.message; item.state = 'failed'; }
        finally { running = false; update(item); }
      }
      retry.addEventListener('click', () => void deliver());
      const view = {card, update, deliver}; views.set(item.id, view); update(item);
      return view;
    }
    // Restoring never silently submits. The user can check/retry each retained item.
    try {
      Object.keys(localStorage).filter(key => key.startsWith(prefix)).map(key => JSON.parse(localStorage.getItem(key)))
        .filter(matches).sort((a,b) => a.createdAt - b.createdAt).forEach(render);
    } catch { /* The original form remains usable; Send reports storage failure. */ }
    window.addEventListener('storage', event => {
      if (!event.key?.startsWith(prefix) || !event.newValue) return;
      try { const item = JSON.parse(event.newValue); if (matches(item)) render(item); } catch { /* Ignore unrelated malformed storage. */ }
    });
    return {
      send(fields) {
        const item = {id:crypto.randomUUID(), fields, state:'pending', createdAt:Date.now()};
        save(item);
        const view = render(item);
        body.value = '';
        api.clearDraft(form);
        body.dispatchEvent(new Event('input', {bubbles:true}));
        body.focus({preventScroll:true});
        void view.deliver();
      }
    };
  }
  window.ForumReplyContinuation = {bind};
})();

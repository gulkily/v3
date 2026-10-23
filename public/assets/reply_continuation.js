(function () {
  'use strict';
  const prefix = 'forum-reply-continuation:';
  function bind(form, root, api) {
    if (!root.querySelector('[data-inline-reply-compose]')) return null;
    const body = form.querySelector('[name="body"]');
    function save(item) { localStorage.setItem(prefix + item.id, JSON.stringify(item)); }
    function render(item) {
      const card = api.card({pendingId:item.id, parentId:item.fields.parent_id, body:item.fields.body, status:'Posting…'});
      card.dataset.replyOperation = item.id;
      const status = card.querySelector('[data-role="pending-reply-status"]');
      status.setAttribute('role', 'status');
      const retry = document.createElement('button');
      retry.type = 'button'; retry.textContent = 'Retry'; retry.hidden = true;
      card.append(retry);
      api.insert(root, card);
      retry.addEventListener('click', () => void deliver(item));
      async function deliver(item) {
        retry.hidden = true; status.textContent = 'Posting…';
        try {
          if (!item.delivery) {
            item.delivery = await api.prepare(item.fields);
            save(item);
          }
          const result = await api.finalize(item.delivery);
          if (!result.ok) throw new Error(result.error);
          status.textContent = '';
          const link = document.createElement('a');
          link.href = '/posts/' + encodeURIComponent(result.postId);
          link.textContent = 'Open reply'; status.append(link);
          card.removeAttribute('data-pending-reply-id');
          card.classList.remove('pending-reply-card');
          card.dataset.replyState = 'accepted';
          localStorage.removeItem(prefix + item.id);
        } catch (error) {
          status.textContent = error.message; retry.hidden = false;
          card.dataset.replyState = 'failed';
        }
      }
      return {card, deliver:() => deliver(item)};
    }
    return {
      send(fields) {
        const item = {id:prefix + crypto.randomUUID(), fields, createdAt:Date.now()};
        save(item); // Do not discard the only recoverable copy if storage fails.
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

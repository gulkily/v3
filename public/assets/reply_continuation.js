(function () {
  'use strict';
  const prefix = 'forum-reply-continuation:';
  let lastCreatedAt = 0;
  let scrollIntent = 0;
  for (const event of ['wheel', 'touchmove']) window.addEventListener(event, () => { scrollIntent++; }, {passive:true});
  window.addEventListener('keydown', event => {
    if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End'].includes(event.key)) scrollIntent++;
  });
  function save(item) {
    try { localStorage.setItem(prefix + item.id, JSON.stringify(item, (key, value) => key === 'imageFile' ? undefined : value)); }
    catch { throw new Error('Reply recovery storage is unavailable or full. Keep this page open, free browser storage and retry.'); }
  }
  function load(id) { return JSON.parse(localStorage.getItem(prefix + id) || 'null'); }
  const unsavedImages = new Map();
  async function attachmentStore(id, file, remove = false) {
    // ArrayBuffer storage also works in engines that cannot clone File into IDB.
    const retained = file ? {bytes:await file.arrayBuffer(), name:file.name, type:file.type} : null;
    return new Promise((resolve, reject) => {
      const fail = () => reject(new Error('Attachment recovery storage is unavailable or full. Keep this page open, free browser storage and retry.'));
      let request;
      try { request = indexedDB.open('forum-reply-attachments-v1', 1); } catch { fail(); return; }
      request.onupgradeneeded = () => request.result.createObjectStore('attachments');
      request.onerror = fail;
      request.onsuccess = () => {
        const db = request.result;
        const tx = db.transaction('attachments', file || remove ? 'readwrite' : 'readonly');
        const operation = remove ? tx.objectStore('attachments').delete(id) : file ? tx.objectStore('attachments').put(retained, id) : tx.objectStore('attachments').get(id);
        tx.oncomplete = () => { db.close(); resolve(file ? operation.result : operation.result ? new File([operation.result.bytes], operation.result.name, {type:operation.result.type}) : null); };
        tx.onerror = tx.onabort = () => { db.close(); fail(); };
      };
    });
  }
  window.addEventListener('beforeunload', event => {
    if (unsavedImages.size) { event.preventDefault(); event.returnValue = ''; }
  });
  function bind(form, root, api) {
    if (!form.hasAttribute('data-phone-reply') || !navigator.locks || !window.visualViewport) return null;
    root.classList.add('reply-continuation-root');
    const body = form.querySelector('[name="body"]');
    const threadId = form.querySelector('[name="thread_id"]').value;
    const parentId = form.querySelector('[name="parent_id"]').value;
    const views = new Map();
    const panel = root.querySelector('[data-reply-panel]');
    const anchor = root.querySelector('[data-inline-reply-compose]') ? root : panel;
    function matches(item) {
      return typeof item?.id === 'string' && typeof item?.fields?.body === 'string' &&
        item.fields.thread_id === threadId && item.fields.parent_id === parentId;
    }
    function render(item) {
      if (views.has(item.id)) { views.get(item.id).update(item); return views.get(item.id); }
      if (item.result && document.getElementById('post-' + item.result.postId)) return null;
      let card = api.card({pendingId:item.id, parentId:item.fields.parent_id, body:item.fields.body, status:'Posting…'});
      card.classList.add('reply-operation-placeholder');
      card.dataset.replyOperation = item.id;
      const status = card.querySelector('[data-role="pending-reply-status"]');
      status.setAttribute('role', 'status');
      const retry = document.createElement('button');
      retry.type = 'button'; retry.textContent = 'Retry';
      card.append(retry);
      const flowAnchor = anchor.previousElementSibling?.hasAttribute('data-reply-flow-spacer') ? anchor.previousElementSibling : anchor;
      flowAnchor.before(card);
      let running = false;
      let preview;
      let rendering = false;
      let rendered = false;
      const initialScrollIntent = scrollIntent;
      async function hydrate() {
        if (rendering || rendered || !card.isConnected) return;
        rendering = true;
        retry.hidden = true;
        try {
          const full = await api.renderPost(item.result.postId, item.fields.thread_id, item.result.commitSha);
          if (!card.isConnected) return;
          const editorTop = body.getBoundingClientRect().top;
          const flowAnchor = anchor.previousElementSibling?.hasAttribute('data-reply-flow-spacer') ? anchor.previousElementSibling : anchor;
          const following = panel.dataset.replyReading === '1' && flowAnchor.previousElementSibling === card && scrollIntent === initialScrollIntent;
          full.dataset.replyOperation = item.id;
          full.dataset.replyState = 'accepted';
          full.dataset.replyRendered = '1';
          if (preview) URL.revokeObjectURL(preview.src);
          card.replaceWith(full);
          card = full; rendered = true;
          api.bindPost(full);
          if (document.activeElement === body) window.scrollBy(0, body.getBoundingClientRect().top - editorTop);
          else if (following) {
            full.style.scrollMarginBottom = (window.innerHeight - panel.getBoundingClientRect().top + 24) + 'px';
            full.scrollIntoView({block:'end'});
          }
        } catch {
          status.hidden = false;
          status.textContent = 'Reply posted. Retry loading its full view.';
          retry.hidden = false;
        } finally { rendering = false; }
      }
      async function showImage() {
        const file = item.imageUrl ? null : unsavedImages.get(item.id) || await attachmentStore(item.id);
        if ((!file && !item.imageUrl) || preview) return;
        preview = document.createElement('img');
        preview.alt = item.description || 'Reply attachment';
        preview.src = item.imageUrl || URL.createObjectURL(file);
        preview.className = 'reply-operation-image';
        card.querySelector('.body').append(preview);
      }
      if (item.hasImage) void showImage().catch(() => {});
      function update(next) {
        item = next;
        card.dataset.replyState = item.state;
        card.setAttribute('aria-busy', String(running));
        retry.hidden = running || item.state === 'accepted';
        if (item.state === 'accepted') {
          if (rendered) return;
          if (preview && item.imageUrl && preview.getAttribute('src') !== item.imageUrl) {
            URL.revokeObjectURL(preview.src); preview.src = item.imageUrl;
          }
          status.textContent = ''; status.hidden = true;
          const canonical = document.getElementById('post-' + item.result.postId);
          if (canonical && canonical !== card) {
            if (preview) URL.revokeObjectURL(preview.src);
            card.remove();
          }
          card.removeAttribute('data-pending-reply-id');
          card.classList.remove('pending-reply-card');
          void hydrate();
        } else {
          status.textContent = running ? 'Posting…' : item.error || 'Not confirmed. Check and retry.';
        }
      }
      async function transmit() {
        // A second tab must reload the winning operation while holding the same lock.
        item = load(item.id) || item;
        if (item.state === 'accepted') { update(item); return; }
        const confirmed = item.delivery && await api.confirm(item.delivery);
        let attachment;
        if (item.hasImage && !confirmed) {
          attachment = unsavedImages.get(item.id) || await attachmentStore(item.id);
          if (!attachment) throw new Error('Attachment recovery is unavailable. Keep the original page open and retry there.');
          if (unsavedImages.has(item.id)) { await attachmentStore(item.id, attachment); unsavedImages.delete(item.id); }
          if (item.delivery) item.delivery.fields.imageFile = attachment;
        }
        if (confirmed) {
          item.result = {postId:item.delivery.prepared.postId};
        } else {
          if (!item.delivery) {
            item.delivery = await api.prepare(item.fields, attachment, item.description);
            if (item.delivery.fields.imageFile) {
              unsavedImages.set(item.id, item.delivery.fields.imageFile);
              await attachmentStore(item.id, item.delivery.fields.imageFile);
              unsavedImages.delete(item.id);
            }
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
        const reference = item.delivery?.fields.body.match(/(?:^|\n)Image-v1: ([a-f0-9]{64}) (png|jpg) /);
        if (reference) item.imageUrl = '/images/' + reference[1] + '.' + reference[2];
        item.state = 'accepted'; item.error = ''; save(item);
        update(item);
        if (item.hasImage) void attachmentStore(item.id, undefined, true).catch(() => {});
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
      // Retrying an older item must not blur/collapse the active composer
      // between pointer down and click, or move a newer draft out of view.
      retry.addEventListener('pointerdown', event => event.preventDefault());
      retry.addEventListener('click', () => { if (item.state === 'accepted') void hydrate(); else void deliver(); });
      const view = {get card() { return card; }, update, deliver}; views.set(item.id, view); update(item);
      return view;
    }
    // Restoring never silently submits. The user can check/retry each retained item.
    try {
      Object.keys(localStorage).filter(key => key.startsWith(prefix)).map(key => {
        try { return JSON.parse(localStorage.getItem(key)); } catch { return null; }
      })
        .filter(matches).sort((a,b) => a.createdAt - b.createdAt).forEach(render);
    } catch { /* The original form remains usable; Send reports storage failure. */ }
    window.addEventListener('storage', event => {
      if (!event.key?.startsWith(prefix) || !event.newValue) return;
      try { const item = JSON.parse(event.newValue); if (matches(item)) render(item); } catch { /* Ignore unrelated malformed storage. */ }
    });
    return {
      send(fields, attachment, description) {
        lastCreatedAt = Math.max(Date.now(), lastCreatedAt + 1);
        const item = {id:crypto.randomUUID(), fields, hasImage:Boolean(attachment), description, state:'pending', createdAt:lastCreatedAt};
        save(item);
        if (attachment) unsavedImages.set(item.id, attachment);
        const editorTop = body.getBoundingClientRect().top;
        const view = render(item);
        if (attachment) api.clearImage();
        body.value = '';
        api.clearDraft(form);
        body.dispatchEvent(new Event('input', {bubbles:true}));
        if (window.matchMedia('(max-width: 1024px) and (pointer: coarse)').matches) {
          form.dispatchEvent(new Event('forum:reply-sent'));
          // Reading mode owns keyboard dismissal; reveal the sent card above its bar.
          requestAnimationFrame(() => {
            view.card.style.scrollMarginBottom = (window.innerHeight - panel.getBoundingClientRect().top + 24) + 'px';
            view.card.scrollIntoView({block:'end'});
          });
        } else {
          if (document.activeElement !== body) body.focus({preventScroll:true});
          // Keep the editor at its writing position as the reply enters the page.
          const preservePosition = () => {
            if (document.activeElement === body && !body.value) window.scrollBy(0, body.getBoundingClientRect().top - editorTop);
          };
          preservePosition();
          // Browsers may reveal the cleared caret during their next rendering step.
          requestAnimationFrame(preservePosition);
        }
        void view.deliver();
      }
    };
  }
  window.ForumReplyContinuation = {bind};
})();

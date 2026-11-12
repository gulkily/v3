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
    // Rows from the server show readable times at once, whether or not their previews load.
    rendered.forEach(function (row) {
      const time = row.querySelector('[data-role="time"]');
      if (time) window.ForumMessageTime.render(time, time.getAttribute('datetime'));
    });
    let cursor = root.dataset.pageCursor;
    let loading = false;
    const senderKeys = new Map();
    const previewMessages = new Map();
    const previewRequests = new Map();
    function refreshHistory(event) {
      rendered.forEach(function (row) {
        if (row.dataset.previewState==='verified' || row.dataset.previewState==='bad-signature') return;
        if (event.detail && event.detail.messageId && event.detail.messageId !== row.dataset.messageId) return;
        const message = previewMessages.get(row.dataset.messageId);
        if (message) {
          const candidate=window.ForumPrivateMessageHistorySync?.recovered?.(message.message_id,message.encrypted_envelope);
          const retry=()=>{if(row.dataset.previewState!=='verified')return preview(row,message,candidate);};
          if(previewRequests.has(message.message_id))previewRequests.get(message.message_id).then(retry);else retry();
        }
      });
    }
    document.addEventListener('private-message-history-restored', refreshHistory);
    document.addEventListener('private-message-viewer-known', refreshHistory, {once:true});

    function preview(row, message, historyCandidate) {
      if (previewRequests.has(message.message_id)) return previewRequests.get(message.message_id);
      previewMessages.set(message.message_id, message);
      const request = readPreview(row, message, historyCandidate).finally(function () { previewRequests.delete(message.message_id); });
      previewRequests.set(message.message_id, request);
      return request;
    }

    function showPreview(row, messageId, senderUsernameToken, plaintext) {
      const text = row.querySelector('[data-role="preview"]');
      const prefix = senderUsernameToken === root.dataset.viewer ? 'You: ' : '';
      row.dataset.previewState = 'verified';
      row.dataset.previewMessageId = messageId;
      text.title = '';
      text.textContent = prefix + plaintext.replace(/\s+/gu, ' ').trim().slice(0, 200);
      text.className = 'message-preview';
      row.querySelector('[data-role="verification"]').hidden = false;
      row.querySelector('[data-action="retry-preview"]').hidden = true;
    }

    // Saved copies (private_message_cache.js) fill previews this browser has already read, without waiting
    // for the conversation list request or decrypting. Server rows carry the message id and sender.
    const cache = root.dataset.privateMessageCache === '1' ? window.ForumPrivateMessageCache : null;
    function savedPreview(row, messageId, senderUsernameToken) {
      if (!cache || !cache.enabled() || !messageId) return Promise.resolve(false);
      return cache.get(messageId).then(function (plaintext) {
        if (plaintext === null || row.dataset.messageId !== messageId) return false;
        showPreview(row, messageId, senderUsernameToken, plaintext);
        return true;
      }, function () { return false; });
    }
    const savedPreviews = Promise.all(Array.from(rendered.values()).map(function (row) {
      return savedPreview(row, row.dataset.messageId, row.dataset.sender);
    })).then(function () {
      root.dataset.privateMessageCacheChecked = '1';
    });

    async function readPreview(row, message, historyCandidate) {
      const text = row.querySelector('[data-role="preview"]');
      const verified = row.querySelector('[data-role="verification"]');
      const retryPreview = row.querySelector('[data-action="retry-preview"]');
      if (!historyCandidate && row.dataset.previewState === 'verified' && row.dataset.previewMessageId === message.message_id) return;
      if (!historyCandidate && await savedPreview(row, message.message_id, message.sender_username_token)) return;
      text.textContent = 'Decrypting…';
      text.className = 'message-preview is-loading';
      verified.hidden = true;
      retryPreview.hidden = true;
      let result;
      historyCandidate=historyCandidate || window.ForumPrivateMessageHistorySync?.recovered?.(message.message_id,message.encrypted_envelope);
      try {
        if (!senderKeys.has(message.sender_username_token)) {
          const request = (window.ForumPrivateMessages.verificationKeys || window.ForumPrivateMessages.recipientKeys)(message.sender_username_token);
          senderKeys.set(message.sender_username_token, request);
          request.catch(function () { senderKeys.delete(message.sender_username_token); });
        }
        result = await window.ForumPrivateMessageReader.decryptEnvelope({
          messageId: message.message_id,
          encryptedEnvelope: message.encrypted_envelope,
          senderPublicKeyArmors: await senderKeys.get(message.sender_username_token), historyCandidate: historyCandidate,
        });
      } catch (error) {
        result = { kind: 'load-failed', message: 'Preview unavailable. Sender keys could not be loaded. Try again.' };
      }
      if (row.dataset.messageId !== message.message_id) return;
      row.dataset.previewState = result.kind;
      text.title = '';
      if (result.kind === 'verified') {
        showPreview(row, message.message_id, message.sender_username_token, result.plaintext);
        if (cache && cache.enabled()) cache.put(message.message_id, result.plaintext);
      } else {
        // One short line on the row; the reader's full reason stays in the title.
        text.textContent = result.kind === 'bad-signature' ? 'Couldn’t verify preview'
          : result.kind === 'load-failed' ? 'Couldn’t load preview' : 'Couldn’t decrypt preview';
        text.title = result.message || '';
        text.className = 'message-preview is-failed';
        retryPreview.hidden = false;
      }
      retryPreview.onclick = function () {
        senderKeys.delete(message.sender_username_token);
        return preview(row, message);
      };
    }

    function render(message) {
      let row = rendered.get(message.counterpart);
      if (!row) {
        row = template.content.firstElementChild.cloneNode(true);
        rendered.set(message.counterpart, row);
        rows.appendChild(row);
      }
      row.dataset.counterpart = message.counterpart;
      row.dataset.messageId = message.message_id;
      row.dataset.sender = message.sender_username_token;
      row.querySelector('a').href = '/messages/conversation/' + encodeURIComponent(message.counterpart);
      row.querySelector('[data-role="counterpart"]').textContent = message.counterpart;
      const time = row.querySelector('[data-role="time"]');
      time.dateTime = message.created_at;
      time.textContent = message.created_at;
      window.ForumMessageTime.render(time, message.created_at);
      return preview(row, message);
    }

    async function load() {
      if (loading || cursor === null) return;
      loading = true;
      more.disabled = true;
      retry.hidden = true;
      restart.hidden = true;
      rows.setAttribute('aria-busy', 'true');
      // Progress and success are announced but not shown; a failure is one visible line with Retry.
      status.className = 'meta visually-hidden';
      status.textContent = 'Loading conversations…';
      try {
        const response = await window.ForumPrivateMessageAvailability.request('/api/private_messages/conversations?cursor=' + encodeURIComponent(cursor), {
          credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok || !payload || payload.status !== 'ok' || !Array.isArray(payload.conversations)) {
          if (payload && payload.restart) restart.hidden = false;
          throw new Error(String(payload && payload.error || 'Unable to load conversations. Try again.'));
        }
        await Promise.all(payload.conversations.map(render));
        if (document.dispatchEvent) document.dispatchEvent(new CustomEvent('private-message-rows-changed'));
        cursor = payload.next_cursor;
        more.hidden = cursor === null;
        empty.hidden = rendered.size !== 0;
        status.textContent = cursor === null
          ? (rendered.size ? 'All conversations loaded.' : '')
          : rendered.size + ' conversations loaded.';
      } catch (error) {
        status.textContent = 'Couldn’t load messages.';
        status.title = error instanceof Error ? error.message : '';
        status.className = 'message-list-alert';
        retry.hidden = !restart.hidden;
        // Retry replaces Load more while the failure stands; rows that never loaded say what they hold.
        more.hidden = true;
        rows.querySelectorAll('.message-preview.is-loading').forEach(function (text) { text.className = 'message-preview'; });
      } finally {
        loading = false;
        more.disabled = false;
        rows.setAttribute('aria-busy', 'false');
      }
    }

    more.addEventListener('click', load);
    retry.addEventListener('click', load);
    // The list request runs alongside saved previews and only decrypts what they didn't fill.
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

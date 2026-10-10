(function () {
  "use strict";

  function bindRecipient(root) {
    const form = root.querySelector('[data-role="recipient-form"]');
    if (!form) return;
    const field = form.querySelector('[name="username"]');
    const submit = form.querySelector('button[type="submit"]');
    const feedback = root.querySelector('[data-role="recipient-feedback"]');
    let checking = false;
    root.querySelector('[data-action="new-message"]').addEventListener('click', function () {
      root.querySelector('[data-role="new-message"]').open = true;
      field.focus();
    });
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (checking) return;
      checking = true;
      submit.disabled = true;
      feedback.hidden = false;
      feedback.className = 'feedback';
      feedback.textContent = 'Checking recipient…';
      try {
        const username = field.value.trim().toLowerCase();
        if (!/^[a-z0-9][a-z0-9._-]{0,63}$/.test(username)) throw new Error('Enter a valid username.');
        if (username === root.dataset.viewer) throw new Error('Choose another user to start a conversation.');
        await window.ForumPrivateMessages.recipientKeys(username);
        window.location.assign('/messages/conversation/' + encodeURIComponent(username));
      } catch (error) {
        feedback.className = 'feedback feedback-error';
        feedback.textContent = error instanceof Error ? error.message : 'Recipient unavailable. Try again.';
        field.focus();
      } finally {
        checking = false;
        submit.disabled = false;
      }
    });
  }

  function bind(root) {
    if (!root || root.dataset.listBound === "1") return null;
    root.dataset.listBound = "1";
    bindRecipient(root);
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
    const senderKeys = new Map();

    async function preview(row, message) {
      const text = row.querySelector('[data-role="preview"]');
      const verified = row.querySelector('[data-role="verification"]');
      const retryPreview = row.querySelector('[data-action="retry-preview"]');
      text.textContent = 'Decrypting preview…';
      text.className = 'message-preview meta';
      verified.hidden = true;
      retryPreview.hidden = true;
      let result;
      try {
        if (!senderKeys.has(message.sender_username_token)) {
          const request = window.ForumPrivateMessages.recipientKeys(message.sender_username_token);
          senderKeys.set(message.sender_username_token, request);
          request.catch(function () { senderKeys.delete(message.sender_username_token); });
        }
        result = await window.ForumPrivateMessageReader.decryptEnvelope({
          encryptedEnvelope: message.encrypted_envelope,
          senderPublicKeyArmors: await senderKeys.get(message.sender_username_token),
        });
      } catch (error) {
        result = { kind: 'load-failed', message: 'Preview unavailable. Sender keys could not be loaded. Try again.' };
      }
      if (row.dataset.messageId !== message.message_id) return;
      if (result.kind === 'verified') {
        const prefix = message.sender_username_token === root.dataset.viewer ? 'You: ' : '';
        text.textContent = prefix + result.plaintext.replace(/\s+/gu, ' ').trim().slice(0, 200);
        verified.hidden = false;
      } else {
        text.textContent = result.message || 'This message could not be verified.';
        text.className = 'message-preview feedback feedback-error';
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
        await Promise.all(payload.conversations.map(render));
        if (document.dispatchEvent) document.dispatchEvent(new CustomEvent('private-message-rows-changed'));
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

  window.ForumPrivateMessageList = { bind: bind, bindRecipient: bindRecipient };
  document.addEventListener('DOMContentLoaded', function () {
    Array.from(document.querySelectorAll('[data-conversation-list]')).forEach(bind);
  });
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
})();

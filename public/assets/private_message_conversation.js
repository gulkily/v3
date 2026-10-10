(function () {
  "use strict";
  function format(root) {
    const transcript = root.querySelector('[data-role="private-message-transcript"]');
    transcript.querySelectorAll('[data-role="message-date"]').forEach(node => node.remove());
    let lastDate = "", lastSender = "";
    transcript.querySelectorAll('[data-private-message-id]').forEach(function (card) {
      const sender = card.dataset.sender;
      const outgoing = sender === root.dataset.viewerUsernameToken;
      const label = outgoing ? "You" : sender;
      card.classList.toggle('is-outgoing', outgoing);
      card.setAttribute('aria-label', 'Message from ' + label);
      card.querySelector('[data-role="message-sender"]').textContent = label;
      const date = new Date(card.dataset.createdAt);
      const day = date.toDateString();
      if (day !== lastDate && !Number.isNaN(date.getTime())) {
        const divider = document.createElement('p');
        divider.dataset.role = 'message-date';
        divider.className = 'private-message-date meta';
        divider.textContent = date.toLocaleDateString(undefined, { dateStyle: 'full' });
        transcript.insertBefore(divider, card);
      }
      card.classList.toggle('is-grouped', sender === lastSender && day === lastDate);
      window.ForumMessageTime.render(card.querySelector('time'), card.dataset.createdAt);
      lastDate = day;
      lastSender = sender;
    });
  }
  function bind(root) {
    if (!root || root.dataset.conversationBound) return;
    root.dataset.conversationBound = '1';
    format(root);
    root.addEventListener('private-message-sent', function (event) {
      event.preventDefault();
      const detail = event.detail;
      const message = detail.message;
      const transcript = root.querySelector('[data-role="private-message-transcript"]');
      if (Array.from(transcript.querySelectorAll('[data-private-message-id]')).some(card => card.dataset.privateMessageId === message.message_id)) return;
      root.dispatchEvent(new CustomEvent('private-message-before-append'));
      const card = root.querySelector('[data-role="private-message-template"]').content.firstElementChild.cloneNode(true);
      card.dataset.privateMessageId = message.message_id;
      card.dataset.sender = message.sender_username_token;
      card.dataset.createdAt = message.created_at;
      const later = Array.from(transcript.querySelectorAll('[data-private-message-id]')).find(item => item.dataset.createdAt > message.created_at);
      transcript.insertBefore(card, later || null);
      root.querySelector('[data-role="private-message-empty"]').hidden = true;
      format(root);
      window.ForumPrivateMessageReader.readCard('conversation', card, root.dataset.counterpartUsernameToken,
        Object.assign({}, message, { encrypted_envelope: detail.encryptedEnvelope })).then(function () {
        root.dispatchEvent(new CustomEvent('private-message-appended', { detail: { card: card } }));
      });
    });
  }
  window.ForumPrivateMessageConversation = { bind: bind, format: format };
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mailbox="conversation"]').forEach(bind);
  });
})();

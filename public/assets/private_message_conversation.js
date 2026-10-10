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
  }
  window.ForumPrivateMessageConversation = { bind: bind, format: format };
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mailbox="conversation"]').forEach(bind);
  });
})();

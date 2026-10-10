(function () {
  "use strict";

  function render(node, value, now) {
    const date = new Date(value);
    if (!node || Number.isNaN(date.getTime())) return;
    now = now || new Date();
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    node.dateTime = value;
    node.title = date.toLocaleString(undefined, { dateStyle: 'full', timeStyle: 'long' }) + ' (' + value + ')';
    node.setAttribute('aria-label', node.title);
    if (date.toDateString() === now.toDateString()) {
      node.textContent = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    } else if (date.toDateString() === yesterday.toDateString()) {
      node.textContent = 'Yesterday';
    } else {
      node.textContent = date.toLocaleDateString(undefined, {
        month: 'short', day: 'numeric', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric',
      });
    }
  }

  window.ForumMessageTime = { render: render };
})();

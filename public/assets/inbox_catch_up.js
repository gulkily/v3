(function () {
  'use strict';
  // Caught up without a page reload. The form still posts to /unread on its own
  // when this script is missing; here the same POST goes through fetch, which
  // follows the server's redirect back to Unread, and only the submitted
  // section is swapped for its fresh copy. A refused save comes back as the
  // page with that section's "Couldn't save" alert, which is swapped in the same way.
  function failInPlace(form, button) {
    let alert = form.querySelector('.message-list-alert');
    if (!alert) {
      alert = document.createElement('p');
      alert.className = 'message-list-alert';
      alert.setAttribute('role', 'alert');
      form.insertBefore(alert, form.querySelector('.button-row'));
    }
    alert.textContent = 'Couldn’t save. Nothing was marked read.';
    button.textContent = 'Retry';
    button.disabled = false;
    form.removeAttribute('aria-busy');
  }

  async function catchUp(form) {
    const section = form.closest('.inbox-section');
    const button = form.querySelector('button[type="submit"]');
    if (!section || !section.id || !button || button.disabled) return;
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        // The same encoding the plain form uses.
        body: new URLSearchParams(new FormData(form)),
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'text/html' },
      });
      if (!response.ok && response.status !== 409) throw new Error('Caught up failed');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const fresh = page.getElementById(section.id);
      if (!fresh) throw new Error('Section missing');
      const heading = fresh.querySelector('h2');
      if (heading) heading.tabIndex = -1;
      section.replaceWith(document.adoptNode(fresh));
      // The button is gone (or became Retry); keep keyboard focus in this section.
      const retry = fresh.querySelector('.inbox-catch-up button[type="submit"]');
      (retry || heading || fresh).focus({ preventScroll: true });
      if (window.ForumInboxUnread) window.ForumInboxUnread.refresh();
    } catch (error) {
      if (form.isConnected) failInPlace(form, button);
    }
  }

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('inbox-catch-up')) return;
    if (!window.fetch || !window.DOMParser) return;
    event.preventDefault();
    catchUp(form);
  });
})();

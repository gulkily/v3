(function () {
  'use strict';
  let disabled = false, generation = 0, observer = null;
  const message = 'Private messaging is disabled on this server. Your draft is retained. Reload after messaging is re-enabled to resume.';
  const scopes = '[data-private-message-mailbox], [data-private-message-composer], [data-conversation-list]';

  function unavailable() {
    const error = new Error(message);
    error.code = 'messaging_disabled';
    return error;
  }
  function renderDisabled() {
    document.querySelectorAll('[data-private-message-unread]').forEach(nav => { nav.hidden = true; });
    document.querySelectorAll(scopes).forEach(root => {
      root.querySelectorAll('button').forEach(button => { if (!button.disabled) button.disabled = true; });
    });
    if (!document.querySelector('[data-messaging-disabled-notice]')) {
      const notice = document.createElement('p');
      notice.dataset.messagingDisabledNotice = '1';
      notice.className = 'feedback feedback-error';
      notice.setAttribute('role', 'status');
      notice.textContent = message;
      (document.querySelector('main') || document.body).prepend(notice);
    }
  }
  function disable() {
    if (disabled) return;
    disabled = true;
    generation++;
    renderDisabled();
    // In-flight completions may render new controls or reset their disabled state.
    // Keep those controls inert, while leaving draft text editable/copyable.
    observer = new MutationObserver(renderDisabled);
    observer.observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['disabled'] });
    document.dispatchEvent(new CustomEvent('private-messaging-disabled'));
  }
  // Only the settings-page coordinator uses this after server confirmation.
  function setEnabled(enabled) {
    if (!enabled) { disable(); return; }
    generation++;
    disabled = false;
    if (observer) observer.disconnect();
    observer = null;
    document.querySelectorAll('[data-private-message-unread]').forEach(nav => { nav.hidden = false; });
    document.querySelectorAll('[data-messaging-disabled-notice]').forEach(notice => notice.remove());
  }
  async function request(url, options) {
    if (disabled) throw unavailable();
    const current = generation;
    const response = await fetch(url, options);
    if (response.status === 403) {
      const payload = await response.clone().json().catch(() => null);
      if (payload && payload.code === 'messaging_disabled') {
        if (current === generation) disable();
        throw unavailable();
      }
    }
    return response;
  }
  window.ForumPrivateMessageAvailability = { request, disable, setEnabled, isDisabled: () => disabled, message };
})();

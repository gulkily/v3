(function () {
  'use strict';
  let disabled = false;
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
    renderDisabled();
    // In-flight completions may render new controls or reset their disabled state.
    // Keep those controls inert, while leaving draft text editable/copyable.
    const observer = new MutationObserver(renderDisabled);
    observer.observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['disabled'] });
    document.dispatchEvent(new CustomEvent('private-messaging-disabled'));
  }
  async function request(url, options) {
    if (disabled) throw unavailable();
    const response = await fetch(url, options);
    if (response.status === 403) {
      const payload = await response.clone().json().catch(() => null);
      if (payload && payload.code === 'messaging_disabled') {
        disable();
        throw unavailable();
      }
    }
    return response;
  }
  window.ForumPrivateMessageAvailability = { request, isDisabled: () => disabled, message };
})();

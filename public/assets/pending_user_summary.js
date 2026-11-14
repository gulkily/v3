(function () {
  'use strict';
  // A "Pending" chip beside an author's name opens a short summary of that
  // key in place, with Approve for viewers remembered as approved members
  // (the button is [data-member-only]; the server still checks authority).
  // Approving removes every chip for that key on the page. Without this
  // script the chip is a plain link to the profile.

  let dialog = null;
  let current = '';

  function field(text, key) {
    const match = new RegExp('^' + key + ': ?(.*)$', 'm').exec(text);
    return match ? match[1].trim() : '';
  }

  function part(role) {
    return dialog.querySelector('[data-role="' + role + '"]');
  }

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'card pending-user-dialog';
    dialog.setAttribute('aria-labelledby', 'pending-user-dialog-title');
    dialog.innerHTML = [
      '<header class="pending-user-dialog__head">',
      '  <h2 id="pending-user-dialog-title" data-role="name"></h2>',
      '  <span class="chip chip--pending">Pending</span>',
      '</header>',
      '<p class="meta pending-user-dialog__key" data-role="key"></p>',
      '<p class="pending-user-dialog__counts" data-role="counts"></p>',
      '<p class="feedback feedback-error" data-role="error" hidden></p>',
      '<div class="pending-user-dialog__actions">',
      '  <button type="button" data-role="approve" data-member-only>Approve</button>',
      '  <a data-role="profile" href="#">Profile</a>',
      '  <button type="button" data-role="close">Close</button>',
      '</div>',
    ].join('\n');
    part('close').addEventListener('click', function () { dialog.close(); });
    part('approve').addEventListener('click', approve);
    // A click on the backdrop lands on the dialog itself.
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
    document.body.appendChild(dialog);
  }

  function showError(message) {
    const node = part('error');
    node.textContent = message;
    node.hidden = message === '';
  }

  function plural(count, word) {
    return count + ' ' + word + (count === '1' ? '' : 's');
  }

  async function open(chip) {
    const slug = chip.getAttribute('data-pending-profile') || '';
    if (!dialog) build();
    current = slug;
    part('name').textContent = chip.getAttribute('data-pending-username') || slug;
    // Shortened like the Waiting list; the full key is the tooltip.
    part('key').textContent = slug.length > 17 ? slug.slice(0, 17) + '...' : slug;
    part('key').title = slug;
    part('counts').textContent = '\u00a0';
    part('profile').href = chip.getAttribute('href') || ('/profiles/' + encodeURIComponent(slug));
    part('approve').disabled = false;
    showError('');
    dialog.showModal();

    try {
      const response = await fetch('/api/get_profile?profile_slug=' + encodeURIComponent(slug), { credentials: 'same-origin', cache: 'no-store' });
      const text = await response.text();
      if (current !== slug) return;
      if (!response.ok) throw new Error('Profile not found.');
      const username = field(text, 'Username');
      if (username) part('name').textContent = username;
      part('counts').textContent = plural(field(text, 'Threads') || '0', 'thread') + ' \u00b7 ' + plural(field(text, 'Posts') || '0', 'post');
      // Someone else approved it since this page was built.
      if (field(text, 'Approved') === 'yes') settle(slug);
    } catch (error) {
      if (current === slug) part('counts').textContent = 'Summary unavailable.';
    }
  }

  function loadScript(path) {
    return new Promise(function (resolve, reject) {
      const url = new URL(path, window.location.href);
      if (Array.from(document.scripts).some(function (script) { return script.src === url.href; })) {
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = url.href;
      script.async = false;
      script.onload = resolve;
      script.onerror = function () { script.remove(); reject(new Error('Signing is unavailable. Refresh and try again.')); };
      document.head.appendChild(script);
    });
  }

  // Pages without signing scripts load them on first use.
  async function signing() {
    if (!window.ForumBrowserSigning) {
      const paths = window.__forumAssetPaths || {};
      if (!paths.openpgpLoader || !paths.browserSigning) throw new Error('Signing is unavailable. Refresh and try again.');
      await loadScript(paths.openpgpLoader);
      await loadScript(paths.browserSigning);
    }
    if (!window.ForumBrowserSigning || typeof window.ForumBrowserSigning.submitSignedApproval !== 'function') {
      throw new Error('Signing is unavailable. Refresh and try again.');
    }
    return window.ForumBrowserSigning;
  }

  async function approve() {
    const slug = current;
    const button = part('approve');
    button.disabled = true;
    showError('');
    try {
      const api = await signing();
      // This dialog is the review step, so no second confirmation.
      const result = await api.submitSignedApproval(slug, { confirm: false });
      if (!result || !result.ok) throw new Error(result && result.error ? result.error : 'Unable to approve.');
      settle(slug);
    } catch (error) {
      if (current !== slug) return;
      showError(error instanceof Error ? error.message : 'Unable to approve.');
      button.disabled = false;
    }
  }

  // The key is approved: its chips go, and the open summary closes.
  function settle(slug) {
    document.querySelectorAll('[data-pending-profile]').forEach(function (chip) {
      if (chip.getAttribute('data-pending-profile') === slug) chip.remove();
    });
    if (dialog && current === slug && dialog.open) dialog.close();
  }

  document.addEventListener('click', function (event) {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const chip = event.target instanceof Element ? event.target.closest('[data-pending-profile]') : null;
    if (!chip || !chip.getAttribute('data-pending-profile') || typeof HTMLDialogElement !== 'function') return;
    event.preventDefault();
    open(chip);
  });
})();

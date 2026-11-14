(function () {
  'use strict';
  // A "Pending" chip beside an author's name opens a short summary of that
  // key in place, with Approve for viewers remembered as approved members
  // (the button is [data-member-only]; the server still checks authority).
  // Approving removes every chip for that key on the page. Without this
  // script the chip is a plain link to the profile.

  let dialog = null;
  let current = '';
  let anchor = null;
  let placing = 0;

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
      '  <button type="button" class="pending-user-dialog__close" data-role="close" aria-label="Close">\u00d7</button>',
      '</header>',
      '<p class="meta pending-user-dialog__key" data-role="key"></p>',
      '<p class="pending-user-dialog__counts" data-role="counts"></p>',
      '<ul class="pending-user-dialog__recent" data-role="recent" hidden></ul>',
      '<p class="feedback feedback-error" data-role="error" hidden></p>',
      '<div class="pending-user-dialog__actions">',
      '  <button type="button" data-role="approve" data-member-only>Approve</button>',
      '  <a data-role="profile" href="#">Profile</a>',
      '</div>',
    ].join('\n');
    part('close').addEventListener('click', function () { dialog.close(); });
    part('approve').addEventListener('click', approve);
    // A click on the backdrop lands on the dialog itself.
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', function () { anchor = null; });
    document.body.appendChild(dialog);
    window.addEventListener('resize', schedulePlace);
    window.addEventListener('scroll', schedulePlace, true);
  }

  // Below the chip, left edges aligned; above it when there is more room
  // there; always kept inside the viewport.
  function place() {
    placing = 0;
    if (!dialog || !dialog.open || !anchor || !anchor.isConnected) return;
    const gap = 6;
    const edge = 8;
    const chip = anchor.getBoundingClientRect();
    const box = dialog.getBoundingClientRect();
    const viewWidth = document.documentElement.clientWidth;
    const viewHeight = window.innerHeight;
    const below = viewHeight - chip.bottom - gap - edge;
    const above = chip.top - gap - edge;
    let top = box.height <= below || below >= above ? chip.bottom + gap : chip.top - gap - box.height;
    top = Math.max(edge, Math.min(top, viewHeight - edge - box.height));
    const left = Math.max(edge, Math.min(chip.left, viewWidth - edge - box.width));
    dialog.style.top = Math.round(top) + 'px';
    dialog.style.left = Math.round(left) + 'px';
  }

  function schedulePlace() {
    if (!placing) placing = window.requestAnimationFrame(place);
  }

  function showError(message) {
    const node = part('error');
    node.textContent = message;
    node.hidden = message === '';
  }

  function plural(count, word) {
    return count + ' ' + word + (count === '1' ? '' : 's');
  }

  function shortDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const options = { month: 'short', day: 'numeric', timeZone: 'UTC' };
    if (date.getUTCFullYear() !== new Date().getUTCFullYear()) options.year = 'numeric';
    return date.toLocaleDateString('en-US', options);
  }

  // Recent-Post: <created_at> TAB <thread_id> TAB <post_id> TAB <text>
  function showRecent(text) {
    const list = part('recent');
    list.replaceChildren();
    text.split('\n').forEach(function (line) {
      if (!line.startsWith('Recent-Post: ')) return;
      const cells = line.slice(13).split('\t');
      if (cells.length < 4) return;
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = cells[2] === cells[1] ? '/threads/' + encodeURIComponent(cells[1]) : '/posts/' + encodeURIComponent(cells[2]);
      link.textContent = cells.slice(3).join('\t') || cells[2];
      const when = document.createElement('span');
      when.className = 'meta';
      when.textContent = shortDate(cells[0]);
      link.title = link.textContent;
      item.append(link, when);
      list.append(item);
    });
    list.hidden = list.children.length === 0;
  }

  async function open(chip) {
    const slug = chip.getAttribute('data-pending-profile') || '';
    if (!dialog) build();
    current = slug;
    anchor = chip;
    part('name').textContent = chip.getAttribute('data-pending-username') || slug;
    // Shortened like the Waiting list; the full key is the tooltip.
    part('key').textContent = slug.length > 17 ? slug.slice(0, 17) + '...' : slug;
    part('key').title = slug;
    part('counts').textContent = '\u00a0';
    part('recent').replaceChildren();
    part('recent').hidden = true;
    part('profile').href = chip.getAttribute('href') || ('/profiles/' + encodeURIComponent(slug));
    part('approve').disabled = false;
    showError('');
    if (!dialog.open) dialog.showModal();
    place();

    try {
      const response = await fetch('/api/get_profile?profile_slug=' + encodeURIComponent(slug), { credentials: 'same-origin', cache: 'no-store' });
      const text = await response.text();
      if (current !== slug) return;
      if (!response.ok) throw new Error('Profile not found.');
      const username = field(text, 'Username');
      if (username) part('name').textContent = username;
      part('counts').textContent = plural(field(text, 'Threads') || '0', 'thread') + ' \u00b7 ' + plural(field(text, 'Posts') || '0', 'post');
      showRecent(text);
      place();
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

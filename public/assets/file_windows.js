(function () {
  'use strict';
  // On a post's details page, the file links (Source, Commit, Signature,
  // Public key) open their text in small windows over the page, each with a
  // Raw link to the same address. Windows are non-modal dialogs, so several
  // can stay open; each theme dresses them as it dresses its cards. Modified
  // clicks, and any fetch that fails, follow the link as usual.

  const STEP = 24;
  const EDGE = 8;
  const open = new Map();
  let opened = 0;

  function fileName(link) {
    const path = new URL(link.href).pathname;
    return decodeURIComponent(path.slice(path.lastIndexOf('/') + 1)) || link.textContent.trim();
  }

  function keepInView(dialog) {
    const box = dialog.getBoundingClientRect();
    const width = document.documentElement.clientWidth;
    const height = window.innerHeight;
    dialog.style.left = Math.round(Math.max(EDGE, Math.min(box.left, width - EDGE - box.width))) + 'px';
    dialog.style.top = Math.round(Math.max(EDGE, Math.min(box.top, height - EDGE - box.height))) + 'px';
  }

  function close(dialog) {
    if (dialog.open) dialog.close();
  }

  function build(link) {
    const id = 'file-window-' + (++opened);
    const dialog = document.createElement('dialog');
    dialog.className = 'card file-window';
    dialog.setAttribute('aria-labelledby', id);
    dialog.innerHTML = [
      '<header class="file-window__head">',
      '  <h2 class="file-window__title"></h2>',
      '  <a class="file-window__raw">Raw</a>',
      '  <button type="button" class="file-window__close" aria-label="Close">×</button>',
      '</header>',
      '<pre class="file-window__text" tabindex="0">Loading…</pre>',
    ].join('\n');
    const title = dialog.querySelector('.file-window__title');
    title.id = id;
    title.textContent = fileName(link);
    dialog.querySelector('.file-window__raw').href = link.href;
    dialog.querySelector('.file-window__close').addEventListener('click', function () { close(dialog); });
    dialog.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); close(dialog); }
    });
    dialog.addEventListener('close', function () {
      open.delete(link.href);
      dialog.remove();
      if (link.isConnected) link.focus();
    });
    return dialog;
  }

  async function show(link) {
    const existing = open.get(link.href);
    if (existing) {
      document.body.appendChild(existing);
      existing.querySelector('.file-window__close').focus();
      return;
    }
    const dialog = build(link);
    open.set(link.href, dialog);
    document.body.appendChild(dialog);
    // Below the table, under the link's column, so the other links stay
    // clickable; each further window a step down and to the right.
    const anchor = link.getBoundingClientRect();
    const table = (link.closest('[data-file-windows]') || link).getBoundingClientRect();
    const cascade = (open.size - 1) * STEP;
    dialog.style.left = Math.round(anchor.left + cascade) + 'px';
    dialog.style.top = Math.round(table.bottom + 6 + cascade) + 'px';
    dialog.show();
    keepInView(dialog);
    dialog.querySelector('.file-window__close').focus();
    try {
      const response = await fetch(link.href, { credentials: 'same-origin', headers: { Accept: 'text/plain' } });
      const type = response.headers.get('Content-Type') || '';
      if (!response.ok || !type.startsWith('text/plain')) throw new Error('not a text file');
      dialog.querySelector('.file-window__text').textContent = await response.text();
      keepInView(dialog);
    } catch (error) {
      close(dialog);
      window.location.href = link.href;
    }
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest ? event.target.closest('[data-file-windows] a[href^="/source/"]') : null;
    if (!link || typeof HTMLDialogElement !== 'function') return;
    event.preventDefault();
    show(link);
  });
})();

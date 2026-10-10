(function () {
  "use strict";
  function messageCard(root, message) {
    const card = root.querySelector('[data-role="private-message-template"]').content.firstElementChild.cloneNode(true);
    card.dataset.privateMessageId = message.message_id;
    card.dataset.sender = message.sender_username_token;
    card.dataset.createdAt = message.created_at;
    return card;
  }
  function validHistoryPage(root, page) {
    if (!page || page.status !== 'ok' || !Array.isArray(page.messages) || page.messages.length > 25 ||
        typeof page.page_cursor !== 'string' || !page.page_cursor ||
        !(page.next_cursor === null || (typeof page.next_cursor === 'string' && page.next_cursor))) return false;
    const ids = new Set();
    return page.messages.every(function (message) {
      if (!message || !/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/.test(message.message_id) || ids.has(message.message_id) ||
          typeof message.encrypted_envelope !== 'string' || !message.created_at || Number.isNaN(new Date(message.created_at).getTime())) return false;
      ids.add(message.message_id);
      const viewer = root.dataset.viewerUsernameToken, other = root.dataset.counterpartUsernameToken;
      return (message.sender_username_token === viewer && message.recipient_username_token === other) ||
        (message.sender_username_token === other && message.recipient_username_token === viewer);
    });
  }
  function preserveReadingPosition(root) {
    const transcript = root.querySelector('[data-role="private-message-transcript"]');
    const viewport = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    const anchor = Array.from(transcript.querySelectorAll('[data-private-message-id]')).find(function (card) {
      const rect = card.getBoundingClientRect();
      return rect.bottom > 0 && rect.top < viewport;
    });
    if (!anchor) return { correct() {}, finish() {} };
    const offset = anchor.getBoundingClientRect().top;
    const priorAnchoring = document.documentElement.style.overflowAnchor;
    document.documentElement.style.overflowAnchor = 'none';
    let active = true;
    function correct() {
      if (!active || !anchor.isConnected) return;
      const shift = anchor.getBoundingClientRect().top - offset;
      if (Math.abs(shift) > .5) window.scrollBy({ top: shift, behavior: 'instant' });
    }
    const observer = window.ResizeObserver ? new ResizeObserver(correct) : null;
    if (observer) observer.observe(transcript);
    function stop() {
      if (!active) return;
      active = false;
      if (observer) observer.disconnect();
      document.documentElement.style.overflowAnchor = priorAnchoring;
      ['wheel', 'touchstart', 'pointerdown', 'keydown', 'resize'].forEach(type => window.removeEventListener(type, navigate));
      root.removeEventListener('private-message-reveal-latest', stop);
    }
    function navigate(event) {
      if (event.type === 'keydown' && !['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', 'Tab', ' '].includes(event.key)) return;
      stop();
    }
    ['wheel', 'touchstart', 'pointerdown', 'keydown', 'resize'].forEach(type => window.addEventListener(type, navigate, { passive: true }));
    root.addEventListener('private-message-reveal-latest', stop);
    return { correct: correct, finish() { correct(); stop(); } };
  }
  function bindHistory(root) {
    const load = root.querySelector('[data-role="history-load"]');
    const status = root.querySelector('[data-role="history-status"]');
    const transcript = root.querySelector('[data-role="private-message-transcript"]');
    if (!load || !status) return;
    let cursor = root.dataset.historyNextCursor || null;
    let loading = false;
    load.addEventListener('click', async function () {
      if (loading || !cursor) return;
      let readingPosition = preserveReadingPosition(root);
      loading = true;
      root.dataset.historyLoading = '1';
      load.disabled = true;
      status.className = 'meta';
      status.textContent = 'Loading older messages...';
      readingPosition.correct();
      try {
        const response = await fetch('/api/private_messages/conversation?username_token=' + encodeURIComponent(root.dataset.counterpartUsernameToken) + '&cursor=' + encodeURIComponent(cursor), {
          credentials: 'same-origin', headers: { Accept: 'application/json' },
        });
        const page = await response.json();
        if (!response.ok || !validHistoryPage(root, page)) throw new Error('Unable to load older messages. Try again.');
        const known = new Set(Array.from(transcript.querySelectorAll('[data-private-message-id]')).map(card => card.dataset.privateMessageId));
        const fragment = document.createDocumentFragment();
        const added = [];
        page.messages.forEach(function (message) {
          if (known.has(message.message_id)) return;
          const card = messageCard(root, message);
          fragment.appendChild(card);
          added.push({ card: card, message: message });
        });
        readingPosition.finish();
        readingPosition = preserveReadingPosition(root);
        transcript.insertBefore(fragment, transcript.querySelector('[data-private-message-id]'));
        format(root);
        readingPosition.correct();
        cursor = page.next_cursor;
        root.dataset.historyNextCursor = cursor || '';
        await Promise.allSettled(added.map(item => window.ForumPrivateMessageReader.readCard('conversation', item.card, root.dataset.counterpartUsernameToken, item.message).finally(readingPosition.correct)));
        status.textContent = cursor ? added.length + ' older messages loaded.' : 'All history loaded.';
        load.hidden = !cursor;
        load.textContent = 'Load older';
      } catch (error) {
        status.className = 'feedback feedback-error';
        status.textContent = 'Unable to load older messages. Your conversation and draft are unchanged.';
        load.textContent = 'Retry loading older';
      } finally {
        loading = false;
        root.dataset.historyLoading = '0';
        load.disabled = false;
        if (readingPosition) readingPosition.finish();
      }
    });
  }
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
    bindHistory(root);
    const composer = root.querySelector('[data-private-message-composer]');
    const transcript = root.querySelector('[data-role="private-message-transcript"]');
    const latest = root.querySelector('[data-role="private-message-latest"]');
    let navigated = false, followReply = false;
    function userNavigation(event) {
      if (event.type === 'keydown' && !['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', 'Tab', ' '].includes(event.key)) return;
      navigated = true;
      followReply = false;
    }
    ['wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(type => window.addEventListener(type, userNavigation, { passive: true }));
    function viewportHeight() { return window.visualViewport ? window.visualViewport.height : window.innerHeight; }
    function layout() {
      const height = viewportHeight();
      const zoomed = window.visualViewport && window.visualViewport.scale > 1.25;
      const keyboard = height < window.innerHeight * .75;
      root.classList.toggle('composer-inline', height < 420 || keyboard || zoomed || composer.getBoundingClientRect().height > height * .45);
    }
    function revealLatest() {
      root.dispatchEvent(new CustomEvent('private-message-reveal-latest'));
      const last = transcript.querySelector('[data-private-message-id]:last-child') || transcript;
      const obstruction = root.classList.contains('composer-inline') ? 0 : composer.getBoundingClientRect().height;
      window.scrollBy({ top: last.getBoundingClientRect().bottom - viewportHeight() + obstruction + 24, behavior: 'instant' });
    }
    function initiallySettled() { layout(); if (!navigated) revealLatest(); }
    root.addEventListener('private-message-reader-settled', initiallySettled);
    if (root.dataset.privateMessageReaderSettled === '1') initiallySettled();
    latest.addEventListener('click', function () { revealLatest(); });
    root.addEventListener('private-message-before-append', function () {
      const obstruction = root.classList.contains('composer-inline') ? 0 : composer.getBoundingClientRect().height;
      followReply = transcript.getBoundingClientRect().bottom <= viewportHeight() - obstruction + 96;
    });
    root.addEventListener('private-message-appended', function () { layout(); if (followReply) revealLatest(); });
    window.addEventListener('resize', layout);
    if (window.visualViewport) window.visualViewport.addEventListener('resize', layout);
    if (window.ResizeObserver) new ResizeObserver(layout).observe(composer);
    layout();
    root.addEventListener('private-message-sent', function (event) {
      event.preventDefault();
      const detail = event.detail;
      const message = detail.message;
      const transcript = root.querySelector('[data-role="private-message-transcript"]');
      if (Array.from(transcript.querySelectorAll('[data-private-message-id]')).some(card => card.dataset.privateMessageId === message.message_id)) return;
      root.dispatchEvent(new CustomEvent('private-message-before-append'));
      const card = messageCard(root, message);
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

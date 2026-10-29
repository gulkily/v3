(function () {
  const viewport = window.visualViewport;
  if (!viewport) return;
  const phone = window.matchMedia('(max-width: 1024px) and (pointer: coarse)');
  // Dock only while an on-screen keyboard has shrunk the visible area. Without one (a hardware
  // keyboard, a tablet, desktop phone emulation) a docked box would fill the screen and block
  // scrolling. The baseline is the tallest visible area seen at this width, or the window
  // height if larger; rotating starts a new baseline.
  let fullHeight = 0;
  let fullHeightWidth = 0;
  function keyboardUp() {
    if (viewport.width !== fullHeightWidth) {
      fullHeightWidth = viewport.width;
      fullHeight = 0;
    }
    fullHeight = Math.max(fullHeight, viewport.height, window.innerHeight);
    return viewport.height < fullHeight * 0.75;
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-phone-reply]').forEach(function (form) {
      const panel = form.closest('[data-reply-panel]');
      const body = form.querySelector('textarea[name="body"]');
      const actions = form.querySelector('.compose-form-actions');
      if (!panel || !body || !actions) return;
      const menu = actions.querySelector('.compose-action-menu');
      const spacer = document.createElement('div');
      spacer.setAttribute('aria-hidden', 'true');
      spacer.setAttribute('data-reply-flow-spacer', '');
      let frame = 0;
      let interacting = false;
      let userEditing = false;
      let reading = false;
      function restore() {
        panel.classList.remove('reply-panel-active', 'reply-panel-reading');
        delete panel.dataset.replyReading;
        form.classList.remove('reply-viewport-active');
        for (const key of ['top', 'left', 'width', 'height', 'bottom', 'menu-space', 'menu-height']) {
          panel.style.removeProperty('--reply-panel-' + key);
        }
        spacer.remove();
      }
      function update() {
        frame = 0;
        if (reading && phone.matches && viewport.scale === 1 && viewport.height >= 200) {
          const alreadyReading = panel.classList.contains('reply-panel-reading');
          if (!alreadyReading) restore();
          const rect = (alreadyReading ? spacer : panel).getBoundingClientRect();
          const statusBar = document.querySelector('.site-status-bar');
          const footerHeight = statusBar && getComputedStyle(statusBar).position === 'fixed' ? statusBar.getBoundingClientRect().height : 0;
          panel.style.setProperty('--reply-panel-bottom', (footerHeight + 8) + 'px');
          panel.style.setProperty('--reply-panel-left', Math.max(8, rect.left) + 'px');
          panel.style.setProperty('--reply-panel-width', Math.min(rect.width, window.innerWidth - 16) + 'px');
          panel.classList.add('reply-panel-reading');
          panel.dataset.replyReading = '1';
          spacer.style.height = (panel.getBoundingClientRect().height + footerHeight + 24) + 'px';
          if (!spacer.isConnected) panel.before(spacer);
          return;
        }
        const active = document.activeElement;
        const editing = interacting || panel.contains(active) || form.__imagePickerOpen ||
          (menu && menu.open && active === document.body && panel.classList.contains('reply-panel-active'));
        if (!phone.matches || !userEditing || !editing || viewport.scale !== 1 ||
            viewport.height < 200 || !keyboardUp() || !form.getClientRects().length) {
          restore();
          return;
        }
        const rect = (spacer.isConnected ? spacer : panel).getBoundingClientRect();
        const left = Math.max(viewport.offsetLeft + 8, rect.left);
        const right = Math.min(viewport.offsetLeft + viewport.width - 8, rect.right);
        if (right - left < 200) { restore(); return; }
        if (!spacer.isConnected) {
          spacer.style.height = rect.height + 'px';
          panel.before(spacer);
        }
        panel.style.setProperty('--reply-panel-top', (viewport.offsetTop + 8) + 'px');
        panel.style.setProperty('--reply-panel-left', left + 'px');
        panel.style.setProperty('--reply-panel-width', (right - left) + 'px');
        panel.style.setProperty('--reply-panel-height', (viewport.height - 16) + 'px');
        panel.style.setProperty('--reply-panel-menu-height', Math.max(44, Math.min(200, viewport.height - 240)) + 'px');
        panel.classList.add('reply-panel-active');
        form.classList.add('reply-viewport-active');
        // The existing menu stays in the form. Reserve its open footprint above
        // the action row instead of letting it obscure the editor or feedback.
        const list = menu && menu.open && menu.querySelector('.compose-action-menu-list');
        panel.style.setProperty('--reply-panel-menu-space', list ? (list.getBoundingClientRect().height + 4) + 'px' : '0px');
        const editorRect = body.getBoundingClientRect();
        const actionRect = actions.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const menuTop = list ? list.getBoundingClientRect().top : actionRect.top;
        // Large feedback, attachments, zoom or a small viewport must remain
        // reachable through normal page flow rather than an overflowing panel.
        if (editorRect.height < 79 || editorRect.bottom > menuTop + 1 ||
            editorRect.top < panelRect.top || actionRect.bottom > panelRect.bottom ||
            panel.scrollHeight > panel.clientHeight + 1) restore();
      }
      function schedule() {
        if (!frame) frame = window.requestAnimationFrame(update);
      }
      form.addEventListener('forum:reply-sent', function () {
        reading = true; userEditing = false; interacting = false;
        if (menu) menu.open = false;
        if (panel.contains(document.activeElement)) document.activeElement.blur();
        update();
      });
      body.addEventListener('focus', function () {
        if (reading) { reading = false; restore(); userEditing = true; schedule(); }
      });
      for (const event of ['pointerdown', 'keydown', 'input']) {
        body.addEventListener(event, function () { userEditing = true; schedule(); });
      }
      panel.addEventListener('compose-editing-intent', function () { userEditing = true; schedule(); });
      panel.addEventListener('pointerdown', function () { interacting = true; });
      function endInteraction() {
        window.setTimeout(function () { interacting = false; schedule(); }, 0);
      }
      window.addEventListener('pointerup', endInteraction);
      window.addEventListener('pointercancel', endInteraction);
      document.addEventListener('focusin', schedule);
      panel.addEventListener('focusout', schedule);
      panel.addEventListener('input', schedule);
      if (menu) menu.addEventListener('toggle', schedule);
      new MutationObserver(schedule).observe(panel, {
        subtree: true, childList: true, characterData: true,
        attributes: true, attributeFilter: ['hidden', 'data-kind', 'open']
      });
      viewport.addEventListener('resize', schedule);
      viewport.addEventListener('scroll', schedule);
      window.addEventListener('resize', schedule);
      window.addEventListener('scroll', schedule, {passive: true});
      phone.addEventListener('change', schedule);
      schedule();
    });
  });
})();

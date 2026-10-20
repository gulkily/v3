(function () {
  // Keep the canonical controls inside their form: signing and submitter handling
  // continue to use the original buttons, including anonymous submission.
  const viewport = window.visualViewport;
  if (!viewport) return;
  const phone = window.matchMedia('(max-width: 1024px) and (pointer: coarse)');
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-phone-reply]').forEach(function (form) {
      const body = form.querySelector('textarea[name="body"]');
      const actions = form.querySelector('.compose-form-actions');
      const menu = actions && actions.querySelector('.compose-action-menu');
      if (!body || !actions) return;
      let frame = 0;
      let interacting = false;
      let userEditing = false;
      function update() {
        frame = 0;
        const active = document.activeElement;
        const editing = interacting || active === body || actions.contains(active) ||
          (menu && menu.open && active === document.body && form.classList.contains('reply-viewport-active'));
        if (!phone.matches || !userEditing || !editing || viewport.scale !== 1 || !form.getClientRects().length) {
          form.classList.remove('reply-viewport-active');
          for (const key of ['top', 'left', 'width', 'space', 'writing-height', 'menu-height']) {
            form.style.removeProperty('--reply-viewport-' + key);
          }
          return;
        }
        const rect = form.getBoundingClientRect();
        const left = Math.max(viewport.offsetLeft + 8, rect.left);
        const right = Math.min(viewport.offsetLeft + viewport.width - 8, rect.right);
        if (right <= left || viewport.height < 150) {
          form.classList.remove('reply-viewport-active');
          return;
        }
        form.style.setProperty('--reply-viewport-left', left + 'px');
        form.style.setProperty('--reply-viewport-width', (right - left) + 'px');
        form.classList.add('reply-viewport-active');
        const height = actions.getBoundingClientRect().height;
        form.style.setProperty('--reply-viewport-top', (viewport.offsetTop + viewport.height - height - 8) + 'px');
        form.style.setProperty('--reply-viewport-space', (height + 16) + 'px');
        form.style.setProperty('--reply-viewport-writing-height', Math.max(60, viewport.height - height - 64) + 'px');
        form.style.setProperty('--reply-viewport-menu-height', Math.max(44, viewport.height - height - 24) + 'px');
      }
      function schedule() {
        if (!frame) frame = window.requestAnimationFrame(update);
      }
      for (const event of ['pointerdown', 'keydown', 'input']) {
        body.addEventListener(event, function () { userEditing = true; schedule(); });
      }
      actions.addEventListener('pointerdown', function () { interacting = true; });
      function endInteraction() {
        window.setTimeout(function () { interacting = false; schedule(); }, 0);
      }
      window.addEventListener('pointerup', endInteraction);
      window.addEventListener('pointercancel', endInteraction);
      if (menu) menu.addEventListener('toggle', schedule);
      document.addEventListener('focusin', schedule);
      form.addEventListener('focusout', schedule);
      viewport.addEventListener('resize', schedule);
      viewport.addEventListener('scroll', schedule);
      window.addEventListener('resize', schedule);
      window.addEventListener('scroll', schedule, {passive: true});
      phone.addEventListener('change', schedule);
      if (window.ResizeObserver) new ResizeObserver(schedule).observe(actions);
      schedule();
    });
  });
})();

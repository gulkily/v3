(function () {
  function bindInlineComposer(composer, stateKey) {
    const form = composer.querySelector('[data-compose-form]');
    const body = form && form.querySelector('textarea[name="body"]');
    if (!body) return;
    let engaged = false;

    function sync() {
      const editing = composer.contains(document.activeElement);
      if (editing) engaged = true;
      const attached = form.__imagePickerOpen || window.ForumImageUpload?.has(form);
      const warning = composer.querySelector('[data-kind="error"], [data-role="compose-field-normalization-status"]:not([hidden])');
      composer.dataset[stateKey] = editing || ((stateKey !== 'inlineThreadExpanded' || engaged) && body.value.trim() !== "") || attached || warning ? '1' : '0';
    }

    // The browser focuses the actual editor directly. Never transfer focus or
    // scroll the container while the software keyboard is opening.
    if (composer.hasAttribute('data-inline-reply-compose')) {
      document.querySelectorAll('[data-inline-reply-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
          if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey ||
              event.shiftKey || event.altKey || link.hasAttribute('download') ||
              (link.target && link.target !== '_self')) return;
          event.preventDefault();
          // A Reply link explicitly navigates to this editor; let native focus
          // reveal it once rather than adding another scrolling animation.
          body.dispatchEvent(new Event('compose-editing-intent', {bubbles: true}));
          body.focus();
        });
      });
    }
    composer.addEventListener('focusin', sync);
    composer.addEventListener('focusout', function () { window.setTimeout(sync, 0); });
    composer.addEventListener('input', sync);
    composer.addEventListener('change', sync);
    new MutationObserver(sync).observe(composer, {subtree: true, attributes: true, attributeFilter: ['hidden', 'data-kind']});
    window.addEventListener('pageshow', sync);
    sync();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-inline-thread-compose]").forEach(function (node) { bindInlineComposer(node, "inlineThreadExpanded"); });
    document.querySelectorAll("[data-inline-reply-compose]").forEach(function (node) { bindInlineComposer(node, "inlineReplyExpanded"); });
  });
})();

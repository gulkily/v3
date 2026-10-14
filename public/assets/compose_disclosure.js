(function () {
  var selector = 'details[data-compose-disclosure]';

  function value(form, name) {
    var field = form.elements ? form.elements[name] : null;
    return field && typeof field.value === 'string' ? field.value.trim() : '';
  }

  function summaryText(form, kind) {
    var parts = [];
    if (kind === 'tags-subject') {
      parts = [value(form, 'subject'), value(form, 'board_tags')];
    } else if (kind === 'event') {
      parts = [value(form, 'event_date'), value(form, 'event_time'), value(form, 'event_location')];
    }
    return parts.filter(function (part) { return part !== ''; }).join(' · ');
  }

  function refreshSummary(details) {
    var form = details.closest('form');
    var node = details.querySelector('[data-role="compose-disclosure-summary"]');
    if (form && node) {
      node.textContent = summaryText(form, details.getAttribute('data-compose-disclosure'));
    }
  }

  function openIfWarning(details) {
    var warnings = details.querySelectorAll('[data-role="compose-field-normalization-status"]');
    for (var i = 0; i < warnings.length; i += 1) {
      if (!warnings[i].hidden) {
        details.open = true;
        return;
      }
    }
  }

  function refreshAll(root) {
    Array.prototype.forEach.call(root.querySelectorAll(selector), function (details) {
      refreshSummary(details);
      openIfWarning(details);
    });
  }

  function init(details) {
    if (details.__composeDisclosureReady) {
      return;
    }
    details.__composeDisclosureReady = true;
    if (typeof MutationObserver === 'function') {
      new MutationObserver(function () { openIfWarning(details); })
        .observe(details, { subtree: true, attributes: true, attributeFilter: ['hidden'] });
    }
    details.addEventListener('input', function () { refreshSummary(details); });
    openIfWarning(details);
  }

  function start() {
    Array.prototype.forEach.call(document.querySelectorAll(selector), init);
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('[data-action="clear-compose-fields"]')) {
        var form = target.closest('form');
        if (form) {
          window.setTimeout(function () { refreshAll(form); }, 0);
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

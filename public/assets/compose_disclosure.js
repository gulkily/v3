(function () {
  var selector = 'details[data-compose-disclosure]';

  function value(form, name) {
    var field = form.elements ? form.elements[name] : null;
    return field && typeof field.value === 'string' ? field.value.trim() : '';
  }

  function summaryText(form, kind) {
    var parts = [];
    if (kind === 'tags') {
      parts = [value(form, 'board_tags')];
    } else if (kind === 'event') {
      var date = value(form, 'event_date');
      var endDate = value(form, 'event_end_date');
      parts = [date !== '' && endDate !== '' ? date + ' \u2013 ' + endDate : date, value(form, 'event_time'), value(form, 'event_location')];
    } else if (kind === 'options') {
      var markdown = form.querySelector('[data-role="markdown-toggle"]');
      var theme = form.querySelector('[data-role="thread-theme-choice"]');
      parts = [markdown && markdown.checked ? 'Markdown' : '', theme && theme.value !== '' && theme.selectedIndex >= 0 ? theme.options[theme.selectedIndex].text : ''];
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
    details.addEventListener('change', function () { refreshSummary(details); });
    // The Markdown box can tick itself as the body is typed (markdown_detect.js).
    var form = details.closest('form');
    if (form && details.getAttribute('data-compose-disclosure') === 'options') {
      form.addEventListener('input', function () { refreshSummary(details); });
    }
    openIfWarning(details);
  }

  var menuSelector = 'details.compose-action-menu';

  function closeMenus(except) {
    Array.prototype.forEach.call(document.querySelectorAll(menuSelector + '[open]'), function (menu) {
      if (menu !== except) {
        menu.open = false;
      }
    });
  }

  // The menu opens upward; when that would run past the top of the page (the
  // New post page on a phone) it opens downward.
  function placeMenu(menu) {
    var list = menu.querySelector('.compose-action-menu-list');
    menu.classList.remove('compose-action-menu--below');
    if (!menu.open || !list || menu.closest('.reply-panel-active')) {
      return;
    }
    if (list.getBoundingClientRect().top + window.scrollY < 0) {
      menu.classList.add('compose-action-menu--below');
    }
  }

  function startMenus() {
    // Capture: toggle does not bubble.
    document.addEventListener('toggle', function (event) {
      var menu = event.target && event.target.closest ? event.target.closest(menuSelector) : null;
      if (menu) {
        placeMenu(menu);
      }
    }, true);
    document.addEventListener('click', function (event) {
      var target = event.target;
      var menu = target && target.closest ? target.closest(menuSelector) : null;
      if (menu && target.closest('.compose-action-menu-list button')) {
        window.setTimeout(function () { menu.open = false; }, 0);
      }
      closeMenus(menu);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }
      var menu = document.querySelector(menuSelector + '[open]');
      if (menu) {
        menu.open = false;
        var toggle = menu.querySelector('summary');
        if (toggle) {
          toggle.focus();
        }
      }
    });
  }

  function start() {
    Array.prototype.forEach.call(document.querySelectorAll(selector), init);
    startMenus();
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

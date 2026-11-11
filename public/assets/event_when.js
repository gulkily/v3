// Fill in place: the When phrase is read with the poster's own date, and Date, Until and
// Time are filled where they are. Nothing is posted; the pickers show what will be saved.
// Without scripts, Fill submits the form and the server does the same with the UTC date.
(function () {
  function localToday() {
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
  }

  function timeZone() {
    try {
      return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (error) {
      return '';
    }
  }

  function field(form, name) {
    return form.elements ? form.elements[name] : null;
  }

  function value(form, name) {
    var node = field(form, name);
    return node && typeof node.value === 'string' ? node.value : '';
  }

  function setField(form, name, next) {
    var node = field(form, name);
    if (!node || node.value === next) {
      return;
    }
    node.value = next;
    // compose_disclosure.js and the draft store listen for input.
    node.dispatchEvent(new Event('input', { bubbles: true }));
    node.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function showStatus(box, message) {
    var status = box.parentNode ? box.parentNode.querySelector('[data-role="event-when-status"]') : null;
    if (!status) {
      return;
    }
    status.textContent = message;
    status.hidden = message === '';
  }

  function fill(box) {
    var form = box.closest('form');
    var button = box.querySelector('[data-action="fill-event-when"]');
    if (!form || !button || button.disabled) {
      return;
    }
    var date = field(form, 'event_date');
    var params = new URLSearchParams({
      phrase: value(form, 'event_when'),
      today: localToday(),
      time_zone: timeZone(),
      event_date: value(form, 'event_date'),
      event_end_date: value(form, 'event_end_date'),
      event_time: value(form, 'event_time'),
      event_date_fixed: (date && date.readOnly) || value(form, 'event_date_fixed') === '1' ? '1' : ''
    });
    button.disabled = true;
    fetch('/api/read_event_phrase?' + params.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload || payload.status !== 'ok') {
            throw new Error(payload && payload.error ? payload.error : 'Could not read that right now.');
          }
          return payload;
        });
      })
      .then(function (payload) {
        if (payload.filled) {
          setField(form, 'event_date', payload.event_date || '');
          setField(form, 'event_end_date', payload.event_end_date || '');
          setField(form, 'event_time', payload.event_time || '');
        }
        showStatus(box, payload.message || '');
      })
      .catch(function (error) {
        showStatus(box, error && error.message ? error.message : 'Could not read that right now.');
      })
      .then(function () {
        button.disabled = false;
      });
  }

  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-action="fill-event-when"]') : null;
    if (!button) {
      return;
    }
    event.preventDefault();
    fill(button.closest('[data-role="event-when"]'));
  });

  // Enter in the When box means Fill, not Post.
  document.addEventListener('keydown', function (event) {
    var input = event.target;
    if (event.key !== 'Enter' || !input || input.name !== 'event_when' || event.isComposing) {
      return;
    }
    event.preventDefault();
    fill(input.closest('[data-role="event-when"]'));
  });
})();

(function () {
  'use strict';
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-high-traffic-control], [data-high-traffic-setting]'));
  if (!rows.length) return;
  function setting(row) { return row.getAttribute('data-high-traffic-setting') || 'mode'; }
  async function request(path, fields) {
    var response = await fetch(path, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields).toString() });
    var result = await response.json();
    if (!response.ok || result.status !== 'ok') throw new Error(result.error || 'Unable to switch mode.');
    return result;
  }
  function show(row, on, note, unavailable) {
    var button = row.querySelector('form button');
    var state = row.querySelector('[data-high-traffic-state]');
    var noteLine = row.querySelector('[data-high-traffic-note]');
    button.dataset.enabled = String(on); button.setAttribute('aria-checked', String(on));
    button.disabled = Boolean(unavailable) && !on;
    state.textContent = on ? 'enabled' : 'disabled'; state.classList.toggle('is-on', on);
    if (note !== undefined) { noteLine.textContent = note || ''; noteLine.hidden = !note; }
  }
  // The server's status after any change updates every row, since one switch can move another.
  function update(result) {
    rows.forEach(function (row) {
      var name = setting(row);
      if (name === 'mode') show(row, result.effective_mode === 'high-traffic', result.status_reason);
      else if (name === 'turn-on') show(row, result.turn_on_automatically === true);
      else show(row, result.turn_off_automatically === true, result.turn_off_unavailable || '', result.turn_off_unavailable);
    });
  }
  rows.forEach(function (row) {
    var form = row.querySelector('form');
    var button = form.querySelector('button');
    var status = row.querySelector('[data-high-traffic-status]');
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (button.disabled) return;
      button.disabled = true; status.hidden = false; status.textContent = 'Switching…';
      try {
        if (!window.ForumBrowserSigning) throw new Error('Browser signing is unavailable. Reload and try again.');
        await window.ForumBrowserSigning.ensureActionIdentity(null, null);
        var prepared = await request('/api/prepare_high_traffic', { setting: setting(row), enabled: button.dataset.enabled === 'true' ? 'false' : 'true' });
        var signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
        var result = await request('/api/finalize_high_traffic', { prepare_token: prepared.prepare_token,
          canonical_record: prepared.canonical_record, detached_signature: signature });
        status.textContent = ''; status.hidden = true;
        update(result);
      } catch (error) {
        status.textContent = (error.message || 'Unable to switch mode.') + (setting(row) === 'mode' ? ' Reload, or CLI recovery: ./v3 high-traffic restore' : '');
        button.disabled = false;
      }
    });
  });
}());

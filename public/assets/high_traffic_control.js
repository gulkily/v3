(function () {
  'use strict';
  var row = document.querySelector('[data-high-traffic-control]');
  if (!row) return;
  var form = row.querySelector('form');
  var button = form.querySelector('button');
  var state = row.querySelector('[data-high-traffic-state]');
  var status = row.querySelector('[data-high-traffic-status]');
  async function request(path, fields) {
    var response = await fetch(path, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields).toString() });
    var result = await response.json();
    if (!response.ok || result.status !== 'ok') throw new Error(result.error || 'Unable to switch mode.');
    return result;
  }
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (button.disabled) return;
    button.disabled = true; status.hidden = false; status.textContent = 'Switching…';
    try {
      if (!window.ForumBrowserSigning) throw new Error('Browser signing is unavailable. Reload and try again.');
      await window.ForumBrowserSigning.ensureActionIdentity(null, null);
      var prepared = await request('/api/prepare_high_traffic', { enabled: button.dataset.enabled === 'true' ? 'false' : 'true' });
      var signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
      var result = await request('/api/finalize_high_traffic', { prepare_token: prepared.prepare_token,
        canonical_record: prepared.canonical_record, detached_signature: signature });
      var enabled = result.effective_mode === 'high-traffic';
      button.dataset.enabled = String(enabled); button.setAttribute('aria-checked', String(enabled));
      state.textContent = enabled ? 'enabled' : 'disabled'; state.classList.toggle('is-on', enabled);
      status.textContent = ''; status.hidden = true;
    } catch (error) {
      status.textContent = (error.message || 'Unable to switch mode.') + ' Reload, or CLI recovery: ./v3 high-traffic restore';
    } finally { button.disabled = false; }
  });
}());

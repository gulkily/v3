(function () {
  'use strict';
  var dialog = document.querySelector('[data-fastmod-dialog]');
  var open = document.querySelector('[data-fastmod-open]');
  if (!dialog || !open) return;
  var form = dialog.querySelector('form');
  var status = dialog.querySelector('[data-fastmod-status]');
  var pending = false;
  open.addEventListener('click', function () { dialog.showModal(); });
  dialog.querySelector('[data-fastmod-close]').addEventListener('click', function () { if (!pending) dialog.close(); });
  dialog.addEventListener('cancel', function (event) { if (pending) event.preventDefault(); });
  async function request(path, fields) {
    var response = await fetch(path, { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields).toString() });
    var result = await response.json();
    if (!response.ok || result.status !== 'ok') throw new Error(result.error || 'Unable to save rubrics.');
    return result;
  }
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (pending) return;
    var selected = Array.from(form.querySelectorAll('input:checked')).map(function (input) { return input.value; });
    if (!selected.length) { status.textContent = 'Enable at least one rubric.'; return; }
    pending = true;
    form.querySelectorAll('button, input').forEach(function (control) { control.disabled = true; });
    status.textContent = 'Saving…';
    try {
      if (!window.ForumBrowserSigning) throw new Error('Browser signing is unavailable. Reload and try again.');
      await window.ForumBrowserSigning.ensureActionIdentity(null, null);
      var fields = selected.map(function (id) { return ['rubrics[]', id]; });
      var prepared = await request('/api/prepare_fastmod_rubrics', fields);
      var signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
      await request('/api/finalize_fastmod_rubrics', { prepare_token: prepared.prepare_token,
        canonical_record: prepared.canonical_record, detached_signature: signature });
      status.textContent = 'Saved.';
      window.location.reload();
    } catch (error) {
      status.textContent = error.message || 'Unable to save rubrics.';
    } finally {
      pending = false;
      form.querySelectorAll('button, input').forEach(function (control) { control.disabled = false; });
    }
  });
}());

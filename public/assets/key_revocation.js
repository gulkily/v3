(function () {
  "use strict";
  const review = document.querySelector('[data-key-revocation-review]');
  if (!review) return;
  const find = name => review.querySelector('[data-revocation-' + name + ']');
  const confirm = find('confirm'), cancel = find('cancel'), feedback = find('feedback');
  let pending = null, signature = null, origin = null, busy = false, mustReview = false;
  const say = text => { feedback.textContent = text; feedback.hidden = !text; };
  async function request(path, fields) {
    const response = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields) });
    let result;
    try { result = await response.json(); } catch { const error = new Error('The server response could not be read. Retry or reload to check the account.'); error.outcomeUnknown = true; throw error; }
    if (!response.ok || result.status !== 'ok') throw new Error(result.error || 'The request failed. Retry or reload to check the account.');
    return result;
  }
  function buttons(disabled) { busy = disabled; confirm.disabled = disabled; cancel.disabled = disabled; }
  function show() { review.hidden = false; find('heading').focus(); review.scrollIntoView({ block: 'nearest', behavior: 'auto' }); }
  async function prepare(button) {
    if (busy) return;
    origin = button; pending = null; signature = null; mustReview = false;
    buttons(true); button.disabled = true; say(''); find('reload').hidden = true;
    try {
      const prepared = await request('/api/prepare_key_revocation', { target_identity_id: button.dataset.revokeKey });
      const data = prepared.revocation_review;
      if (!data || !data.target_identity_id || !data.revocation_cutoff || data.target_identity_id !== button.dataset.revokeKey) throw new Error('Revocation review is incomplete. Reload the account and try again.');
      const fields = Object.fromEntries(String(prepared.canonical_record || '').split('\n').filter(line => line.includes(': ')).map(line => { const i = line.indexOf(': '); return [line.slice(0, i), line.slice(i + 2)]; }));
      if (fields['Target-Identity-ID'] !== data.target_identity_id || fields.Username !== data.username || fields['Revocation-Cutoff'] !== data.revocation_cutoff) throw new Error('Signed request does not match the review. Reload the account and try again.');

      pending = prepared;
      find('heading').textContent = 'Revoke ' + data.username + '’s account key?';
      find('account').textContent = data.username; find('key').textContent = data.target_identity_id;
      find('cutoff').textContent = data.revocation_cutoff;
      find('last-key').hidden = data.active_key_count !== 1;
      confirm.textContent = 'Sign & revoke key'; confirm.hidden = false; cancel.textContent = 'Cancel';
      show();
    } catch (error) {
      find('heading').textContent = 'Revocation unavailable'; confirm.hidden = true;
      find('account').textContent = ''; find('key').textContent = button.dataset.revokeKey;
      find('cutoff').textContent = ''; find('last-key').hidden = true;
      say(error.message); find('reload').hidden = false; show();
    } finally { buttons(false); button.disabled = false; }
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-revoke-key]');
    if (button) { event.preventDefault(); void prepare(button); }
  });
  cancel.addEventListener('click', () => {
    if (busy) return;
    pending = null; signature = null; review.hidden = true; if (origin) origin.focus();
  });
  confirm.addEventListener('click', async () => {
    if (busy || !pending) return;
    if (mustReview) { await prepare(origin); return; }
    buttons(true); say('');
    let submitted = false;
    try {
      if (!signature) signature = await window.ForumBrowserSigning.signCanonicalRecord(pending.canonical_record);
      submitted = true;
      await request('/api/create_prepared_key_revocation', { prepare_token: pending.prepare_token, record_path: pending.record_path, canonical_record: pending.canonical_record, detached_signature: signature });
      window.location.reload();
    } catch (error) {
      say(submitted && (error instanceof TypeError || error.outcomeUnknown) ? 'Could not confirm the outcome. Retry or reload to check whether the key was revoked.' : error.message);
      if (submitted) cancel.textContent = 'Close';
      find('reload').hidden = false;
      // Keep the exact signed bytes after a lost response; committed retry is idempotent.
      mustReview = /changed|expired|review again|stale/i.test(error.message) && !/committed/i.test(error.message);
      confirm.textContent = mustReview ? 'Review again' : 'Retry';
    } finally { buttons(false); }
  });
})();

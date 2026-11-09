(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-operator-grant]');
    if (!root) return;
    const review = root.querySelector('[data-operator-grant-review]');
    const confirm = root.querySelector('[data-operator-grant-confirm]');
    const question = root.querySelector('[data-operator-grant-question]');
    const submit = root.querySelector('[data-operator-grant-submit]');
    const cancel = root.querySelector('[data-operator-grant-cancel]');
    const feedback = root.querySelector('[data-operator-grant-feedback]');
    let prepared = null, signed = null, actor = '';
    function say(message) { feedback.textContent = message; feedback.hidden = false; }
    async function request(path, payload) {
      const response = await fetch(path, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || result.status === 'error') throw new Error(result.error || 'Unable to make this key an operator.');
      return result;
    }
    review.addEventListener('click', async function () {
      review.disabled = true;
      try {
        actor = 'openpgp:' + (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
        prepared = await request('/api/prepare_operator_grant', { target_identity_id: root.dataset.targetIdentityId });
        signed = null;
        question.textContent = 'Make ' + prepared.target_username + ' (' + root.dataset.targetIdentityId.slice(8, 16) + '…) a root operator? This key will be able to do everything root can.';
        feedback.hidden = true; confirm.hidden = false; review.hidden = true;
        submit.focus();
      } catch (error) { say(error.message); }
      finally { review.disabled = false; }
    });
    cancel.addEventListener('click', function () {
      prepared = signed = null; confirm.hidden = true; review.hidden = false; feedback.hidden = true; review.focus();
    });
    submit.addEventListener('click', async function () {
      if (!prepared) return;
      submit.disabled = cancel.disabled = true;
      try {
        if (!signed) {
          if (!window.ForumBrowserSigning) throw new Error('Browser signing is unavailable. Refresh and retry.');
          signed = { prepare_token: prepared.prepare_token, record_id: prepared.record_id, record_path: prepared.record_path,
            canonical_record: prepared.canonical_record,
            detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record, actor) };
        }
        await request('/api/finalize_operator_grant', signed);
        window.location.reload();
      } catch (error) {
        // An uncertain outcome may have committed; reloading shows the key's current status.
        say(error.message + ' Reload the page to see whether the key is now an operator.');
      } finally { submit.disabled = cancel.disabled = false; }
    });
  });
})();

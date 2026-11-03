(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-key-retirement]');
    if (!root) return;
    const review = root.querySelector('[data-retirement-review]');
    const confirm = root.querySelector('[data-retirement-confirm]');
    const submit = root.querySelector('[data-retirement-submit]');
    const cancel = root.querySelector('[data-retirement-cancel]');
    const feedback = root.querySelector('[data-retirement-feedback]');
    let prepared = null, signed = null, actor = '';
    function say(message) { feedback.textContent = message; feedback.hidden = false; }
    async function request(path, payload) {
      const response = await fetch(path, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || result.status === 'error') throw new Error(result.error || 'Retirement failed.');
      return result;
    }
    review.addEventListener('click', async function () {
      review.disabled = true;
      try {
        actor = 'openpgp:' + (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
        prepared = await request('/api/prepare_key_retirement', { actor_identity_id: actor, target_identity_id: root.dataset.targetIdentityId });
        signed = null;
        root.querySelector('[data-retirement-warning]').textContent = prepared.retirement_review.warning;
        root.querySelector('[data-retirement-target]').textContent = prepared.retirement_review.target_identity_id;
        root.querySelector('[data-retirement-replacement]').textContent = prepared.retirement_review.replacement_identity_id;
        feedback.hidden = true; confirm.hidden = false; review.hidden = true; submit.textContent = 'Retire permanently';
        submit.focus();
      } catch (error) { say(error.message || 'Unable to review retirement.'); }
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
          signed = { prepare_token: prepared.prepare_token, canonical_record: prepared.canonical_record,
            detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record, actor) };
        }
        const result = await request('/api/finalize_key_retirement', signed);
        if (result.status !== 'retired') throw new Error('Retirement was not confirmed. Retry the same request.');
        if (result.refresh === 'pending') { say(result.warning); submit.textContent = 'Retry refresh'; return; }
        window.location.reload();
      } catch (error) {
        // Keep the exact signed request in this page for an uncertain-outcome retry.
        say(error.message || 'Unable to confirm retirement. Retry the same request.');
        submit.textContent = signed ? 'Retry retirement' : 'Retire permanently';
      } finally { submit.disabled = cancel.disabled = false; }
    });
  });
})();

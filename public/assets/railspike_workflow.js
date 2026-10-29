(function () {
  'use strict';
  const endpoint = '/api/railspike_workflow';
  let busy = false, pending = null, timer = null, controller = null, failures = 0, stopped = false, locked = false, suspended = false;
  let initialIdentity = '', draftStorageFailed = false;
  const memoryDrafts = new Map();
  function identity() {
    try { return ['fingerprint', 'public_key', 'private_key'].map(k => localStorage.getItem('forum_pki_' + k) || '').join('|'); }
    catch (_) { return ''; }
  }
  function notice(message, draftWarning = false) {
    mounts().forEach(m => {
      const attribute = draftWarning ? 'data-workflow-draft-warning' : 'data-workflow-notice';
      let alert = m.querySelector('[' + attribute + ']');
      if (!alert) { alert = node('p'); alert.setAttribute(attribute, ''); alert.setAttribute('role', 'alert'); m.append(alert); }
      alert.textContent = message;
    });
  }
  function stop(message, terminal = true) {
    stopped = true; locked = terminal; clearTimeout(timer);
    if (controller) controller.abort();
    if (message) notice(message);
    if (terminal) mounts().forEach(m => m.querySelectorAll('button').forEach(b => { b.disabled = true; }));
  }
  function drafts(mount) {
    const key = 'forum_railspike_draft:' + initialIdentity.split('|')[0] + ':' + mount.dataset.railspikePost;
    let saved = memoryDrafts.get(key);
    if (!saved) {
      saved = {};
      try { const value = JSON.parse(sessionStorage.getItem(key) || '{}'); if (value && typeof value === 'object' && !Array.isArray(value)) saved = value; } catch (_) {}
      memoryDrafts.set(key, saved);
    }
    const write = () => {
      try { sessionStorage.setItem(key, JSON.stringify(saved)); }
      catch (_) { draftStorageFailed = true; notice('Draft cannot be saved on this device. Keep this page open or copy your text before reloading.', true); }
    };
    return { saved, write };
  }
  function field(mount, panel, labelText, id, maxLength) {
    const label = node('label', labelText), input = node('textarea'), draft = drafts(mount);
    input.maxLength = maxLength; input.value = draft.saved[id]?.value || ''; input.dataset.draftId = id;
    input.addEventListener('input', () => {
      const current = drafts(mount);
      if (input.value) current.saved[id] = { label: labelText, value: input.value };
      else delete current.saved[id];
      current.write();
    });
    label.append(input); panel.append(label); return input;
  }
  async function submitDraft(mount, inputs, send) {
    const values = inputs.map(input => input.value);
    await send();
    const draft = drafts(mount);
    inputs.forEach((input, i) => { if (draft.saved[input.dataset.draftId]?.value === values[i]) delete draft.saved[input.dataset.draftId]; });
    draft.write();
  }
  function savedDrafts(mount, panel) {
    const used = new Set(Array.from(panel.querySelectorAll('[data-draft-id]')).map(input => input.dataset.draftId));
    for (const [id, item] of Object.entries(drafts(mount).saved)) {
      if (used.has(id) || !item.value) continue;
      const details = node('details'); details.append(node('summary', 'Saved draft: ' + item.label));
      const text = node('textarea'); text.value = item.value; text.readOnly = true; details.append(text);
      button('Discard saved draft', async () => { const draft = drafts(mount); delete draft.saved[id]; draft.write(); details.remove(); }, details);
      panel.append(details);
    }
  }
  const mounts = () => Array.from(document.querySelectorAll('[data-railspike-post]')).filter(m => !m.closest('[data-railspike-shell]').hidden);
  function node(tag, text) { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; return el; }
  async function request(data) {
    const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: {
      'Content-Type': 'application/json', 'X-Requested-With': 'ForumRailspike', Accept: 'application/json'
    }, body: JSON.stringify(data) });
    const result = await response.json();
    if (response.status === 401 || response.status === 403) stop(result.error || 'Sign in and reload to resume. Your draft is retained.');
    if (!response.ok) throw new Error(result.error || 'Workflow unavailable.');
    return result;
  }
  async function act(action, postId, workflow, payload) {
    const signing = window.ForumBrowserSigning;
    if (!signing) throw new Error('Browser signing is unavailable. Reload this page.');
    const prepared = await request({ operation: 'prepare', action, post_id: postId,
      workflow_id: workflow ? workflow.id : '', version: workflow ? workflow.version : 0, payload: payload || {} });
    const signature = await signing.signCanonicalRecord(prepared.signed_text);
    return request({ signed_text: prepared.signed_text, signature });
  }
  function button(label, callback, parent) {
    const b = node('button', label); b.type = 'button';
    b.addEventListener('click', async () => {
      if (busy) return;
      busy = true; b.disabled = true;
      try { if (controller) controller.abort(); if (pending) await pending; await callback(); busy = false; await refresh(); }
      catch (error) { if (!locked) { const message = node('p', error.message); message.setAttribute('role', 'alert'); parent.append(message); } }
      finally { busy = false; b.disabled = locked; }
    });
    parent.append(b); return b;
  }
  function render(mount, eligible, row) {
    // Preserve an open panel or typed response across status polling.
    if (mount.dataset.version === (row ? String(row.version) + row.state : 'start:' + eligible)) return;
    mount.dataset.version = row ? String(row.version) + row.state : 'start:' + eligible;
    mount.style.flexBasis = '100%'; mount.style.minWidth = '0'; mount.style.width = '100%';
    const expanded = Array.from(mount.querySelectorAll('details[open]')).map(d => d.querySelector('summary')?.textContent);
    mount.replaceChildren(); mount.hidden = !eligible && !row;
    if (mount.hidden) return;
    const menu = node('details'); menu.append(node('summary', '…'));
    if (!row) button('Start development', () => act('start', mount.dataset.railspikePost), menu);
    else menu.append(node('p', 'Development request already started.'));
    mount.append(menu);
    if (!row) return;
    const panel = node('section'); panel.setAttribute('aria-label', 'Site development');
    panel.append(node('p', 'Railspike Step 1: ' + row.state));
    button('Refresh status', async () => {}, panel);
    const link = node('a', 'Original thread'); link.href = '/threads/' + encodeURIComponent(row.thread_id) + '#post-' + encodeURIComponent(row.post_id); panel.append(link);
    if (row.failure) panel.append(node('p', row.failure));
    if (row.state === 'failed') button('Retry assessment', () => act('retry', row.post_id, row), panel);
    for (const revision of row.revisions) {
      const details = node('details'); details.open = revision === row.revisions[row.revisions.length - 1];
      details.append(node('summary', 'Revision ' + revision.number + ': ' + revision.kind));
      for (const question of revision.questions) details.append(node('p', question));
      if (revision.artifact) { const pre = node('pre', revision.artifact); pre.style.whiteSpace = 'pre-wrap'; pre.style.overflowWrap = 'anywhere'; details.append(pre); }
      panel.append(details);
    }
    const latest = row.revisions[row.revisions.length - 1];
    if (row.state === 'questions' && latest) {
      const answers = latest.questions.map((question, i) => field(mount, panel, question, 'answer:' + latest.number + ':' + i, 8000));
      button('Send answers', () => submitDraft(mount, answers, () => act('answer', row.post_id, row, { revision: latest.number, answers: answers.map(a => a.value) })), panel);
    }
    if (['review', 'approved'].includes(row.state) && latest) {
      const input = field(mount, panel, 'Requested changes', 'changes:' + latest.number, 16000);
      if (row.state === 'review') button('Approve Step 1', () => act('approve', row.post_id, row, { revision: latest.number, digest: latest.digest }), panel);
      button('Request changes', () => submitDraft(mount, [input], () => act('changes', row.post_id, row, { revision: latest.number, text: input.value })), panel);
    }
    if (row.handoff) panel.append(node('p', row.handoff));
    for (const item of row.history) {
      const entry = node('details'); entry.append(node('summary', item.action + ' · ' + item.actor));
      if (item.answers) item.answers.forEach((a, i) => entry.append(node('p', item.questions[i] + ': ' + a)));
      if (item.text) entry.append(node('p', item.text));
      panel.append(entry);
    }
    savedDrafts(mount, panel);
    mount.append(panel);
    if (draftStorageFailed) notice('Draft cannot be saved on this device. Keep this page open or copy your text before reloading.', true);
    mount.querySelectorAll('details').forEach(d => { if (expanded.includes(d.querySelector('summary')?.textContent)) d.open = true; });
  }
  function schedule() {
    clearTimeout(timer);
    if (!stopped && !suspended && !document.hidden && mounts().length) timer = setTimeout(() => refresh(false), 5000);
  }
  async function refresh(manual = true) {
    if (locked || suspended || document.hidden || busy || !mounts().length) return;
    if (identity() !== initialIdentity) { stop(); mounts().forEach(m => { m.closest('[data-railspike-shell]').hidden = true; }); return; }
    if (pending) return pending;
    if (manual) { stopped = false; failures = 0; }
    if (stopped) return;
    clearTimeout(timer);
    controller = new AbortController();
    const signal = controller.signal, timeout = setTimeout(() => controller.abort(), 10000);
    pending = (async () => {
      try {
        for (const id of new Set(mounts().map(m => m.dataset.railspikeThread))) {
          const response = await fetch(endpoint + '?thread_id=' + encodeURIComponent(id), { credentials: 'same-origin', cache: 'no-store', signal });
          if (response.status === 401 || response.status === 403) {
            stop('Development is unavailable. Re-enable it or sign in, then reload. Your draft is retained.'); return;
          }
          if (!response.ok) throw new Error('Workflow unavailable.');
          const result = await response.json();
          if (signal.aborted || stopped || document.hidden || identity() !== initialIdentity) return;
          if (!result.eligible.length) {
            stop(); mounts().forEach(m => { m.closest('[data-railspike-shell]').hidden = true; }); return;
          }
          mounts().filter(m => m.dataset.railspikeThread === id).forEach(m => render(m, result.eligible.includes(m.dataset.railspikePost), result.workflows.find(w => w.post_id === m.dataset.railspikePost)));
        }
        failures = 0;
        mounts().forEach(m => m.querySelector('[data-workflow-notice]')?.remove());
      } catch (_) {
        if (!document.hidden && !suspended && !busy && !stopped && ++failures >= 3) stop('Status updates stopped. Refresh status or reload to retry. Your draft is retained.', false);
      } finally { clearTimeout(timeout); pending = null; controller = null; schedule(); }
    })();
    return pending;
  }
  window.ForumRailspike = { act, refresh, button, node };
  async function init() {
    if (!mounts().length) return;
    initialIdentity = identity();
    // Always offer recovery, including when the first status request fails.
    mounts().forEach(m => button('Refresh status', () => { stopped = false; failures = 0; }, m));
    try {
      const auth = await fetch('/api/auth_status', { credentials: 'same-origin', cache: 'no-store' });
      if (auth.status === 401 && window.PrivateSiteAuth && localStorage.getItem('forum_pki_private_key')) await window.PrivateSiteAuth.authenticate({ navigate: false });
      await refresh();
    } catch (_) { stop('Sign in and reload to resume. Your draft is retained.'); }
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) { clearTimeout(timer); if (controller) controller.abort(); }
      else if (!stopped) { if (pending) pending.finally(() => refresh(false)); else refresh(false); }
    });
    window.addEventListener('pagehide', () => { suspended = true; clearTimeout(timer); if (controller) controller.abort(); });
    window.addEventListener('pageshow', () => { suspended = false; if (!stopped) refresh(false); });
    const identityChanged = () => { if (identity() !== initialIdentity) { stop(); mounts().forEach(m => { m.closest('[data-railspike-shell]').hidden = true; }); } };
    window.addEventListener('storage', identityChanged);
    window.addEventListener('forum:identity-changed', identityChanged);
  }
  document.addEventListener('DOMContentLoaded', init);
})();

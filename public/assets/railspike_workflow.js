(function () {
  'use strict';
  const endpoint = '/api/railspike_workflow';
  let busy = false;
  const mounts = () => Array.from(document.querySelectorAll('[data-railspike-post]'));
  function node(tag, text) { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; return el; }
  async function request(data) {
    const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: {
      'Content-Type': 'application/json', 'X-Requested-With': 'ForumRailspike', Accept: 'application/json'
    }, body: JSON.stringify(data) });
    const result = await response.json();
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
      try { await callback(); busy = false; await refresh(); }
      catch (error) { const message = node('p', error.message); message.setAttribute('role', 'alert'); parent.append(message); }
      finally { busy = false; b.disabled = false; }
    });
    parent.append(b); return b;
  }
  function render(mount, eligible, row) {
    // Preserve an open panel or typed response across status polling.
    if (mount.dataset.version === (row ? String(row.version) + row.state : 'start')) return;
    mount.dataset.version = row ? String(row.version) + row.state : 'start';
    mount.style.flexBasis = '100%'; mount.style.minWidth = '0'; mount.style.width = '100%';
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
    for (const revision of row.revisions) {
      const details = node('details'); details.open = revision === row.revisions[row.revisions.length - 1];
      details.append(node('summary', 'Revision ' + revision.number + ': ' + revision.kind));
      for (const question of revision.questions) details.append(node('p', question));
      if (revision.artifact) { const pre = node('pre', revision.artifact); pre.style.whiteSpace = 'pre-wrap'; pre.style.overflowWrap = 'anywhere'; details.append(pre); }
      panel.append(details);
    }
    mount.append(panel);
  }
  async function refresh() {
    if (busy || document.hidden) return;
    for (const id of new Set(mounts().map(m => m.dataset.railspikeThread))) {
      const response = await fetch(endpoint + '?thread_id=' + encodeURIComponent(id), { credentials: 'same-origin', cache: 'no-store' });
      if (response.status === 403) { mounts().forEach(m => { m.replaceChildren(); m.hidden = true; delete m.dataset.version; }); return; }
      if (!response.ok) continue;
      const result = await response.json();
      mounts().filter(m => m.dataset.railspikeThread === id).forEach(m => render(m, result.eligible.includes(m.dataset.railspikePost), result.workflows.find(w => w.post_id === m.dataset.railspikePost)));
    }
  }
  window.ForumRailspike = { act, refresh, button, node };
  async function init() {
    if (!mounts().length) return;
    try { if (window.PrivateSiteAuth && localStorage.getItem('forum_pki_private_key')) await window.PrivateSiteAuth.authenticate(); await refresh(); } catch (_) { /* Account controls handle login failures. */ }
    let attempts = 0;
    const timer = setInterval(() => { if (++attempts > 120) { clearInterval(timer); return; } refresh().catch(() => {}); }, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh().catch(() => {}); });
  }
  document.addEventListener('DOMContentLoaded', init);
})();

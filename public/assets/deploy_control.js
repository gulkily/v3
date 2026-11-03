// Deploy page actions. Without this script the form posts normally and the
// server answers with the page and a notice; with it, requests go as JSON and
// the page reloads when there is a new state to show.
(() => {
  const form = document.querySelector('[data-deploy-form]');
  const status = document.querySelector('[data-deploy-status]');
  const root = document.querySelector('[data-deploy-root]');
  if (!form || !status) return;
  const buttons = Array.from(form.querySelectorAll('button'));
  const say = (text) => { status.textContent = text; status.hidden = text === ''; };
  const setBusy = (on) => buttons.forEach((button) => { button.disabled = on; });
  const post = async (action) => {
    const response = await fetch('/tools/deploy/', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumDeploy' },
      body: JSON.stringify({ action }),
    });
    const data = await response.json().catch(() => ({ status: 'error', error: `Unexpected response (${response.status}).` }));
    return response.ok ? data : { status: 'error', error: data.error || `Request failed (${response.status}).` };
  };

  let polling = false;
  // A queued deploy waits for cron; check every few seconds and reload when
  // it finishes. Buttons stay usable so Deploy now remains available.
  const poll = async () => {
    if (polling) return;
    polling = true;
    const deadline = Date.now() + 15 * 60 * 1000;
    while (Date.now() < deadline) {
      await new Promise((resolve) => setTimeout(resolve, 5000));
      let data;
      try { data = await post('status'); } catch { continue; }
      if (data.status !== 'ok') { say(data.error); break; }
      const task = data.state.task_status;
      if (task !== 'queued' && task !== 'running' && !data.state.busy) { window.location.reload(); return; }
      say(task === 'running' || data.state.busy ? 'Deploying…' : 'Queued; waiting for the task worker…');
    }
    polling = false;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const action = (event.submitter && event.submitter.value) || 'queue';
    setBusy(true);
    say(action === 'check' ? 'Checking GitHub…' : action === 'now' ? 'Deploying…' : 'Queuing…');
    let data;
    try {
      data = await post(action);
    } catch {
      setBusy(false);
      say('The connection dropped. The deploy may still have run; reload to see the latest result.');
      return;
    }
    setBusy(false);
    if (data.status !== 'ok') { say(data.error || 'Request refused.'); return; }
    const result = data.result;
    if (result.outcome === 'queued') { say(result.message); poll(); return; }
    if (result.outcome === 'refused' || result.outcome === 'failed') { say(result.message); return; }
    window.location.reload();
  });

  if (root && root.dataset.deployPending === '1') poll();
})();

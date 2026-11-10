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
  // A deploy from this page turns on the header prompt in this browser (deploy_prompt.js).
  let startedHere = false;
  const remember = () => { try { window.localStorage.setItem('forum_deploy_prompt', '1'); } catch { /* storage unavailable */ } };
  const post = async (action) => {
    const response = await fetch('/tools/deploy/', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumDeploy' },
      body: JSON.stringify({ action }),
    });
    const data = await response.json().catch(() => null);
    if (response.ok && data) return data;
    // A deploy swaps code and site data under the server, so a busy or
    // gateway reply now usually means "try again shortly", not a failure.
    const transient = [502, 503, 504].includes(response.status);
    return { status: 'error', transient, error: (data && data.error) || `Request failed (${response.status}).` };
  };

  // How long a queued deploy has waited, so the delay is visible rather than
  // looking stuck. Starts from the task's request time when the page knows it.
  let queuedAt = root && root.dataset.deployQueuedAt ? Date.parse(root.dataset.deployQueuedAt) : NaN;
  const waited = () => {
    if (Number.isNaN(queuedAt)) return '';
    const seconds = Math.max(0, Math.round((Date.now() - queuedAt) / 1000));
    return ` ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
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
      if (data.transient) { say('Server busy; checking again…'); continue; }
      if (data.status !== 'ok') { say(data.error); break; }
      const task = data.state.task_status;
      const rebuild = data.state.rebuild_status;
      const deploying = task === 'queued' || task === 'running' || data.state.busy;
      const rebuilding = rebuild === 'queued' || rebuild === 'running';
      if (!deploying && !rebuilding) {
        if (startedHere && data.state.latest && data.state.latest.Outcome === 'deployed') remember();
        window.location.reload();
        return;
      }
      if (deploying) {
        say(task === 'running' || data.state.busy ? 'Deploying…' : `Queued; waiting for the task worker…${waited()}`);
      } else {
        say(rebuild === 'running' ? `Rebuilding site data… ${data.state.rebuild_progress || ''}`.trim() : 'Site data rebuild waiting for the task worker…');
      }
    }
    polling = false;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const action = (event.submitter && event.submitter.value) || 'queue';
    if (action !== 'check') startedHere = true;
    setBusy(true);
    say(action === 'check' ? 'Checking GitHub…' : action === 'now' ? 'Deploying…' : 'Queuing…');
    // The deploy may have run even when its reply was lost; follow the status instead.
    const followStatus = () => { say('Checking whether the deploy ran…'); poll(); };
    let data;
    try {
      data = await post(action);
    } catch {
      setBusy(false);
      if (action === 'check') say('The connection dropped; try again.'); else followStatus();
      return;
    }
    setBusy(false);
    if (data.transient && action !== 'check') { followStatus(); return; }
    if (data.status !== 'ok') { say(data.error || 'Request refused.'); return; }
    const result = data.result;
    if (result.outcome === 'queued') {
      if (Number.isNaN(queuedAt)) queuedAt = Date.now();
      say(`${result.message} It usually starts within a minute.`);
      poll();
      return;
    }
    if (result.outcome === 'refused' || result.outcome === 'failed') { say(result.message); return; }
    if (result.outcome === 'deployed' || result.outcome === 'up_to_date') remember();
    if (data.state && (data.state.rebuild_status === 'queued' || data.state.rebuild_status === 'running')) {
      say(result.message);
      poll();
      return;
    }
    window.location.reload();
  });

  if (root && root.dataset.deployPending === '1') poll();
})();

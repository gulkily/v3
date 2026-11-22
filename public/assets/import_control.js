// Import page actions, for Tools → Import and the local start page. Without
// this script the form posts normally and the server answers with the page and a notice; with it, requests go as JSON,
// the step list and timer follow the task worker, and the page reloads when
// the import finishes so the result shows.
(() => {
  const form = document.querySelector('[data-import-form]');
  const status = document.querySelector('[data-import-status]');
  const progress = document.querySelector('[data-import-progress]');
  const root = document.querySelector('[data-import-root]');
  const run = document.querySelector('[data-import-run]');
  const stepList = document.querySelector('[data-import-steps]');
  const timing = document.querySelector('[data-import-timing]');
  const notStarted = document.querySelector('[data-import-not-started]');
  const retry = document.querySelector('[data-import-form-retry]');
  if (!form || !status || !progress) return;
  const controls = Array.from(form.querySelectorAll('button, input'));
  const say = (text) => { status.textContent = text; status.hidden = text === ''; };
  // While an import runs the step list replaces the form.
  const setBusy = (on) => { controls.forEach((control) => { control.disabled = on; }); form.hidden = on; };

  const showLog = (lines) => {
    if (!Array.isArray(lines) || lines.length === 0) return;
    progress.replaceChildren(...lines.map((line) => {
      const item = document.createElement('li');
      item.textContent = line;
      return item;
    }));
    const log = progress.closest('details');
    if (log) log.hidden = false;
    progress.scrollTop = progress.scrollHeight;
  };

  // Same wording as templates/pages/tools_import.php.
  const size = (bytes) => (bytes < 1048576 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1048576).toFixed(1)} MB`);
  const count = (step) => {
    if (step.done === null || step.done === undefined || (step.done === 0 && step.total === null)) return '';
    if (step.key === 'download') return step.total !== null ? `${size(step.done)} of ${size(step.total)}` : size(step.done);
    return step.done.toLocaleString('en-US') + (step.total !== null ? ` of ${step.total.toLocaleString('en-US')}` : '');
  };
  const showSteps = (steps) => {
    if (!run || !stepList || !Array.isArray(steps)) return;
    stepList.replaceChildren(...steps.map((step) => {
      const item = document.createElement('li');
      item.className = `is-${step.status}`;
      const label = document.createElement('span');
      label.textContent = step.label;
      item.append(label);
      const text = count(step);
      if (text !== '') {
        const meta = document.createElement('span');
        meta.className = 'meta';
        meta.textContent = text;
        item.append(' ', meta);
      }
      return item;
    }));
    run.hidden = steps.length === 0;
  };

  // The server's numbers at the last poll, then ticked locally each second.
  let clock = null;
  const clockText = (seconds) => `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  const leftText = (left, scope) => {
    if (left === null) return 'estimating time left';
    const suffix = scope === 'step' ? ' in this step' : '';
    return left < 60 ? `under a minute left${suffix}` : `about ${Math.ceil(left / 60)} min left${suffix}`;
  };
  const drawTiming = () => {
    if (!timing || !clock) return;
    const passed = Math.floor((Date.now() - clock.at) / 1000);
    const left = clock.left === null ? null : Math.max(0, clock.left - passed);
    timing.textContent = `Elapsed ${clockText(clock.elapsed + passed)} · updated ${clock.since + passed} s ago · ${leftText(left, clock.scope)}`;
    timing.hidden = false;
  };
  const setTiming = (data) => {
    if (!data) { clock = null; if (timing) timing.hidden = true; return; }
    const total = data.seconds_left;
    const step = data.step_seconds_left;
    clock = { at: Date.now(), elapsed: data.elapsed, since: data.since_update,
      left: total ?? step ?? null, scope: total === null && step !== null ? 'step' : 'total' };
    drawTiming();
  };
  if (timing && !timing.hidden) {
    const left = timing.dataset.secondsLeft;
    clock = { at: Date.now(), elapsed: Number(timing.dataset.elapsed), since: Number(timing.dataset.sinceUpdate),
      left: left === '' ? null : Number(left), scope: timing.dataset.leftScope };
  }
  setInterval(drawTiming, 1000);

  // Tools → Import posts to its own address; the local start page posts to /local/ with its token.
  const endpoint = form.getAttribute('action') || '/tools/import/';
  const token = form.elements.token ? form.elements.token.value : null;
  const post = async (body) => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumImport' },
      body: JSON.stringify(token === null ? body : { ...body, token }),
    });
    const data = await response.json().catch(() => null);
    if (response.ok && data) return data;
    const transient = [502, 503, 504].includes(response.status);
    return { status: 'error', transient, error: (data && data.error) || `Request failed (${response.status}).` };
  };

  const show = (state) => {
    if (!state) return;
    showSteps(state.steps);
    showLog(state.progress);
    setTiming(state.timing);
    if (notStarted) notStarted.hidden = state.start !== 'not_started';
  };

  let polling = false;
  const poll = async () => {
    if (polling) return;
    polling = true;
    setBusy(true);
    const deadline = Date.now() + 20 * 60 * 1000;
    while (Date.now() < deadline) {
      await new Promise((resolve) => setTimeout(resolve, 2000));
      let data;
      try { data = await post({ action: 'status' }); } catch { continue; }
      if (data.transient) { say('Server busy; checking again…'); continue; }
      if (data.status !== 'ok') { say(data.error); break; }
      say('');
      show(data.state);
      if (!data.state.busy) {
        window.location.reload();
        return;
      }
    }
    polling = false;
    setBusy(false);
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const action = (event.submitter && event.submitter.value) || 'import';
    const source = form.elements.source ? form.elements.source.value.trim() : '';
    setBusy(true);
    say('Queuing…');
    let data;
    try {
      data = await post({ action, source });
    } catch {
      // The request may have queued even if its reply was lost; follow the status.
      say('Checking whether the import was queued…');
      poll();
      return;
    }
    if (data.transient) { poll(); return; }
    if (data.status !== 'ok') { setBusy(false); say(data.error || 'Request refused.'); return; }
    say('');
    progress.replaceChildren();
    show(data.state);
    poll();
  });

  if (retry) {
    retry.addEventListener('submit', async (event) => {
      event.preventDefault();
      let data;
      try { data = await post({ action: 'start_worker' }); } catch { return; }
      if (data.status !== 'ok') { say(data.error || 'Request refused.'); return; }
      say('');
      show(data.state);
      poll();
    });
  }

  if (root && root.dataset.importBusy === '1') poll();
})();

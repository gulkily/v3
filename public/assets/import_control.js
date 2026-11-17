// Import page actions. Without this script the form posts normally and the
// server answers with the page and a notice; with it, requests go as JSON,
// progress lines appear while the task worker runs, and the page reloads
// when the import finishes so the result shows.
(() => {
  const form = document.querySelector('[data-import-form]');
  const status = document.querySelector('[data-import-status]');
  const progress = document.querySelector('[data-import-progress]');
  const root = document.querySelector('[data-import-root]');
  if (!form || !status || !progress) return;
  const controls = Array.from(form.querySelectorAll('button, input'));
  const say = (text) => { status.textContent = text; status.hidden = text === ''; };
  const setBusy = (on) => controls.forEach((control) => { control.disabled = on; });
  const showProgress = (lines) => {
    if (!Array.isArray(lines) || lines.length === 0) return;
    progress.replaceChildren(...lines.map((line) => {
      const item = document.createElement('li');
      item.textContent = line;
      return item;
    }));
    progress.hidden = false;
    progress.scrollTop = progress.scrollHeight;
  };
  const post = async (body) => {
    const response = await fetch('/tools/import/', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumImport' },
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => null);
    if (response.ok && data) return data;
    const transient = [502, 503, 504].includes(response.status);
    return { status: 'error', transient, error: (data && data.error) || `Request failed (${response.status}).` };
  };

  let polling = false;
  const poll = async () => {
    if (polling) return;
    polling = true;
    setBusy(true);
    const deadline = Date.now() + 20 * 60 * 1000;
    while (Date.now() < deadline) {
      await new Promise((resolve) => setTimeout(resolve, 3000));
      let data;
      try { data = await post({ action: 'status' }); } catch { continue; }
      if (data.transient) { say('Server busy; checking again…'); continue; }
      if (data.status !== 'ok') { say(data.error); break; }
      showProgress(data.state.progress);
      if (!data.state.busy) {
        window.location.reload();
        return;
      }
      const running = data.state.task && data.state.task.status === 'running';
      say(running ? 'Importing…' : 'Queued; waiting for the task worker…');
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
      polling = false;
      poll();
      return;
    }
    if (data.transient) { poll(); return; }
    if (data.status !== 'ok') { setBusy(false); say(data.error || 'Request refused.'); return; }
    say(`${data.message} It usually starts within a minute.`);
    progress.replaceChildren();
    progress.hidden = true;
    poll();
  });

  if (root && root.dataset.importBusy === '1') poll();
})();

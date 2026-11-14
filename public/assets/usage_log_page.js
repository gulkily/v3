(function () {
  'use strict';
  // Tools → Usage log: turn recording on or off, read the log, copy or clear
  // it. The log itself lives in usage_log.js (window.forumUsageLog), which the
  // layout loads after this page script, so start once every deferred script ran.
  // Deferred scripts run while readyState is already "interactive", so wait for
  // DOMContentLoaded (or load, if this script arrives later) and start once.
  let started = false;
  const once = () => { if (!started) { started = true; start(); } };
  if (document.readyState === 'complete') once();
  else { document.addEventListener('DOMContentLoaded', once); window.addEventListener('load', once); }

  function start() {
  const page = document.querySelector('[data-usage-log]');
  if (!page) return;
  const status = page.querySelector('[data-role="usage-log-status"]');
  const toggle = page.querySelector('[data-role="usage-log-recording"]');
  const text = page.querySelector('[data-role="usage-log-text"]');
  const copy = page.querySelector('[data-action="usage-log-copy"]');
  const clear = page.querySelector('[data-action="usage-log-clear"]');
  const upload = page.querySelector('[data-action="usage-log-upload"]');
  const log = window.forumUsageLog;

  if (!log || !log.available()) {
    status.textContent = 'Recording is unavailable: this browser is not keeping site storage for this page (private browsing or blocked site data).';
    return;
  }

  function lineCount() { return log.state().count; }
  function refresh(message) {
    const state = log.state();
    toggle.checked = state.recording;
    text.value = log.text();
    const lines = state.count === 1 ? '1 line' : state.count + ' lines';
    status.textContent = message || (state.recording ? 'Recording is on. ' : 'Recording is off. ') + 'The log has ' + lines + '.';
    copy.disabled = state.count === 0;
    upload.disabled = state.count === 0;
    clear.disabled = state.count === 0;
  }

  toggle.disabled = false;
  toggle.addEventListener('change', () => {
    if (!log.setRecording(toggle.checked)) {
      refresh('This browser would not save the change.');
      return;
    }
    refresh();
  });
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(log.text());
      refresh('Copied ' + lineCount() + ' lines.');
    } catch (error) {
      text.focus();
      text.select();
      refresh('Copy is blocked here; the log is selected so you can copy it yourself.');
    }
  });
  // Sends exactly the text shown above. The log stays here whatever happens.
  upload.addEventListener('click', async () => {
    const body = log.text();
    upload.disabled = true;
    status.textContent = 'Uploading…';
    let message;
    try {
      const response = await fetch('/tools/usage-log/upload', {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        body,
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => null);
      if (response.ok && payload && payload.status === 'stored') {
        message = (payload.username ? 'Uploaded as ' + payload.username + '.' : 'Uploaded anonymously.') + ' Your log is still here; clear it if you like.';
      } else if (payload && payload.error) {
        message = payload.error;
      } else if (response.status === 403 || response.status === 401) {
        message = 'This site takes uploads only from signed-in members. Your log is still here, and you can copy it instead.';
      } else {
        message = 'The upload did not go through (' + response.status + '). Your log is still here.';
      }
    } catch (error) {
      message = 'Could not reach the site. Your log is still here.';
    }
    refresh(message);
  });
  clear.addEventListener('click', () => {
    log.clear();
    refresh('Cleared. Recording is ' + (log.state().recording ? 'still on.' : 'off.'));
  });
  window.addEventListener('pageshow', () => refresh());
  refresh();
  }
})();

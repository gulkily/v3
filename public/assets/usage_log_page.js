(function () {
  'use strict';
  // Tools → Usage log: turn recording on or off, read the latest steps in
  // words, check the exact text, upload, copy or clear it. The log itself lives
  // in usage_log.js (window.forumUsageLog), which the
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
  const card = page.querySelector('[data-role="usage-log-status-card"]');
  const stateLabel = page.querySelector('[data-role="usage-log-state"]');
  const countLabel = page.querySelector('[data-role="usage-log-count"]');
  const stepsList = page.querySelector('[data-role="usage-log-steps"]');
  const empty = page.querySelector('[data-role="usage-log-empty"]');
  const showAll = page.querySelector('[data-action="usage-log-show-all"]');
  const log = window.forumUsageLog;
  const LATEST = 10;
  const STOP_SECONDS = 20;
  let showingAll = false;

  if (!log || !log.available()) {
    card.dataset.state = 'unavailable';
    stateLabel.textContent = 'Recording is unavailable';
    countLabel.textContent = 'This browser is not keeping site storage for this page (private browsing or blocked site data).';
    return;
  }

  // The same names as Tools → Usage Reports (UsageNames on the server).
  let names = { pages: {}, tabs: {}, tools: {} };
  try { names = JSON.parse(page.getAttribute('data-usage-names') || '{}'); } catch (error) { /* raw names */ }
  const humanize = key => { const words = String(key).replace(/[-_]+/g, ' ').trim(); return words ? words[0].toUpperCase() + words.slice(1) : key; };
  const pageName = kind => (names.pages && names.pages[kind]) || kind;
  function controlName(name) {
    if (name.startsWith('tab:')) return (names.tabs && names.tabs[name.slice(4)]) || humanize(name.slice(4));
    if (name.startsWith('tool:')) return (names.tools && names.tools[name.slice(5)]) || humanize(name.slice(5));
    if (name.startsWith('action:')) return humanize(name.slice(7));
    return name;
  }
  const REGIONS = { navbar: 'in the navbar', menu: 'in the menu', 'tools-bar': 'in the Tools bar', list: 'in a list', post: 'in a post', composer: 'in the composer', footer: 'in the footer' };
  function sentence(entry) {
    const where = pageName(entry.page);
    if (entry.type === 'view') return ['Opened ', where, ''];
    if (entry.type === 'submit') return ['Pressed ', controlName(entry.name), ' on ' + where];
    return ['Clicked ', controlName(entry.name), ' ' + (REGIONS[entry.region] || 'on ' + where)];
  }
  const span = seconds => seconds < 60 ? seconds + ' s' : seconds < 3600 ? Math.round(seconds / 60) + ' min' : Math.round(seconds / 3600) + ' h';
  function item(className, raw) {
    const li = document.createElement('li');
    li.className = className;
    if (raw) li.title = raw;
    return li;
  }
  function renderSteps(entries) {
    const shown = showingAll ? entries : entries.slice(-LATEST);
    const now = Math.floor(Date.now() / 1000);
    stepsList.replaceChildren();
    shown.forEach((entry, index) => {
      if (index > 0 && entry.dt >= STOP_SECONDS) {
        const pause = item('usage-log-steps__pause');
        pause.textContent = 'paused ' + span(entry.dt);
        stepsList.append(pause);
      }
      const li = item('usage-log-steps__step', [entry.type, entry.page, entry.region, entry.name, entry.to].filter(Boolean).join(' · '));
      const when = document.createElement('span');
      when.className = 'usage-log-steps__when meta';
      when.textContent = index === 0 ? span(Math.max(0, now - entry.t)) + ' ago' : '+' + span(entry.dt);
      const [verb, what, rest] = sentence(entry);
      const words = document.createElement('span');
      const strong = document.createElement('strong');
      strong.textContent = what;
      words.append(verb, strong, rest);
      li.append(when, words);
      stepsList.append(li);
    });
    empty.hidden = entries.length > 0;
    showAll.hidden = entries.length <= LATEST;
    showAll.textContent = showingAll ? 'Show the latest ' + LATEST : 'Show all ' + entries.length + ' steps';
  }

  function lineCount() { return log.state().count; }
  function refresh(message) {
    const state = log.state();
    const steps = state.count === 1 ? '1 step' : state.count + ' steps';
    toggle.checked = state.recording;
    card.dataset.state = state.recording ? 'on' : 'off';
    stateLabel.textContent = state.recording ? 'Recording' : 'Not recording';
    countLabel.textContent = steps + ' saved in this browser';
    text.value = log.text();
    renderSteps(log.entries());
    status.textContent = message || '';
    upload.textContent = state.count === 0 ? 'Upload' : 'Upload ' + steps;
    copy.disabled = state.count === 0;
    upload.disabled = state.count === 0;
    clear.disabled = state.count === 0;
  }

  showAll.addEventListener('click', () => { showingAll = !showingAll; renderSteps(log.entries()); });

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
      text.closest('details').open = true;
      text.focus();
      text.select();
      refresh('Copy is blocked here; the log is selected so you can copy it yourself.');
    }
  });
  // Sends exactly the text shown above, wrapped in JSON: some hosts' request
  // filters refuse raw line breaks in a POST body, but accept them escaped.
  // The log stays here whatever happens.
  upload.addEventListener('click', async () => {
    const body = log.text();
    upload.disabled = true;
    status.textContent = 'Uploading…';
    let message;
    try {
      const response = await fetch('/tools/usage-log/upload', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ log: body }),
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

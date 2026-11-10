// Deploy now in the header, for a browser that has deployed from Tools → Deploy
// (deploy_control.js remembers it). Loaded only with FORUM_DEPLOY_HEADER_PROMPT_ENABLED.
// The server still decides who may check or deploy; a refusal forgets this browser.
(() => {
  const KEY = 'forum_deploy_prompt';
  const root = document.querySelector('[data-deploy-prompt]');
  // The Deploy page has its own buttons.
  if (!root || document.querySelector('[data-deploy-root]')) return;
  let remembered = false;
  try { remembered = window.localStorage.getItem(KEY) === '1'; } catch { return; }
  if (!remembered || typeof window.fetch !== 'function' || typeof window.AbortController !== 'function') return;
  const forget = () => { try { window.localStorage.removeItem(KEY); } catch { /* storage unavailable */ } };

  const send = async (body, timeoutMs) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
      return await fetch('/tools/deploy/', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumDeploy' },
        body: JSON.stringify(body),
        signal: controller.signal,
      });
    } finally {
      window.clearTimeout(timer);
    }
  };

  // Accepts a fetch from the last three minutes, so most page loads never reach GitHub.
  const check = async () => {
    let response;
    try { response = await send({ action: 'check', max_age: 180 }, 15000); } catch { return; }
    if (response.status === 403) { forget(); return; }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.status !== 'ok') return;
    if ((data.state && data.state.behind) > 0) root.hidden = false;
  };

  const button = root.querySelector('[data-deploy-prompt-button]');
  const status = root.querySelector('[data-deploy-prompt-status]');
  // Tools → Deploy shows the attempt log and follows a deploy whose reply was lost.
  const say = (text, withLink) => {
    status.textContent = text;
    if (withLink) {
      const link = document.createElement('a');
      link.href = '/tools/deploy/';
      link.textContent = 'Open Deploy';
      status.append(' ', link);
    }
  };

  const deploy = async () => {
    button.disabled = true;
    say('Deploying…');
    let response;
    // The server allows a deploy two minutes and finishes it even if this page goes away.
    try { response = await send({ action: 'now' }, 150000); } catch {
      button.disabled = false;
      say('The connection dropped; the deploy may still have run.', true);
      return;
    }
    if ([502, 503, 504].includes(response.status)) {
      button.disabled = false;
      say('The server is busy; the deploy may still have run.', true);
      return;
    }
    const data = await response.json().catch(() => null);
    if (response.status === 403) forget();
    if (!response.ok || !data || data.status !== 'ok') {
      button.disabled = false;
      say((data && data.error) || `Request failed (${response.status}).`, true);
      return;
    }
    const outcome = data.result && data.result.outcome;
    if (outcome === 'deployed' || outcome === 'up_to_date') { window.location.reload(); return; }
    button.disabled = false;
    say((data.result && data.result.message) || 'The deploy did not run.', true);
  };
  button.addEventListener('click', deploy);

  window.ForumDeployPrompt = { check, deploy };
  window.setTimeout(check, 3000);
})();

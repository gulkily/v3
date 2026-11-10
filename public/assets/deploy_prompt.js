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

  // Accepts a fetch from the last ten minutes, so most page loads never reach GitHub.
  const check = async () => {
    let response;
    try { response = await send({ action: 'check', max_age: 600 }, 15000); } catch { return; }
    if (response.status === 403) { forget(); return; }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.status !== 'ok') return;
    if ((data.state && data.state.behind) > 0) root.hidden = false;
  };

  window.ForumDeployPrompt = { check, send, forget, root };
  window.setTimeout(check, 3000);
})();

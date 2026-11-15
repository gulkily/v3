// Celebrates a deploy that changed the code. deploy_prompt.js and deploy_control.js
// leave the server's message in sessionStorage just before their success reload;
// the page that loads next plays confetti once and shows a Deployed banner.
// Reduced motion keeps the banner and skips the confetti.
(() => {
  const KEY = 'forum_deploy_celebrate';
  if (window.ForumDeployCelebrate) return;
  window.ForumDeployCelebrate = {
    // Called by the deploy scripts right before they reload.
    remember(message) {
      try { window.sessionStorage.setItem(KEY, message || ''); } catch { /* storage unavailable: no party */ }
    },
  };

  let message;
  try {
    message = window.sessionStorage.getItem(KEY);
    if (message === null) return;
    window.sessionStorage.removeItem(KEY);
  } catch { return; }

  const banner = document.createElement('div');
  banner.className = 'deploy-celebration';
  banner.setAttribute('role', 'status');
  const title = document.createElement('strong');
  title.textContent = 'Deployed!';
  banner.append(title);
  if (message) banner.append(' ', message);
  const dismiss = () => banner.remove();
  banner.addEventListener('click', dismiss);
  document.body.append(banner);
  window.setTimeout(() => banner.classList.add('is-leaving'), 5000);
  window.setTimeout(dismiss, 5600);

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const colors = ['var(--active-line, #4d79d8)', '#f2b705', '#e5487a', '#2bb673', '#8a5cf6', '#ff7a1a'];
  const layer = document.createElement('div');
  layer.className = 'deploy-confetti';
  layer.setAttribute('aria-hidden', 'true');
  for (let i = 0; i < 90; i += 1) {
    const piece = document.createElement('i');
    const r = Math.random;
    piece.style.left = `${r() * 100}%`;
    piece.style.background = colors[i % colors.length];
    piece.style.setProperty('--drift', `${(r() - 0.5) * 40}vw`);
    piece.style.setProperty('--spin', `${(r() - 0.5) * 1440}deg`);
    piece.style.animationDelay = `${r() * 0.6}s`;
    piece.style.animationDuration = `${2.2 + r() * 1.6}s`;
    if (i % 3 === 0) piece.style.borderRadius = '50%';
    layer.append(piece);
  }
  document.body.append(layer);
  window.setTimeout(() => layer.remove(), 4800);
})();

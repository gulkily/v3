<?php
/**
 * The generated tour page (public/assets/tour/index.html).
 *
 * @var list<array{name: string, route: string, title: string, paragraphs: list<string>}> $sections
 * @var string $siteName
 */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $e($siteName) ?></title>
<style>
  :root { --bg:#f4f1ea; --ink:#1d1b18; --muted:#5f594f; --card:#fff; --line:#ddd6c8; --accent:#2f6b4f; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#151412; --ink:#eee9df; --muted:#a39b8c; --card:#1f1d1a; --line:#34302a; --accent:#7fc4a0; }
  }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--ink);
         font:17px/1.6 ui-serif, Georgia, "Times New Roman", serif; }
  header { max-width:1120px; margin:0 auto; padding:40px 24px 0; }
  h1 { font:600 30px/1.2 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; margin:0; letter-spacing:-.01em; }
  .controls { position:sticky; top:0; z-index:10; display:flex; flex-wrap:wrap; gap:8px; align-items:center;
              max-width:1120px; margin:0 auto; padding:12px 24px; padding-top:max(12px, env(safe-area-inset-top));
              background:color-mix(in srgb, var(--bg) 88%, transparent); -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
              font:15px/1.4 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
  .seg { display:inline-flex; border:1px solid var(--line); border-radius:999px; overflow:hidden; background:var(--card); }
  .seg button { border:0; background:none; color:var(--ink); padding:9px 16px; min-height:40px; font:inherit; cursor:pointer; }
  .seg button[aria-pressed="true"] { background:var(--ink); color:var(--bg); }

  main { max-width:1120px; margin:0 auto; padding:16px 24px 72px; }
  section { display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1.25fr); gap:40px; align-items:start;
            padding:40px 0; border-top:1px solid var(--line); }
  section:first-child { border-top:0; padding-top:16px; }
  body.phone section { grid-template-columns:minmax(0, 1.4fr) minmax(0, 1fr); }
  .essay { position:sticky; top:84px; }
  .essay h2 { font:600 22px/1.3 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; margin:0 0 12px; }
  .essay p { margin:0 0 14px; }
  .essay a { color:var(--accent); font:600 15px/1.4 ui-sans-serif, system-ui, sans-serif; text-decoration:none; }

  figure { margin:0; background:var(--card); border:1px solid var(--line); border-radius:14px; overflow:hidden;
           box-shadow:0 1px 0 rgba(0,0,0,.03), 0 10px 28px -18px rgba(0,0,0,.3); }
  body.phone figure { max-width:360px; justify-self:center; width:100%; }
  .viewport { position:relative; width:100%; aspect-ratio:820/1100; overflow:hidden; background:var(--bg); }
  body.phone .viewport { aspect-ratio:390/844; }
  .viewport iframe { position:absolute; top:0; left:0; border:0; width:820px; height:1100px; transform-origin:0 0; background:#fff; }
  body.phone .viewport iframe { width:390px; height:844px; }
  .shield { position:absolute; inset:0; touch-action:pan-x pan-y; cursor:pointer; }
  body.interactive .shield { display:none; }

  @media (max-width: 760px) {
    body { font-size:16px; }
    section, body.phone section { grid-template-columns:1fr; gap:18px; padding:32px 0; }
    .essay { position:static; }
    header, main { padding-left:16px; padding-right:16px; }
    .controls { padding-left:16px; padding-right:16px; gap:6px; font-size:14px; }
    .seg button { padding:8px 12px; }
  }
</style>
</head>
<body>
<header>
  <h1><?= $e($siteName) ?></h1>
</header>
<div class="controls">
  <div class="seg" role="group" aria-label="Viewport">
    <button type="button" data-view="desktop" aria-pressed="true">Desktop</button>
    <button type="button" data-view="phone" aria-pressed="false">Phone</button>
  </div>
  <div class="seg" role="group" aria-label="Mode">
    <button type="button" data-mode="look" aria-pressed="true">Look</button>
    <button type="button" data-mode="interactive" aria-pressed="false">Interact</button>
  </div>
</div>
<main>
<?php foreach ($sections as $section): ?>
  <section id="<?= $e($section['name']) ?>">
    <div class="essay">
      <h2><?= $e($section['title']) ?></h2>
<?php foreach ($section['paragraphs'] as $paragraph): ?>
      <p><?= $e($paragraph) ?></p>
<?php endforeach; ?>
      <a href="<?= $e($section['route']) ?>">Open</a>
    </div>
    <figure>
      <div class="viewport" data-href="<?= $e($section['route']) ?>">
        <iframe data-src="<?= $e($section['name']) ?>.html" title="<?= $e($section['title']) ?>"></iframe>
        <div class="shield"></div>
      </div>
    </figure>
  </section>
<?php endforeach; ?>
</main>
<script>
(function () {
  'use strict';
  var frames = Array.prototype.slice.call(document.querySelectorAll('iframe[data-src]'));
  function load(frame) { if (!frame.src) frame.src = frame.dataset.src; }

  // Snapshots are static files: load the first two now, the rest a screen ahead.
  frames.slice(0, 2).forEach(load);
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { load(entry.target); observer.unobserve(entry.target); }
      });
    }, { rootMargin: '150% 0px' });
    frames.slice(2).forEach(function (frame) { observer.observe(frame); });
  } else {
    frames.forEach(load);
  }

  function fit() {
    var width = document.body.classList.contains('phone') ? 390 : 820;
    document.querySelectorAll('.viewport').forEach(function (viewport) {
      viewport.querySelector('iframe').style.transform = 'scale(' + (viewport.clientWidth / width) + ')';
    });
  }
  if ('ResizeObserver' in window) new ResizeObserver(fit).observe(document.querySelector('main'));
  window.addEventListener('resize', fit);

  document.querySelectorAll('.viewport').forEach(function (viewport) {
    viewport.querySelector('.shield').addEventListener('click', function () {
      location.href = viewport.dataset.href;
    });
  });

  function wire(attribute, apply) {
    document.querySelectorAll('[data-' + attribute + ']').forEach(function (button) {
      button.addEventListener('click', function () {
        button.parentElement.querySelectorAll('button').forEach(function (other) {
          other.setAttribute('aria-pressed', String(other === button));
        });
        apply(button.dataset[attribute]);
        fit();
      });
    });
  }
  wire('view', function (view) { document.body.classList.toggle('phone', view === 'phone'); });
  wire('mode', function (mode) { document.body.classList.toggle('interactive', mode === 'interactive'); });
  fit();
}());
</script>
</body>
</html>

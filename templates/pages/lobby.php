<?php $viewerProfile = is_array($viewerProfile ?? null) ? $viewerProfile : null; ?>
<section class="stack">
  <article class="card">
    <h1>Lobby</h1>
<?php if ($viewerProfile === null): ?>
    <p>This site is for approved members. Set up or select your browser key to continue.</p>
<?php elseif (((int) ($viewerProfile['is_approved'] ?? 0)) === 1): ?>
    <p>Your account is approved. <a href="/">Enter the site</a>.</p>
<?php else: ?>
    <p>Your identity is set up, but approval is still pending.</p>
    <p>Use <a href="/account/key/">Account</a> to review your identity and profile.</p>
<?php endif; ?>
  </article>
</section>

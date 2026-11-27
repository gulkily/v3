<?php
// Hands this browser's key to a local clone on this computer, after one click. The page never sends the
// key anywhere else: the target must be a loopback address, the opener must exist, and the browser
// delivers the message only to that exact origin. No server sees the key.
$target = (string) ($target ?? '');
?>
<section class="card stack key-handoff" data-key-handoff data-target="<?= $e($target) ?>">
  <h1>Use this key on <?= $target !== '' ? $e(substr($target, strlen('http://'))) : 'your computer' ?></h1>
<?php if ($target === ''): ?>
  <p role="alert">This link has no valid local address. Start again from your local copy's start page.</p>
<?php else: ?>
  <p>Your local copy asks for this browser's key, to read your private messages there. It stays in your browser on this computer; no server receives it.</p>
  <p data-key-handoff-key>Checking this browser's key…</p>
  <div class="button-row"><button type="button" data-key-handoff-send hidden>Use it there</button></div>
  <p class="meta" data-key-handoff-status role="status"></p>
<?php endif; ?>
</section>

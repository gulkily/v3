<section class="stack compose-page" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
    <h1><?= $e($job['postLabel'] ?? 'New post') ?></h1>
<?php if ($job !== null): ?>
    <p><?= $e($job['details']) ?></p>
    <p>This listing is public. Share exact addresses, contact details, and access instructions privately.</p>
<?php endif; ?>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
<?= $indent($partial('partials/thread_compose_form.php', [
    'compact' => false,
    'action' => $job === null ? '/compose/thread' : '/compose/thread?job=' . $job['tag'],
    'composeDraftScope' => $job === null ? '' : 'job:' . $job['tag'],
]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
  </article>
</section>

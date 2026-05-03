<section class="stack" data-compose-root<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
    <h1>Compose Reply</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p><strong>Thread ID:</strong> <?= $e($threadId !== '' ? $threadId : 'missing') ?></p>
    <p><strong>Parent ID:</strong> <?= $e($parentId !== '' ? $parentId : 'missing') ?></p>
    <p class="meta" data-role="compose-identity-status">Ready.</p>
<?= $indent($partial('partials/reply_form.php'), 2) ?>
  </article>
</section>

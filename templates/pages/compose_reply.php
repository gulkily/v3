<section class="stack" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
    <h1>Compose Reply</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p><strong>Thread ID:</strong> <?= $e($threadId !== '' ? $threadId : 'missing') ?></p>
    <p><strong>Parent ID:</strong> <?= $e($parentId !== '' ? $parentId : 'missing') ?></p>
    <p class="meta" data-role="compose-identity-status">Ready.</p>
    <p class="meta">
      <button type="button" data-action="prepare-browser-identity">Prepare browser identity</button>
    </p>
<?= $indent($partial('partials/reply_form.php'), 2) ?>
  </article>
</section>

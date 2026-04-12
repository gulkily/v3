<section class="stack" data-compose-root>
  <article class="card">
    <h1>Compose Reply</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p><strong>Thread ID:</strong> <?= $e($threadId !== '' ? $threadId : 'missing') ?></p>
    <p><strong>Parent ID:</strong> <?= $e($parentId !== '' ? $parentId : 'missing') ?></p>
    <p class="meta" data-role="compose-identity-status">Ready.</p>
    <form method="post" class="stack" data-compose-form data-compose-kind="reply">
      <input type="hidden" name="thread_id" value="<?= $e($threadId) ?>">
      <input type="hidden" name="parent_id" value="<?= $e($parentId) ?>">
      <input type="hidden" name="author_identity_id" value="">
      <label>Board tags<input type="text" name="board_tags" value="general"></label>
      <label>Body<textarea name="body" rows="7" placeholder="ASCII reply body"></textarea></label>
      <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
        <span data-role="compose-normalization-message"></span>
        <button
          type="button"
          class="compose-normalization-inline-action"
          data-action="remove-unsupported-compose-characters"
          hidden
        >Remove unsupported characters</button>
      </p>
      <button type="submit">Create reply</button>
    </form>
  </article>
</section>

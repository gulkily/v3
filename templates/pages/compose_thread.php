<section class="stack" data-compose-root>
  <article class="card">
    <h1>Compose Thread</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p>Posts are stored as canonical ASCII files and the SQLite read model rebuilds immediately.</p>
    <p class="meta" data-role="compose-identity-status">Ready.</p>
    <p class="meta" data-role="compose-normalization-status"></p>
    <div class="button-row" data-role="compose-normalization-actions" hidden>
      <button type="button" data-action="remove-unsupported-compose-characters" disabled>Remove unsupported characters</button>
    </div>
    <form method="post" class="stack" data-compose-form data-compose-kind="thread">
      <input type="hidden" name="author_identity_id" value="">
      <label>Board tags<input type="text" name="board_tags" value="<?= $e($boardTags) ?>"></label>
      <label>Subject<input type="text" name="subject" placeholder="Thread subject" value="<?= $e($subject) ?>"></label>
      <label>Body<textarea name="body" rows="7" placeholder="ASCII body"><?= $e($body) ?></textarea></label>
      <button type="submit">Create thread</button>
    </form>
  </article>
</section>

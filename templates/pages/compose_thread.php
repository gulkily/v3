<section class="stack" data-compose-root>
  <h1>Compose Thread</h1>
  <article class="card">
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p>Posts are stored as canonical ASCII files and the SQLite read model rebuilds immediately.</p>
    <p class="meta" data-role="compose-identity-status">Your username and keypair will be prepared automatically when you send your first post.</p>
    <form method="post" class="stack" data-compose-form data-compose-kind="thread">
      <input type="hidden" name="author_identity_id" value="">
      <label>Board tags<input type="text" name="board_tags" value="general"></label>
      <label>Subject<input type="text" name="subject" placeholder="Thread subject"></label>
      <label>Body<textarea name="body" rows="7" placeholder="ASCII body"></textarea></label>
      <button type="submit">Create thread</button>
    </form>
  </article>
</section>

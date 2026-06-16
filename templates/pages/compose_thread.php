<section class="stack" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
    <h1>Compose Thread</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p>Posts are stored as canonical text files and the SQLite read model rebuilds immediately.</p>
    <p class="meta" data-role="compose-identity-status" hidden></p>
    <form method="post" class="stack" data-compose-form data-compose-kind="thread">
      <input type="hidden" name="author_identity_id" value="">
      <label>Board tags<input type="text" name="board_tags" value="<?= $e($boardTags) ?>"></label>
      <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="board_tags" hidden>
        <span data-role="compose-field-normalization-message"></span>
        <button
          type="button"
          class="compose-normalization-inline-action"
          data-action="remove-unsupported-compose-characters"
          data-compose-field-remove-for="board_tags"
          hidden
        >Remove unsupported characters</button>
      </p>
      <label>Subject<input type="text" name="subject" data-compose-field-label="Subject" placeholder="Thread subject" value="<?= $e($subject) ?>"></label>
      <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="subject" hidden>
        <span data-role="compose-field-normalization-message"></span>
        <button
          type="button"
          class="compose-normalization-inline-action"
          data-action="remove-unsupported-compose-characters"
          data-compose-field-remove-for="subject"
          hidden
        >Remove unsupported characters</button>
      </p>
      <label>Body<textarea name="body" data-compose-field-label="Body" rows="7" placeholder="Body"><?= $e($body) ?></textarea></label>
      <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="body" hidden>
        <span data-role="compose-field-normalization-message"></span>
        <button
          type="button"
          class="compose-normalization-inline-action"
          data-action="remove-unsupported-compose-characters"
          data-compose-field-remove-for="body"
          hidden
        >Remove unsupported characters</button>
      </p>
      <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
        <span data-role="compose-normalization-message"></span>
      </p>
      <div class="compose-form-actions">
        <button type="submit">Create thread</button>
        <button type="submit" data-action="submit-anonymous-compose">Create thread anonymously</button>
        <button type="button" class="compose-clear-button" data-action="clear-compose-fields">Clear fields</button>
      </div>
    </form>
  </article>
</section>

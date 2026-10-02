<section class="stack qdb-add-page" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/thread_compose_form.php', [
    'compact' => true,
    'boardTags' => $boardTags,
    'subject' => $subject,
    'body' => $body,
]), 2) ?>
  </article>
</section>

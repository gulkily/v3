<section class="stack compose-page" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card">
    <h1>New post</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
<?= $indent($partial('partials/thread_compose_form.php', ['compact' => false]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
  </article>
</section>

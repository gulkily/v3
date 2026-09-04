<section class="stack" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
<?php if (is_array($parentPost)): ?>
  <article class="card reply-context-card" aria-labelledby="reply-context-title">
    <h2 id="reply-context-title">Replying to</h2>
    <p class="meta"><?= $contentMeta($parentPost, 'created_at', '') ?> &middot; <a href="/posts/<?= $e($parentPost['post_id']) ?>">Post <?= $e($parentPost['post_id']) ?></a></p>
<?php if (trim((string) ($parentPost['subject'] ?? '')) !== ''): ?>
    <p><strong><?= $e($parentPost['subject']) ?></strong></p>
<?php endif; ?>
    <div class="body reply-context-body"><?= $br($parentPost['body']) ?></div>
  </article>
<?php elseif ($parentId !== ''): ?>
  <article class="card reply-context-card">
    <h2>Replying to</h2>
    <p class="meta">Parent post <?= $e($parentId) ?> could not be found for this thread.</p>
  </article>
<?php endif; ?>
  <article class="card">
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/reply_form.php', ['showBodyLabel' => false]), 2) ?>
  </article>
</section>

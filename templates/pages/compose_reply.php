<?php
$replyThreadTitle = (string) ($replyThreadTitle ?? '');
$parentAuthorName = is_array($parentPost)
    ? trim(str_replace('(unapproved)', '', strip_tags($author($parentPost))))
    : '';
$parentBody = is_array($parentPost) ? (string) ($parentPost['body'] ?? '') : '';
// Long parents are clamped to a few lines; the full post is one tap away.
$parentIsLong = mb_strlen($parentBody) > 280 || substr_count($parentBody, "\n") >= 4;
?>
<section class="stack compose-page" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>"<?= $notice !== null ? ' data-compose-submitted="1"' : '' ?>>
  <article class="card reply-context-card">
    <h1 class="compose-page-title"><?= $e($parentAuthorName !== '' ? 'Reply to ' . $parentAuthorName : 'Reply') ?></h1>
<?php if (is_array($parentPost)): ?>
    <blockquote class="reply-context">
      <p class="meta"><?= $rowMeta($parentPost) ?><?php if ($replyThreadTitle !== ''): ?> · in <a href="/threads/<?= $e($threadId) ?>"><?= $e($replyThreadTitle) ?></a><?php endif; ?></p>
      <div class="body reply-context-body<?= $parentIsLong ? ' reply-context-body--clamped' : '' ?>"><?= $br($parentBody) ?></div>
<?php if ($parentIsLong): ?>
      <p class="meta"><a href="/posts/<?= $e($parentPost['post_id']) ?>">Show more</a></p>
<?php endif; ?>
    </blockquote>
<?php elseif ($parentId !== ''): ?>
    <p class="reply-context reply-context--missing">That post was removed or isn’t in this thread.<?php if ($threadId !== ''): ?> <a href="/threads/<?= $e($threadId) ?>">Reply to the thread instead.</a><?php endif; ?></p>
<?php endif; ?>
  </article>
  <article class="card" data-reply-panel>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
<?= $indent($partial('partials/reply_form.php', ['phoneReplyEnabled' => true]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
  </article>
</section>

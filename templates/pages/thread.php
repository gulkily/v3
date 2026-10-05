<?= $partial('partials/agent_response_mode_catalog.php') ?>
<section class="stack"<?= $createdPostId !== '' ? ' data-created-post-id="' . $e($createdPostId) . '"' : '' ?>>
<?php
// Continuations: same-author quick replies within this window merge visually with the previous post.
$continuationWindowSeconds = 15 * 60;
$isThreadPostContinuation = function (array $previousPost, array $post, int $windowSeconds): bool {
    $previousAuthor = (string) ($previousPost['author_label'] ?? '');
    $author = (string) ($post['author_label'] ?? '');
    if ($previousAuthor === '' || $author === '' || $previousAuthor !== $author) {
        return false;
    }
    if ($previousAuthor === 'reply-agent' || $author === 'reply-agent') {
        return false;
    }
    try {
        $previousTime = new DateTimeImmutable((string) ($previousPost['created_at'] ?? ''));
        $time = new DateTimeImmutable((string) ($post['created_at'] ?? ''));
    } catch (\Exception) {
        return false;
    }
    $delta = $time->getTimestamp() - $previousTime->getTimestamp();
    return $delta >= 0 && $delta <= $windowSeconds;
};

$rootPost = null;
$replyPosts = [];
foreach ($posts as $post) {
    if ((string) $post['post_id'] === (string) $thread['root_post_id']) {
        $rootPost = $post;
        continue;
    }

    $replyPosts[] = $post;
}

// Pre-compute continuation flags so the root card can show a true reply count before the reply loop runs.
$replyContinuationFlags = [];
$previousPost = $rootPost;
$trueReplyCount = 0;
foreach ($replyPosts as $post) {
    $isContinuation = $previousPost !== null && $isThreadPostContinuation($previousPost, $post, $continuationWindowSeconds);
    $replyContinuationFlags[] = $isContinuation;
    if (!$isContinuation) {
        $trueReplyCount++;
    }
    $previousPost = $post;
}

// The byline for a merged run renders under the run's LAST post, not its first. Only one run can
// ever start at the root: the contiguous stretch of continuations immediately following it.
$rootRunTailIndex = -1;
foreach ($replyContinuationFlags as $flagIndex => $flag) {
    if (!$flag) {
        break;
    }
    $rootRunTailIndex = $flagIndex;
}
$rootMetaVisible = $rootRunTailIndex === -1;
?>
<?php if ($rootPost !== null): ?>
<?= $indent($partial('partials/thread_root_card.php', ['post' => $rootPost, 'trueReplyCount' => $trueReplyCount, 'metaVisible' => $rootMetaVisible]), 1) ?>
<?php else: ?>
  <article class="card" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>">
    <h1><?= $e($title) ?></h1>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
  </article>
<?php endif; ?>
<?php foreach ($replyPosts as $index => $post): ?>
<?php
$isRunTail = $index === count($replyPosts) - 1 || !$replyContinuationFlags[$index + 1];
$showRootMetaExtras = $index === $rootRunTailIndex;
?>
<?= $indent($partial('partials/post_card.php', [
    'post' => $post,
    'isContinuation' => $replyContinuationFlags[$index],
    'isRunTail' => $isRunTail,
    'showRootMetaExtras' => $showRootMetaExtras,
    'trueReplyCount' => $trueReplyCount,
]), 1) ?>
<?php endforeach; ?>
  <article class="card inline-reply-composer" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>">
    <details class="inline-reply-details" data-inline-reply-details>
      <summary class="inline-reply-summary">
        <textarea
          class="inline-reply-prompt"
          rows="2"
          placeholder="Write a reply..."
          aria-label="Write a reply"
          data-inline-reply-trigger
          readonly
        ></textarea>
      </summary>
      <div class="inline-reply-expanded">
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => $thread['root_post_id'],
    'parentId' => $thread['root_post_id'],
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post reply',
    'showBodyLabel' => false,
]), 2) ?>
      </div>
    </details>
  </article>
</section>

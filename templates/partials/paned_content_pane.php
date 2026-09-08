<?php
/**
 * @var array<int, array<string, mixed>> $posts
 * @var array<string, mixed> $thread
 */
$fallbackSubject = static function (array $post): string {
    $subject = trim((string) ($post['subject'] ?? ''));
    if ($subject !== '') {
        return $subject;
    }

    $bodyText = trim((string) preg_replace('/\s+/u', ' ', (string) ($post['body'] ?? '')));
    if ($bodyText === '') {
        return (string) $post['post_id'];
    }

    if (mb_strlen($bodyText) <= 60) {
        return $bodyText;
    }

    return mb_substr($bodyText, 0, 60) . '...';
};

$rootPostId = (string) $thread['root_post_id'];
?>
<div class="paned-content-pane" data-paned-content-pane>
<?php foreach ($posts as $post): ?>
<?php
$postId = (string) $post['post_id'];
$isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';
$isSelectedByDefault = $postId === $rootPostId;
?>
  <article class="paned-content-post" data-paned-content-post-id="<?= $e($postId) ?>"<?= $isSelectedByDefault ? '' : ' hidden' ?>>
    <div class="paned-content-head">
      <div class="paned-content-subject"><?= $e($fallbackSubject($post)) ?><?php if ($isAgentPost): ?> <span class="agent-label">(agent-authored)</span><?php endif; ?></div>
      <div class="paned-content-meta">
        <span>From: <?= $author($post) ?></span>
        <span><?= $timestamp((string) ($post['created_at'] ?? '')) ?></span>
      </div>
    </div>
    <div class="body"><?= $br($post['body']) ?></div>
  </article>
<?php endforeach; ?>
</div>

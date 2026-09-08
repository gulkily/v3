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
  <article class="card paned-content-post" data-paned-content-post-id="<?= $e($postId) ?>"<?= $isSelectedByDefault ? '' : ' hidden' ?>>
    <h2><?= $e($fallbackSubject($post)) ?></h2>
    <p class="meta"><?= $contentMeta($post, 'created_at', '') ?></p>
<?php if ($isAgentPost): ?>
    <p class="meta"><span class="agent-label">Agent-authored reply</span></p>
<?php endif; ?>
    <div class="body"><?= $br($post['body']) ?></div>
    <div class="button-row button-row-natural paned-content-actions">
      <a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($postId) ?>">Reply</a>
      <a href="/posts/<?= $e($postId) ?>">Permalink</a>
    </div>
  </article>
<?php endforeach; ?>
</div>

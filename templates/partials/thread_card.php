<?php
$subject = $threadTitle($thread);
$showPinnedMarker ??= false;
$showLabels ??= false;
$isPinned = $showPinnedMarker && in_array('pinned', $thread['thread_labels'] ?? [], true);
$previewDuplicatesTitle = trim((string) $thread['body_preview']) === trim($subject);
$bareMediaEmbedWarmBeacon = '';
if (($mediaEmbedsEnabled ?? false) && $subject === 'Untitled') {
    $bareMediaEmbedMatch = \ForumRewrite\Support\ThreadTitle::bareMediaEmbedMatch(
        (string) ($thread['subject'] ?? ''),
        (string) ($thread['body_preview'] ?? $thread['body'] ?? '')
    );
    if ($bareMediaEmbedMatch !== null) {
        $bareMediaEmbedWarmBeaconUrl = '/internal/media-embeds/warm-preview?provider=' . rawurlencode($bareMediaEmbedMatch['provider'])
            . '&url=' . rawurlencode($bareMediaEmbedMatch['url'])
            . '&thread_id=' . rawurlencode((string) $thread['root_post_id']);
        $bareMediaEmbedWarmBeacon = '<img class="media-embed-card__warm-beacon" data-media-embed-warm-beacon src="' . $e($bareMediaEmbedWarmBeaconUrl) . '" alt="" width="0" height="0" style="display:none" loading="eager">';
    }
}
?>
<article class="card thread-card" data-heat="<?= $heat($thread['last_activity_at'] ?? null, (int) ($thread['reply_count'] ?? 0)) ?>">
  <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a><?php if ($isPinned): ?> <span class="pinned-thread-marker">Pinned</span><?php endif; ?></h2>
<?= $bareMediaEmbedWarmBeacon ?>
  <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
<?= $partial('partials/event_block.php') ?>
<?php if ($showLabels && ($thread['thread_labels'] ?? []) !== []): ?>
  <p class="meta">Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?></p>
<?php endif; ?>
<?php if (!$previewDuplicatesTitle): ?>
  <p class="thread-card__preview"><?= $br($thread['body_preview']) ?></p>
<?php endif; ?>
<?php if ((int) $thread['reply_count'] > 0): ?>
  <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
</article>

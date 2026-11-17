<?php
/**
 * One thread in the `list` board: title, reply count, a one-line excerpt
 * when the body adds something beyond the title, and compact metadata.
 *
 * @var array<string, mixed> $thread
 */
$subject = $threadTitle($thread);
$replyCount = (int) $thread['reply_count'];
// Image references and their photo details are data, not text; the excerpt skips them.
$excerpt = trim((string) preg_replace('/^' . preg_quote(\ForumRewrite\Images\ImageReference::PREFIX, '/') . '.*(?:\nPhoto-(?:' . implode('|', \ForumRewrite\Images\ImageReference::PHOTO_DETAIL_LABELS) . '): .*)*$/m', '', (string) $thread['body_preview']));
// A campaign row's line says what its goal line says, so the excerpt leaves that line out, as the thread page does.
if (is_array($thread['pledge_campaign'] ?? null)) {
    $excerpt = trim(\ForumRewrite\Support\PledgeLines::hide($excerpt, \ForumRewrite\Support\PledgeLines::GOAL));
}
$showExcerpt = $excerpt !== '' && $excerpt !== trim($subject) && ($thread['retracted_at'] ?? null) === null;
?>
<?php $thumbnail = $thread['thumbnail'] ?? null; ?>
<article id="post-<?= $e($thread['root_post_id']) ?>" class="thread-row<?= $thumbnail !== null ? ' thread-row--thumb' : '' ?>" data-heat="<?= $heat($thread['last_activity_at'] ?? null, $replyCount) ?>">
<?php if ($thumbnail !== null): ?>
  <?= $partial('partials/row_thumbnail.php', ['thumbnail' => $thumbnail, 'href' => '/threads/' . $thread['root_post_id']]) ?>
<?php endif; ?>
  <h3 class="thread-row__title"><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h3>
<?php $pledgeCampaign = $thread['pledge_campaign'] ?? null; ?>
<?php if (is_array($pledgeCampaign)): ?>
<?php
// A campaign's progress, as its thread page counts it: pledged against the goal, then the status word.
$pledgeStatus = (string) $pledgeCampaign['status'];
$pledgeStatusLabel = match ($pledgeStatus) {
    \ForumRewrite\View\PledgeCampaign::STATUS_COMPLETE => 'Complete',
    \ForumRewrite\View\PledgeCampaign::STATUS_GOAL_REACHED => 'Goal reached',
    default => '',
};
?>
  <p class="meta thread-row__pledge" data-pledge-row data-pledge-status="<?= $e($pledgeStatus) ?>"><strong><?= $e($pledgeCampaign['pledged']) ?></strong> / <?= $e($pledgeCampaign['goal'] . ($pledgeCampaign['unit'] !== '' ? ' ' . $pledgeCampaign['unit'] : '')) ?><?php if ($pledgeStatusLabel !== ''): ?> <span class="pledge-status"><?= $e($pledgeStatusLabel) ?></span><?php endif; ?></p>
<?php endif; ?>
  <p class="thread-row__count"><?php if ($replyCount > 0): ?><span class="thread-row__count-number"><?= $replyCount ?></span> <span class="thread-row__count-label"><?= $replyCount === 1 ? 'reply' : 'replies' ?></span><?php endif; ?></p>
<?php if ($showExcerpt): ?>
  <p class="thread-row__excerpt"><?= $e($excerpt) ?></p>
<?php endif; ?>
  <p class="meta thread-row__meta"><?= $rowMeta($thread, 'root_post_created_at') ?></p>
<?= $partial('partials/event_block.php') ?>
</article>

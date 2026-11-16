<?php
// /tools/tag-unlocks/: read only. What resources/tagsets.txt unlocks, and every marked post.
$targetWords = ['reply' => 'replies', 'root' => 'the opening post'];
$stateHeadings = ['claimed' => 'Claimed', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$stateEmpty = ['claimed' => 'Nobody has claimed anything.', 'resolved' => 'Nothing resolved yet.', 'closed' => 'Nothing closed yet.'];
$formatWhen = static function (?string $at): string {
    if ($at === null || $at === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($at))->format('M j, Y');
    } catch (\Exception) {
        return '';
    }
};
?>
<section class="stack">
  <article class="card tag-unlocks-page">
    <h1>Tag Unlocks</h1>
    <p class="meta">Read only. A thread tag can unlock extra tags on that thread's posts. The list lives in <code>resources/tagsets.txt</code> in the code; edit that file to change it.</p>
<?php if (!$tagUnlocksOn): ?>
    <p class="meta">Tag unlocks are turned off on this site (<code>FORUM_TAG_UNLOCKS_ENABLED</code>), so no buttons are shown.</p>
<?php endif; ?>

    <section class="tag-unlocks-section" aria-labelledby="tag-unlocks-sets">
      <h2 id="tag-unlocks-sets">What unlocks what</h2>
<?php if ($tagsets === []): ?>
      <p class="meta">No tagsets are defined.</p>
<?php else: ?>
      <ul class="tag-unlocks-sets">
<?php foreach ($tagsets as $set): ?>
        <li class="tag-unlocks-set">
          <p class="tag-unlocks-rule"><a class="tag-unlocks-parent" href="/tags/<?= $e(rawurlencode($set['parent'])) ?>">#<?= $e($set['parent']) ?></a> <span class="tag-unlocks-arrow" aria-label="unlocks">unlocks</span>
<?php foreach ($set['unlocks'] as $tag): ?>
            <span class="tag-unlocks-tag"><?= $e($tag) ?></span>
<?php endforeach; ?>
          </p>
          <p class="meta">On <?= $e(implode(' and ', array_map(static fn (string $target): string => $targetWords[$target] ?? $target, $set['targets']))) ?>. <?= $set['exclusive'] ? 'One state at a time: the latest mark from an approved key wins, and <em>open</em> clears it.' : 'Independent tags, counted like Like and Flag.' ?></p>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

<?php if ($tagUnlocksOn): ?>
<?php foreach ($stateHeadings as $state => $heading): ?>
<?php $rows = $markedPosts[$state] ?? []; ?>
    <section class="tag-unlocks-section" aria-labelledby="tag-unlocks-<?= $e($state) ?>">
      <h2 id="tag-unlocks-<?= $e($state) ?>"><?= $e($heading) ?> <span class="meta">(<?= count($rows) ?>)</span></h2>
<?php if ($rows === []): ?>
      <p class="meta"><?= $e($stateEmpty[$state]) ?></p>
<?php else: ?>
      <ul class="tag-unlocks-posts" data-state="<?= $e($state) ?>">
<?php foreach ($rows as $row): ?>
        <li class="tag-unlocks-post">
          <a href="/threads/<?= $e(rawurlencode($row['thread_id'])) ?><?= $row['is_root'] ? '' : '#post-' . $e(rawurlencode($row['post_id'])) ?>"><?= $e($row['excerpt'] !== '' ? $row['excerpt'] : $row['post_id']) ?></a>
          <p class="meta"><?= $row['is_root'] ? 'Opening post' : 'In' ?><?= $row['thread_subject'] !== '' ? ' “' . $e($row['thread_subject']) . '”' : '' ?><?= $row['set_by'] !== '' ? ' · ' . ($state === 'claimed' ? 'claimed by ' : 'marked by ') . $e($row['set_by']) : '' ?><?= $formatWhen($row['set_at']) !== '' ? ' · ' . $e($formatWhen($row['set_at'])) : '' ?></p>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
<?php endforeach; ?>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

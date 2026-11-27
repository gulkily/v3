<?php
/**
 * The support wall: one square per unit, one block per pledge. See ForumRewrite\View\SupportWall.
 * Every square of a block links to its pledge in the thread and carries the badge; the first
 * square also carries the name. Delivered squares are filled in, open ones outlined, and a
 * pledge waiting for approval is dashed and marked Pending. A line ends the goal's last square.
 *
 * @var array{blocks: list<array<string, mixed>>, also: list<string>, drawn: int, size: int, goal: int} $supportWall
 * @var string $supportThreadId
 */
$wallThreadHref = '/threads/' . rawurlencode($supportThreadId);
$wallIndex = 0;
?>
<div class="support-wall" data-support-wall data-squares="<?= (int) $supportWall['drawn'] ?>" data-goal="<?= (int) $supportWall['goal'] ?>">
  <ol class="support-wall__grid" aria-label="<?= (int) $supportWall['drawn'] ?> of <?= (int) $supportWall['goal'] ?> squares covered">
<?php foreach ($supportWall['blocks'] as $block): ?>
<?php
$blockHref = $wallThreadHref . '#post-' . rawurlencode((string) $block['post_id']);
$blockState = $block['pending'] ? 'pending' : 'counted';
?>
<?php for ($square = 0; $square < (int) $block['squares']; $square++): ?>
<?php
$wallIndex++;
$squareClasses = 'support-wall__square support-wall__square--claimed'
    . ($block['pending'] ? ' support-wall__square--pending' : ($square < (int) $block['delivered'] ? ' support-wall__square--delivered' : ' support-wall__square--open'))
    . ($square === 0 ? ' support-wall__square--first' : '')
    . ($wallIndex === (int) $supportWall['goal'] ? ' support-wall__square--goal' : '');
?>
    <li class="<?= $squareClasses ?>"<?= $square === 0 ? ' id="post-' . $e((string) $block['post_id']) . '"' : '' ?> data-block="<?= $e((string) $block['post_id']) ?>" data-state="<?= $blockState ?>">
      <a href="<?= $e($blockHref) ?>"<?= $square === 0 ? '' : ' tabindex="-1" aria-hidden="true"' ?> title="<?= $e($block['name'] . ' · ' . $block['squares'] . ($block['squares'] === 1 ? ' square' : ' squares')) ?>">
        <span class="support-wall__badge" aria-hidden="true"></span>
<?php if ($square === 0): ?>
        <span class="support-wall__name"><?= $e($block['name']) ?></span><?php if ($block['pending']): ?> <span class="chip chip--pending">Pending</span><?php endif; ?>
<?php endif; ?>
      </a>
    </li>
<?php endfor; ?>
<?php endforeach; ?>
<?php for (; $wallIndex < (int) $supportWall['size']; ): ?>
<?php $wallIndex++; ?>
    <li class="support-wall__square<?= $wallIndex === (int) $supportWall['goal'] ? ' support-wall__square--goal' : '' ?>" aria-hidden="true"></li>
<?php endfor; ?>
  </ol>
<?php if ($supportWall['also'] !== []): ?>
  <p class="meta support-wall__also">Also: <?= $e(implode(', ', $supportWall['also'])) ?></p>
<?php endif; ?>
</div>

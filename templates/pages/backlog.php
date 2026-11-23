<?php
// Tools → Backlog. Sort and filter are plain links with readable words; the page
// needs no script.
$backlogLinks = static function (array $options) use ($e): string {
    $html = '';
    foreach ($options as $option) {
        $label = $e($option['label']);
        if (isset($option['order']) && $option['is_active']) {
            $label .= '<span class="visually-hidden">, ' . ($option['order'] === 'oldest' ? 'oldest first' : 'newest first') . '</span>'
                . '<span class="backlog-order" aria-hidden="true">' . ($option['order'] === 'oldest' ? '↑' : '↓') . '</span>';
        }
        $html .= '<a class="nav-link' . ($option['is_active'] ? ' is-active' : '') . '" href="' . $e($option['href']) . '"'
            . ($option['is_active'] ? ' aria-current="true"' : '') . '>' . $label . '</a>';
    }
    return $html;
};
?>
<section class="stack backlog-page">
  <h1 class="visually-hidden">Backlog</h1>
<?php if ($backlogUnknownWords !== []): ?>
  <p class="feedback" role="status">Not understood, so the default is shown: <?= $e(implode(', ', $backlogUnknownWords)) ?></p>
<?php endif; ?>
  <div class="backlog-controls">
    <nav class="board-filter backlog-filter" aria-label="Sort by"><?= $backlogLinks($backlogSortOptions) ?></nav>
    <nav class="board-filter backlog-filter" aria-label="Show"><?= $backlogLinks($backlogShowOptions) ?></nav>
    <nav class="board-filter backlog-filter" aria-label="Kind"><?= $backlogLinks($backlogKindOptions) ?></nav>
  </div>
  <p class="meta backlog-count"><?= count($backlogItems) ?> of <?= (int) $backlogOpenCount ?> open · version <code><?= $e($backlogVersion) ?></code> · <a href="<?= $e($backlogTextHref) ?>">text</a></p>
  <article class="card backlog-card">
<?php if ($backlogItems === []): ?>
    <p class="meta">Nothing here.</p>
<?php else: ?>
    <ul class="backlog-list">
<?php foreach ($backlogItems as $item): ?>
      <li class="backlog-row" data-workflow="<?= $e($item['workflow']) ?>">
        <span class="backlog-row__title"><?= $e($item['title']) ?></span>
        <span class="backlog-row__meta meta">
          <span class="chip backlog-chip"><?= $e($item['workflow']) ?></span>
          <span><?= $e($item['kind']) ?></span>
<?php if ($item['href'] !== null): ?>
          <a class="backlog-row__id" href="<?= $e($item['href']) ?>"><?= $e($item['id']) ?></a>
<?php else: ?>
          <span class="backlog-row__id"><?= $e($item['id']) ?></span>
<?php endif; ?>
          <span class="backlog-row__numbers">effort <?= $e($item['effort'] ?? '—') ?> · impact <?= $e($item['impact'] ?? '—') ?></span>
        </span>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>
</section>

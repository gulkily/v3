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
    <form class="backlog-form" method="get" action="/backlog/start" data-backlog-form>
    <ul class="backlog-list">
<?php foreach ($backlogItems as $item): ?>
      <li class="backlog-row" data-workflow="<?= $e($item['workflow']) ?>">
        <input class="backlog-row__pick" type="checkbox" name="items[]" value="<?= $e($item['id']) ?>" id="pick-<?= $e($item['id']) ?>">
        <label class="backlog-row__title" for="pick-<?= $e($item['id']) ?>"><?= $e($item['title']) ?></label>
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
    <div class="backlog-start-bar">
      <span class="meta" data-backlog-count hidden></span>
      <button type="submit">Start</button>
    </div>
    </form>
<?php endif; ?>
  </article>
</section>
<script>
  // Shows how many items are ticked and sends the tidy address (?items=A,B).
  // Without this script the form still works: the server redirects to that address.
  (function (form) {
    if (!form) return;
    var count = form.querySelector('[data-backlog-count]');
    var picked = function () {
      return Array.prototype.map.call(form.querySelectorAll('input[name="items[]"]:checked'), function (box) { return box.value; });
    };
    var show = function () {
      var n = picked().length;
      count.hidden = n === 0;
      count.textContent = n + ' selected';
    };
    form.addEventListener('change', show);
    window.addEventListener('pageshow', show);
    form.addEventListener('submit', function (event) {
      var ids = picked();
      if (ids.length === 0) return;
      event.preventDefault();
      location.href = form.getAttribute('action') + '?items=' + ids.join(',');
    });
    show();
  })(document.querySelector('[data-backlog-form]'));
</script>

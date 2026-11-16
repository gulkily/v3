<section class="stack usage-reports">
  <article class="card usage-reports-head">
    <h1>Usage Reports</h1>
<?php if (!$hasReports): ?>
    <p>No reports yet. Turn on recording in <a href="/tools/usage-log/">Usage Log</a>, browse, and upload.</p>
<?php else: ?>
    <nav class="usage-pills" aria-label="Uploaded by">
<?php foreach ($uploaderLinks as $link): ?>
      <a class="usage-pill" href="<?= $e($link['href']) ?>"<?= $link['current'] ? ' aria-current="page"' : '' ?>><?= $e($link['label']) ?><?php if ($link['count'] !== null): ?> · <?= $e((string) $link['count']) ?><?php endif; ?></a>
<?php endforeach; ?>
    </nav>
    <dl class="usage-tiles">
      <div><dt>Reports</dt><dd><?= $e(number_format($reportCount)) ?></dd></div>
      <div><dt>Steps</dt><dd><?= $e(number_format($entryCount)) ?></dd></div>
      <div class="usage-tile--ideas"><dt>Shortcut ideas</dt><dd><?= $e((string) $shortcutCount) ?></dd></div>
    </dl>
<?php endif; ?>
  </article>
<?php if ($hasReports): ?>
  <div class="usage-reports-columns">
    <article class="card usage-journeys">
      <h2>Shortcut ideas</h2>
      <p class="meta">Journeys of two or more clicks between stops (a pause of <?= $e((string) $stopSeconds) ?> seconds or a form submit), ideas first: places never reached in one click. Hints, not findings.</p>
<?php if ($journeyCards === []): ?>
      <p>No journeys of two or more clicks yet.</p>
<?php else: ?>
<?php foreach ($journeyCards as $card): ?>
      <section class="usage-journey<?= $card['shortcut'] ? ' usage-journey--idea' : '' ?>"<?= $card['shortcut'] ? ' data-shortcut-idea' : '' ?>>
        <div class="usage-journey__head">
          <h3><span title="<?= $e($card['fromRaw']) ?>"><?= $e($card['from']) ?></span> → <span title="<?= $e($card['toRaw']) ?>"><?= $e($card['to']) ?></span></h3>
          <span class="usage-journey__clicks"><?= $e((string) $card['clicks']) ?> clicks</span>
        </div>
        <ol class="usage-steps">
<?php foreach ($card['steps'] as $step): ?>
          <li title="<?= $e($step['raw']) ?>"><?= $e($step['name']) ?></li>
<?php endforeach; ?>
        </ol>
        <p class="meta"><?= $e((string) $card['times']) ?> <?= $card['times'] === 1 ? 'time' : 'times' ?> · <?= $card['shortcut'] ? 'never in one click' : 'fewest ' . $e((string) $card['fewest']) . ($card['fewest'] === 1 ? ' click' : ' clicks') ?></p>
      </section>
<?php endforeach; ?>
<?php endif; ?>
    </article>
    <article class="card usage-most-clicked">
      <h2>Most clicked</h2>
<?php if ($clickRows === []): ?>
      <p>No clicks recorded yet.</p>
<?php else: ?>
      <ol class="usage-bars">
<?php foreach ($clickRows as $row): ?>
        <li title="<?= $e($row['raw']) ?>">
          <span class="usage-bars__label"><strong><?= $e($row['name']) ?></strong> <span class="meta"><?= $e($row['where']) ?></span></span>
          <span class="usage-bars__count"><?= $e((string) $row['clicks']) ?></span>
          <span class="usage-bars__track" aria-hidden="true"><span style="width: <?= $e((string) $row['percent']) ?>%"></span></span>
        </li>
<?php endforeach; ?>
      </ol>
      <p class="meta">Hover or long-press a name for its raw form.</p>
<?php endif; ?>
    </article>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

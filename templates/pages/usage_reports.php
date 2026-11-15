<section class="stack">
  <article class="card">
    <h1>Usage Reports</h1>
    <p class="meta">Usage logs visitors chose to upload from Tools → Usage log. Kept in a private file, outside forum content and exports.</p>
<?php if ($uploaders === []): ?>
    <p>No reports yet. Turn on recording in <a href="/tools/usage-log/">Usage log</a>, browse, and upload.</p>
<?php else: ?>
    <form class="visitor-statistics-period" action="/tools/usage-reports/" method="get">
      <label for="usage-reports-uploader">Uploaded by</label>
      <select id="usage-reports-uploader" name="uploader" onchange="this.form.submit()">
        <option value=""<?= $uploader === '' ? ' selected' : '' ?>>Everyone</option>
<?php foreach ($uploaders as $row): ?>
<?php $value = $row['username'] ?? 'anonymous'; ?>
        <option value="<?= $e($value) ?>"<?= $value === $uploader ? ' selected' : '' ?>><?= $e($row['username'] ?? 'Anonymous') ?> (<?= $e((string) $row['reports']) ?>)</option>
<?php endforeach; ?>
      </select>
      <noscript><button type="submit">Show</button></noscript>
    </form>
    <dl class="visitor-statistics-tiles">
      <div><dt>Reports</dt><dd><?= $e(number_format($reportCount)) ?></dd></div>
      <div><dt>Lines</dt><dd><?= $e(number_format($entryCount)) ?></dd></div>
      <div><dt>Shortcut candidates</dt><dd><?= $e((string) count(array_filter($journeyGroups, static fn (array $group): bool => $group['shortcut']))) ?></dd></div>
    </dl>
<?php endif; ?>
  </article>
<?php if ($uploaders !== []): ?>
  <article class="card">
    <h2>Journeys</h2>
    <p class="meta">A journey is the clicks between two stops: a pause of <?= $e((string) $stopSeconds) ?> seconds or more, or a form submit. Rows list journeys that took two or more clicks. <strong>Shortcut candidate</strong> means that end was never reached from that start in one click. These are hints, not findings.</p>
<?php if ($journeyGroups === []): ?>
    <p>No journeys of two or more clicks yet.</p>
<?php else: ?>
<?php $journeyRows = array_merge([['From', 'Usual clicks', 'To', 'Times', 'Fewest clicks', '']], array_map(static fn (array $group): array => [$group['start'], implode(' → ', $group['usual']), $group['end'], (string) $group['times'], (string) $group['fewest'], $group['shortcut'] ? 'Shortcut candidate' : ''], $journeyGroups)); ?>
    <?= \ForumRewrite\View\ZoomTable::open('tools', \ForumRewrite\View\ZoomTable::minimumEm($journeyRows), 'Usage journeys table') ?>
      <table class="visitor-statistics-table usage-reports-journeys">
        <thead><tr><th>From</th><th>Usual clicks</th><th>To</th><th>Times</th><th>Fewest clicks</th><th></th></tr></thead>
        <tbody>
<?php foreach ($journeyGroups as $group): ?>
          <tr<?= $group['shortcut'] ? ' data-shortcut-candidate' : '' ?>>
            <td><code><?= $e($group['start']) ?></code></td>
            <td><?= $e(implode(' → ', $group['usual'])) ?></td>
            <td><code><?= $e($group['end']) ?></code></td>
            <td><?= $e((string) $group['times']) ?></td>
            <td><?= $e((string) $group['fewest']) ?></td>
            <td><?= $group['shortcut'] ? '<strong>Shortcut candidate</strong>' : '' ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    <?= \ForumRewrite\View\ZoomTable::close() ?>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Most clicked</h2>
    <p class="meta">Clicks by page, part of the page, and control. Controls without a name hook are counted by their label.</p>
<?php if ($clickCounts === []): ?>
    <p>No clicks recorded yet.</p>
<?php else: ?>
<?php $clickRows = array_merge([['Page', 'Where', 'Control', 'Clicks']], array_map(static fn (array $row): array => [$row['page'], $row['region'], $row['name'], (string) $row['clicks']], $clickCounts)); ?>
    <?= \ForumRewrite\View\ZoomTable::open('tools', \ForumRewrite\View\ZoomTable::minimumEm($clickRows), 'Most clicked table') ?>
      <table class="visitor-statistics-table usage-reports-clicks">
        <thead><tr><th>Page</th><th>Where</th><th>Control</th><th>Clicks</th></tr></thead>
        <tbody>
<?php foreach ($clickCounts as $row): ?>
          <tr><td><code><?= $e($row['page']) ?></code></td><td><?= $e($row['region']) ?></td><td><?= $e($row['name']) ?></td><td><?= $e((string) $row['clicks']) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table>
    <?= \ForumRewrite\View\ZoomTable::close() ?>
<?php endif; ?>
  </article>
<?php endif; ?>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

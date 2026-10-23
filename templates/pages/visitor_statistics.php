<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Visitor Statistics</h1>
    <p class="meta">Page requests, excluding known bots. No browsing history is retained.</p>
<?php if ($dashboard['status'] === 'unavailable'): ?>
    <div class="feedback feedback-error">Visitor statistics are currently unavailable. Check the private statistics-state path and try again.</div>
<?php elseif ($dashboard['status'] === 'initializing'): ?>
    <p>Visitor statistics are initializing.</p>
<?php else: ?>
<?php $totals = $dashboard['totals']; ?>
<?php $buckets = $dashboard['buckets']; ?>
    <form class="visitor-statistics-period" action="/tools/visitor-statistics/" method="get">
      <label for="visitor-statistics-period">Period</label>
      <select id="visitor-statistics-period" name="period" onchange="this.form.submit()">
<?php foreach ($periodOptions as $key => $option): ?>
        <option value="<?= $e($key) ?>"<?= $key === $periodKey ? ' selected' : '' ?>><?= $e($option['label']) ?></option>
<?php endforeach; ?>
      </select>
    </form>
<?php if ($dashboard['status'] === 'partial'): ?>
    <p class="feedback feedback-warning">Hourly collection began <?= $e($dashboard['collection_started_at']) ?>, so this period is incomplete.</p>
<?php endif; ?>
    <dl class="visitor-statistics-tiles">
      <div><dt>Eligible</dt><dd><?= $e(number_format($totals['visits'])) ?></dd></div>
      <div><dt>Anonymous</dt><dd><?= $e(number_format($totals['anonymous_visits'])) ?></dd></div>
      <div><dt>Authenticated</dt><dd><?= $e(number_format($totals['authenticated_visits'])) ?></dd></div>
      <div><dt>Estimated clients</dt><dd><?= $e(number_format($totals['clients'])) ?></dd></div>
      <div><dt>Authenticated users</dt><dd><?= $e(number_format($totals['authenticated_users'])) ?></dd></div>
    </dl>
<?php if ($buckets === []): ?>
    <p class="meta">No eligible requests were observed in this period.</p>
<?php endif; ?>
<?php $maxVisits = $buckets === [] ? 0 : max(array_map(static fn (array $bucket): int => $bucket['visits'], $buckets)); ?>
<?php $plotWidth = 700; $plotHeight = 160; $barWidth = max(2, min(18, (int) floor($plotWidth / max(1, count($buckets))))); ?>
<?php $periodStartTimestamp = (new \DateTimeImmutable($dashboard['period_start']))->getTimestamp(); ?>
<?php $periodEndTimestamp = (new \DateTimeImmutable($dashboard['period_end']))->getTimestamp(); ?>
<?php $periodDuration = max(1, $periodEndTimestamp - $periodStartTimestamp); ?>
    <figure class="visitor-statistics-trend">
      <figcaption>Eligible requests over time (<?= $e($periodOptions[$periodKey]['label']) ?>)</figcaption>
      <svg viewBox="0 0 740 210" role="img" aria-labelledby="visitor-statistics-trend-title visitor-statistics-trend-description">
        <title id="visitor-statistics-trend-title">Eligible requests over time</title>
        <desc id="visitor-statistics-trend-description">Each bar shows the eligible requests in an observed <?= $periodKey === '24h' ? 'UTC hour' : 'UTC day' ?>. Green is anonymous and dark green is server-authenticated.</desc>
        <line x1="20" y1="180" x2="720" y2="180" class="visitor-statistics-axis" />
<?php foreach ($buckets as $index => $bucket): ?>
<?php $height = $maxVisits === 0 ? 0 : (int) round($plotHeight * $bucket['visits'] / $maxVisits); ?>
<?php $authenticatedHeight = $bucket['visits'] === 0 ? 0 : (int) round($height * $bucket['authenticated_visits'] / $bucket['visits']); ?>
<?php $bucketTimestamp = (new \DateTimeImmutable($bucket['bucket_start']))->getTimestamp(); ?>
<?php $x = 20 + (int) round(($plotWidth - $barWidth) * max(0, min(1, ($bucketTimestamp - $periodStartTimestamp) / $periodDuration))); ?>
        <rect class="visitor-statistics-bar-anonymous" x="<?= $x ?>" y="<?= 180 - $height ?>" width="<?= $barWidth ?>" height="<?= $height - $authenticatedHeight ?>"><title><?= $e($bucket['bucket_start'] . ': ' . $bucket['anonymous_visits'] . ' anonymous requests') ?></title></rect>
        <rect class="visitor-statistics-bar-authenticated" x="<?= $x ?>" y="<?= 180 - $authenticatedHeight ?>" width="<?= $barWidth ?>" height="<?= $authenticatedHeight ?>"><title><?= $e($bucket['bucket_start'] . ': ' . $bucket['authenticated_visits'] . ' server-authenticated requests') ?></title></rect>
<?php endforeach; ?>
        <text x="20" y="202"><?= $e($dashboard['period_start']) ?></text>
        <text x="720" y="202" text-anchor="end"><?= $e($dashboard['period_end']) ?></text>
      </svg>
    </figure>
    <table class="visitor-statistics-table">
      <thead><tr><th><?= $periodKey === '24h' ? 'UTC hour' : 'UTC day' ?></th><th>Eligible</th><th>Anonymous</th><th>Authenticated</th><th>Estimated clients</th><th>Authenticated users</th></tr></thead>
      <tbody>
<?php foreach ($buckets as $bucket): ?>
        <tr><th scope="row"><?= $e($periodKey === '24h' ? $bucket['bucket_start'] : substr($bucket['bucket_start'], 0, 10)) ?></th><td><?= $e(number_format($bucket['visits'])) ?></td><td><?= $e(number_format($bucket['anonymous_visits'])) ?></td><td><?= $e(number_format($bucket['authenticated_visits'])) ?></td><td><?= $e(number_format($bucket['clients'])) ?></td><td><?= $e(number_format($bucket['authenticated_users'])) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <details class="visitor-statistics-counting">
      <summary>How this is counted</summary>
      <p>Assets, APIs, known bots, and traffic outside this application are excluded. Authenticated requests have a verified session. Client estimates may overlap before and after sign-in.</p>
      <p>No IP addresses, user agents, sessions, public keys, paths, or referrers are retained. UTC hourly totals are kept for up to 90 days.</p>
    </details>
<?php endif; ?>
  </article>
</section>

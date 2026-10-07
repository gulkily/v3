<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Visitor Statistics</h1>
    <p class="meta">Server-observed eligible page requests only. Static assets, APIs, recognizable automation, and traffic served outside this application are excluded.</p>
<?php if ($summary['status'] === 'unavailable'): ?>
    <div class="feedback feedback-error">Visitor statistics are currently unavailable. Check the private statistics-state path and try again.</div>
<?php elseif ($summary['status'] === 'initializing'): ?>
    <p>Visitor statistics are initializing. Counts will appear after eligible page visits are observed.</p>
<?php else: ?>
    <p class="meta">Client counts are privacy-preserving estimates. Authenticated-user counts cover only server-authenticated visitors. No visitor list, browsing history, IP address, user agent, path, or referrer is retained.</p>
    <table>
      <thead><tr><th>Period</th><th>Eligible visits</th><th>Estimated clients</th><th>Authenticated users</th></tr></thead>
      <tbody>
<?php foreach ([1 => 'Last 24 hours', 7 => 'Last 7 days', 30 => 'Last 30 days'] as $window => $label): ?>
<?php $counts = $summary['windows'][$window]; ?>
        <tr><th scope="row"><?= $e($label) ?></th><td><?= $e((string) $counts['visits']) ?></td><td><?= $e((string) $counts['clients']) ?></td><td><?= $e((string) $counts['authenticated_users']) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </article>
</section>

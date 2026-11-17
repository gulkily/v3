<section class="stack">
  <article class="card">
    <h1>LLM Exchanges</h1>
    <p class="meta">Private provider conversations.</p>
<?php if ($exchanges === []): ?>
    <p>No recorded LLM exchanges are available.</p>
<?php else: ?>
<?php
$tableRows = [['Time', 'Call', 'Related post', 'Provider', 'Status']];
foreach ($exchanges as $exchange) {
    $tableRows[] = [
        (string) $exchange['occurred_at'],
        (string) $exchange['call_type'],
        (string) ($exchange['related_post_id'] ?? 'none'),
        ($exchange['provider'] ?? '') . (($exchange['provider_model'] ?? '') !== '' ? ' / ' . $exchange['provider_model'] : ''),
        (string) $exchange['status'],
    ];
}
?>
    <?= \ForumRewrite\View\ZoomTable::open('tools', \ForumRewrite\View\ZoomTable::minimumEm($tableRows), 'LLM exchanges table') ?>
      <table class="codebase-facts codebase-facts--columns">
        <thead>
          <tr><th scope="col">Time</th><th scope="col">Call</th><th scope="col">Related post</th><th scope="col">Provider</th><th scope="col">Status</th></tr>
        </thead>
        <tbody>
<?php foreach ($exchanges as $exchange): ?>
          <tr>
            <td><a href="/tools/llm-exchanges/<?= (int) $exchange['id'] ?>/"><?= $e($exchange['occurred_at']) ?></a></td>
            <td><?= $e($exchange['call_type']) ?></td>
            <td>
<?php if (($exchange['related_post_id'] ?? null) !== null): ?>
              <a href="/posts/<?= $e($exchange['related_post_id']) ?>"><?= $e($exchange['related_post_id']) ?></a>
<?php else: ?>
              none
<?php endif; ?>
            </td>
            <td><?= $e(($exchange['provider'] ?? '') . (($exchange['provider_model'] ?? '') !== '' ? ' / ' . $exchange['provider_model'] : '')) ?></td>
            <td><?= $e($exchange['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    <?= \ForumRewrite\View\ZoomTable::close() ?>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

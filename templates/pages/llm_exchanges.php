<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card">
    <h1>LLM Exchanges</h1>
    <p class="meta">Private provider conversations, newest first.</p>
<?php if ($exchanges === []): ?>
    <p>No recorded LLM exchanges are available.</p>
<?php else: ?>
    <div class="table-scroll">
      <table class="codebase-facts">
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
    </div>
<?php endif; ?>
  </article>
</section>

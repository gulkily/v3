<?php
$requestJson = json_encode($exchange['request'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$responseJson = json_encode($exchange['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$errorJson = json_encode($exchange['error'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
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
    <p><a href="/tools/llm-exchanges/">Back to LLM Exchanges</a></p>
    <h1>LLM Exchange <?= (int) $exchange['id'] ?></h1>
    <p class="meta"><?= $e($exchange['occurred_at']) ?> · <?= $e($exchange['call_type']) ?> · <?= $e($exchange['status']) ?></p>
    <p class="meta">Provider: <?= $e((string) ($exchange['provider'] ?? '')) ?> / <?= $e((string) ($exchange['provider_model'] ?? '')) ?> · Request ID: <?= $e((string) ($exchange['provider_request_id'] ?? 'unavailable')) ?></p>
<?php if (($exchange['related_post_id'] ?? null) !== null): ?>
    <p class="meta">Related post: <a href="/posts/<?= $e($exchange['related_post_id']) ?>"><?= $e($exchange['related_post_id']) ?></a></p>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Prompt / request</h2>
    <pre><code><?= $e((string) $requestJson) ?></code></pre>
  </article>
  <article class="card">
    <h2>Response</h2>
    <pre><code><?= $e((string) $responseJson) ?></code></pre>
<?php if ($errorJson !== '{}' && $errorJson !== '[]'): ?>
    <h2>Error</h2>
    <pre><code><?= $e((string) $errorJson) ?></code></pre>
<?php endif; ?>
  </article>
</section>

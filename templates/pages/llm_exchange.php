<?php
$formatContent = static function ($content) use (&$formatContent) {
    if (is_scalar($content)) {
        return (string) $content;
    }
    if (!is_array($content)) {
        return '';
    }

    $parts = [];
    foreach ($content as $item) {
        if (is_array($item) && array_key_exists('text', $item)) {
            $parts[] = $formatContent($item['text']);
        } elseif (is_array($item) && array_key_exists('content', $item)) {
            $parts[] = $formatContent($item['content']);
        } elseif (is_scalar($item)) {
            $parts[] = (string) $item;
        }
    }

    $formatted = trim(implode("\n\n", array_filter($parts, static function ($part) {
        return $part !== '';
    })));

    return $formatted !== '' ? $formatted : (string) json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
};
$formatBody = static function ($body) use ($formatContent) {
    if (!is_string($body) || trim($body) === '') {
        return '';
    }

    $decoded = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $formatContent($decoded);
    }

    return $body;
};
$request = is_array($exchange['request'] ?? null) ? $exchange['request'] : [];
$response = is_array($exchange['response'] ?? null) ? $exchange['response'] : [];
$requestPayload = is_array($request['payload'] ?? null) ? $request['payload'] : $request;
$requestMessages = is_array($requestPayload['messages'] ?? null) ? $requestPayload['messages'] : [];
$systemPrompt = trim($formatContent($requestPayload['system'] ?? ''));
$responseDecoded = is_array($response['decoded'] ?? null) ? $response['decoded'] : [];
$responseMessages = [];
if (is_array($responseDecoded['choices'] ?? null)) {
    foreach ($responseDecoded['choices'] as $choice) {
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        if ($message !== []) {
            $responseMessages[] = [
                'role' => (string) ($message['role'] ?? 'assistant'),
                'content' => $formatContent($message['content'] ?? ''),
            ];
        }
    }
}
if (is_array($responseDecoded['content'] ?? null)) {
    foreach ($responseDecoded['content'] as $block) {
        if (is_array($block) && (string) ($block['type'] ?? '') === 'text') {
            $responseMessages[] = ['role' => 'assistant', 'content' => $formatContent($block['text'] ?? '')];
        }
    }
}
$requestJson = json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$responseJson = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
    <h2>Prompt</h2>
<?php if ($systemPrompt !== ''): ?>
    <section class="stack">
      <p class="meta">system</p>
      <pre><code><?= $e($systemPrompt) ?></code></pre>
    </section>
<?php endif; ?>
<?php foreach ($requestMessages as $message): ?>
<?php if (!is_array($message)) { continue; } ?>
    <section class="stack">
      <p class="meta"><?= $e((string) ($message['role'] ?? 'message')) ?></p>
      <pre><code><?= $e($formatContent($message['content'] ?? '')) ?></code></pre>
    </section>
<?php endforeach; ?>
<?php if ($requestMessages === [] && $systemPrompt === ''): ?>
    <pre><code><?= $e($formatBody($requestPayload['body'] ?? $request['body'] ?? '') ?: 'No prompt content extracted.') ?></code></pre>
<?php endif; ?>
    <details>
      <summary>Raw request JSON</summary>
      <pre><code><?= $e((string) $requestJson) ?></code></pre>
    </details>
  </article>
  <article class="card">
    <h2>Response</h2>
<?php if ($responseMessages !== []): ?>
<?php foreach ($responseMessages as $message): ?>
    <section class="stack">
      <p class="meta"><?= $e($message['role']) ?></p>
      <pre><code><?= $e((string) $message['content']) ?></code></pre>
    </section>
<?php endforeach; ?>
<?php elseif ($formatBody($response['body'] ?? '') !== ''): ?>
    <pre><code><?= $e($formatBody($response['body'])) ?></code></pre>
<?php else: ?>
    <p>No response body was recorded.</p>
<?php endif; ?>
    <details>
      <summary>Raw response JSON</summary>
      <pre><code><?= $e((string) $responseJson) ?></code></pre>
    </details>
<?php if ($errorJson !== '{}' && $errorJson !== '[]'): ?>
    <h2>Error</h2>
    <pre><code><?= $e((string) $errorJson) ?></code></pre>
<?php endif; ?>
  </article>
</section>

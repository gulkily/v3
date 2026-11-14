<?php
$sourceHref = static fn (string $path): string => '/source/current/' . implode('/', array_map('rawurlencode', explode('/', $path)));
$kind = match (true) {
    str_starts_with($recordId, 'post-format-') => 'Format change',
    str_starts_with($recordId, 'thread-subject-') => 'Title',
    $family === 'post-reaction' => 'Post reaction',
    $family === 'thread-label' => 'Thread label',
    default => 'Post',
};
$author = (string) ($headers['Author-Identity-ID'] ?? $headers['Actor-Identity-ID'] ?? ($removal['post']['author_identity_id'] ?? ''));
?>
<section class="stack record-page">
  <article class="card">
    <h1><?= $e($kind) ?> <?= $e($recordId) ?></h1>
<?php if ($targetPostId !== null && $targetPostId !== ''): ?>
    <p class="meta">On <a href="/posts/<?= $e(rawurlencode((string) $targetPostId)) ?>">post <?= $e($targetPostId) ?></a> · <a href="/posts/<?= $e(rawurlencode((string) $targetPostId)) ?>#records">all its records</a></p>
<?php endif; ?>
<?php if ($isPost): ?>
    <p class="meta"><a href="/posts/<?= $e(rawurlencode($recordId)) ?>">Read this post</a></p>
<?php endif; ?>
    <p class="meta">By <span title="<?= $e($author) ?>"><?= $e($author !== '' ? $author : 'unsigned') ?></span></p>
<?php
$recordTags = preg_split('/\s+/', trim((string) ($headers['Tags'] ?? $headers['Labels'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$recordIsLike = $recordPath !== null && in_array($family, ['post-reaction', 'thread-label'], true) && $recordTags === ['like']
    && ($headers['Operation'] ?? 'add') === 'add' && !isset($headers['Source-Post-ID']);
?>
<?php if ($recordIsLike && !is_array($removal)): ?>
    <p><?= $partial('partials/retract_record_control.php', ['recordId' => $recordId, 'recordAuthorIdentityId' => $author,
        'viewerProfile' => $viewerProfile ?? null, 'viewerCanRetractOthers' => $viewerCanRetractOthers ?? false]) ?></p>
<?php endif; ?>
<?php if (is_array($removal)): ?>
    <div class="record-removed" role="status">
      <p><strong>Retracted.</strong> Accepted <?= $e((string) ($removal['accepted_at'] ?? '')) ?><?php if (($removal['actor'] ?? '') !== '' && ($removal['actor'] ?? '') !== $author): ?> by <span title="<?= $e((string) $removal['actor']) ?>"><?= $e((string) $removal['actor']) ?></span><?php endif; ?>.</p>
      <p class="meta">The record was removed; its receipt remains: <a href="<?= $e($sourceHref((string) $removalPath)) ?>"><?= $e((string) $removalPath) ?></a></p>
    </div>
<?php endif; ?>
<?php if ($recordPath !== null): ?>
    <dl class="record-fields">
<?php foreach ($headers as $name => $value): ?>
      <dt><?= $e((string) $name) ?></dt><dd><?= $e((string) $value) ?></dd>
<?php endforeach; ?>
    </dl>
<?php if (!$isPost && trim((string) $body) !== ''): ?>
    <pre class="record-body"><?= $e((string) $body) ?></pre>
<?php endif; ?>
    <p class="meta">Source: <a href="<?= $e($sourceHref($recordPath)) ?>"><?= $e($recordPath) ?></a></p>
    <p class="meta">Signature:
<?php if ($signaturePath !== null): ?>
      <a href="<?= $e($sourceHref($signaturePath)) ?>"><?= $e($signaturePath) ?></a><?= $signatureStatus !== null ? ' (' . $e((string) $signatureStatus) . ')' : '' ?>
<?php else: ?>
      <span>none</span>
<?php endif; ?>
    </p>
<?php endif; ?>
  </article>
</section>

<?php
/** @var list<array<string, mixed>> $records rows from ForumRewrite\Canonical\PostRecordList::forPost() */
$records = $records ?? [];
$sourceHref = static fn (string $path): string => '/source/current/' . implode('/', array_map('rawurlencode', explode('/', $path)));
$shortIdentity = static fn (?string $identity): string => $identity === null ? 'unsigned' : 'openpgp:…' . substr($identity, -8);
?>
<details class="card post-records" id="records">
  <summary>Records (<?= count($records) ?>)</summary>
  <p class="meta">Every signed record that targets this post. Each one has its own page.</p>
<?php if ($records === []): ?>
  <p class="meta">No records found.</p>
<?php else: ?>
  <table class="post-records-table">
    <thead><tr><th scope="col">Kind</th><th scope="col">What</th><th scope="col">By</th><th scope="col">When</th><th scope="col">Files</th></tr></thead>
    <tbody>
<?php foreach ($records as $record): ?>
      <tr<?= !empty($record['retracted']) ? ' class="post-record-retracted"' : '' ?> data-record-id="<?= $e($record['record_id']) ?>">
        <td><a href="/records/<?= $e(rawurlencode((string) $record['record_id'])) ?>"><?= $e($record['kind']) ?></a></td>
        <td><?= !empty($record['retracted']) ? '<s>' . $e($record['kind']) . '</s> ' : '' ?><?= $e($record['summary']) ?></td>
        <td title="<?= $e((string) ($record['author_identity_id'] ?? '')) ?>"><?= $e($shortIdentity($record['author_identity_id'] ?? null)) ?></td>
        <td><?= $e($record['created_at']) ?></td>
        <td><a href="<?= $e($sourceHref((string) $record['path'])) ?>"><?= !empty($record['retracted']) ? 'receipt' : 'source' ?></a></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</details>

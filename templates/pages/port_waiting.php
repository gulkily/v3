<?php
/**
 * Ports guests sent, waiting for an approved member to port them.
 *
 * @var list<array{id:string, port:\ForumRewrite\Port\PortFile, held_at:string, error:string}> $items
 */
?>
<section class="stack port-waiting">
  <article class="card">
    <h1>Ports waiting</h1>
<?php if ($items === []): ?>
    <p>Nothing waiting.</p>
<?php else: ?>
    <ul>
<?php foreach ($items as $item): ?>
      <li><a href="/port/<?= $e($item['id']) ?>"><?= $e($item['port']->threadTitle !== '' ? $item['port']->threadTitle : $item['port']->root()->title) ?></a> <span class="meta">· <?= $e($item['port']->commentCount() === 1 ? '1 comment' : $item['port']->commentCount() . ' comments') ?> · <?= $shortDate($item['held_at']) ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>
</section>

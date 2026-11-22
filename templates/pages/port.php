<?php
/**
 * Port preview: what the bookmarklet sent, and Port for an approved member.
 *
 * @var string $heldId
 * @var \ForumRewrite\Port\PortFile $port
 * @var ?string $existingThreadId
 * @var bool $canPort
 * @var bool $signedIn
 * @var bool $guestsMayPort
 * @var string $error
 */
$root = $port->root();
$count = $port->commentCount();
?>
<section class="stack port-preview">
  <article class="card">
    <h1><?= $e($port->threadTitle !== '' ? $port->threadTitle : $root->title) ?></h1>
    <p class="meta"><?= $e($root->author) ?> on <a href="<?= $e(\ForumRewrite\Port\PortFile::itemAddress($root->id)) ?>" rel="nofollow noopener">Hacker News</a> · <?= $e($count === 1 ? '1 comment' : $count . ' comments') ?></p>
<?php if ($error !== ''): ?>
    <p role="alert"><?= $e($error) ?></p>
<?php endif; ?>
<?php if ($existingThreadId !== null): ?>
    <p>Already ported. <a href="/threads/<?= $e(rawurlencode($existingThreadId)) ?>">Open the thread</a></p>
<?php elseif ($canPort): ?>
    <form method="post" action="/port/<?= $e($heldId) ?>">
      <button type="submit"><?= $error !== '' ? 'Try again' : 'Port' ?></button>
    </form>
<?php elseif ($guestsMayPort): ?>
    <p>Waiting for a member to port it.</p>
<?php elseif ($signedIn): ?>
    <p>Only approved members can port.</p>
<?php else: ?>
    <p><a href="/account/key/">Sign in</a> as an approved member to port.</p>
<?php endif; ?>
  </article>
</section>

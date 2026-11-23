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
$isComment = \ForumRewrite\Port\PortFile::itemId($port->threadLink) !== $root->id;
?>
<section class="stack port-preview">
  <article class="card">
    <h1><?= $e($port->threadTitle !== '' ? $port->threadTitle : $root->title) ?></h1>
    <p class="meta">
      <?= $e($root->author) ?> · <time datetime="<?= $e($root->createdAt) ?>"><?= $e(str_replace(['T', 'Z'], [' ', ' UTC'], $root->createdAt)) ?></time>
<?php if ($isComment): ?>
      · <a href="<?= $e(\ForumRewrite\Port\PortFile::itemAddress($root->id)) ?>" rel="nofollow noopener">Original comment</a>
<?php endif; ?>
    </p>
    <p class="meta">
      <a href="<?= $e($port->threadLink) ?>" rel="nofollow noopener">HN discussion</a>
<?php if ($port->points !== null): ?>
      · <?= $e($port->points . ($port->points === 1 ? ' point' : ' points')) ?>
<?php endif; ?>
<?php if ($port->sourceCommentCount !== null): ?>
      · <?= $e($port->sourceCommentCount . ($port->sourceCommentCount === 1 ? ' comment on HN' : ' comments on HN')) ?>
<?php endif; ?>
    </p>
    <p class="meta"><?= $e($count . ($count === 1 ? ' comment to port' : ' comments to port')) ?></p>
<?php if ($root->link !== ''): ?>
    <p style="overflow-wrap: anywhere"><a href="<?= $e($root->link) ?>" rel="nofollow noopener"><?= $e($root->link) ?></a></p>
<?php endif; ?>
<?php if ($root->text !== ''): ?>
    <div style="white-space: pre-wrap; overflow-wrap: anywhere"><?= $e($root->text) ?></div>
<?php endif; ?>
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

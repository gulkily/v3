<?php
/**
 * Inbox: unread messages, then replies to the viewer's posts, then other new posts.
 *
 * @var array{enabled:bool,failed:bool,conversations:list<array{counterpart:string,unread_count:int,latest_at:string}>} $messages
 */
?>
<section class="stack inbox">
  <h1 class="visually-hidden">Inbox</h1>
<?php if ($messages['enabled']): ?>
  <section class="stack inbox-section" aria-labelledby="inbox-messages">
    <h2 id="inbox-messages">Messages</h2>
<?php if ($messages['failed']): ?>
    <p class="message-list-alert" role="alert">Couldn’t check messages. <a href="/inbox">Retry</a></p>
<?php elseif ($messages['conversations'] === []): ?>
    <p class="meta">No unread messages</p>
<?php else: ?>
    <div class="thread-rows">
<?php foreach ($messages['conversations'] as $conversation): ?>
      <article class="thread-row inbox-conversation" data-counterpart="<?= $e($conversation['counterpart']) ?>">
        <h3 class="thread-row__title"><a href="/messages/conversation/<?= $e(rawurlencode($conversation['counterpart'])) ?>"><?= $e($conversation['counterpart']) ?></a></h3>
        <p class="meta thread-row__meta"><?= $e((string) $conversation['unread_count']) ?> new · <time datetime="<?= $e($conversation['latest_at']) ?>"><?= $e(str_replace(['T', 'Z'], [' ', ' UTC'], $conversation['latest_at'])) ?></time></p>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
    <p class="meta"><a href="/messages">All messages</a></p>
  </section>
<?php endif; ?>
</section>

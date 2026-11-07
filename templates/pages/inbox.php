<?php
/**
 * Inbox: unread messages, then replies to the viewer's posts, then other new posts.
 *
 * @var array{enabled:bool,failed:bool,conversations:list<array{counterpart:string,unread_count:int,latest_at:string}>} $messages
 * @var array<string, array{items:list<array<string,mixed>>,failed:bool,catch_up_failed:bool}> $sections
 */
?>
<section class="stack inbox" data-approved-identity="<?= $e((string) ($viewerProfile['identity_id'] ?? '')) ?>">
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
<?php $sectionHeadings = ['replies' => ['Replies', 'No new replies'], 'new' => ['New posts', 'No new posts']]; ?>
<?php foreach ($sections as $name => $section): ?>
  <section class="stack inbox-section" id="inbox-<?= $e($name) ?>" aria-labelledby="inbox-<?= $e($name) ?>-heading">
    <h2 id="inbox-<?= $e($name) ?>-heading"><?= $e($sectionHeadings[$name][0]) ?></h2>
<?php if ($section['failed']): ?>
    <p class="message-list-alert" role="alert">Couldn’t check <?= $e(strtolower($sectionHeadings[$name][0])) ?>. <a href="/inbox">Retry</a></p>
<?php elseif ($section['items'] === []): ?>
    <p class="meta"><?= $e($sectionHeadings[$name][1]) ?></p>
<?php else: ?>
    <div class="thread-rows">
<?php foreach ($section['items'] as $item): ?>
<?= $indent($partial('partials/recent_entry.php', ['item' => $item]), 3) ?>
<?php endforeach; ?>
    </div>
    <form method="post" action="/inbox" class="inbox-catch-up">
      <input type="hidden" name="section" value="<?= $e($name) ?>">
<?php foreach ($section['items'] as $item): ?>
      <input type="hidden" name="post_ids[]" value="<?= $e($item['post_id']) ?>">
<?php endforeach; ?>
<?php if ($section['catch_up_failed']): ?>
      <p class="message-list-alert" role="alert">Couldn’t save. Nothing was marked read.</p>
<?php endif; ?>
      <button type="submit"><?= $section['catch_up_failed'] ? 'Retry' : 'Caught up' ?></button>
    </form>
<?php endif; ?>
  </section>
<?php endforeach; ?>
</section>

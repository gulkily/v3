<section class="stack thread-list">
<?php if (!$isQdbInstance): ?>
  <article class="card">
    <div class="nav board-controls-nav">
      <a class="nav-link" href="/tags/">Tags</a>
<?php foreach ($viewOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
<?php foreach ($sortOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
      <a class="nav-link" href="/compose/thread">New Post</a>
    </div>
  </article>
<?php endif; ?>
<?php if (!empty($pagination)): ?>
<?= $indent($partial('partials/board_pagination_nav.php', ['pagination' => $pagination]), 0) ?>
<?php endif; ?>
  <article class="card inline-reply-composer compact-thread-compose" data-compose-root data-pending-thread-position="after" data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>">
    <details class="inline-reply-details compact-thread-compose-details" data-inline-reply-details>
      <summary class="inline-reply-summary">
        <textarea
          class="inline-reply-prompt compact-thread-compose-prompt"
          rows="2"
          placeholder="<?= $e($composerPrompt) ?>"
          aria-label="<?= $e(rtrim($composerPrompt, '.')) ?>"
          data-inline-reply-trigger
          readonly
        ></textarea>
      </summary>
      <div class="inline-reply-expanded">
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/thread_compose_form.php', [
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'notice' => null,
    'error' => null,
    'compact' => true,
]), 4) ?>
      </div>
    </details>
  </article>
<?php foreach ($threads as $thread): ?>
<?= $indent($partial($boardCardPartial, ['thread' => $thread] + $boardCardData), 1) ?>
<?php endforeach; ?>
<?php if (!empty($pagination)): ?>
<?= $indent($partial('partials/board_pagination_nav.php', ['pagination' => $pagination]), 0) ?>
<?php endif; ?>
</section>
<?php if ($boardFooterPartial !== null): ?>
<?= $partial($boardFooterPartial, ['qdbQuoteCount' => $qdbQuoteCount]) ?>
<?php endif; ?>

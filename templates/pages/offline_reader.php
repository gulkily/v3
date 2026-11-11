<section class="stack thread-list" data-offline-reader data-tag-links="<?= $tagLinksEnabled ? '1' : '0' ?>" data-snapshot-url="/offline/snapshot.sqlite3" data-update-url="/offline/update.sqlite3" data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-reader-revision="<?= htmlspecialchars((string) ($readerRevision ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-snapshot-revision="<?= htmlspecialchars((string) ($snapshotRevision ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="offline-mode-bar" data-role="offline-mode-bar" hidden>
    <span>offline mode</span>
    <a class="offline-mode-bar__offline" href="/offline/">Offline</a>
    <a class="offline-mode-bar__outbox" href="/tools/outbox/">Outbox</a>
    <span class="offline-mode-bar__indicators" data-role="offline-mode-indicators" hidden>
      <span class="offline-mode-bar__indicator" data-role="offline-archive-indicator"></span>
      <span class="offline-mode-bar__indicator" data-role="offline-reader-indicator"></span>
    </span>
  </div>
  <p class="meta" data-role="offline-reader-status" aria-live="polite">Starting saved reader…</p>
  <div class="stack" data-role="offline-reader-content" hidden></div>
</section>
<?php /* The same composers the online board and thread render; the reader mounts them once it has drawn the page.
   They need the page renderer's helpers, so a bare include of this template (as some browser tests do) leaves them out. */ ?>
<?php if (isset($partial)): ?>
<template data-offline-composer="thread">
  <article class="card inline-reply-composer compact-thread-compose" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>">
    <div class="inline-reply-expanded" data-inline-thread-compose data-inline-thread-expanded="0">
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/thread_compose_form.php', [
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'notice' => null,
    'error' => null,
    'compact' => true,
    'bodyPlaceholder' => $composerPrompt,
]), 4) ?>
    </div>
  </article>
</template>
<template data-offline-composer="reply">
  <article class="card inline-reply-composer" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>">
    <div class="inline-reply-expanded" data-inline-reply-compose data-inline-reply-expanded="0" data-reply-panel>
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => '',
    'parentId' => '',
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post reply',
    'phoneReplyEnabled' => true,
    'showBodyLabel' => false,
    'bodyPlaceholder' => 'Write a reply...',
]), 2) ?>
    </div>
  </article>
</template>
<?php endif; ?>

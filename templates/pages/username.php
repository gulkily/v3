<?php
// One page per person: name, counts and status, Message (or Edit in Account on your own page),
// Threads | Replies | Identities (each key, most recently active first).
$personTab = (string) ($personTab ?? 'threads');
$isOwnPage = (bool) ($isOwnPage ?? false);
$viewerIsOperator = (bool) ($viewerIsOperator ?? false);
$personName = $approvedProfiles !== [] ? (string) ($approvedProfiles[0]['username'] ?? $usernameToken) : $usernameToken;
$isAgent = $usernameToken === 'reply-agent';
$approvedReplies = array_values(array_filter($approvedPosts, static fn (array $post): bool => trim((string) ($post['parent_id'] ?? '')) !== ''));
$firstLine = static function (string $body): string {
    foreach (preg_split('/\R/', $body) ?: [] as $line) {
        if (trim($line) !== '') {
            return mb_strimwidth(trim($line), 0, 120, '…');
        }
    }
    return 'Reply';
};
?>
<section class="stack person-page">
  <div class="account-card person-head" data-tone="neutral" data-person-head>
    <div class="starfield banner" data-seed="<?= $e($usernameToken) ?>" aria-hidden="true"></div>
    <div class="account-id">
      <span class="account-avatar" aria-hidden="true"><?= $e(mb_substr($personName, 0, 1)) ?></span>
      <div class="account-id__text">
        <h1 class="account-name"><?= $e($personName) ?></h1>
<?php if ($approvedProfiles !== []): ?>
        <p class="account-key-line"><?= (int) $approvedThreadCount ?> <?= (int) $approvedThreadCount === 1 ? 'thread' : 'threads' ?> · <?= (int) $approvedPostCount ?> <?= (int) $approvedPostCount === 1 ? 'post' : 'posts' ?> <?php if ($isAgent): ?><span class="chip agent-label">Automated reply agent</span><?php else: ?><span class="chip chip--approved">Approved</span><?php endif; ?></p>
<?php endif; ?>
      </div>
    </div>
<?php if ($isOwnPage): ?>
    <form class="person-head__action" method="get" action="/account/key/"><button type="submit">Edit in Account</button></form>
<?php elseif ($canPrivateMessage && !$isAgent): ?>
    <form class="person-head__action" method="get" action="/messages/conversation/<?= $e(rawurlencode($usernameToken)) ?>"><button type="submit">Message</button></form>
<?php endif; ?>
  </div>

<?php if ($approvedProfiles === []): ?>
  <article class="card">
    <p>No approved member uses this name.</p>
  </article>
<?php else: ?>
  <nav class="board-filter" aria-label="<?= $e($personName) ?>’s posts" data-person-tabs>
<?php foreach (['threads' => 'Threads', 'replies' => 'Replies', 'identities' => 'Identities'] as $tabKey => $tabLabel): ?>
    <a class="nav-link<?= $personTab === $tabKey ? ' is-active' : '' ?>" href="/user/<?= $e($usernameToken) ?><?= $tabKey === 'threads' ? '' : '?tab=' . $tabKey ?>" data-person-tab="<?= $tabKey ?>"<?= $personTab === $tabKey ? ' aria-current="page"' : '' ?>><?= $tabLabel ?></a>
<?php endforeach; ?>
  </nav>
  <div data-person-panel="threads"<?= $personTab === 'threads' ? '' : ' hidden' ?>>
<?php if ($approvedThreads === []): ?>
    <p class="meta">No threads<?= $isAgent ? ' — ' . $e($personName) . ' only answers threads that others start.' : '.' ?></p>
<?php else: ?>
    <div class="thread-rows">
<?php foreach ($approvedThreads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', ['thread' => $thread]), 3) ?>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
  <div data-person-panel="replies"<?= $personTab === 'replies' ? '' : ' hidden' ?>>
<?php if ($approvedReplies === []): ?>
    <p class="meta">No replies.</p>
<?php else: ?>
    <div class="thread-rows">
<?php foreach ($approvedReplies as $post): ?>
      <article class="thread-row">
        <h3 class="thread-row__title"><a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($firstLine((string) ($post['body'] ?? ''))) ?></a></h3>
        <p class="meta thread-row__meta"><?= $rowMeta($post) ?></p>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
  <div data-person-panel="identities"<?= $personTab === 'identities' ? '' : ' hidden' ?>>
    <div class="account-group" id="account-keys">
<?php foreach ($identityRows as $profile): ?>
<?php
    $approvedBy = trim((string) ($profile['approved_by_label'] ?? ''));
    $latest = $profile['latest_post'];
    $latestText = $latest === null ? '' : mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', $plainText($latest, trim((string) ($latest['subject'] ?? '')) !== '' ? (string) $latest['subject'] : (string) ($latest['body'] ?? '')))), 0, 240, '…');
?>
      <a class="account-row account-row--link person-identity" data-tone="neutral" href="/profiles/<?= $e($profile['profile_slug']) ?>"><span class="account-row__title"><span class="account-key-fingerprint-value" title="<?= $e($profile['profile_slug']) ?>"><?= $e($shortenProfileSlug((string) $profile['profile_slug'])) ?></span><span class="meta person-identity__status"><?= $latest !== null ? 'Active ' . $relativeTimestamp((string) $latest['created_at']) : 'No posts' ?> · <?= !empty($profile['retired_at']) ? 'Retired' : ($approvedBy !== '' ? 'Approved by ' . $e($approvedBy) : 'Approved') ?></span><?php if ($latestText !== ''): ?><span class="person-identity__latest"><?= $e($latestText) ?></span><?php endif; ?></span></a>
<?php endforeach; ?>
    </div>
  </div>
<script>
  // Tabs switch in place; each keeps its own URL, so links, reloads and Back still work.
  (function () {
    var nav = document.querySelector('[data-person-tabs]');
    if (!nav || !window.history || !history.pushState) return;
    var show = function (tab) {
      nav.querySelectorAll('[data-person-tab]').forEach(function (link) {
        var on = link.dataset.personTab === tab;
        link.classList.toggle('is-active', on);
        if (on) { link.setAttribute('aria-current', 'page'); } else { link.removeAttribute('aria-current'); }
      });
      document.querySelectorAll('[data-person-panel]').forEach(function (panel) {
        panel.hidden = panel.dataset.personPanel !== tab;
      });
    };
    nav.addEventListener('click', function (event) {
      var link = event.target.closest('[data-person-tab]');
      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      if (link.getAttribute('aria-current') === 'page') return;
      show(link.dataset.personTab);
      history.pushState({ personTab: link.dataset.personTab }, '', link.href);
    });
    window.addEventListener('popstate', function () {
      var tab = new URLSearchParams(location.search).get('tab');
      show(tab === 'replies' || tab === 'identities' ? tab : 'threads');
    });
  })();
</script>
<?php endif; ?>
<?php if ($viewerIsOperator): ?>
  <p class="meta"><a href="/tools/user-access/accounts/<?= $e($usernameToken) ?><?= $historyOffset > 0 ? '?key_offset=' . $historyOffset : '' ?>#account-keys">Manage access</a></p>
<?php endif; ?>
</section>

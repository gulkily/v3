<?php
// One page per person: name, counts and status, Message (or Edit in Account on your own page),
// Threads | Replies, then each key as an identity.
$personTab = (string) ($personTab ?? 'threads');
$isOwnPage = (bool) ($isOwnPage ?? false);
$viewerIsOperator = (bool) ($viewerIsOperator ?? false);
$personName = $approvedProfiles !== [] ? (string) ($approvedProfiles[0]['username'] ?? $usernameToken) : $usernameToken;
$isAgent = $usernameToken === 'reply-agent';
$approvedReplies = array_values(array_filter($approvedPosts, static fn (array $post): bool => trim((string) ($post['parent_id'] ?? '')) !== ''));
$shortFingerprint = static function (string $slug): string {
    $groups = str_split(strtoupper((string) preg_replace('/^openpgp-/', '', $slug)), 4);
    return count($groups) <= 4 ? implode(' ', $groups) : $groups[0] . ' ' . $groups[1] . ' … ' . $groups[count($groups) - 1];
};
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
  <nav class="board-filter" aria-label="<?= $e($personName) ?>’s posts">
    <a class="nav-link<?= $personTab === 'threads' ? ' is-active' : '' ?>" href="/user/<?= $e($usernameToken) ?>"<?= $personTab === 'threads' ? ' aria-current="page"' : '' ?>>Threads</a>
    <a class="nav-link<?= $personTab === 'replies' ? ' is-active' : '' ?>" href="/user/<?= $e($usernameToken) ?>?tab=replies"<?= $personTab === 'replies' ? ' aria-current="page"' : '' ?>>Replies</a>
  </nav>
<?php if ($personTab === 'threads'): ?>
<?php if ($approvedThreads === []): ?>
  <p class="meta">No threads<?= $isAgent ? ' — ' . $e($personName) . ' only answers threads that others start.' : '.' ?></p>
<?php else: ?>
  <div class="thread-rows">
<?php foreach ($approvedThreads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', ['thread' => $thread]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?php else: ?>
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
<?php endif; ?>

  <h2 class="person-identities__title">Identities</h2>
  <div class="account-group" id="account-keys">
<?php foreach ($approvedProfiles as $profile): ?>
<?php $approvedBy = trim((string) ($profile['approved_by_label'] ?? '')); ?>
    <a class="account-row account-row--link" data-tone="neutral" href="/profiles/<?= $e($profile['profile_slug']) ?>"><span class="account-row__title account-key-fingerprint-value" title="<?= $e($profile['profile_slug']) ?>"><?= $e($shortFingerprint((string) $profile['profile_slug'])) ?></span><span class="account-row__value"><?= $approvedBy !== '' ? 'Approved by ' . $e($approvedBy) : 'Approved' ?></span></a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($viewerIsOperator): ?>
  <p class="meta"><a href="/tools/user-access/accounts/<?= $e($usernameToken) ?><?= $historyOffset > 0 ? '?key_offset=' . $historyOffset : '' ?>#account-keys">Manage access</a></p>
<?php endif; ?>
</section>

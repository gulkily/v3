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
  <article class="card person-head" data-person-head>
    <div class="starfield banner" data-seed="<?= $e($usernameToken) ?>" aria-hidden="true"></div>
    <div class="person-head__main">
      <h1><?= $e($personName) ?></h1>
<?php if ($approvedProfiles !== []): ?>
      <p class="person-head__stats meta">
        <span><?= (int) $approvedThreadCount ?> <?= (int) $approvedThreadCount === 1 ? 'thread' : 'threads' ?> · <?= (int) $approvedPostCount ?> <?= (int) $approvedPostCount === 1 ? 'post' : 'posts' ?></span>
<?php if ($isAgent): ?>
        <span class="chip agent-label">Automated reply agent</span>
<?php else: ?>
        <span class="chip chip--approved">Approved</span>
<?php endif; ?>
      </p>
<?php endif; ?>
    </div>
<?php if ($isOwnPage): ?>
    <form class="person-head__action" method="get" action="/account/key/"><button type="submit">Edit in Account</button></form>
<?php elseif ($canPrivateMessage && !$isAgent): ?>
    <form class="person-head__action" method="get" action="/messages/conversation/<?= $e(rawurlencode($usernameToken)) ?>"><button type="submit">Message</button></form>
<?php endif; ?>
  </article>

<?php if ($approvedProfiles === []): ?>
  <article class="card">
    <p>No approved member uses this name.</p>
  </article>
<?php else: ?>
  <nav class="users-tabs" aria-label="<?= $e($personName) ?>’s posts">
    <a href="/user/<?= $e($usernameToken) ?>"<?= $personTab === 'threads' ? ' aria-current="page"' : '' ?>>Threads</a>
    <a href="/user/<?= $e($usernameToken) ?>?tab=replies"<?= $personTab === 'replies' ? ' aria-current="page"' : '' ?>>Replies</a>
  </nav>
<?php if ($personTab === 'threads'): ?>
<?php if ($approvedThreads === []): ?>
  <p class="meta person-empty">No threads<?= $isAgent ? ' — ' . $e($personName) . ' only answers threads that others start.' : '.' ?></p>
<?php else: ?>
  <ul class="user-rows">
<?php foreach ($approvedThreads as $thread): ?>
    <li><a class="user-row" href="/threads/<?= $e($thread['root_post_id']) ?>"><span class="user-row__name"><?= $e($threadTitle($thread)) ?></span><span class="user-row__counts meta"><?= $timestamp($thread['root_post_created_at'] ?? '') ?></span></a></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php else: ?>
<?php if ($approvedReplies === []): ?>
  <p class="meta person-empty">No replies.</p>
<?php else: ?>
  <ul class="user-rows">
<?php foreach ($approvedReplies as $post): ?>
    <li><a class="user-row" href="/posts/<?= $e($post['post_id']) ?>"><span class="user-row__name"><?= $e($firstLine((string) ($post['body'] ?? ''))) ?></span><span class="user-row__counts meta"><?= $timestamp($post['created_at'] ?? '') ?></span></a></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php endif; ?>

  <h2 class="person-identities__title">Identities</h2>
  <ul class="user-rows" id="account-keys">
<?php foreach ($approvedProfiles as $profile): ?>
<?php $approvedBy = trim((string) ($profile['approved_by_label'] ?? '')); ?>
    <li><a class="user-row" href="/profiles/<?= $e($profile['profile_slug']) ?>"><span class="user-row__name person-identity__key" title="<?= $e($profile['profile_slug']) ?>"><?= $e($shortFingerprint((string) $profile['profile_slug'])) ?></span><span class="user-row__counts meta"><?= $approvedBy !== '' ? 'Approved by ' . $e($approvedBy) : 'Approved' ?></span></a></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if ($viewerIsOperator): ?>
  <p class="meta"><a href="/tools/user-access/accounts/<?= $e($usernameToken) ?><?= $historyOffset > 0 ? '?key_offset=' . $historyOffset : '' ?>#account-keys">Manage access</a></p>
<?php endif; ?>
</section>

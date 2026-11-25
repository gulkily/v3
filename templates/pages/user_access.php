<section class="stack">
  <article class="card">
    <h1>User Access</h1>
    <p><a href="/tools/user-access/pending/">Pending approvals</a></p>
  </article>
  <article class="card">
    <h2>Accounts</h2>
<?php if ($users === []): ?>
    <p>No approved accounts.</p>
<?php else: ?>
    <ul>
<?php foreach ($users as $user): ?>
      <li><a href="/tools/user-access/accounts/<?= $e($user['username_token']) ?>"><?= $e($user['username']) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>
<?php if (($replacedKeys ?? []) !== []): ?>
  <article class="card" id="replaced-keys">
    <h2>Replaced keys</h2>
    <ul>
<?php foreach ($replacedKeys as $replaced): ?>
      <li><a href="/tools/user-access/profiles/<?= $e($replaced['profile_slug']) ?>"><?= $e($replaced['username']) ?> · <?= $e($shortenProfileSlug($replaced['profile_slug'])) ?></a> <span class="chip chip--replaced">Replaced</span></li>
<?php endforeach; ?>
    </ul>
  </article>
<?php endif; ?>
<?php if (($privateAccessWaiting ?? []) !== []): ?>
<?php
    $waitingRow = static fn (array $key): string => '<li class="private-access-row" data-private-access-row data-target-identity-id="' . $e($key['identity_id']) . '">'
        . '<a href="/tools/user-access/profiles/' . $e(rawurlencode((string) $key['profile_slug'])) . '/">' . $e($key['username_token']) . ' · ' . $e($shortenProfileSlug($key['profile_slug'])) . '</a> '
        . '<strong class="private-access-code">' . $e(\ForumRewrite\Security\PrivateKeyAccess::shortCode((string) $key['identity_id'])) . '</strong> '
        . '<button type="button" data-action="confirm-private-access" data-private-access-operator-only>Confirm</button>'
        . '<span class="meta" data-role="private-access-feedback" role="status" hidden></span></li>';
?>
  <article class="card" id="private-access-waiting" data-private-access-waiting>
    <h2>Waiting for private access</h2>
    <p class="meta" data-role="private-access-card-feedback" role="status" hidden></p>
    <script>
      (function () {
        var operators = <?= json_encode($operatorFingerprints ?? [], JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        function update() {
          var operator = false;
          try {
            var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
            operator = operators.indexOf(fingerprint) !== -1 && !!localStorage.getItem('forum_pki_private_key');
          } catch (error) {}
          document.documentElement.toggleAttribute('data-private-access-operator', operator);
        }
        update();
        window.addEventListener('forum:identity-changed', update);
        window.addEventListener('storage', update);
      })();
    </script>
    <ul class="private-access-list">
<?php foreach (array_slice($privateAccessWaiting, 0, 10) as $key): ?>
      <?= $waitingRow($key) ?>

<?php endforeach; ?>
    </ul>
<?php if (count($privateAccessWaiting) > 10): ?>
    <details>
      <summary><?= $e((string) (count($privateAccessWaiting) - 10)) ?> more</summary>
      <ul class="private-access-list">
<?php foreach (array_slice($privateAccessWaiting, 10) as $key): ?>
        <?= $waitingRow($key) ?>

<?php endforeach; ?>
      </ul>
    </details>
<?php endif; ?>
  </article>
<?php endif; ?>
<?php if ($canSecurityReview || ($canResumeHistory ?? false)): ?>
  <article class="card" id="access-history">
    <div class="access-history-heading">
      <h2>Access history</h2>
<?php if ($canSecurityReview && $historyHasMore): ?>
      <a href="/activity/?view=security">View all</a>
<?php endif; ?>
    </div>
<?php if (!$canSecurityReview): ?>
<?= $partial('partials/access_history_auth.php', ['canSignIn' => true, 'authReturnTo' => '/tools/user-access/#access-history']) ?>
<?php elseif ($historyItems === []): ?>
    <p class="meta">No access changes recorded yet.</p>
<?php else: ?>
<?php foreach ($historyItems as $item): ?>
<?= $partial('partials/access_history_event.php', ['item' => $item]) ?>
<?php endforeach; ?>
<?php endif; ?>
  </article>
<?php endif; ?>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

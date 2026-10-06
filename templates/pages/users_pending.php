<?php
$shortenProfileSlug = static function (string $slug): string {
    if (strlen($slug) <= 34) {
        return $slug;
    }

    return substr($slug, 0, 18) . '...' . substr($slug, -10);
};
?>
<section class="stack" data-pending-approvals-root>
  <article class="card">
    <h1>Users Awaiting Approval</h1>
    <p><a href="/users/">Back to approved users</a></p>
  </article>
  <article class="card" data-role="pending-approvals-feedback" hidden></article>
  <article class="card" data-role="pending-approvals-empty"<?= $profiles === [] ? '' : ' hidden' ?>>
    <p>No users are awaiting approval.</p>
  </article>
  <article class="card" data-role="pending-approvals-table"<?= $profiles === [] ? ' hidden' : '' ?>>
    <table data-role="pending-approvals-table-element"<?= $profiles === [] ? ' hidden' : '' ?>>
      <thead>
        <tr>
          <th class="pending-approvals-user-cell">User</th>
          <th class="pending-approvals-profile-cell">Profile</th>
          <th class="pending-approvals-action-cell">Approve</th>
        </tr>
      </thead>
      <tbody data-role="pending-approvals-body">
<?php foreach ($profiles as $profile): ?>
<?php
$latestActivityLabel = trim((string) ($profile['latest_activity_label'] ?? ''));
$latestActivityAt = trim((string) ($profile['latest_activity_at'] ?? ''));
$latestActivityPostId = trim((string) ($profile['latest_activity_post_id'] ?? ''));
?>
        <tr data-role="pending-approval-row" data-profile-slug="<?= $e($profile['profile_slug']) ?>" data-username="<?= $e($profile['username']) ?>">
          <td class="pending-approvals-user-cell" data-label="User">
            <a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['username']) ?></a>
          </td>
          <td class="pending-approvals-profile-cell" data-label="Profile">
            <a
              class="pending-approvals-profile-link"
              href="/profiles/<?= $e($profile['profile_slug']) ?>"
              title="<?= $e($profile['profile_slug']) ?>"
              aria-label="<?= $e($profile['profile_slug']) ?>"
            ><?= $e($shortenProfileSlug($profile['profile_slug'])) ?></a>
          </td>
          <td class="pending-approvals-action-cell" data-label="Approve">
            <button type="button" class="pending-approvals-action-button" data-action="approve-user" data-profile-slug="<?= $e($profile['profile_slug']) ?>">
              Approve
            </button>
          </td>
        </tr>
        <tr class="pending-approvals-activity-row" data-role="pending-approval-activity-row" data-profile-slug="<?= $e($profile['profile_slug']) ?>">
          <td class="pending-approvals-activity-cell" colspan="2">
<?php if ($latestActivityLabel !== '' && $latestActivityPostId !== ''): ?>
            <a href="/posts/<?= $e($latestActivityPostId) ?>"><?= $e($latestActivityLabel) ?></a>
<?php else: ?>
            <?= $e($latestActivityLabel !== '' ? $latestActivityLabel : 'No recorded activity') ?>
<?php endif; ?>
<?php if ($latestActivityAt !== ''): ?>
            <span class="meta">&mdash; <?= $relativeTimestamp($latestActivityAt) ?></span>
<?php endif; ?>
          </td>
          <td class="pending-approvals-activity-spacer" aria-hidden="true"></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
</section>

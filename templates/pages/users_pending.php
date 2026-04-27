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
        <tr data-profile-slug="<?= $e($profile['profile_slug']) ?>" data-username="<?= $e($profile['username']) ?>">
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
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
</section>

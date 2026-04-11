<section class="stack" data-pending-approvals-root>
  <article class="card">
    <h1>Users Awaiting Approval</h1>
    <p><a href="/users/">Back to approved users</a></p>
  </article>
  <article class="card" data-role="pending-approvals-feedback" hidden></article>
<?php if ($profiles === []): ?>
  <article class="card" data-role="pending-approvals-empty">
    <p>No users are awaiting approval.</p>
  </article>
<?php else: ?>
  <article class="card">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Profile</th>
          <th class="pending-approvals-action-cell">Approve</th>
        </tr>
      </thead>
      <tbody data-role="pending-approvals-body">
<?php foreach ($profiles as $profile): ?>
        <tr data-profile-slug="<?= $e($profile['profile_slug']) ?>" data-username="<?= $e($profile['username']) ?>">
          <td><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['username']) ?></a></td>
          <td><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></td>
          <td class="pending-approvals-action-cell">
            <button type="button" class="pending-approvals-action-button" data-action="approve-user" data-profile-slug="<?= $e($profile['profile_slug']) ?>">
              Approve
            </button>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
<?php endif; ?>
</section>

<?php
/** @var list<array<string, mixed>> $entries from ForumRewrite\View\ThreadActivity::build() */
$entries ??= [];
$activityActor = static function (array $actor) use ($e): string {
    $name = $e((string) $actor['username']);

    return isset($actor['profile_slug']) && $actor['profile_slug'] !== ''
        ? '<a href="/profiles/' . $e((string) $actor['profile_slug']) . '">' . $name . '</a>'
        : $name;
};
// A reactor's name opens the record that added the reaction when there is one, otherwise the profile.
$activityReactor = static fn (array $actor): string => isset($actor['record_id'])
    ? '<a href="/records/' . $e(rawurlencode((string) $actor['record_id'])) . '">' . $e((string) $actor['username']) . '</a>'
    : $activityActor($actor);
$activityActors = static fn (array $actors): string => implode(', ', array_map($activityReactor, $actors));
?>
<details class="card thread-activity" id="activity">
  <summary>Activity (<?= count($entries) ?>)</summary>
  <ul class="thread-activity-list">
<?php foreach ($entries as $entry): ?>
<?php if ($entry['kind'] === 'tagged'): ?>
    <li data-activity-kind="tagged" data-post-id="<?= $e($entry['post_id']) ?>"><?= $activityActor($entry['author']) ?> tagged <?= implode(' ', array_map(static fn (string $label): string => '<a href="/tags/' . $e(rawurlencode($label)) . '">#' . $e($label) . '</a>', $entry['labels'])) ?> · <a href="<?= $e($entry['href']) ?>"><?= $shortDate($entry['created_at']) ?></a></li>
<?php else: ?>
    <li data-activity-kind="reactions" data-post-id="<?= $e($entry['post_id']) ?>"><a href="<?= $e($entry['href']) ?>"><?= $e($entry['label']) ?></a> — <?php if ($entry['like'] !== []): ?>Liked by <?= $activityActors($entry['like']) ?><?php endif; ?><?= $entry['like'] !== [] && $entry['flag'] !== [] ? ' · ' : '' ?><?php if ($entry['flag'] !== []): ?>Flagged by <?= $activityActors($entry['flag']) ?><?php endif; ?></li>
<?php endif; ?>
<?php endforeach; ?>
  </ul>
</details>

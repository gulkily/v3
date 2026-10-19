<div class="body retracted-post" data-retracted="true" data-retracted-post-id="<?= $e($post['post_id'] ?? '') ?>">
  <p class="meta"><?= $e(\ForumRewrite\Support\RetractedPostNotice::text($post)) ?></p>
</div>

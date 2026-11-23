<?php
// /tools/tag-unlocks/: what the tagsets in force unlock, and every marked post. Active operators
// (recognised in the browser before paint, as in partials/operator_grant.php) can edit the text.
$targetWords = ['reply' => 'replies', 'root' => 'the opening post'];
$stateHeadings = ['claimed' => 'Claimed', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$stateEmpty = ['claimed' => 'Nobody has claimed anything.', 'resolved' => 'Nothing resolved yet.', 'closed' => 'Nothing closed yet.'];
$formatWhen = static function (?string $at): string {
    if ($at === null || $at === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($at))->format('M j, Y');
    } catch (\Exception) {
        return '';
    }
};
?>
<section class="stack">
  <article class="card tag-unlocks-page">
    <h1>Tag Unlocks</h1>
    <p class="meta">A thread tag can unlock extra tags on that thread's posts. <?= $tagsetsSource === 'site' ? 'These rules were set on this site.' : 'These are the shipped rules (<code>resources/tagsets.txt</code>).' ?></p>
<?php if ($tagsetsError !== null): ?>
    <p class="tag-unlocks-error" role="alert">The site rules are not in use because of an error. <?= $e($tagsetsError) ?></p>
<?php endif; ?>
<?php if ($tagsetEditors !== []): ?>
    <style>[data-tagsets-editor] { display: none; } html[data-tagsets-editor-eligible] [data-tagsets-editor] { display: block; }</style>
    <script>
      (function () {
        var editors = <?= json_encode($tagsetEditors, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        function update() {
          var eligible = false;
          try {
            var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
            eligible = editors.indexOf(fingerprint) !== -1 && !!localStorage.getItem('forum_pki_private_key');
          } catch (error) {}
          document.documentElement.toggleAttribute('data-tagsets-editor-eligible', eligible);
        }
        update();
        window.addEventListener('forum:identity-changed', update);
        window.addEventListener('storage', update);
      })();
    </script>
    <details class="tag-unlocks-editor" data-tagsets-editor>
      <summary>Edit</summary>
      <form data-tagsets-form>
        <textarea name="text" rows="12" spellcheck="false" autocapitalize="off" aria-label="Tagsets" data-tagsets-text><?= $e($tagsetsText) ?></textarea>
        <template data-tagsets-shipped><?= $e($shippedTagsetsText) ?></template>
        <div class="tag-unlocks-editor-actions">
          <button type="button" data-tagsets-preview>Preview</button>
          <button type="submit" data-tagsets-save disabled>Save</button>
<?php if ($tagsetsSource === 'site'): ?>
          <button type="button" class="link-button" data-tagsets-reset>Reset to default</button>
<?php endif; ?>
        </div>
        <p class="meta" data-tagsets-status role="status" hidden></p>
        <div class="tag-unlocks-preview" data-tagsets-result hidden></div>
      </form>
    </details>
<?php endif; ?>
<?php if (!$tagUnlocksOn): ?>
    <p class="meta">Tag unlocks are turned off on this site (<code>FORUM_TAG_UNLOCKS_ENABLED</code>), so no buttons are shown.</p>
<?php endif; ?>

    <section class="tag-unlocks-section" aria-labelledby="tag-unlocks-sets">
      <h2 id="tag-unlocks-sets">What unlocks what</h2>
<?php if ($tagsets === []): ?>
      <p class="meta">No tagsets are defined.</p>
<?php else: ?>
      <ul class="tag-unlocks-sets">
<?php foreach ($tagsets as $set): ?>
        <li class="tag-unlocks-set">
          <p class="tag-unlocks-rule"><a class="tag-unlocks-parent" href="/tags/<?= $e(rawurlencode($set['parent'])) ?>">#<?= $e($set['parent']) ?></a> <span class="tag-unlocks-arrow" aria-label="unlocks">unlocks</span>
<?php foreach ($set['unlocks'] as $tag): ?>
            <span class="tag-unlocks-tag"><?= $e($tag) ?></span>
<?php endforeach; ?>
          </p>
          <p class="meta">On <?= $e(implode(' and ', array_map(static fn (string $target): string => $targetWords[$target] ?? $target, $set['targets']))) ?>. <?= $set['exclusive'] ? 'One state at a time: the latest mark from an approved key wins, and <em>open</em> clears it.' : 'Independent tags, counted like Like and Flag.' ?></p>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

<?php if ($tagUnlocksOn): ?>
<?php foreach ($stateHeadings as $state => $heading): ?>
<?php $rows = $markedPosts[$state] ?? []; ?>
    <section class="tag-unlocks-section" aria-labelledby="tag-unlocks-<?= $e($state) ?>">
      <h2 id="tag-unlocks-<?= $e($state) ?>"><?= $e($heading) ?> <span class="meta">(<?= count($rows) ?>)</span></h2>
<?php if ($rows === []): ?>
      <p class="meta"><?= $e($stateEmpty[$state]) ?></p>
<?php else: ?>
      <ul class="tag-unlocks-posts" data-state="<?= $e($state) ?>">
<?php foreach ($rows as $row): ?>
        <li class="tag-unlocks-post">
          <a href="/threads/<?= $e(rawurlencode($row['thread_id'])) ?><?= $row['is_root'] ? '' : '#post-' . $e(rawurlencode($row['post_id'])) ?>"><?= $e($row['excerpt'] !== '' ? $row['excerpt'] : $row['post_id']) ?></a>
          <p class="meta"><?= $row['is_root'] ? 'Opening post' : 'In' ?><?= $row['thread_subject'] !== '' ? ' “' . $e($row['thread_subject']) . '”' : '' ?><?= $row['set_by'] !== '' ? ' · ' . ($state === 'claimed' ? 'claimed by ' : 'marked by ') . $e($row['set_by']) : '' ?><?= $formatWhen($row['set_at']) !== '' ? ' · ' . $e($formatWhen($row['set_at'])) : '' ?></p>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
<?php endforeach; ?>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

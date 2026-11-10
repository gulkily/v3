<section class="stack">
  <h1 class="visually-hidden">Tools</h1>
  <article class="card tool-launcher tools-launcher-page">
<?php foreach ($toolGroups as $groupKey => $groupLabel): ?>
<?php $groupTools = array_values(array_filter($toolPages, static fn (array $tool): bool => $tool['group'] === $groupKey && !(($textBackupEnabled ?? true) === false && ($imageBackupEnabled ?? true) === false && $tool['key'] === 'backup'))); ?>
<?php if ($groupTools === []) { continue; } ?>
    <section class="tool-launcher-group" aria-labelledby="tools-group-<?= $e($groupKey) ?>">
      <h2 class="tool-launcher-group-heading" id="tools-group-<?= $e($groupKey) ?>" data-shape="<?= $e($groupKey) ?>"><?= $e($groupLabel) ?></h2>
      <ul class="tool-launcher-list">
<?php foreach ($groupTools as $toolPage): ?>
        <li class="tool-launcher-item" data-tone="<?= $e($toolPage['tone']) ?>">
          <a class="tool-launcher-button" href="<?= $e($toolPage['href']) ?>"><?= $e($toolPage['label']) ?></a>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>
  </article>
</section>

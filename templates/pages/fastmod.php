<section class="stack">
  <article class="card"><?= $partial('partials/tools_nav.php') ?></article>
  <article class="card">
    <h1>Fastmod</h1>
    <p><a href="/tools/feature-flags/#flag-FASTMOD_AUTOMATIC_TAGGING_ENABLED">Feature Flags</a></p>
<?php if ($settingsError !== null): ?>
    <p role="alert"><?= $e($settingsError) ?></p>
<?php else: ?>
    <h2>Introductory prompt</h2>
    <p id="fastmod-introduction"><?= nl2br($e($introduction)) ?></p>
    <h2>Fastmod rubrics</h2>
    <p>Enable at least one.</p>
    <form<?= $canManageFastmod ? ' data-fastmod-form' : '' ?>>
<?php foreach ($rubrics as $id => $prompt): ?>
      <div class="stack">
        <label class="fastmod-rubric-label"><input type="checkbox" name="rubrics[]" value="<?= $e($id) ?>" aria-describedby="fastmod-prompt-<?= $e($id) ?>"<?= in_array($id, $enabledRubrics, true) ? ' checked' : '' ?><?= $canManageFastmod ? '' : ' disabled' ?>> <?= $e(ucfirst(str_replace('_', ' ', $id))) ?></label>
        <p id="fastmod-prompt-<?= $e($id) ?>"><?= nl2br($e($prompt)) ?></p>
      </div>
<?php endforeach; ?>
<?php if ($canManageFastmod): ?>
      <p role="status" aria-live="polite" data-fastmod-status hidden></p>
      <button type="submit">Save</button>
      <noscript>JavaScript and a root-approved browser identity are required to save settings.</noscript>
<?php else: ?>
      <p>A root-approved identity is required to change these settings.</p>
<?php endif; ?>
    </form>
<?php endif; ?>
  </article>
</section>

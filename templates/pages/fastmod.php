<section class="stack">
  <article class="card"><?= $partial('partials/tools_nav.php') ?></article>
  <article class="card">
    <h1>Fastmod</h1>
    <p>Choose which scores to record for future evaluations. Scores do not trigger actions.</p>
<?php if ($settingsError !== null): ?>
    <p role="alert"><?= $e($settingsError) ?></p>
<?php else: ?>
    <p>Enabled: <?= $e(implode(', ', $enabledRubrics)) ?></p>
<?php if ($canManageFastmod): ?>
    <button type="button" data-fastmod-open>Choose rubrics</button>
    <dialog data-fastmod-dialog aria-labelledby="fastmod-title">
      <h2 id="fastmod-title">Fastmod rubrics</h2>
      <p>Enable at least one. Each evaluation records the selected scores together.</p>
      <form data-fastmod-form>
<?php foreach ($rubrics as $id): ?>
        <p><label><input type="checkbox" name="rubrics[]" value="<?= $e($id) ?>"<?= in_array($id, $enabledRubrics, true) ? ' checked' : '' ?>> <?= $e(ucfirst(str_replace('_', ' ', $id))) ?></label></p>
<?php endforeach; ?>
        <p role="status" aria-live="polite" data-fastmod-status></p>
        <button type="submit">Save</button>
        <button type="button" data-fastmod-close>Cancel</button>
      </form>
    </dialog>
    <noscript>JavaScript and a root-approved browser identity are required to save settings.</noscript>
<?php else: ?>
    <p>A root-approved identity is required to change these settings.</p>
<?php endif; ?>
<?php endif; ?>
  </article>
</section>

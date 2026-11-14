<section class="stack">
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
    <h2>Local meta model</h2>
<?php if (($localNotice ?? null) !== null): ?>
    <p role="status"><?= $e($localNotice) ?></p>
<?php endif; ?>
<?php if (is_array($localReport ?? null)): ?>
    <table class="codebase-facts">
      <tbody>
<?php foreach ($localReport['summary'] as [$key, $value]): ?>
        <tr><th scope="row"><?= $e(str_replace('-', ' ', $key)) ?></th><td><?= $e($value) ?></td></tr>
<?php endforeach; ?>
<?php foreach (['train' => 'Training', 'sweep' => 'Scoring'] as $kind => $label): ?>
<?php $task = $localTasks[$kind] ?? null; if (is_array($task)): ?>
        <tr><th scope="row"><?= $e($label) ?></th><td><?= $e((string) $task['status']) ?> <?= $e(str_replace('T', ' ', substr((string) ($task['completed_at'] ?? $task['requested_at'] ?? ''), 0, 16))) ?><?= ($task['status'] ?? '') === 'failed' && ($task['failure_message'] ?? '') !== '' ? ': ' . $e((string) $task['failure_message']) : '' ?></td></tr>
<?php endif; endforeach; ?>
      </tbody>
    </table>
<?php else: ?>
<?php $task = $localTasks['train'] ?? null; ?>
    <p><?= is_array($task) ? $e('Training ' . $task['status'] . (($task['failure_message'] ?? '') !== '' ? ': ' . $task['failure_message'] : '.')) : 'Not trained yet.' ?></p>
<?php endif; ?>
<?php if (!empty($canRetrain)): ?>
    <form method="post" action="/tools/fastmod/">
      <input type="hidden" name="action" value="retrain">
      <button type="submit">Retrain</button>
    </form>
<?php if (is_array($localReport ?? null) && $localReport['disagreements'] !== []): ?>
    <details>
      <summary>Disagreements (<?= count($localReport['disagreements']) ?>)</summary>
      <ul>
<?php foreach ($localReport['disagreements'] as $item): ?>
        <li><a href="/threads/<?= $e($item['Post'] ?? '') ?>"><?= $e($item['Subject'] ?? '') ?></a> · <?= $e($item['Label'] ?? '') ?> · <?= $e($item['Score'] ?? '') ?> · <?= $e($item['Words'] ?? '') ?></li>
<?php endforeach; ?>
      </ul>
    </details>
<?php endif; ?>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>

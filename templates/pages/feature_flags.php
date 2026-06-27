<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card">
    <h1>Feature Flags</h1>
    <table class="codebase-facts">
      <thead>
        <tr>
          <th scope="col">Flag</th>
          <th scope="col">Effective</th>
          <th scope="col">Default</th>
          <th scope="col">Source</th>
          <th scope="col">Mutable</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($flags as $flag): ?>
        <tr>
          <th scope="row">
            <?= $e($flag->definition->label) ?>
            <div class="codebase-source"><code><?= $e($flag->definition->key) ?></code></div>
            <div class="meta"><?= $e($flag->definition->description) ?></div>
          </th>
          <td><code><?= $flag->effectiveValue ? 'enabled' : 'disabled' ?></code></td>
          <td>
            <code><?= $flag->definition->defaultValue ? 'enabled' : 'disabled' ?></code>
<?php if ($flag->isDefault()): ?>
            <div class="meta">current value is default</div>
<?php else: ?>
            <div class="meta">current value differs from default</div>
<?php endif; ?>
          </td>
          <td>
            <code><?= $e($flag->source) ?></code>
<?php if ($flag->environmentValue !== null): ?>
            <div class="codebase-source"><code><?= $e($flag->definition->environmentVariable) ?></code></div>
<?php endif; ?>
          </td>
          <td><code><?= $flag->canChangeFromSite() ? 'yes' : 'no' ?></code></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
</section>

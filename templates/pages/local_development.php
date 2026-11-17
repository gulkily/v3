<section class="stack">
  <article class="card stack">
    <h1>Local development</h1>
    <p>Explore your local copy, or bring in public content from another instance.</p>
    <div class="button-row button-row-natural">
      <a href="/threads/?view=all">Browse threads</a>
      <a href="/recent/">Recent posts</a>
      <a href="/themes/">Themes</a>
      <a href="/docs/">Documentation</a>
    </div>
  </article>
  <article class="card stack">
    <h2>Import public content</h2>
    <p>Local posts and settings stay. Private messages and approval authority are excluded. Conflicts keep the local version.</p>
<?php if ($error !== null): ?>
    <p role="alert"><?= $e($error) ?></p>
<?php elseif ($result !== null): ?>
    <p><a href="/threads/?view=all">Browse imported threads</a></p>
<?php endif; ?>
<?php if ($pending): ?>
    <p>An interrupted import needs to finish before another can start.</p>
<?php endif; ?>
    <form method="post" action="/local/" class="stack" data-local-import>
      <input type="hidden" name="token" value="<?= $e($token) ?>">
      <label>Source <input type="text" name="source" value="<?= $e($source) ?>" required maxlength="2048"<?= $pending ? ' readonly' : '' ?>></label>
      <div class="button-row">
        <button type="submit" name="action" value="<?= $pending ? 'resume' : 'import' ?>"><?= $pending ? 'Resume import' : ($error !== null ? 'Retry import' : ($result !== null ? 'Import again' : 'Import')) ?></button>
<?php if ($error !== null): ?>
        <a href="/threads/?view=all">Browse local content</a>
<?php endif; ?>
      </div>
      <p class="meta" data-local-import-pending hidden role="status">Importing… This can take several minutes. Keep this page open.</p>
    </form>
<?php if ($result !== null): ?>
    <details>
      <summary>Import details</summary>
      <pre><?= $e($result['output']) ?></pre>
    </details>
<?php endif; ?>
  </article>
  <article class="card stack">
    <h2>Try the forum</h2>
    <p><a href="/compose/thread">Start a thread</a>, reply to a post, or <a href="/search/">search your local content</a>.</p>
    <p><a href="/agent/">Work with an agent</a> · <a href="/tools/">Tools</a> · <a href="/about/">About the project</a></p>
  </article>
</section>

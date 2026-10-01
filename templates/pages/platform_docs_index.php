<section class="stack docs-page">
  <article class="card">
    <h1>Platform Docs</h1>
    <p>How Zenmemes is built, operated, and extended. Every page names its authoritative repository source file.</p>
  </article>
<?php foreach ($categories as $category => $entries): ?>
  <article class="card docs-category">
    <h2><?= $e($category) ?></h2>
    <ul class="docs-entry-list">
<?php foreach ($entries as $entry): ?>
      <li>
        <a href="<?= $e($entry['href']) ?>"><?= $e($entry['title']) ?></a>
        <p><?= $e($entry['description']) ?></p>
        <code><?= $e($entry['path']) ?></code>
      </li>
<?php endforeach; ?>
    </ul>
  </article>
<?php endforeach; ?>
</section>

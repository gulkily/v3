<section class="stack docs-page">
  <article class="card docs-source">
    <a href="/docs/"><?= $e($platformDocsBrand['heading']) ?></a>
    <p>Repository source: <code><?= $e($sourcePath) ?></code></p>
  </article>
  <article class="card docs-content">
<?= $indent($documentHtml, 2) ?>
  </article>
</section>

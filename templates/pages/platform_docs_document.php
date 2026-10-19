<section class="stack docs-page">
  <article class="card docs-source">
    <a href="/docs/<?= $returnQuery === [] ? '' : '?' . $e(http_build_query($returnQuery)) ?>#docs-library-heading" data-docs-return><?= $e($platformDocsBrand['heading']) ?></a>
    <a href="<?= $e($downloadHref) ?>" download>Download</a>
    <p>Repository source: <code><?= $e($sourcePath) ?></code></p>
  </article>
  <article class="card docs-content">
<?= $indent($documentHtml, 2) ?>
  </article>
</section>

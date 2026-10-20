<?php
$documentCount = array_sum($counts['group']);
?>
<section class="stack docs-page">
  <article class="card docs-intro">
    <h1><?= $e($platformDocsBrand['heading']) ?></h1>
    <div data-docs-landing<?= $isResults ? ' hidden' : '' ?>>
      <p><?= $e($platformDocsBrand['introduction']) ?></p>
      <p>Each entry below shows the path of the file it is rendered from, so you can read the source and propose changes.</p>
    </div>
    <form class="docs-search" action="/docs/" method="get" role="search">
      <label for="docs-search-input">Search</label>
      <div class="docs-search__row">
        <input id="docs-search-input" name="q" type="search" value="<?= $e(is_string($query['q'] ?? null) ? $query['q'] : '') ?>" placeholder="Search all <?= $e(number_format($documentCount)) ?> docs…">
        <button type="submit">Search</button>
      </div>
    </form>
    <p class="docs-intro__browse" data-docs-landing<?= $isResults ? ' hidden' : '' ?>><a href="/docs/?page=1">Browse all docs <span aria-hidden="true">→</span></a></p>
  </article>
  <article class="card docs-transparency" data-docs-landing<?= $isResults ? ' hidden' : '' ?>>
    <h2>How it works</h2>
    <ol class="docs-flow" aria-label="<?= $e($platformDocsBrand['architectureTitle']) ?>">
      <li class="docs-flow__node">
        <svg class="docs-flow__key" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><path d="M20 4 v32 M4 20 h32 M8 8 l24 24 M32 8 l-24 24" /></svg>
        <strong>Member's browser</strong>
        <span>private key (never leaves)</span>
      </li>
      <li class="docs-flow__edge"><span class="docs-flow__arrow" aria-hidden="true"></span><span>signed write</span></li>
      <li class="docs-flow__node docs-flow__node--source">
        <strong>records/ in Git</strong>
        <code>records/posts/</code>
        <code>records/identity/</code>
        <span>source of truth</span>
      </li>
      <li class="docs-flow__edge"><span class="docs-flow__arrow" aria-hidden="true"></span><span>builds</span></li>
      <li class="docs-flow__node docs-flow__node--views">
        <strong>SQLite</strong>
        <strong>static HTML</strong>
        <span>built from records, can be rebuilt</span>
      </li>
      <li class="docs-flow__edge"><span class="docs-flow__arrow" aria-hidden="true"></span><span>serves</span></li>
      <li class="docs-flow__node"><strong>Readers</strong></li>
    </ol>
    <div class="docs-transparency-grid">
      <section>
        <h3>Public records live in Git</h3>
        <p>Posts, public keys, and identity events are stored as plain-text files under <code>records/</code>, one file per record, in Git. The SQLite database is built from those files and can be rebuilt with <code>./v3 rebuild</code>. Static HTML is built and published with <code>./v3 build-static</code>.</p>
      </section>
      <section>
        <h3>Identity is an OpenPGP key</h3>
        <p>Each member who uses browser identity has an OpenPGP key pair. The private key stays in the member's browser. The public key and fingerprint are published, and a member's signed public writes can be verified against them.</p>
      </section>
      <section>
        <h3>What stays out of the public record</h3>
        <p>The server does not generate or store member private keys. Operator credentials and private workflow state are kept outside <code>public/</code> and outside Git.</p>
      </section>
    </div>
    <p>Full details: <a href="/docs/architecture/public_architecture_and_trust.md">Public Architecture and Trust Model</a></p>
  </article>

  <div class="docs-topics" data-docs-landing<?= $isResults ? ' hidden' : '' ?>>
<?php foreach ($categories as $topic => $entries): ?>
    <section class="card docs-topic<?= count($entries) > 1 ? ' docs-topic--wide' : '' ?>" aria-labelledby="docs-topic-<?= $e(\ForumRewrite\Docs\PlatformDocsCatalog::categorySlug($topic)) ?>">
      <h2 id="docs-topic-<?= $e(\ForumRewrite\Docs\PlatformDocsCatalog::categorySlug($topic)) ?>"><?= $e($topic) ?></h2>
      <ul class="docs-entry-list">
<?php foreach ($entries as $entry): ?>
        <li class="docs-entry">
          <a class="docs-entry__title" href="<?= $e($entry['href']) ?>"><?= $e($entry['title']) ?></a>
          <p class="docs-entry__description"><?= $e($entry['description']) ?></p>
          <code class="docs-entry__path"><?= $e($entry['path']) ?></code>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>
  </div>
  <section class="card docs-overview" aria-labelledby="docs-overview-heading" data-docs-landing<?= $isResults ? ' hidden' : '' ?>>
    <h2 id="docs-overview-heading">All documents</h2>
    <p><?= $e(number_format($documentCount)) ?> documents across architecture, specs, runbooks, plans, and more. <a href="/docs/?page=1">Browse all</a></p>
    <ul class="docs-quick-links">
<?php foreach ($counts['group'] as $group => $count): ?>
      <li><a href="/docs/?<?= $e(http_build_query(['group' => $group])) ?>"><?= $e($group) ?> (<?= $e(number_format($count)) ?>)</a></li>
<?php endforeach; ?>
    </ul>
  </section>
<?php
$filterValue = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
$returnQuery = array_filter(array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])), 'is_scalar');
$docHref = static fn (string $href): string => $href . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
?>
<section class="card docs-library" aria-labelledby="docs-library-heading" data-docs-library<?= $isResults ? '' : ' hidden' ?>>
  <h2 id="docs-library-heading">All documents</h2>
  <form action="/docs/" method="get" data-docs-form>
    <label>Search <input name="q" type="search" value="<?= $e($filterValue('q')) ?>"></label>
<?php foreach (['folder' => ['Folder', array_combine($folders, $folders)], 'category' => ['Category', array_map(static fn (array $category): string => $category['label'], $counts['category'])], 'group' => ['Collection', array_combine(array_keys($counts['group']), array_keys($counts['group']))]] as $key => [$label, $options]): ?>
    <label><?= $e($label) ?> <select name="<?= $e($key) ?>">
      <option value="">All</option>
<?php foreach ($options as $item => $optionLabel): ?>
      <option value="<?= $e((string) $item) ?>"<?= ($key === 'category' ? \ForumRewrite\Docs\PlatformDocsCatalog::categorySlug($filterValue($key)) : $filterValue($key)) === (string) $item ? ' selected' : '' ?>><?= $e($optionLabel) ?></option>
<?php endforeach; ?>
    </select></label>
<?php endforeach; ?>
    <button type="submit">Search</button>
    <a href="/docs/#docs-library-heading" data-docs-clear>Clear filters</a>
  </form>
  <p role="status" data-docs-count><?= $e((string) $total) ?> documents</p>
  <p data-docs-error hidden>Search unavailable. <button type="button" data-docs-retry>Retry</button> or <a href="/docs/">refresh the library</a>.</p>
  <ul class="docs-entry-list" data-docs-results>
<?php foreach ($library as $entry): ?>
    <li><a href="<?= $e($docHref($entry['href'])) ?>"><?= $e($entry['title']) ?></a><br><code><?= $e($entry['path']) ?></code> · <?= $e($entry['group']) ?></li>
<?php endforeach; ?>
  </ul>
  <p data-docs-empty<?= $total > 0 ? ' hidden' : '' ?>>No documents found.</p>
  <nav aria-label="Document pages" data-docs-pages>
<?php foreach (['Previous' => $page - 1, 'Next' => $page + 1] as $label => $target): ?>
<?php if ($target >= 1 && ($target - 1) * 40 < $total): ?>
    <a href="?<?= $e(http_build_query(array_merge($returnQuery, ['page' => $target]))) ?>#docs-library-heading"><?= $e($label) ?></a>
<?php endif; ?>
<?php endforeach; ?>
  </nav>
</section>

</section>

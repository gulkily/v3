<?php
$documentCount = array_sum($counts['group']);
$filterLabels = ['q' => 'Search', 'group' => 'Collection', 'category' => 'Category', 'folder' => 'Folder'];
$active = [];
foreach (array_keys($filterLabels) as $key) {
    $value = is_string($query[$key] ?? null) ? trim($query[$key]) : '';
    if ($value !== '') {
        $active[$key] = $key === 'category' ? \ForumRewrite\Docs\PlatformDocsCatalog::categorySlug($value) : $value;
    }
}
$activeLabel = static fn (string $key, string $value): string => $key === 'category' ? ($counts['category'][$value]['label'] ?? $value) : $value;
// Results URLs always carry a parameter, so an empty filter set still shows the results view.
$resultsUrl = static fn (array $params): string => '/docs/?' . http_build_query(array_filter($params, static fn ($value): bool => $value !== '' && $value !== null) ?: ['page' => 1]);
$returnQuery = array_filter(array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])), 'is_scalar');
$docHref = static fn (string $href): string => $href . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
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
<?php foreach (array_diff_key($active, ['q' => true]) as $key => $value): ?>
      <input type="hidden" name="<?= $e($key) ?>" value="<?= $e($value) ?>" data-docs-filter-input>
<?php endforeach; ?>
    </form>
    <p class="docs-intro__home" data-docs-results-only<?= $isResults ? '' : ' hidden' ?>><a href="/docs/"><span aria-hidden="true">←</span> Docs home</a></p>
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
<section class="card docs-library" aria-labelledby="docs-library-heading" data-docs-library<?= $isResults ? '' : ' hidden' ?>>
  <h2 id="docs-library-heading">Documents</h2>
<?php if ($active !== []): ?>
  <ul class="docs-chips" aria-label="Active filters">
<?php foreach ($active as $key => $value): ?>
    <li><a class="docs-chip" href="<?= $e($resultsUrl(array_diff_key($active, [$key => true]))) ?>" aria-label="Remove <?= $e($filterLabels[$key] . ': ' . $activeLabel($key, $value)) ?>"><?= $e($filterLabels[$key]) ?>: <?= $e($activeLabel($key, $value)) ?> <span aria-hidden="true">×</span></a></li>
<?php endforeach; ?>
    <li><a href="<?= $e($resultsUrl([])) ?>">Clear all</a></li>
  </ul>
<?php endif; ?>
  <p role="status" data-docs-count><?= $e(number_format($total)) ?> <?= $total === 1 ? 'document' : 'documents' ?></p>
  <p data-docs-error hidden>Search unavailable. <button type="button" data-docs-retry>Retry</button> or <a href="/docs/">refresh the library</a>.</p>
  <details class="docs-filters">
    <summary>Filters</summary>
    <nav aria-label="Filters" class="docs-filters__groups">
      <section>
        <h3>Collection</h3>
        <ul class="docs-filter-list">
<?php foreach ($counts['group'] as $group => $count): ?>
          <li><a class="docs-filter-list__link" href="<?= $e($resultsUrl(['q' => $active['q'] ?? '', 'group' => $group])) ?>"<?= ($active['group'] ?? null) === $group ? ' aria-current="page"' : '' ?>><span><?= $e($group) ?></span> <span class="docs-filter-list__count"><?= $e(number_format($count)) ?></span></a></li>
<?php endforeach; ?>
        </ul>
      </section>
      <section>
        <h3>Category</h3>
        <ul class="docs-filter-list">
<?php foreach ($counts['category'] as $slug => $category): ?>
          <li><a class="docs-filter-list__link" href="<?= $e($resultsUrl(['q' => $active['q'] ?? '', 'category' => (string) $slug])) ?>"<?= ($active['category'] ?? null) === (string) $slug ? ' aria-current="page"' : '' ?>><span><?= $e($category['label']) ?></span> <span class="docs-filter-list__count"><?= $e(number_format($category['count'])) ?></span></a></li>
<?php endforeach; ?>
        </ul>
      </section>
      <section>
        <h3>Folder</h3>
<?php
$folderItems = static function (string $parent) use (&$folderItems, $counts, $e, $resultsUrl, $active): string {
    $html = '';
    foreach (array_keys($counts['folder']) as $folder) {
        if ($parent === '' ? !in_array($folder, ['Project', 'docs'], true) : dirname($folder) !== $parent) {
            continue;
        }
        $name = $parent === '' ? $folder : basename($folder);
        $link = static fn (string $label): string => '<a class="docs-filter-list__link" href="' . $e($resultsUrl(['q' => $active['q'] ?? '', 'folder' => $folder])) . '"'
            . (($active['folder'] ?? null) === $folder ? ' aria-current="page"' : '') . '><span>' . $e($label) . '</span> <span class="docs-filter-list__count">' . $e(number_format($counts['folder'][$folder])) . '</span></a>';
        $nested = $folderItems($folder);
        if ($nested === '') {
            $html .= '<li>' . $link($name) . '</li>';
            continue;
        }
        // Summaries can't hold links, so each branch starts with a link to the whole folder.
        $open = isset($active['folder']) && ($active['folder'] === $folder || str_starts_with($active['folder'], $folder . '/')) ? ' open' : '';
        $html .= '<li><details' . $open . '><summary>' . $e($name) . ' <span class="docs-filter-list__count">' . $e(number_format($counts['folder'][$folder])) . '</span></summary>'
            . '<ul class="docs-filter-list docs-filter-list--tree"><li>' . $link('All in ' . $name) . '</li>' . $nested . '</ul></details></li>';
    }
    return $html;
};
?>
        <ul class="docs-filter-list docs-filter-list--tree"><?= $folderItems('') ?></ul>

      </section>
    </nav>
  </details>
  <ol class="docs-results" data-docs-results>
<?php foreach ($library as $entry): ?>
    <li class="docs-result">
      <a class="docs-result__title" href="<?= $e($docHref($entry['href'])) ?>"><?= $e($entry['title']) ?></a>
<?php if ($entry['summary'] !== ''): ?>
      <p class="docs-result__summary"><?= $e($entry['summary']) ?></p>
<?php endif; ?>
      <p class="docs-result__meta"><code class="docs-result__path"><?= $e($entry['path']) ?></code> <span class="docs-badge"><?= $e($entry['categoryLabel']) ?></span><?php if (!isset($active['group']) && $entry['group'] !== 'Current guidance'): ?> <span class="docs-badge"><?= $e($entry['group']) ?></span><?php endif; ?></p>
    </li>
<?php endforeach; ?>
  </ol>
  <div class="docs-empty" data-docs-empty<?= $total > 0 ? ' hidden' : '' ?>>
    <p>No documents found.</p>
    <p><a href="<?= $e($resultsUrl([])) ?>">Clear filters</a> · <a href="/docs/">Docs home</a></p>
  </div>
<?php $pageCount = max(1, (int) ceil($total / 40)); ?>
  <nav class="docs-pages" aria-label="Document pages" data-docs-pages<?= $pageCount > 1 ? '' : ' hidden' ?>>
<?php if ($page > 1): ?>
    <a href="<?= $e($resultsUrl($active + ['page' => $page - 1])) ?>"><span aria-hidden="true">←</span> Previous</a>
<?php endif; ?>
    <span aria-current="page">Page <?= $e((string) $page) ?> of <?= $e((string) $pageCount) ?></span>
<?php if ($page < $pageCount): ?>
    <a href="<?= $e($resultsUrl($active + ['page' => $page + 1])) ?>">Next <span aria-hidden="true">→</span></a>
<?php endif; ?>
  </nav>
</section>

</section>

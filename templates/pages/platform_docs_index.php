<section class="stack docs-page">
  <article class="card">
    <h1><?= $e($platformDocsBrand['heading']) ?></h1>
    <p><?= $e($platformDocsBrand['introduction']) ?></p>
  </article>
  <details class="card docs-starting-guides">
    <summary>Starting guides</summary>
    <ul class="docs-entry-list">
<?php foreach ($categories as $entries): ?>
<?php foreach ($entries as $entry): ?>
      <li><a href="<?= $e($entry['href']) ?>"><?= $e($entry['title']) ?></a><br><code><?= $e($entry['path']) ?></code></li>
<?php endforeach; ?>
<?php endforeach; ?>
    </ul>
  </details>
<?php
$filterValue = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
$returnQuery = array_filter(array_intersect_key($query, array_flip(['q', 'folder', 'category', 'group', 'page'])), 'is_scalar');
$docHref = static fn (string $href): string => $href . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
?>
<section class="card docs-library" aria-labelledby="docs-library-heading" data-docs-library>
  <h2 id="docs-library-heading">All documents</h2>
  <form action="/docs/" method="get" data-docs-form>
    <label>Search <input name="q" type="search" value="<?= $e($filterValue('q')) ?>"></label>
<?php foreach (['folder' => ['Folder', $folders], 'category' => ['Category', array_values(array_unique(array_column($inventory, 'category')))], 'group' => ['Collection', ['Current guidance', 'Plans and history', 'Workflow']]] as $key => [$label, $options]): ?>
    <label><?= $e($label) ?> <select name="<?= $e($key) ?>">
      <option value="">All</option>
<?php foreach ($options as $item): ?>
      <option value="<?= $e($item) ?>"<?= $filterValue($key) === $item ? ' selected' : '' ?>><?= $e($item) ?></option>
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

  <details class="card docs-transparency">
    <summary>How it works</summary>
    <div class="docs-architecture-diagram">
      <svg viewBox="0 0 740 250" role="img" aria-labelledby="docs-diagram-title docs-diagram-description">
        <title id="docs-diagram-title"><?= $e($platformDocsBrand['architectureTitle']) ?></title>
        <desc id="docs-diagram-description"><?= $e($platformDocsBrand['architectureDescription']) ?></desc>
        <defs>
          <marker id="docs-diagram-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
            <path d="M0,0 L8,4 L0,8 z" class="docs-diagram-arrowhead" />
          </marker>
        </defs>

        <rect x="20" y="45" width="145" height="125" rx="4" class="docs-diagram-box" />
        <text x="92" y="76" text-anchor="middle" class="docs-diagram-title">Member's browser</text>
        <path d="M79 98 v33 M63 113 h32 M67 105 l24 24 M91 105 l-24 24" class="docs-diagram-key" />
        <text x="92" y="153" text-anchor="middle" class="docs-diagram-caption">private key (never leaves)</text>

        <path d="M165 110 H220" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <text x="192" y="96" text-anchor="middle" class="docs-diagram-caption">signed write</text>

        <rect x="220" y="30" width="150" height="160" rx="4" class="docs-diagram-records" />
        <text x="295" y="70" text-anchor="middle" class="docs-diagram-title">records/ in Git</text>
        <text x="295" y="110" text-anchor="middle" class="docs-diagram-code">records/posts/</text>
        <text x="295" y="140" text-anchor="middle" class="docs-diagram-code">records/identity/</text>
        <text x="295" y="170" text-anchor="middle" class="docs-diagram-caption">source of truth</text>

        <path d="M370 80 H470" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <rect x="470" y="42" width="130" height="70" rx="4" class="docs-diagram-box" />
        <text x="535" y="84" text-anchor="middle" class="docs-diagram-title">SQLite</text>

        <path d="M370 150 H470" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <rect x="470" y="128" width="130" height="70" rx="4" class="docs-diagram-box" />
        <text x="535" y="170" text-anchor="middle" class="docs-diagram-title">static HTML</text>
        <text x="535" y="220" text-anchor="middle" class="docs-diagram-caption">built from records, can be rebuilt</text>

        <path d="M600 77 H620 V110 H640" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <path d="M600 163 H620 V110" class="docs-diagram-arrow" />
        <rect x="640" y="75" width="80" height="70" rx="4" class="docs-diagram-box" />
        <text x="680" y="117" text-anchor="middle" class="docs-diagram-title">Readers</text>

      </svg>
    </div>
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
  </details>

</section>

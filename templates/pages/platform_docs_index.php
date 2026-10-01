<section class="stack docs-page">
  <article class="card">
    <h1>Platform Docs</h1>
    <p>Zenmemes keeps its public state in Git and builds the site's pages from it. These docs are rendered from the same repository.</p>
    <p>Each entry below shows the path of the file it is rendered from, so you can read the source and propose changes.</p>
  </article>
  <article class="card docs-transparency">
    <h2>How it works</h2>
    <div class="docs-architecture-diagram">
      <svg viewBox="0 0 740 360" role="img" aria-labelledby="docs-diagram-title docs-diagram-description">
        <title id="docs-diagram-title">Zenmemes public architecture</title>
        <desc id="docs-diagram-description">A member's browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers; separate operator credentials and private workflow state remain outside public files and Git.</desc>
        <defs>
          <marker id="docs-diagram-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
            <path d="M0,0 L8,4 L0,8 z" class="docs-diagram-arrowhead" />
          </marker>
        </defs>

        <rect x="20" y="45" width="110" height="125" rx="4" class="docs-diagram-box" />
        <text x="75" y="76" text-anchor="middle" class="docs-diagram-title">Member's browser</text>
        <path d="M62 98 v33 M46 113 h32 M50 105 l24 24 M74 105 l-24 24" class="docs-diagram-key" />
        <text x="75" y="153" text-anchor="middle" class="docs-diagram-caption">private key (never leaves)</text>

        <path d="M130 110 H190" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <text x="160" y="96" text-anchor="middle" class="docs-diagram-caption">signed write</text>

        <rect x="190" y="30" width="150" height="160" rx="4" class="docs-diagram-records" />
        <text x="265" y="70" text-anchor="middle" class="docs-diagram-title">records/ in Git</text>
        <text x="265" y="110" text-anchor="middle" class="docs-diagram-code">records/posts/</text>
        <text x="265" y="140" text-anchor="middle" class="docs-diagram-code">records/identity/</text>
        <text x="265" y="170" text-anchor="middle" class="docs-diagram-caption">source of truth</text>

        <path d="M340 80 H470" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <text x="405" y="65" text-anchor="middle" class="docs-diagram-code">./v3 rebuild</text>
        <rect x="470" y="42" width="130" height="70" rx="4" class="docs-diagram-box" />
        <text x="535" y="84" text-anchor="middle" class="docs-diagram-title">SQLite</text>

        <path d="M340 150 H470" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <text x="405" y="177" text-anchor="middle" class="docs-diagram-code">./v3 build-static</text>
        <rect x="470" y="128" width="130" height="70" rx="4" class="docs-diagram-box" />
        <text x="535" y="170" text-anchor="middle" class="docs-diagram-title">static HTML</text>
        <text x="535" y="220" text-anchor="middle" class="docs-diagram-caption">built from records, can be rebuilt</text>

        <path d="M600 77 H620 V110 H640" class="docs-diagram-arrow" marker-end="url(#docs-diagram-arrow)" />
        <path d="M600 163 H620 V110" class="docs-diagram-arrow" />
        <rect x="640" y="75" width="80" height="70" rx="4" class="docs-diagram-box" />
        <text x="680" y="117" text-anchor="middle" class="docs-diagram-title">Readers</text>

        <rect x="190" y="260" width="410" height="75" rx="4" class="docs-diagram-private" />
        <text x="210" y="288" class="docs-diagram-private-title">Operator credentials and private workflow state</text>
        <text x="210" y="314" class="docs-diagram-caption">outside public/ and outside Git · reply-agent key</text>
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

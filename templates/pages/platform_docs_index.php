<section class="stack docs-page">
  <article class="card">
    <h1>Platform Docs</h1>
    <p>Zenmemes is designed to be legible: public state is Git-backed, site views are derived from it, and this documentation is rendered from the same repository it describes.</p>
    <p>Every page names its authoritative source file so that readers can inspect, challenge, and improve the implementation.</p>
  </article>
  <article class="card docs-transparency">
    <h2>Transparent by construction</h2>
    <div class="docs-transparency-grid">
      <section>
        <h3>Git-backed public record</h3>
        <p>Posts, public keys, identity events, and other canonical public facts are plain records in Git. SQLite and static HTML make them fast to read, but can be rebuilt from the record.</p>
      </section>
      <section>
        <h3>PKI-backed identity</h3>
        <p>Browser identities use OpenPGP. Members keep private keys in their browsers; the public key, fingerprint, and verifiable public writes provide the accountable public trail.</p>
      </section>
      <section>
        <h3>Private boundaries are explicit</h3>
        <p>No member private key belongs in the server-side public record. Optional operator credentials and private workflow state live outside <code>public/</code> and Git, not mixed with community data.</p>
      </section>
    </div>
    <p><a href="/docs/architecture/public_architecture_and_trust.md">Read the public architecture and trust model.</a></p>
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

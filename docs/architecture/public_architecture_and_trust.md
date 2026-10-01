# Public Architecture and Trust Model

Zenmemes is designed so that the important public parts of the forum are
legible, portable, and auditable. The application is ordinary PHP, the public
record is plain text in Git, and derived views can be rebuilt from that record.

## A Git-backed public record

Posts, public keys, identity bootstrap records, approvals, reactions, and
other canonical public facts are stored as small files in the content
repository. Git records how that repository changed over time. The SQLite
read model and static HTML release make the site fast to read, but they are
derived views rather than the only source of truth.

That separation makes the public forum portable: an operator can archive the
repository, inspect a record directly, or rebuild a read model from the
canonical files. The [canonical post record](../specs/canonical_post_record_v1.md)
defines the public post format, and the [production deploy runbook](../runbooks/production_deploy.md)
explains which pieces are canonical and which are derived.

## Public-key identity, not server-held user keys

When someone chooses browser identity, their browser generates and keeps the
OpenPGP private key. The site receives the public key, its fingerprint, and
the signatures needed to verify signed public writes; it does not need the
member's private key to publish or verify that identity. The visible identity
reference is an `openpgp:<fingerprint>` value tied to the stored public key.

The [identity bootstrap record](../specs/identity_bootstrap_record_v1.md)
spells out the binding between a public key, fingerprint, username, and first
accepted identity event. This is PKI-backed accountability: readers can see
the public identity material and the record history without being asked to
trust an opaque account database.

## Clear private boundaries

The public content repository, application source, public assets, and these
docs are not places for secrets. In particular, member OpenPGP private keys
are browser-held rather than stored in the public forum record or application
checkout.

That does not mean an operator can never have a server-side secret. Optional
LLM features require provider credentials, and their private configuration
lives outside `public/` and outside Git. Private queues, LLM exchanges, and
other operational state likewise remain outside the public record. The point
is a clear boundary: private runtime material is kept separate, while public
community state stays inspectable and reproducible.

## What visitors can inspect

- This site renders the same Markdown documentation committed with the
  application, and each page shows its exact source path.
- The [Tools](/tools/) page links to repository and read-model backups.
- The public activity and source views expose relevant canonical record and
  commit context where it is available.
- The [CLI reference](../reference/v3_cli.md) and runbooks describe rebuild,
  backup, signature-audit, and release procedures.

Transparency does not remove every operational trust decision, but it makes
the public state, the rules for deriving it, and the software that presents it
available for inspection and improvement.

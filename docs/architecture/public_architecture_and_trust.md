# Public Architecture and Trust Model

## The public record lives in Git

Zenmemes stores every canonical public fact as a small plain-text file in the content repository: posts, public keys, identity bootstrap records, approvals, reactions. Git tracks how that repository changes.

The SQLite read model and the static HTML release make the site fast to read. Both are derived. Delete them and you can rebuild them from the canonical files.

An operator can archive the repository, open any record directly, or rebuild a read model from scratch. The [canonical post record](../specs/canonical_post_record_v1.md) defines the public post format. The [production deploy runbook](../runbooks/production_deploy.md) says which pieces are canonical and which are derived.

## Your browser holds your private key

If you choose browser identity, your browser generates an OpenPGP private key and keeps it. The site receives the public key, its fingerprint, and the signatures it needs to verify your signed public writes. It does not need your private key to publish or verify your identity.

Your visible identity is an `openpgp:<fingerprint>` value tied to the stored public key. The [identity bootstrap record](../specs/identity_bootstrap_record_v1.md) specifies how a public key, fingerprint, username, and first accepted identity event bind together. Readers can check the identity material and the record history themselves, with no opaque account database to trust.

## Where secrets live

No secrets go in the public content repository, the application source, the public assets, or these docs. Member private keys stay in the browser. They never enter the forum record or the application checkout.

Operators can still hold server-side secrets. Optional LLM features need provider credentials, and that private configuration sits outside `public/` and outside Git. Private queues, LLM exchanges, and other operational state stay out of the public record too. You still have to trust the operator with that private state.

## What visitors can inspect

- This site renders the same Markdown documentation committed with the application, and each page shows its exact source path.
- The [Tools](/tools/) page links to repository and read-model backups.
- The public activity and source views show canonical record and commit context where it is available.
- The [CLI reference](../reference/v3_cli.md) and runbooks describe rebuild, backup, signature-audit, and release procedures.

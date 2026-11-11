# OpenPGP in v3

Use this page as the map of every OpenPGP piece in v3: which library does what, where the files live, how they fit together, and how to check them. It links out for detail rather than repeating it. The [trust model](../architecture/public_architecture_and_trust.md) explains *why* identities are OpenPGP keys; [Authentication Modes and Access](authentication_modes.md) explains identity, sessions, and approval.

## Two implementations, one format

v3 uses two different OpenPGP implementations that must agree byte for byte:

- **Browser: OpenPGP.js.** Generates keys, signs records, encrypts and decrypts private messages and key backups. Private keys never leave the browser.
- **Server: the `gpg` command (GnuPG).** Verifies signatures and inspects public keys. The server never holds a member's private key. The one exception is the server's own reply-agent identity (see below).

Anything that changes signed bytes (record format, line endings, Unicode normalization) has to be checked against both sides.

## Browser side

### The two bundles

Both are vendored, unmodified upstream builds. Both are needed.

| File | Version | Used when |
|---|---|---|
| `public/assets/openpgp.min.js` (+ `.map`) | 6.3.0 | Default: HTTPS and loopback origins |
| `public/assets/openpgp.v5.11.3.min.js` | 5.11.3 | Public plain-HTTP origins |

OpenPGP.js v6 needs WebCrypto (`crypto.subtle`), which browsers expose only in secure contexts. On a public `http://` origin, v6 throws before it defines `window.openpgp`. v3 keeps plain HTTP as a supported access path (no forced redirect, no HSTS), so v5 is served there. v5 still has a non-WebCrypto fallback.

`http://127.0.0.1` and `http://localhost` count as secure contexts. **Local testing over HTTP loads v6 and does not reproduce the production HTTP path.** Use the smoke check or canary below, or a non-loopback hostname.

The 1.8 MB source map is served on purpose. The v6 bundle points to it, and without it DevTools logs a 404. Browsers fetch it only while DevTools is open.

Package hashes, the license, and the update procedure are in `public/assets/openpgp.PROVENANCE`. Background: [HTTP OpenPGP failure fix plan](../plans/http_openpgp_failure_fix_plan_v1.md).

### How a page gets OpenPGP

1. `TemplateRenderer` publishes both bundle URLs (fingerprinted) as `window.__forumAssetPaths.openpgpV6` and `.openpgpV5`.
2. Pages that need signing include `/assets/openpgp_loader.js`. It picks one bundle (`v5` only when `window.isSecureContext === false`), preloads it at low priority, and exposes `window.__forumOpenPgpLoader` with `selectedVersion`, `selectedPath`, and `load()`.
3. Callers `await window.__forumOpenPgpLoader.load()`, which injects the script once and resolves to `window.openpgp`.

Code that uses OpenPGP.js must stay within the API both versions share. Today that is `generateKey`, `readKey`, `readPrivateKey`, `sign`, `createMessage`, `readMessage`, `encrypt` and `decrypt`. Check a new call against both versions' docs before adding it.

### Consumers

- `browser_signing.js`: key generation, local storage, identity registration, and detached signing of posts, replies, reactions, and other records.
- `private_site_auth.js`: signs the server's challenge to establish an authenticated session.
- `key_backup.js`: password-encrypted key backups. See [Browser key backups](key_backups.md).
- `private_messages.js`, `private_message_reader.js`: message encryption and decryption. See [Private messaging](../architecture/private_messaging.md).

### Keys in the browser

- Generated as ECC `ed25519`, with the user ID set to the username.
- **Creation time is backdated 60 seconds.** GnuPG rejects a key whose creation timestamp is ahead of the server clock, so browser clock skew would otherwise break verification (`browserKeyCreationBackdateMilliseconds`).
- Stored in `localStorage` as armored text under `forum_pki_private_key`, `forum_pki_public_key`, `forum_pki_fingerprint`, `forum_pki_username`, and `forum_pki_published_fingerprint`.
- A key stays local until its first signed action registers it. See [Authentication Modes](authentication_modes.md#site-settings).

## Server side

All under `src/ForumRewrite/Security/` unless noted.

- `OpenPgpSignatureVerifier`: verifies a detached signature against an armored public key and an expected fingerprint. Each check runs `gpg` in a fresh temporary home directory, so there is no shared keyring. Limits: 10 seconds, 1 MiB armor, 16 MiB text. Returns a status (`invalid_public_key`, `public_key_import_failed`, `signature_verification_failed`, `signer_fingerprint_mismatch`, and others), timings, and diagnostics, including the key-creation offset for clock-skew cases.
- `OpenPgpKeyInspector`: reads fingerprint and user ID from a public key, using `gpg --import-options show-only` plus a small packet parser.
- `SignatureCheckCache`: caches *successful* checks next to the read model, so rebuilds don't re-run `gpg`. Failures are not cached. The file is derived and safe to delete.
- `GpgHomeCleaner`: stops the `gpg-agent` that `gpg` starts for a temporary home, then deletes the home.
- `ReactionSignature`, `ApprovalAcceptance`, `ApprovalPolicy`, `KeyRetirement`: rules for specific signed record types.
- `Write/SignedPostIdentity`, `Write/SignedPostBundleValidator`, `Write/LocalWriteService`: accept signed posts and identity bootstraps on the write path.
- `Agent/AgentIdentityService`: generates and uses the server's own **reply-agent** key with `gpg` in a temporary home. This is the only private key the server handles.

The server needs `gpg` on `PATH`. `./v3 status` warns when it is missing (`Support/GpgAvailability`); without it, every signature check fails.

## Identities and records

An identity ID is `openpgp:<fingerprint>` with a **lowercase** 40-character hex fingerprint. Watch the case; it is not uniform across files:

| Record | Path | Fingerprint case |
|---|---|---|
| Public key | `records/public-keys/openpgp-<FINGERPRINT>.asc` | upper (some readers also try lower, e.g. `ActivityService`) |
| Identity bootstrap | `records/identity/identity-openpgp-<fingerprint>.txt` | lower |
| Approval seed | `records/approval-seeds/openpgp-<fingerprint>.txt` | lower |
| Key retirement | `records/key-retirements/openpgp-<fingerprint>.txt` | lower |

Paths come from `Canonical/CanonicalPathResolver`. Use it rather than building paths by hand. The bootstrap format is specified in [identity bootstrap record v1](../specs/identity_bootstrap_record_v1.md).

Non-browser clients (agents, `frm.py`) sign with their own `gpg`. See the [agent API](agent_api.md).

## Checking it works

- `./v3 openpgp smoke [--origin=zenmemes.com]`: read-only. For HTTP and HTTPS it reads the page's asset paths, checks that the bundle the browser would pick is served correctly, and also checks both raw bundle URLs that older cached clients may still request. See the [CLI reference](v3_cli.md).
- `./v3 openpgp canary --confirm-production-write`: **writes to production.** Creates a `release-check` identity over HTTP in a real browser and publishes a post. Run it by hand after the smoke check passes, never in CI. Don't rerun it after an ambiguous result.
- `php scripts/audit_post_signatures.php [repository_root]`: counts posts by signature state across the repository.
- Tests: `OpenPgpLoaderTest`, `OpenPgpAssetSmokeProbeTest`, `LazyComposeSigningTest`, `PrivateMessageComposerTest`. Browser tests in `tests/browser/` (`key_backup.cjs`, `key_backup_ui.mjs`, `signed_reactions.mjs`, `account_key_approval.mjs`, and others) can run against either bundle. See [Browser key backups](key_backups.md) for the `BUNDLE=v5` form.

## Updating OpenPGP.js

Follow the steps in `public/assets/openpgp.PROVENANCE`. In short: replace the file from the package's `dist/`, update the hashes, run the smoke check, and run the browser key tests over both HTTP (non-loopback) and HTTPS. A v5 update also renames `openpgp.v5.*.min.js` and its references in `TemplateRenderer` and `OpenPgpAssetSmokeProbe`. The smoke check requests both raw bundle URLs for older cached clients, so decide whether the old v5 file stays served for a while.

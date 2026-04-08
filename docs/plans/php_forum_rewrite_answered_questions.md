# PHP Forum Rewrite Answered Questions

This document collects the questions that have already been answered for the PHP rewrite. These answers reflect the current project direction, the active rewrite spec, the migrated canonical specs under `docs/specs/`, the reduced parity inventory in `v3`, and the retained fixture tree under `tests/fixtures/`. Historical references to `v2` are informational only and exist for design context, not as implementation authority.

## Current Product Contract

- What exact current routes, page states, and plain-text APIs define the "current product slice" that V1 must match?
Answer: The current user-visible route set in `v2` is:
`/`, `/account/key/`, `/instance/`, `/activity/`, `/compose/thread`, `/compose/reply`, `/threads/<thread-id>`, `/posts/<post-id>`, `/profiles/<profile-slug>`, `/user/<username>`, `/assets/...`, `/favicon.ico`, `/llms.txt`.
The plain-text API surface is:
`/api/`, `/api/list_index`, `/api/get_thread`, `/api/get_post`, `/api/get_profile`, `/api/get_username_claim_cta`, `/api/create_thread`, `/api/create_reply`, `/api/pow_requirement`, `/api/link_identity`, `/api/set_identity_hint`, `/api/call_llm`.
- Is there a canonical inventory of existing public routes, write routes, redirects, and RSS outputs?
Answer: Yes. For `v3`, the canonical route inventory is the local authority set described in this document plus `docs/specs/php_forum_rewrite_spec_v1.md` and `docs/plans/php_forum_rewrite_trimmed_parity_suite_v1.md`. RSS outputs currently exist for `/` with `?format=rss`, `/threads/<thread-id>?format=rss`, and `/activity/?view=<mode>&format=rss`.
- Which current behaviors are considered compatibility-critical versus acceptable to simplify in V1?
Answer: Compatibility-critical user-facing behaviors appear to be:
indexed public reads for board/thread/post/profile surfaces; activity filters and RSS; browser-signing-based compose flows; username capture during key generation; account key page; and plain-text API availability. What looks more simplifiable is copy/layout phrasing, some feature-flagged affordances, and whether self-profile/account pages remain partly dynamic initially.
- What is the authoritative reference implementation for behavior that is not yet captured in `docs/specs/`?
Answer: `v3` should not depend on `~/v2` as the authority. The authority set is now:
`docs/specs/php_forum_rewrite_spec_v1.md`,
`docs/specs/canonical_post_record_v1.md`,
`docs/specs/identity_bootstrap_record_v1.md`,
`docs/specs/profile_read_contract_v1.md`,
`docs/specs/public_key_storage_v1.md`,
`docs/plans/php_forum_rewrite_trimmed_parity_suite_v1.md`,
and `tests/fixtures/parity_minimal_v1/`.
If those documents are still silent on a behavior, that behavior needs an explicit product decision before implementation.
- What exact current-product behavior is in scope for parity where `v2` behavior and the rewrite spec are still ambiguous?
Answer: Match the local `v3` authority set only. If the local specs and plans are still ambiguous, make an explicit product decision in `v3` rather than consulting `v2` as a tie-breaker.

## Canonical Record Definitions

- Which exact record files and schemas under `records/` are in scope for V1?
Answer: At minimum, V1 should carry forward the record families already present and used in `v2` that remain in scope: `records/posts/`, `records/identity/`, `records/public-keys/`, and `records/instance/`.
- Are there already versioned spec documents for every required canonical record family, or do some formats still live only in code/current behavior?
Answer: The retained canonical families are now spec-backed inside `v3`: rewrite spec, post records, identity bootstrap records, and public-key storage. Practical rule: do not implement or extend a canonical record family unless its behavior is versioned under `docs/specs/`.
- Which exact `docs/specs/*.md` files are authoritative for each in-scope record family?
Answer: The authoritative in-scope spec files are:
`docs/specs/php_forum_rewrite_spec_v1.md`,
`docs/specs/canonical_post_record_v1.md`,
`docs/specs/identity_bootstrap_record_v1.md`,
`docs/specs/profile_read_contract_v1.md`,
and `docs/specs/public_key_storage_v1.md`.
- Which in-scope record families still require a formal versioned spec before implementation starts?
Answer: None for the retained V1 scope. Posts, identity bootstrap, profile reads, and public-key storage are all covered by versioned local specs.
- What canonical identity/bootstrap records are required for the system to function?
Answer: The current implementation clearly depends on identity bootstrap records under `records/identity/` and canonical public keys under `records/public-keys/`. Identity-aware reads and browser-signing/bootstrap behavior depend on those identity records.
- Are any canonical format changes acceptable for V1, or must the rewrite be fully backward-compatible with the existing repository layout and file contents?
Answer: Backward compatibility should be the default. For a budget-host migration, the cheapest and safest path is to preserve the existing repository layout and canonical record contents exactly and evolve only the derived read model and serving path.
- Are there edge cases around ASCII enforcement, detached signatures, or path naming that need to be preserved exactly?
Answer: Yes. `v2` treats ASCII-only canonical text as a product rule, detached-signature/public-key handling as part of the write contract, and record-family path layout as part of the application contract. Preserve ASCII, LF/trailing-LF, and existing directory-family boundaries exactly in V1 unless a later spec explicitly changes them.

## Read-Model Design

- Should V1 use one SQLite database or two?
Answer: Use two SQLite databases from the start: one for canonical-derived indexed state and one for route/view payloads or snapshots. This sits alongside filesystem `_static_html/` artifacts, which remain the primary direct-serve path for static-safe anonymous reads.
- What exact data must be materialized for posts, threads, profiles, and activity items?
Answer: Enough to render hot routes without reparsing records or reconstructing git history. At minimum:
posts with body/timestamps/author/root/parent; threads with root metadata/reply counts/last activity/tags/type; profiles with identity/current visible username/linked-identity summary/post summary/public-key summary; and activity items preclassified as `content` or `code`.
- What is the required freshness model for incremental refresh after writes?
Answer: Successful writes should update affected indexed rows and then invalidate targeted `_static_html/.../index.html` artifacts and related route/view payloads. V1 should not eagerly regenerate all affected HTML after writes; the default strategy is targeted invalidation plus next-read regeneration.
- Which routes should be lazily regenerated on the next safe read after invalidation?
Answer: All public HTML routes should regenerate lazily by default, including `/`, thread pages, post permalinks, profiles, `/instance/`, and activity pages.
- On startup with a stale or missing read model, should the app block and rebuild, or serve a maintenance response while rebuilding?
Answer: Follow the `v2` pattern: serve a clear lightweight refresh/rebuild interstitial for the first request, with an explicit rebuild path, then materialize the fresh page. That is better aligned with a budget host than globally blocking all requests.
- What operator-visible signals are mandatory for rebuild state, stale state, and recovery events?
Answer: Keep these mandatory: read-model stale/missing status, rebuild started/completed/failed, current repo HEAD associated with the read model, schema/version mismatch, and route source headers or logs showing `static-html`, `php-native`, `php-microcache`, or dynamic fallback.

## Activity and Project Info

- What precisely belongs in the `code` activity feed?
Answer: In `v2`, the `code` feed contains non-record repository commits such as application or docs changes. It excludes canonical content commits. The tests show code activity including paths like `docs/notes.md` and linking to the underlying git commit, while excluding content commits like post/reply creation.
- What data source should power activity items that are currently derived from git history or repo facts?
Answer: In `v2`, activity is still fundamentally git-derived. Content and code activity come from git commit history. `/instance/` also shows lightweight repo facts such as the current commit and the published instance record at `records/instance/public.txt`.
Recommendation for V1: keep git and canonical records as the offline source of truth, but index activity rows into SQLite for `/activity/` and RSS so hot requests do not reconstruct git history on demand.
- What are the exact filter, pagination, and RSS contracts that must remain stable?
Answer: Current activity filters are `all`, `content`, and `code` for V1 planning purposes. Default `/activity/` behavior is effectively `content`. Pagination uses `page=<n>` with invalid or missing values normalized to page 1. RSS is available from `/activity/?view=<mode>&format=rss`. Board RSS is `/?format=rss` and thread RSS is `/threads/<thread-id>?format=rss`.
- What content currently appears on `/instance/` and which parts are mandatory for V1?
Answer: `/instance/` currently shows:
public instance facts from `records/instance/public.txt`; a `Project FAQ`; a `Facts` section; a `Project overview`; the source file path (`records/instance/public.txt`); and current git commit information. The FAQ content includes explanations like why posts are ASCII-only, why OpenPGP is used, why text files and git are used, why the project should be forkable, and a contrast with a generic social feed.

## Profiles, Identity, and Account Semantics

- What exact profile fields are required now that profile updates are out of scope?
Answer: The visible profile model for V1 should include identity ID, current visible username, post history summary, public key material, and basic technical details. Separate post-bootstrap profile editing is out of scope. The username should be captured once during browser keypair generation and otherwise treated as fixed for V1.
- How do public profile state and self-profile/account state differ today?
Answer: Public profile pages show the published profile and post history. Self-profile is triggered by `?self=1` and changes the nav state to `My profile`; it can show an empty-state page for unpublished identities and account-setup affordances. Unknown public profiles return `404`, but unknown self-marked profiles render the "Your profile is ready to start" empty state.
- What cookie semantics, identity hints, and browser-held key flows must be preserved exactly?
Answer: `v2` has an identity-hint mechanism using the `forum_identity_hint` cookie plus `/api/set_identity_hint`. Browser-held OpenPGP keys are central to the compose and account flows: the browser can generate or import a key, sign payloads client-side, and view key material on `/account/key/`. For V1, generated-key bootstrap should ask for a username via standard `prompt()` and fall back to `guest` when `prompt()` is unavailable or unusable. The profile/account nav JS also depends on identity-hint-aware enhancement behavior.
- What are the failure modes for signature verification, and what user-visible error contracts must they produce?
Answer: User-visible failures currently come back as deterministic text/plain API errors with stable status codes such as `400 Bad Request` for malformed or invalid payloads and `403 Forbidden` for policy failures. Signature verification failures, invalid usernames, and missing proof-of-work all surface this way.

## Write Paths and Policy

- What are the exact request/response contracts for:
  - create thread
  - create reply
  - username/account flows
Answer: The signed write APIs all use POSTs. The main JSON request shape in `v2` is `payload`, `signature`, `public_key`, and `dry_run`; unsigned fallback can omit signature/public key when that feature flag is enabled. Success responses are deterministic text/plain bodies that echo key record metadata and include commit information. The specific endpoints still relevant to V1 are:
`/api/create_thread`, `/api/create_reply`, `/api/link_identity`.
- Which current `v2` write flows count as the "most important write/account flows" for V1 acceptance?
Answer: Create thread, create reply, and username capture during key generation are the hard requirement.
- What validations are required before canonical record materialization for each write type?
Answer: Current functional requirements include payload parsing, detached-signature verification for signed flows, username-shape validation during identity bootstrap, and proof-of-work validation for first-post flows when the POW feature is enabled.
- Are writes always synchronous from the user's perspective, or are any slow operations allowed to complete out of band?
Answer: The current product contract is synchronous immediate read-after-write for core flows. Successful API writes return only after the canonical record is stored, git-committed, and the relevant read model refresh has run.
- What should happen if git commit succeeds but incremental read-model refresh fails?
Answer: Treat the write as canonically successful, mark derived state stale, remove affected static HTML artifacts, and force deterministic recovery on the next read or startup. Do not roll back the git commit; git remains canonical.
- What operator tooling is expected for inspecting or recovering partially failed write flows?
Answer: Minimum required tooling: visible stale/read-model status, recent write operation log with timing and failure step, deterministic rebuild commands/paths for indexed state, deterministic static-artifact purge/regenerate commands/paths, and a way to inspect recent write/rebuild failures and current repo HEAD.

## Routing, Rendering, and Assets

- Which existing URLs must remain byte-for-byte compatible, and which may redirect?
Answer: The safest compatibility set is the route list documented above, especially `/`, `/threads/<id>`, `/posts/<id>`, `/instance/`, `/activity/`, `/profiles/<slug>`, `/user/<username>`, compose pages, the plain-text `/api/...` endpoints, `/assets/...`, `/favicon.ico`, and `/llms.txt`.
- Are there any known SEO, bookmark, or external-link dependencies on current route shapes?
Answer: The product clearly expects stable bookmarks and direct links for thread pages, post permalinks, username pages, profile pages, and activity filters. RSS links are embedded in board/thread/activity pages, so those route shapes are also externally consumable.
- What shared templates, CSS contracts, and enhancement assets already exist and must be preserved or ported?
Answer: `v2` has a shared page shell, shared primary nav, shared profile-nav contract, and shared asset routes. Important in-scope assets include `site.css`, `primary_nav.js`, `profile_nav.js`, `browser_signing.js`, `copy_field.js`, `profile_key_viewer.js`, `account_key_actions.js`, and the favicon assets.
- Are assets definitely PHP-served in V1, or can some static serving remain outside PHP if the route contract stays stable?
Answer: Static serving should remain outside PHP whenever possible. For maximum throughput on a budget host: Apache should serve immutable/versioned assets directly, Apache should serve prebuilt `_static_html/.../index.html` files directly for allowlisted HTML routes, and PHP should only handle dynamic fallback, personalized/uncacheable requests, writes, and routes with no current artifact.
- What does `llms.txt` need to contain, and is it generated or static?
Answer: In `v2`, `/llms.txt` is a text route generated by the app. It documents machine-readable and posting surfaces including at least `GET /api/`, `POST /api/create_thread`, `GET /compose/thread`, and guidance to use `/instance/` for public operator/deployment facts.

## Caching and Performance

- What TTLs should be used for the route microcache on each eligible route family?
Answer: Keep the PHP microcache short, around the existing `v2` value of 5 seconds, and treat it as a fallback layer rather than the main throughput path. Static HTML bypass should carry most anonymous read traffic.
- Which query-string variants, if any, are safe to cache?
Answer: For direct static HTML bypass, only queryless GETs should be eligible. For PHP fallback and the route/view payload database, V1 should keep query-bearing variants fully dynamic rather than introducing a fixed snapshot allowlist.
- What invalidation granularity is required after each write type?
Answer: Prefer targeted invalidation over blanket clears. Minimum route-family targeting:
create thread: `/`, affected board listings if materialized, new thread page, new post permalink, and author profile.
create reply: thread page, reply permalink, board index if last-activity ordering changes, and author profile.
identity bootstrap / username capture: profile page, username route, and any surfaces showing the initial username for that identity.
If targeted logic is too complex early on, clearing only the affected subtree under `_static_html/` and corresponding route/view payloads is acceptable; full-tree clears should be the fallback of last resort.
- What measurable performance baseline from the current stack should the rewrite beat?
Answer: The rewrite should beat the current dynamic Python/PHP bridge for anonymous hot reads by serving prebuilt static HTML without PHP execution on the steady-state happy path, by avoiding request-time full repository parses, and by avoiding request-time git-log reconstruction for activity.
- What named timing steps or logs are required for hotspot analysis?
Answer: Keep timing around static HTML hit/miss, PHP-native payload hit/miss, PHP microcache hit/miss, CGI/dynamic fallback duration, read-model refresh duration, and write-path substeps such as parse, verify, validate, git add, git commit, rev-parse, refresh, and static invalidation.

## Deployment and Operations

- What PHP runtime and extensions are available in production?
Answer: Assume a conservative shared-host PHP setup with standard filesystem functions, JSON, SQLite/PDO SQLite, and `.htaccess` with `mod_rewrite`. Avoid unusual dependencies.
- What deployment model will own startup rebuilds, permissions, and writable cache/state directories?
Answer: Use a single checked-out repo plus writable sibling/state directories owned by the web user. Required writable areas are derived SQLite/index state, `_static_html/`, PHP microcache directory, and canonical `records/` families that accept writes. Startup rebuilds should be request-driven and deterministic rather than daemon-driven.
- How is the repository checked out in production, and can PHP safely run the required git commands there?
Answer: The intended model remains a writable on-disk checkout where the application can read and write canonical files and invoke non-interactive git commands. PHP should orchestrate non-interactive git usage carefully and avoid shell interpolation.
- Are there concurrency constraints for simultaneous writes or rebuilds on a single instance?
Answer: Yes. Assume single-instance, low-write concurrency and serialize writes/rebuilds with a simple lock. That matches both the product and the host profile.
- What backup, rollback, or recovery procedure is expected during cutover?
Answer: Treat this as a greenfield project rather than a production cutover. Lean on git plus rebuildable derived state: back up the repo checkout and config, treat SQLite and `_static_html/` as disposable derived artifacts, and rebuild derived state from repo HEAD instead of restoring caches.

## Testing and Acceptance

- What existing automated tests or fixtures can be reused as a regression oracle?
Answer: Inside `v3`, the reduced parity oracle is defined by `docs/plans/php_forum_rewrite_trimmed_parity_suite_v1.md` plus `tests/fixtures/parity_minimal_v1/`. Historical `v2` tests may inspire design ideas, but they are not required for implementation and are not part of the authority set.
- Is there an accepted fixture repository or golden-output set for route parity testing?
Answer: Yes for the reduced retained scope: `tests/fixtures/parity_minimal_v1/` is the seed fixture tree in `v3`. It is intentionally small and should be expanded only for in-scope route parity needs.
- Which browser-level behaviors must be manually verified in addition to request tests?
Answer: Browser-signing flows, account key generation/import/viewing, primary-nav enhancement, profile-nav enhancement, key-generation username prompt behavior, fallback-to-`guest` behavior, and identity-hint cookie interactions should all be manually smoke-tested even if server request tests pass.
- What is the acceptance bar for "feature-complete relative to the current product slice"?
Answer: At minimum, V1 should reproduce the current tested read behavior and the observable contracts for the most important write flows: public reads, compose flows, signed thread/reply writes, username/bootstrap behavior, activity/RSS, and the plain-text API surfaces documented above.
- Who signs off on parity for activity, profiles, account/key-bootstrap flows, and operational tooling?
Answer: One repo owner or product maintainer signs off on all parity areas.

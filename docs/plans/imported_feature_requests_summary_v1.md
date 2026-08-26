# Imported Feature Requests Summary V1

Reviewed source set from `backlog/plans-2026-08-26`:

- `related_feature_requests.txt`
- `todo.txt`
- new planning notes added under `docs/plans/` in the backlog import

## Main Themes

The imported requests mostly cluster around perceived responsiveness, operational control, trust workflows, and user-owned data portability.

1. Perceived responsiveness and offline-readiness
   - Users report that expected-instant actions feel slow: menubar clicks, reply submission, and post-create navigation.
   - The related plan recommends measuring first, fixing CSS/asset hygiene early, then adding a minimal read-mostly PWA layer with safe prefetching and service-worker caching.
   - Offline canonical posting is intentionally out of scope for the first pass.

2. CSS and asset-loading annoyance
   - The "no css loaded yet" message is called out as disturbing.
   - The plan treats this as a separate asset-hygiene track: remove visible diagnostic copy, ensure stylesheet links resolve, stop recursive fingerprinting, and add smoke coverage.

3. Agent replies under explicit operator control
   - Split automatic and manual agent-reply enablement so production can disable background/browser-triggered work while still allowing intentional approved-operator triggers.
   - Add a reusable publisher shared by HTTP and CLI, keep safety gates by default, and add a cron worker with conservative candidate selection.

4. Production stalls and busy-page behavior
   - The stall investigation found a real boolean config parsing bug around disabling automatic agent replies.
   - Clean curl probes did not reproduce slow core write/read paths, so remaining stalls should be diagnosed from browser Network timing and `Server-Timing`.
   - A simple production mitigation is to set `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0` so contended dynamic requests fail fast to the existing busy page.

5. Approval and trust workflows
   - Pending-user approval should become optimistic on `/users/pending`: remove or mark the row immediately, then reconcile or roll back after the server response.
   - Anonymous reply trust should use post-level `vouch` reactions from approved users, not identity approval of anonymous authors.

6. User utility features
   - Site text search should start behind a narrow reusable search service, initially backed by the existing SQLite read model, with room for FTS later.
   - User-specific archives should use a request/cache boundary rather than synchronous regeneration or always-prebuilt archives.
   - Browser private key restore should start with local-only private-key import on `/account/key/`, keeping key material client-side.

7. Codebase readability
   - `todo.txt` includes readability requests: make `$toolNavOptions` traceable to its origin and make function definitions searchable without ambiguous matches.

## Suggested Priority Order

1. Remove sensitive material from `todo.txt` and rotate the exposed provider keys.
2. Address CSS/asset hygiene because it directly affects trust in page load state and supports later PWA caching.
3. Measure current menubar and reply latency with browser timing plus `Server-Timing`.
4. Implement the automatic/manual agent-reply split if production latency work depends on disabling automatic background work.
5. Add the small UX wins: optimistic pending approvals and any missing optimistic reply path.
6. Build the PWA/read-cache foundation only after asset paths and cache classes are stable.
7. Schedule the larger product features: anonymous vouching, site search, user archives, and local key restore.

## Review Notes

- `todo.txt` appears to contain live API keys. They are intentionally not copied here.
- The responsiveness requests are related by user impact, but they are unlikely to be one code bug.
- Several plans are already scoped well enough for implementation slices; the highest-risk unresolved questions are production reproduction data, cache invalidation rules, and exact authorization scope for manual agent replies.

# High-traffic mode: what still reaches PHP

Cycle record: [high_traffic_coverage_one_shot.md](./high_traffic_coverage_one_shot.md). State as of `origin/main` `f83c0b0d` (2026-11-17), after [asset_bypass](../asset_bypass/asset_bypass_step4_implementation_summary.md) (PR #293).

While the mode is on, Apache answers `/`, `/threads/`, `/about/` and `/threads/<id>` (`HighTrafficRoute::canonical()`), plus fingerprinted css, js, image and font files with a retained copy. Everything below still goes to PHP. Each item that gets picked becomes its own Railspike cycle. Tick an item when its cycle merges, and link the cycle.

## 0. Measure first

- [ ] **What a warm page really costs.** Load `/` and a thread page with the mode on, once logged out and once signed in. Count the PHP requests in the access log or with instrumentation.
  - Known: the inbox and message badges (`inbox_unread.js`, `private_message_unread.js`) fetch only for signed-in viewers. `high_traffic.js` POSTs `/internal/high-traffic/refresh` when a page is older than its refresh age.
  - Unknown: whether `version_check.js` polls for anonymous visitors, and what else runs on load for signed-in viewers.
  - Why first: if signed-in viewers make several API calls per view, that may matter more than any page below.

## 1. Pages people land on

- [ ] **Single posts, `/posts/<id>`.** Shared links use this form, for example the sources in [PF-20261111-B26](../../feedback/items/PF-20261111-B26.txt). A spike from a shared link probably lands here first.
- [ ] **Query-string variants of covered pages.** Any `?` skips the cache (`/?view=all`, `?page=`, `?sort=`, `?tag=`, `?with=`, and RSS feeds as `?format=rss`). Decide which board views and pages to cache, and whether a canonical route can take the parameter in its path instead.
- [ ] **Trailing-slash aliases** (`/threads`, `/about`, `/threads/<id>/`). Today PHP sends a 301. Apache could send it with a rewrite rule.

## 2. Public read-only pages

These are candidates only if measurement shows traffic there.

- [ ] `/recent/`
- [ ] `/tags/` and `/tags/<tag>`
- [ ] `/profiles/<id>`
- [ ] `/gallery/`
- [ ] `/calendar/` and its month and day pages
- [ ] `/activity/`
- [ ] `/guestbook/`
- [ ] `/records/<id>` and `/source/…` pages
- [ ] `/forte/…` pages
- [ ] `/llms.txt`
- [ ] `/docs/…`. `DocsResponder` already serves a static release from PHP. Check whether Apache could serve it directly.

## 3. Cache gaps on covered pages

- [ ] **The first request after a miss** (a new thread, eviction, a purge, or turning the mode on with no fresh pages). PHP renders it once, or shows the busy page when every renderer is in use. Option: prewarm `/`, `/threads/` and the busiest threads at turn-on.
- [ ] **Pages that are never cached.** These are pages containing `railspike_workflow.js`, and pages whose backup links no longer match the current settings (`HighTrafficCache::currentBackupPolicyPage()`). Check how often each happens.
- [ ] **Withdrawals.** A feature or access change, a removal, or a moderation tag turns the mode off until an operator turns it back on, unless automatic turn-on catches the next spike. Decide whether some of these can re-enable on their own.

## 4. Files and assets

- [ ] **Fingerprinted files outside the allowlist** (`.wasm`, `.map`, `.json`). Add them if a cached page references them, with types set like the others.
- [ ] **Assets with no retained copy yet.** They go to PHP until a cached page that references them renders.
- [ ] **HTML under `/assets/`** (the tour snapshots, `1111.html`, `tour.html`). These are file reads through PHP. Also see the private-instance gate note below.
- [ ] **`/service_worker.js` and `/manifest.webmanifest`.** They go through PHP on purpose (`WebServerRoutingTest`). Confirm the reason still holds before changing them.

## Out of scope: dynamic by design

These need a session, a write or fresh state, so the mode should keep sending them to PHP:
- compose, Tools, messages and inbox, account and key pages
- invites, approvals, staged submissions
- search
- every `/api/` write
- downloads, the instance page, the offline reader
- the high-traffic refresh endpoint

Private instances cannot turn the mode on at all.

## Related findings

- PHP serves `/assets/1111.html` and `/assets/tour.html` without the members-only gate (`FrontController::resolveRawPublicAssetPath()` runs before it). Recorded in [asset_bypass Step 2](../asset_bypass/asset_bypass_step2_feature_description.md#corrections-to-step-1).
- `tests/high_traffic_apache_test.py` has pre-existing failures that need product decisions under the [retraction handoff](../retraction_simplification_handoff.md): import withdrawal, and stale absent-target replies. See the [asset_bypass closeout](../asset_bypass/asset_bypass_step4_implementation_summary.md#closeout).

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

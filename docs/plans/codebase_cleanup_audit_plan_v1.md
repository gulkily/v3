# Codebase Cleanup & Readability Audit — Plan v1

Prep for a demo to a code-review-literate audience. Goal: find dead code, refactor
opportunities, and general cleanup that reduces codebase size and improves
readability, **without changing behavior**. This is a plan for the audit itself —
each phase below produces a decision or a diff, not a rewrite of the architecture.

## Status

- **Phase 0 (inventory):** done. See `codebase_cleanup_audit_findings_v1.md`.
- **Phase 1 (confirmed dead code removal):** done. ~150-250 lines removed
  across 4 commits (dead `renderFragment()`, `CanonicalRecordFamily`, 6 dead
  `Application` methods, collapsed script-array duplication).
- **Phase 2 (`Application.php` decomposition):** in progress.
  `Application.php`: **8,212 → 5,415 lines (~34% smaller)** across 18
  route-group extractions so far:
  - `/about` → `AboutPageController`
  - `/instance`, `/backup`, `/downloads/*` → `InstancePageController`
  - `/tags/*` → `TagsPageController`
  - `/`, `/threads/` (board, HTML + RSS) → `BoardPageController`
  - `/tools/`, `/tools/bookmarklets/`, `/tools/sqlite/`, `/tools/feature-flags/` → `ToolsPageController`
  - `/tools/llm-exchanges/*` → `LlmExchangesController`
  - `/tools/codebase/`, `/api/read_model_status` → `CodebaseStateController`
  - `/profiles/*`, `/user/*`, `/users/*` → `ProfilePageController`
  - `/forte/profiles/*`, `/forte/user/*` → `ForteProfileController`
  - `/forte` (board view) → `ForteBoardController`
  - `/forte/users/` → `ForteUserDirectoryController`
  - `/lobby/`, `/invites/` → `LobbyController`
  - `/source/current/*`, `/source/blob/*`, `/source/commits/*` → `SourceFileController`
  - `/compose/thread`, `/compose/reply`, `/account/key` (GET+POST, the
    "write flows" slice) → `ComposeAndAccountKeyController`
  - `/api/`, `/api/list_index`, `/api/get_thread`, `/api/get_post`,
    `/api/get_profile`, `/api/get_username_claim_cta` (the plain-text `/api`
    endpoints) → `ApiTextController`
  - `/api/apply_thread_tag`, `/api/apply_post_tag` → `TagApiController`
  - `/api/set_feature_flag`, `/tools/feature-flags/` POST → joined the
    existing `ToolsPageController`
  - `/api/link_identity` → joined the existing `ComposeAndAccountKeyController`

  Shared query/support layer built up alongside the route extractions
  (`src/ForumRewrite/ReadModel/`, `src/ForumRewrite/Http/`, and
  `src/ForumRewrite/Canonical/`): `RouteServices`, `ProfileRepository`,
  `ThreadRowSupport`, `AuthoredContentRepository`, `ThreadRepository`,
  `TagGrouping`, `BoardViewOptions`, `RssFeed`, `ToolsPageSupport`,
  `ViewerTagLookup`, `SourcePathValidator`, plus
  `readMetadata()`/`latestRepositoryCommit()`/`repositoryShortCommit()`
  consolidated onto `ReadModelMetadata`.

  The write-flow slice needed `RouteServices` extended first: `writer()`,
  `requestData()` (+ private `mergeRequestBodyData()`), `elapsedMilliseconds()`,
  `mergeResultTimings()`, `timingsWithTotal()`, and `serverTimingHeaders()`
  turned out to be shared 14-39 times each across the *entire* write-API
  surface (not just compose), so they moved onto `RouteServices` as a
  prerequisite step - the same "build the shared layer, then the slice gets
  cheap" pattern used earlier for `ThreadRepository`/`TagGrouping`/
  `BoardViewOptions` ahead of `TagsPageController`. With that in place, the
  three previously-deferred GET routes (`/compose/thread`, `/compose/reply`,
  `/account/key`) and their POST submit handlers came out together in one
  slice, since the thing that made the GET halves not worth extracting alone
  (sharing a render*Page() method with their POST sibling) stopped being a
  problem once both sides had the same new home.

  A recurring side effect worth noting: several extractions revealed
  Application methods that had gone fully dead in an *earlier* slice
  (their last caller already extracted, but the now-unused wrapper wasn't
  noticed until a later pass touched the same area) — each was removed on
  discovery rather than left as unreachable cruft.

  Peeled the plain-text `/api/*` informational endpoints off the deferred
  `/api` group first, since they're pure read-only formatters with no
  auth/session/write-flow coupling - the same "least-coupled subset first"
  approach used throughout this phase, applied within a route group instead
  of just across them. `/api/version` was left out (one line, dispatched
  specially *before* `ensureReadModel()` so a version probe still answers
  with a broken read model - not worth touching). `/api/read_model_status`
  was thematically read-model/codebase-state rather than generic API text,
  so it landed as a second public method (`apiStatus()`) on the existing
  `CodebaseStateController` instead of `ApiTextController` -
  `commitsCapabilityAvailable()` (memoized, 4 other call sites) and
  `taskQueueStatus()` (shares a memoized task-queue-store instance) stay on
  Application and joined `ExecutionLock`/`ReadModelStaleMarker` as bound
  closures on that controller's constructor.

  Checked `/activity` as a candidate next slice via a fresh look at
  `fetchActivity()`'s full body: it's still genuinely entangled -
  `sourceSignatureLink()`, `sourcePathHref()`, `sourceCommitHref()`,
  `activityCommitManifest()`, `sourceSignatureStatus()`,
  `isHiddenBootstrapBoardTagsJson()`, `hasBoardTag()`, and
  `activityItemRelevantFiles()` all get called per-item on top of
  `resolveActivitySort()`/`normalizeActivityView()`, and `fetchActivity()`
  itself is shared with `/forte/activity` and an AJAX pagination endpoint
  (5+ call sites). Confirms the earlier "harder tier" finding still holds -
  this needs a dedicated shared-service investigation of its own before
  extraction, not a quick recheck.

  Pulled `/api/apply_thread_tag` and `/api/apply_post_tag` out of the
  `/api` group's auth/write bulk instead - unlike most of that group, both
  needed nothing beyond what `RouteServices` already had from the
  write-flow slice (`writer()`, `requestData()`, the timing helpers,
  `sendText()`) plus one closure for the viewer-identity lookup, so they
  were cheap once that infrastructure existed. Confirms the write-flow
  slice's `RouteServices` extension is paying off beyond compose/account -
  worth rechecking other `/api` write endpoints for the same shape before
  assuming the whole group is uniformly hard.

  Followed up with `/api/set_feature_flag` (its predicted-cheap next
  candidate) plus its GET-page sibling's POST twin, `/tools/feature-flags/`
  form submit - both are the write side of a page `ToolsPageController`
  already owns, and `viewerCanManageFeatureFlags()` had exactly these two
  callers, so it moved wholesale rather than becoming a closure. The one
  wrinkle: both handlers reset Application's own memoized
  `$this->featureFlags` cache after a successful write
  (`invalidateFeatureFlagsCache()`, a new one-line bound closure) -
  preserved exactly as-is rather than questioned, since changing it would
  be a behavior change outside this pass's scope.

  Followed `/api/set_feature_flag` with `/api/link_identity`: it was even
  cheaper - no viewer-profile lookup at all, so it needed zero closures,
  just `RouteServices`. It's the plain-text API twin of
  `ComposeAndAccountKeyController::submitAccountKey()`'s own
  `writer()->linkIdentity()` call (same write operation, different response
  shape), so it joined that controller rather than starting a new one -
  the same "same write operation, different route" reasoning as
  `/api/read_model_status` landing on `CodebaseStateController`.

  Remaining route groups still fully on `Application`: `/forte/activity/`
  and the `/api/forte_*`/`/api/get_forte_*` AJAX endpoints; `/activity`
  (harder tier, see above); the single-thread view (`/threads/{id}`,
  `/posts/{id}`); `/api/version` (see above); and the rest of `/api`
  (~23 auth/identity/write endpoints - `/api/set_identity_hint`,
  `/api/authenticate_identity`, `/api/create_identity`/`/api/prepare_identity`,
  `/api/analyze_post`, `/api/generate_agent_reply`, `/api/codex_handoff*`,
  `/api/approve_user`, `/api/prepare_approval`/`/api/create_prepared_approval`,
  `/api/prepare_invitation`/`/api/create_prepared_invitation`,
  `/api/prepare_invitation_redemption`, and the direct
  create-thread/create-reply/create-prepared-post trio - some of which may
  turn out as cheap as the ones above once individually checked).

  Checked both `/forte/activity/` and the single-thread view
  (`/threads/{id}`) as candidate next slices: both are in the harder
  tier, same shape as the `/activity` deferral - `renderForteActivity()`
  shares `fetchActivity()`/`resolveActivitySort()`/commits machinery with
  the un-extracted `/activity` route (5+ shared call sites), and
  `renderThread()` touches 13+ collaborators spanning agent-reply,
  Codex handoff, LLM-exchange, and post-analysis subsystems. Neither is
  a clean single-slice extraction the way `/forte`'s board and users
  views were - either needs a dedicated shared-service investigation
  first (the same call made for `/activity` earlier), or accepting
  significantly more bound closures per slice than prior extractions.
- **Phase 3 (test suite readability):** not started.
- **Phase 4 (docs hygiene):** not started.

Every extraction commit is verified against `tests/run.php` (baseline: ~475
pass / 7-8 known pre-existing failures, unrelated to this work) plus direct
end-to-end route checks against the `parity_minimal_v1` fixture. Full detail
and per-slice lessons are in `codebase_cleanup_audit_findings_v1.md`.

## Why this, why now

The codebase works and has a strong planning trail (`docs/plans/`), but a reviewer
skimming the source for the first time will hit two things fast:

1. `src/ForumRewrite/Application.php` is **8,129 lines / 311 methods** in one file —
   routing, controllers, and business logic for the entire app in a single class.
   This is the single biggest readability risk for a code-review audience.
2. A handful of already-flagged dead/legacy code paths exist but were deliberately
   left in place pending a cleanup pass (see `forte_roadmap.md`'s "Known rough
   edges" section, which already calls out `TemplateRenderer::renderFragment()`
   as dead).

## Non-goals

- No framework introduction, no dependency manager migration, no architecture
  rewrite. This project is deliberately framework-free; keep it that way.
- No deletion of `docs/plans/` history — the FDP (assessment → description →
  plan → summary) trail is the project's memory. Reorganizing for demo clarity
  is in scope; deleting it is not.
- No chasing test coverage numbers. Test *readability* is in scope; adding new
  tests for their own sake is not.
- Every change must be verified against `tests/run.php` (the project's custom
  test runner — there's no composer/phpunit) before and after. If a change
  can't be verified, it doesn't ship as part of this pass.

## Phase 0 — Inventory (read-only, no edits)

Build a concrete list before touching anything:

- Grep for known dead-code smells across `src/`, `templates/`, `scripts/`,
  `public/assets/`: `TODO`, `FIXME`, `deprecated`, `legacy`, unused private
  methods (methods defined once, called zero times within the class/file).
- Cross-reference every `templates/partials/*.php` and `templates/pages/*.php`
  against `grep -r` for its filename in `src/` — flag any template no route
  renders.
- Cross-reference every `public/assets/*.js` against `<script src=...>`
  references in `templates/` — flag orphaned scripts (candidate: two OpenPGP
  bundles, `openpgp.min.js` and `openpgp.v5.11.3.min.js`, look like one
  superseded the other — confirm which is actually loaded).
- List every `private function` in `Application.php` with a call count of 0
  within the file (dead private methods can't be called from outside it).
- Note any file with no incoming references at all (orphaned class).

Output of this phase: a checklist of specific, named candidates (file:line),
not vague categories. Nothing gets removed yet — Phase 0 is diagnosis only.

## Phase 1 — Confirmed dead code removal (low risk, high visibility)

Remove items from Phase 0 that are unambiguously unreachable:

- `TemplateRenderer::renderFragment()` — already flagged as caller-removed
  dead code in `forte_roadmap.md`. Confirm zero remaining callers, then delete.
- Any orphaned template partials found in Phase 0.
- Any orphaned JS assets found in Phase 0 (pending the OpenPGP bundle check).
- Legacy fallback shims worth a closer look (not necessarily removal —
  these may still be load-bearing for existing deployments):
  - `LlmProviderConfig.php` / `scripts/write_private_config.php`'s
    `DEDALUS_*` → `LLM_*` fallback translation layer.
  - `CanonicalRecordRepository.php`'s `resolveLegacyPostCreatedAt*()` pair.
  For each: determine whether any real config/data still depends on the old
  path. If yes, leave it and document why (a one-line comment, not removal).
  If no, remove it and note the removal in the implementation summary.

Each removal gets its own small commit; run `tests/run.php` after each.

## Phase 2 — `Application.php` decomposition (the main event)

This is the highest-value, highest-risk phase — sequence it carefully and do
it incrementally, verifying tests after every extraction.

1. Map the file's route table (dispatcher/switch structure) to identify
   natural boundaries — e.g. board/thread routes, profile routes, agent-reply
   routes, admin/tools routes, auth routes.
2. Extract one cohesive route group at a time into its own controller-style
   class under `src/ForumRewrite/` (mirroring the existing namespace
   conventions already used for `Agent/`, `Analysis/`, `Canonical/`, etc.),
   leaving `Application.php` as a thin dispatcher that delegates to them.
3. Order extractions from most isolated/least-coupled route group first, so
   early wins build confidence before tackling anything entangled with
   session/auth state.
4. After each extraction: run the full test suite, and spot-check the
   affected routes manually (this app has no framework-level test client, so
   rely on `tests/WebServerRoutingTest.php` and `tests/LocalAppSmokeTest.php`
   plus manual route hits).
5. Stop condition: `Application.php` should read as a route table plus thin
   delegation, not business logic. Perfect decomposition isn't the goal —
   readability for a reviewer skimming it top-to-bottom is.

This phase is large enough to warrant its own FDP-style sub-plan once Phase 0
gives real numbers on route-group boundaries — don't scope the extraction
order until the inventory is in hand.

## Phase 3 — Test suite readability

Several test files are large enough to work against their own purpose:

- `tests/BrowserSigningNormalizationTest.php` — 6,876 lines
- `tests/WriteApiSmokeTest.php` — 4,002 lines
- `tests/LocalAppSmokeTest.php` — 3,097 lines

For each: check whether the size is genuine breadth (many distinct scenarios,
fine as-is) or repetition (near-identical setup/assertion blocks that could
collapse into a data-driven loop or shared helper). Only refactor the latter
case — don't restructure tests just to hit a smaller line count if each test
is actually testing something distinct. A demo audience will read test
*names* and structure more than line count; prioritize that.

## Phase 4 — Docs hygiene (presentation, not deletion)

`docs/plans/` has ~377 files, mostly completed 4-step FDP cycles
(`*_step1_solution_assessment.md` through `*_step4_implementation_summary.md`).
This is valuable history but will read as clutter to a reviewer browsing the
repo root for the first time.

- Do not delete or rewrite any of it.
- Propose (as a separate, explicit decision — not bundled into this cleanup)
  moving fully-completed FDP cycles into `docs/plans/archive/`, keeping only
  active/open plans at the top level. This is an organizational change, not
  a content change, and should be its own commit so it's easy to review and
  easy to revert if the team prefers the flat history.
- Leave `todo.txt`, `README.md`, `BLESSING.md`, `sfenc.md` as-is unless the
  audit turns up something factually stale in them.

## Sequencing & verification

1. Phase 0 (inventory) → produce a findings list, share before acting.
2. Phase 1 (confirmed dead code) → small, independent commits, test after each.
3. Phase 2 (`Application.php` decomposition) → the bulk of the effort; do this
   as its own follow-up plan once Phase 0/1 land, sized in route-group slices.
4. Phase 3 (test readability) → after Phase 2, since extraction may naturally
   reorganize which tests cover which class.
5. Phase 4 (docs hygiene) → independent of the code phases, can happen anytime.

All work happens on a branch per existing project convention (no direct merges
to `main`); each phase/slice gets its own PR so the readability improvement is
itself easy to review — fitting, given the audience.

## Deliverables

- A short findings doc from Phase 0 (dead code inventory, file:line specific).
- One or more PRs for Phase 1 (dead code removal), each with before/after line
  counts.
- A dedicated FDP-style plan for the `Application.php` decomposition (Phase 2),
  written once Phase 0 gives real route-group boundaries.
- A short summary at the end: total lines removed, files removed, and the
  `Application.php` line-count before/after — concrete numbers for the demo.

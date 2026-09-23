# Activity/Commit-Manifest Subsystem Extraction — Plan v1

Sub-plan for the last hard piece of `docs/plans/codebase_cleanup_audit_plan_v1.md`
Phase 2 (`Application.php` decomposition). Written after the easy `/api`/`/forte_*`
route groups were exhausted, per the earlier open question: "For `/activity`,
should we make a separate plan with a checklist so we don't get lost?" — yes,
once there were no more easy wins to do first.

## Status

- **Step 1 (data-layer extraction): done.** `ActivityService`
  (`src/ForumRewrite/Activity/ActivityService.php`) now owns `fetchActivity()`
  and its full collaborator graph. `Application.php` keeps thin delegating
  wrappers (same names/signatures) for every method still called from
  elsewhere in the class, so no existing call site — including the
  first-class-callable closure `InstancePageController` already held on
  `fetchActivity(...)` — needed to change.
- **Step 2 (page-shell route extraction): in progress.**
  `/api/forte_commit_detail` → `ForteActivityController` done (the
  smallest of the four, per the recommended order below). `/forte/activity/`
  and `/api/forte_activity_page` next; classic `/activity` last.

## Why this needed its own plan

`fetchActivity()` was checked three separate times earlier in Phase 2 and
deferred each time as "harder tier" — every quick look found it entangled
with 8-13+ collaborators and shared with `/forte/activity`, single-thread
view, and two AJAX endpoints. That's real, but it conflated two different
questions that turned out to have different answers:

1. **Can the data-fetching layer become a standalone class?** Yes, cleanly —
   see the investigation below.
2. **Can the page-shell route handlers (`renderActivity()` etc.) become thin
   controllers?** Not yet, or not fully — they pull in genuinely unrelated
   subsystems (LLM-exchange inspection, Codex handoff, agent-reply gating)
   on top of activity data itself, especially the classic `/activity` page.

Treating both as one lump is what made every earlier look conclude "defer."
Splitting them let step 1 happen safely without waiting on step 2.

## Investigation summary (data layer)

Full method-by-method dependency graph (every `fetchActivity()` collaborator,
its `$this->` deps, and its call sites) was mapped before writing any code.
Key findings:

- The **only** non-`pdo()` Application state the entire data-fetching cluster
  ever touches is `$this->repositoryRoot` and `$this->databasePath` — both
  already carried by `RouteServices` (added for the write-flow slice's
  `writer()`). Nothing in the cluster touches `$this->projectRoot`,
  `$this->routeSource`, `$this->staticHtmlRoot`, `$this->artifactRoot`, or
  `$this->featureFlags()`.
- Three request-lifetime memoization caches live on the cluster
  (`activityCommitManifestCache`, `sourceCommitFileManifestCache`, the lazily
  -opened `SqliteActivityCommitManifestCache` singleton). Since `Application`
  itself lives for exactly one route dispatch per request, these caches'
  real lifetime was always "one route handler's execution," which
  `RouteServices::activityService()`'s own per-request memoization
  reproduces exactly (mirrors how `RouteServices` itself is memoized by
  `Application::routeServices()`).
- `sourcePathHref()`/`sourceCommitHref()`/`sourceSignatureLink()`/
  `sourceSignatureStatus()` are shared with the single-thread view
  (`withPostSourceMetadata()`/`withAuthorPublicKeyMetadata()`, still on
  `Application` since `renderThread()` itself remains deferred) — confirming
  real cross-subsystem sharing beyond what earlier passes had already noted
  (agent-reply/Codex/LLM-exchange/post-analysis). These four stayed public
  on `ActivityService`, with `Application`'s wrappers delegating so
  `withPostSourceMetadata()` needed no changes at all.
- This was **not** the "9-10 closures for core infrastructure" shape that
  made `/api/analyze_post` a genuine defer. Almost the entire cluster only
  needs `(pdo, repositoryRoot, databasePath)` — exactly what `RouteServices`
  already has. The closures would only pile up in the page-shell layer
  (`renderActivity()` specifically), not the data layer.

## What was extracted

`ActivityService` (`ForumRewrite\Activity` namespace, alongside the existing
`SqliteActivityCommitManifestCache`) now owns: `fetchActivity`,
`countActivityViewTotal`, `resolveActivitySort`, `activitySortValueFromItem`,
`normalizeActivityView`, `fetchCommits`, `countCommitsTotal`,
`resolveCommitSort`, `commitSortValueFromItem`, `sourcePathHref`,
`sourceCommitHref`, `sourceSignatureLink`, `sourceSignatureStatus`,
`activityCommitManifest`, `sourceCommitFiles`, plus 17 private
implementation-detail helpers that had no other callers
(`activityViewSql`, `activitySortSql`, `hasBoardTag`,
`isHiddenBootstrapBoardTagsJson`, `commitSortSql`,
`canonicalPostAuthorIdentityId`, `sourceSignaturePathCandidates`,
`activityCommitManifestCacheStore`, `activityItemRelevantFiles`,
`standaloneRelevantFile`, `standaloneSignatureRelevantFile`,
`sourceCommitFileRole`, `sourceCommitFileHref`,
`activityCommitSignatureMetadata`, `sourceSignatureRecordPath`,
`signatureSignerIdentityId`, `openPgpFingerprintFromIdentityId`).

Left on `Application` unchanged: the validator wrappers
(`isValidCanonicalSourcePath` etc. — pure `SourcePathValidator` delegates,
shared with the single-thread view), `encodeSourcePathForUrl` (6 call sites
beyond the cluster), `currentSourcePathExists` (shared with
`withAuthorPublicKeyMetadata()`), and the page-specific link builders
`activityItemBoardLink`/`activitySortHeaderLinks` (single-page, presentation
-only, not data-fetching).

One bug found and fixed during verification: `RouteServices::activityService()`
initially built `ActivityService` with an eagerly-opened `PDO` (`$this->pdo()`
called at construction). That broke
`LocalAppSmokeTest::testActivityCommitManifestLinksSignatureSignerKeyOutsideCommit`,
which reflects directly into `activityCommitManifest()` without going through
`handle()`/`ensureReadModel()` — so the main read-model database file never
gets created, and `activityCommitManifest()` never actually needed a main-db
connection anyway (it's git-exec plus a separate cache file). Fixed by making
`ActivityService`'s own `pdo()` lazy too (a `\Closure` factory, memoized on
first real use), matching `RouteServices::pdo()`'s own laziness exactly.

## What's left (Step 2, not started)

The page-shell route handlers stay on `Application` for now, now backed by
the clean service instead of tangled internals:

- `renderActivity()` (classic `/activity` + implicitly `/activity.rss` via
  `renderActivityRss()`) — the most entangled: also touches `fetchThread`,
  `fetchThreadPosts`, `displayThreadTitle`, `viewerCanInspectLlmExchanges`,
  `llmExchangeStore`, `featureFlags`, `invalidateFeatureFlagsCache`,
  `sourceCommitDetails`, `resolveViewerProfileFromIdentityHint`.
- `renderForteActivity()` (`/forte/activity/`) — comparatively clean, mostly
  cluster-internal plus `commitsCapabilityAvailable`/`enqueueReadModelRecovery`
  (already used as closures elsewhere).
- `handleForteActivityPage()` (`/api/forte_activity_page`) — same shape as
  `renderForteActivity`, its AJAX-pagination twin.
- `handleForteCommitDetail()` (`/api/forte_commit_detail`) — small,
  `activityCommitManifest`/`sourceCommitHref` plus the same
  capability-gating closures.

Recommended order when this is picked back up: `handleForteCommitDetail`
first (smallest), then `renderForteActivity`/`handleForteActivityPage`
together (they share the same closure list), leaving `renderActivity`
(the classic page) for last since it's the one that actually needs the long
closure list — by which point it may be the *only* remaining reason
`viewerCanInspectLlmExchanges`/`llmExchangeStore`/etc. need to leave
`Application` at all, worth rechecking before assuming closures are still
the right call at that point.

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
- **Step 2 (page-shell route extraction): done.** All four page-shell
  route handlers extracted: `/api/forte_commit_detail`, `/forte/activity/`,
  `/api/forte_activity_page` → `ForteActivityController`; classic
  `/activity`/`/activity.rss` → `ActivityPageController`. The activity
  subsystem extraction is now complete end to end (data layer + all
  route handlers). **Lesson applied along the way: grep `tests/` for
  reflection references to a method's exact name before deleting it** -
  `ForteActivityReadModelRecoveryTest` reflected directly into
  `renderForteActivity()`/`handleForteActivityPage()` and needed a real
  fix (reflect into the new controller instead); a later sweep of the
  remaining `Application` delegating wrappers also confirmed
  `activityCommitManifest()` must stay (test-reflected) while 9 others
  were genuinely dead and got removed.

  `renderActivity()`'s predicted dependency list (below) turned out
  stale on re-read - it never actually touched `fetchThread`,
  `viewerCanInspectLlmExchanges`, `llmExchangeStore`, or
  `sourceCommitDetails` (those belonged to unrelated nearby methods,
  an artifact of the original investigation's proximity-based scan).
  It was the cheapest of the four page-shell handlers, needing zero
  closures - the opposite of the original prediction.

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
beyond the cluster), and `currentSourcePathExists` (shared with
`withAuthorPublicKeyMetadata()`). `activityItemBoardLink`/
`activitySortHeaderLinks` (single-page, presentation-only link builders,
not data-fetching) moved wholesale into `ForteActivityController` in step
2 instead, since both their callers ended up there.

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

## What's left (Step 2, in progress)

Three of the four page-shell route handlers are done
(`ForteActivityController::commitDetail`/`board`/`paginationPage`). What
remains on `Application`:

- `renderActivity()` (classic `/activity` + implicitly `/activity.rss` via
  `renderActivityRss()`) — the most entangled: also touches `fetchThread`,
  `fetchThreadPosts`, `displayThreadTitle`, `viewerCanInspectLlmExchanges`,
  `llmExchangeStore`, `featureFlags`, `invalidateFeatureFlagsCache`,
  `sourceCommitDetails`, `resolveViewerProfileFromIdentityHint`.

By the time this is picked back up, `renderActivity()` may be the *only*
remaining reason `viewerCanInspectLlmExchanges`/`llmExchangeStore`/etc.
need to leave `Application` at all — worth rechecking whether that's still
true, and whether those specific methods are cheap enough to just move
wholesale (single-caller by then) rather than staying closures, before
extracting this last piece. **Grep `tests/` for reflection references to
`renderActivity`/`renderActivityRss` by exact name before deleting them** -
see the lesson above from this step's own `ForteActivityReadModelRecoveryTest`
break.

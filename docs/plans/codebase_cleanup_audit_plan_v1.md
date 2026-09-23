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
  `Application.php`: **8,212 → 2,313 lines (~72% smaller)** across 27
  route-group extractions so far, plus the activity/commit-manifest and
  post-analysis/agent-reply/Codex-handoff data-layer extractions (see
  below):
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
  - `/api/set_identity_hint`, `/api/clear_identity` → `IdentityHintController`
  - `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status`
    → `AuthApiController`
  - `/api/create_thread`, `/api/create_reply`, `/api/prepare_thread`,
    `/api/prepare_reply`, `/api/create_prepared_post`,
    `/api/prepare_identity`, `/api/create_identity` →
    `WritePostAndIdentityApiController`
  - `/api/approve_user`, `/api/prepare_approval`,
    `/api/create_prepared_approval`, `/api/prepare_invitation`,
    `/api/create_prepared_invitation`, `/api/prepare_invitation_redemption`
    → `IdentityApprovalAndInvitationApiController`
  - `/api/get_forte_content_summary`, `/api/forte_user_detail` →
    `ForteContentAndUserDetailApiController`
  - `/api/forte_commit_detail`, `/forte/activity/`,
    `/api/forte_activity_page` → `ForteActivityController`
  - `/activity`, `/activity.rss` → `ActivityPageController`
  - `/api/analyze_post`, `/api/generate_agent_reply`, `/api/codex_handoff`,
    `/api/codex_handoff_approval` → `PostWorkflowApiController`

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

  Checked `/api/analyze_post` next (explicitly requested) and found it's
  actually harder tier, not easy: it touches 11 Application methods never
  extracted anywhere (`postAnalysisContext()`, `postAnalysisService()`,
  `postAnalysisResponse()`, `agentRepliesEnabled()`,
  `agentReplyGateFailure()`, `agentReplyStatusResponse()` - 10 other call
  sites, `agentReplyResultForPost()`, `agentReplySummaryForAnalysisResponse()`,
  `noStoreHeaders()`/`noStoreTimingHeaders()`, `timingMetricsFrom()`) plus
  `sendJson()` (69 call sites). Same shape as the `/activity`/single-thread
  harder tier, just the agent-reply/post-analysis subsystem instead of the
  commit-manifest one - deferred rather than force through with 9-10
  closures, most of which would be for genuinely core, heavily-shared
  Application infrastructure, not route-specific logic.

  Did `/api/set_identity_hint` and `/api/clear_identity` instead: pure
  cookie/session bookkeeping, no PDO, no `writer()`, no viewer-profile
  lookup at all - the cheapest slice yet, needing only
  `sendText()`/`noStoreHeaders()`. `noStoreHeaders()` (16 other call sites)
  joined `RouteServices` the same way the write-flow slice's timing helpers
  did, rather than becoming a closure.

  Followed with the challenge/signature auth trio -
  `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status` -
  into a new `AuthApiController`. `fetchProfileByIdentityId()` turned out
  to be a one-line wrapper around `ProfileRepository::byIdentityId(pdo())`,
  so the controller calls that repository directly instead of taking a
  closure for it; only `authenticatedViewerProfile()` (8 other call sites)
  stayed a bound closure, and `OpenPgpSignatureVerifier` is instantiated
  directly since it's already a standalone `Security`-namespace class with
  no Application coupling.

  Followed immediately with the direct write/prepare API group -
  `/api/create_thread`, `/api/create_reply`, `/api/prepare_thread`,
  `/api/prepare_reply`, `/api/create_prepared_post`,
  `/api/prepare_identity`, `/api/create_identity` - into a new
  `WritePostAndIdentityApiController`, the biggest single slice so far
  (215 lines removed from `Application.php`). All seven handlers were
  already pure `requestData() -> writer()->x() -> mergeResultTimings() ->
  send*()` sequences with every dependency already on `RouteServices` from
  the write-flow slice, *except* `sendJson()` (69 call sites) - the same
  blocker that made `/api/analyze_post` harder tier. Since `sendJson()` is
  a pure response sender with no Application-state coupling (identical
  shape to `sendText()`/`sendHtml()`/`sendXml()`, already on
  `RouteServices`), it moved there too rather than becoming a closure -
  unblocking this whole slice in one move, and chipping away at what made
  `/api/analyze_post` hard (10 of its 11 blockers remain, but the
  `sendJson()` one is now gone project-wide). This slice needed zero
  closures.

  Finished off the identity/approval/invitation write group -
  `/api/approve_user`, `/api/prepare_approval`,
  `/api/create_prepared_approval`, `/api/prepare_invitation`,
  `/api/create_prepared_invitation`, `/api/prepare_invitation_redemption`
  - into `IdentityApprovalAndInvitationApiController`, same shape again
  (everything already on `RouteServices`). `prepareUserApprovalBySlug()`
  had exactly one caller so it moved wholesale rather than becoming a
  closure; it needed `fetchProfileBySlug()` (4 other call sites) and
  `resolveViewerProfileFromIdentityHint()` (14 other call sites) as
  closures, plus `authenticatedViewerProfile()` (8 other call sites) for
  `prepareInvitation()`. `prepareInvitationRedemption()`'s
  `fetchProfileByIdentityId()` call became a direct
  `ProfileRepository::byIdentityId(pdo())` call, same as the
  `AuthApiController` slice. This closes out the entire "rest of `/api`"
  group named below in earlier notes - every plain write/prepare/auth `/api`
  endpoint identified at the start of this phase is now off `Application`.

  While verifying this slice, `WriteApiSmokeTest::testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`
  failed in the full suite run. Isolated reruns (3x on the new code, 3x on
  the pre-refactor commit) showed it fails ~1-in-3 either way - a
  pre-existing flake in `/activity` incremental-vs-full-rebuild HTML
  comparison, unrelated to this slice's approval-endpoint changes. Not
  counted against the baseline.

  Checked the four `/api/forte_*`/`/api/get_forte_*` AJAX endpoints next.
  Two split off cleanly into a new `ForteContentAndUserDetailApiController`:
  `/api/get_forte_content_summary` (pure `fetchPost()` + a `reply_count`
  query) and `/api/forte_user_detail` (profile/thread/post aggregation
  mirroring `ForteProfileController::username()`, no harder-tier deps).
  `fetchVisibleAuthoredThreads()`/`fetchVisibleAuthoredPosts()`/
  `fetchProfilesByUsernameToken()` were one-line repository wrappers with
  exactly one caller each, so the new controller calls
  `AuthoredContentRepository`/`ProfileRepository` directly instead of
  taking closures - same pattern as `AuthApiController`. Only `fetchPost()`
  stayed a closure. Also added `renderFragment()` to `RouteServices` as a
  passthrough (mirroring `renderPageTemplate()`/`renderStandalonePage()`
  already there) since both handlers needed it.

  The other two - `/api/forte_activity_page`, `/api/forte_commit_detail` -
  stay on `Application`: both are directly coupled to `fetchActivity()`/
  `activityCommitManifest()`, the same harder-tier activity/commit-manifest
  subsystem already deferred for `/activity` itself. Confirms that group
  splits along exactly the same "commit-manifest vs. everything else" line
  as the rest of the activity subsystem, not along the `/api/forte_*` vs.
  `/api/get_forte_*` naming.

  With the easy `/api`/`/forte_*` route groups exhausted, swept
  `Application.php` for methods orphaned by earlier extractions (the
  "wrapper not noticed until a later pass" pattern already seen a few
  times this phase) - found and removed 11 confirmed-dead private
  methods, all thin wrappers around already-static repository/support
  calls whose real (and only) callers had been extracted in prior slices:
  `fetchProfilesByUsernameToken`, `fetchVisibleAuthoredThreads`,
  `fetchVisibleAuthoredPosts`, `hydrateThreadRows`, `normalizeBoardView`,
  `normalizeBoardSort`, `boardViewOptions`, `boardSortOptions`,
  `activeBoardOptionLabel`, `repositoryShortCommit`,
  `hasPendingUserDirectoryProfiles`. Left `mergeResultTimings()` alone
  despite zero remaining production callers - `tests/ApplicationServerTimingTest.php`
  reflects into it directly, and retargeting that test to
  `RouteServices::mergeResultTimings()` is a test-file change, out of
  scope for this pass (candidate for Phase 3).

  (Note: at this point in the phase, `/forte/activity/`,
  `/api/forte_activity_page`, `/api/forte_commit_detail`, and `/activity`
  were still deferred as harder tier - all four have since been
  extracted, see further down. What's genuinely still deferred as of the
  latest entry at the top of this section: the single-thread view
  (`/threads/{id}`, `/posts/{id}`), the agent-reply/post-analysis
  subsystem, and `/api/version`.)

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

  Did that dedicated shared-service investigation for the activity/
  commit-manifest subsystem (the last big deferred piece) once the easy
  `/api`/`/forte_*` route groups ran out - see
  `activity_subsystem_extraction_plan_v1.md` for the full dependency-graph
  writeup. Finding: the *data-fetching* layer (`fetchActivity()` and ~30
  collaborators) is almost entirely pure, needing only
  `(pdo, repositoryRoot, databasePath)` - already exactly what
  `RouteServices` carries - unlike the page-shell route handlers
  (`renderActivity()` etc.), which really do pull in unrelated subsystems.
  Extracted the data layer wholesale into a new `ActivityService`
  (`src/ForumRewrite/Activity/`), with `Application` keeping thin
  delegating wrappers (same names/signatures) for every method still
  called elsewhere in the class - including the `fetchActivity(...)`
  closure `InstancePageController` already held, which needed zero
  changes as a result. 17 now-fully-unused private helpers were deleted
  outright rather than wrapped. One real bug found during verification:
  `RouteServices::activityService()` initially opened its `PDO` eagerly
  at construction, which broke a test that reflects directly into
  `activityCommitManifest()` without going through `ensureReadModel()`
  (that method never actually needs the main read-model connection - git
  exec plus a separate cache file) - fixed by making `ActivityService`'s
  own `pdo()` lazy too, matching `RouteServices::pdo()`'s own laziness.
  Full test suite (487 total) and `BrowserSigningNormalizationTest` (62/62)
  and `LocalAppSmokeTest` (88/93, 5 pre-existing baseline failures) run
  clean. The page-shell route handlers themselves
  (`renderActivity`/`renderActivityRss`/`renderForteActivity`/
  `handleForteActivityPage`/`handleForteCommitDetail`) are step 2, not yet
  done - see the sub-plan doc for the recommended order.

  Started step 2 with `/api/forte_commit_detail` (the smallest of the
  four page-shell handlers per the sub-plan's recommended order), into a
  new `ForteActivityController`. Needed `commitsCapabilityAvailable()`
  (4 other call sites), `enqueueReadModelRecovery()` (3), and
  `sendReadModelCapabilityUnavailable()` (2) as closures - all three
  already-established shared closures from earlier slices - plus direct
  calls into `RouteServices::activityService()` for the data itself.
  `/forte/activity/` and `/api/forte_activity_page` are next (they share
  this same closure list); classic `/activity` last.

  Followed immediately with `/forte/activity/` and
  `/api/forte_activity_page` together into the same
  `ForteActivityController` (as `board()`/`paginationPage()`) - they
  needed zero *new* closures beyond the three `handleForteCommitDetail`
  already established, since everything else routes through
  `RouteServices::activityService()` directly or moved wholesale as pure
  page-specific helpers (`activityItemBoardLink()`,
  `activitySortHeaderLinks()` - both single-page link builders, not data
  -fetching, per the sub-plan's original assessment). This was the
  biggest single-commit line reduction of the whole phase (3,875 → 3,501).

  One test broke and needed a real fix (not deferred like
  `mergeResultTimings()`):
  `ForteActivityReadModelRecoveryTest::testForteActivityDegradesAndQueuesOneRebuildWhenCommitsAreMissing`
  reflected directly into `Application::renderForteActivity()`/
  `handleForteActivityPage()` by name. Unlike the `ActivityService` slice
  (where moved methods kept many other internal callers, so delegating
  wrappers were the right call), these two had *only* `handle()`'s
  dispatch as a production caller - a genuine full route-handler move,
  the same shape as the other 24 extractions - so the fix was updating
  the test to reflect into the new controller instead of resurrecting
  dead methods on `Application`. Lesson for the remaining slice
  (`renderActivity`/`renderActivityRss`): grep `tests/` for reflection
  references to a method's exact name before deleting it, not just
  production call sites - this is the first slice where a test reflected
  into a route-handler method directly rather than exercising it via the
  HTTP route.

  Finished the activity subsystem with classic `/activity` +
  `/activity.rss` (`renderActivity()`/`renderActivityRss()`) into a new
  `ActivityPageController` - the last remaining piece. The earlier
  dependency-graph investigation had predicted this would be the *most*
  entangled of the four page-shell handlers (touching `fetchThread`,
  `viewerCanInspectLlmExchanges`, `llmExchangeStore`,
  `sourceCommitDetails`, etc.); re-reading the actual current code found
  that prediction stale - it only ever needed
  `normalizeActivityView()`/`fetchActivity()` (already on
  `ActivityService`) and `renderPageTemplate()`/`sendXml()` (already on
  `RouteServices`) plus the standalone `RssFeed` static class. Needed
  zero closures - the cheapest of the four, not the most expensive.
  (`sourceCommitDetails` turned out to belong to `SourceFileController`'s
  constructor closure, not `renderActivity()` - an adjacent-code mixup in
  the earlier investigation, not a real dependency.)

  Applying the tests-first-grep lesson from the previous slice caught
  nothing this time (`renderActivity`/`renderActivityRss` had no
  reflection references), but the same pass, done systematically across
  *all* the activity-cluster delegating wrappers left on `Application`
  after all four page-shell handlers moved, found and removed 9 more
  now-genuinely-dead wrappers (`normalizeActivityView`,
  `countActivityViewTotal`, `resolveActivitySort`,
  `activitySortValueFromItem`, `fetchCommits`, `countCommitsTotal`,
  `resolveCommitSort`, `commitSortValueFromItem`, `sourceCommitFiles`) -
  while confirming `activityCommitManifest()` must stay (a test reflects
  into it directly) and `fetchActivity()` must stay (the
  `InstancePageController` closure).

  **This completes the entire activity/commit-manifest subsystem
  extraction** (both the data layer and all four page-shell route
  handlers) - see `activity_subsystem_extraction_plan_v1.md` for the full
  writeup. What remains deferred in Phase 2: the single-thread view
  (`/threads/{id}`, `/posts/{id}`), the agent-reply/post-analysis
  subsystem, and `/api/version` (intentionally left alone, see above).

  Did the same dedicated dependency-graph investigation for the
  agent-reply/post-analysis/Codex-handoff subsystem next (the same
  treatment activity got). Found the identical shape: a large pure
  data/orchestration layer plus a small config-touching core
  (`postAnalysisService()`, `agentIdentityService()`,
  `agentReplyFulfillmentService()`, `agentRepliesEnabled()`, etc.) whose
  every real dependency - `pdo`, `projectRoot`, `repositoryRoot`,
  `databasePath`, `artifactRoot`, `staticHtmlRoot`, `featureFlags()`,
  `writer()` - was already on `RouteServices`. Extracted the whole
  cluster (~35 methods) wholesale into a new `PostWorkflowService`
  (`ForumRewrite\Agent` namespace, alongside `AgentIdentityService`/
  `AgentReplyFulfillmentService` already there) plus a new
  `PostWorkflowApiController` for the four route handlers
  (`/api/analyze_post`, `/api/generate_agent_reply`, `/api/codex_handoff`,
  `/api/codex_handoff_approval`). Applied the lazy-PDO lesson from the
  `ActivityService` slice from the start this time (a `\Closure` factory,
  memoized on first real use) rather than rediscovering the same bug.

  `viewerCanUseCodexHandoff()`, `fetchPostAnalysesForPosts()`,
  `fetchAgentReplyGenerationsForPosts()`, `fetchCodexHandoffsForPosts()`,
  `codexHandoffEligiblePostIds()`, and `agentReplyWorkByPostId()` stayed
  as thin delegating wrappers on `Application` - the single-thread view
  (`renderThread()`/`renderPost()`, still deferred, a separate slice)
  calls each of these directly. `llmExchangeRecorder()`/
  `llmExchangeStore()`/`viewerCanInspectLlmExchanges()` were deliberately
  **not** moved - both stayed on `Application` since `llmExchangeStore()`
  already had an external consumer (`LlmExchangesController`'s closure)
  beyond this cluster; `PostWorkflowApiController`/`PostWorkflowService`
  take `llmExchangeRecorder` as a closure instead.

  This was the largest and most error-prone slice of the whole phase
  (a ~35-method, multi-namespace cluster spanning post-analysis, agent
  identity, agent-reply fulfillment, and Codex handoff), and two real
  mistakes surfaced during verification, both caught by the full test
  suite rather than by upfront analysis:
  1. Deleting the `findCodexHandoffFromInput()`-through-`postAnalysisResponse()`
     block accidentally caught `llmExchangeRecorder()`/`llmExchangeStore()`
     in the same sed range, despite the plan being to keep them on
     `Application` - restored both verbatim once ~35 tests failed with
     "Call to undefined method".
  2. `fetchPostAnalysesForPosts()`/`fetchAgentReplyGenerationsForPosts()`/
     `fetchCodexHandoffsForPosts()`/`agentReplyWorkByPostId()`/
     `viewerCanUseCodexHandoff()`/`codexHandoffEligiblePostIds()` were
     deleted-and-moved before being converted into the delegating wrappers
     they needed to be (the single-thread view still calls them) - their
     old bodies referenced methods that had just been deleted. Fixed by
     converting each into a one-line wrapper calling the new service.
  3. A public method, `Application::fulfillAgentReplyRequest()` - called
     externally by `scripts/run_agent_reply_requests.php`, a CLI script,
     not by any route or test - called `agentReplyFulfillmentService()`
     directly. This was invisible to every `grep '$this->METHOD('`
     call-count check used throughout this phase, since those only search
     *within* `Application.php`; a CLI script calling a *public* method is
     external and easy to miss. Caught by
     `WriteApiSmokeTest::testAgentReplyRequestCommandProcessesQueuedRequestOnce`,
     which actually shells out to the script. Fixed by adding a
     `fulfillAgentReplyRequest()` passthrough on `PostWorkflowService` and
     retargeting Application's public method to it. **Lesson for any
     future slice with public methods:** grep `scripts/` (and any other
     directory that constructs `Application` directly) for calls to
     public methods, not just `tests/` for reflection into private ones -
     the two are different blind spots.

  All three were caught and fixed before committing - full suite (487),
  `WriteApiSmokeTest` (103/103), `LocalAppSmokeTest` (88/93, 5 pre-existing
  baseline failures), `BrowserSigningNormalizationTest` (62/62),
  `ApplicationServerTimingTest` (3/3, confirms `noStoreTimingHeaders()`/
  `mergeResultTimings()` reflection still works) all run clean.

  `Application.php`: 3,380 → 2,313 lines - the single biggest line-count
  drop of the entire phase.
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

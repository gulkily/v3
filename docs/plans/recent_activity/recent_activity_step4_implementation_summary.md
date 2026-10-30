> **Feature plan:** [Step 1](./recent_activity_step1_solution_assessment.md) · [Step 2](./recent_activity_step2_feature_description.md) · [Step 3](./recent_activity_step3_development_plan.md) · [Step 4](./recent_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/recent_activity; [ledger](../effort_estimation_trial.md#cycle-v3recent_activity)
Extended estimates: enabled
**Cycle estimate: 0.85**

Entry: 2026-10-30, Approved Step 3 received; planning commit de4ba1433 on feature/recent-activity. Scope v1/A, original baseline 3a21fab67708c07ef77712790218e12a92c61bcd, R1/P2, single exposed Codex rater. Step 3 projection 0.85 reaffirmed; earlier 0.75 and inherited 0.5 preserved. Original S1–S7 stage work: 0.10/0.18/0.15/0.10/0.15/0.15/0.05, non-additive. Shared acceptance belongs to S6. Measured labor unknown.

Request: Delete mockups rather than save them in repository. Disposition: Entire untracked mockup directory deleted before planning commit; links removed. No mockups committed. Product implementation authorized, deployment not included.

## Stage 1 - Public-contribution contract

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Reusable fixture for six recent contributions, agent/unsigned posts, hidden/retracted/bootstrap/expansion exclusions, absent/hidden root context, title/image-only entries. Existing readers allow visible replies independently of root visibility; Recent will retain those replies with neutral context, never hidden root text.
- Verification: PHP syntax and fixture insertion into actual in-memory schema passed (16 posts). Inspected ThreadRepository post visibility, ThreadRowSupport identity tag, profile-history expansion exclusion and shared attribution rendering.
- Notes: No approval/count restriction on feed membership. No changed deletion policy. No dependency changes; runtime route acceptance belongs to subsequent stages. Estimates unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Bounded read and startup compatibility

**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: RecentPostRepository selects 50 rows maximum, bounded subject/body/context projections and date/post-ID positions; shared pagination tokens will wrap these boundaries. Schema 39 adds a partial ordering index; schema specification updated. S1 commit 171f43a31.
- Verification: RecentPostTest and ReadModelSchemaTest 6/6, covering exclusions/unsigned membership, safe context, ties, forward/back, deleted anchor, arrival, invalid/empty position and prior-version startup rebuilding missing index. Actual isolated CLI rebuild passed. In-memory 1k/10k/40k large-body probe returned 50 rows and 51,200 body bytes at every size; ordering query uses posts_recent_idx. Exact timing is local evidence, not production latency.
- Notes: Initial schema inventory assertion failed until the expected index was added, then passed. Runtime page startup check will extend the API-startup check in Stage 3. No new dependencies; estimates unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 3 - Readable route and recovery

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: /recent/ controller, compact entry/page, theme-variable CSS and Application wiring. Reuses contentMeta, ThreadTitle, exact-post URLs and PageNavigation; no copied full-card controls. S2 commit 0b10168d5.
- Verification: RecentPostTest 5/5 including normal route startup from version 38, safe previews/escaping, image fallback, unsigned labels, invalid/array positions and empty state. First test run caught a missing sendHtml status argument; corrected and rerun passed.
- Notes: Navigation/static acceptance follows. No dependency changes; estimates unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 4 - Navigation and themes

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Recent in canonical forum/app main navigation; existing destinations retained. Shared icon mapping covers Recent links/tool buttons in Word97/Chicago/Midnight. Application route classification supports normal session/access recovery. S3 commit 93539d722.
- Verification: Navigation/auth/Recent PHP checks 16/16; Chromium actual front-controller flow passes 13 themes at 390/1280px, long content without overflow, icon background checks and native no-JavaScript keyboard paging. Phone screenshots for three icon themes visually reviewed; screenshots kept only in /tmp, not repository.
- Notes: Initial browser fixture omitted the templates symlink and failed; corrected fixture, complete rerun passed. No production/iPhone-device claim. Zero dependencies; estimates unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 5 - Publication and invalidation

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Recent first-page static artifact, targeted builds and front-controller lookup; invalidation for post/reply/profile/identity/approval/history and generated-presentation removal. Reuses existing release activation and query bypass. HighTrafficRoute and worker cache allowlists unchanged. S4 commit e90a9ff5c.
- Verification: Static build/targeted rebuild, actual cursor bypass and write invalidation plus offline suite 13/13. Expanded removal/history invalidation and failed release retention 2/2; uncached Recent first/cursor URLs reject unrelated offline shell (1/1). Existing failed-release test now asserts Recent remains served from prior active release.
- Notes: No deployment performed. Shared browser acceptance and high-traffic fallback follow in S6. Estimates unchanged; no dependencies.

*— Codex · 2026-10-30 UTC*

## Stage 6 - End-to-end acceptance

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Browser exact-post/Back/reload/offline reconnect acceptance; high-traffic-enabled ordinary fallback test; reproducible bounded-reader probe. Updated two shared-build progress assertions for the additional page. S5 commit 4c0ff293c.
- Verification: `node tests/browser/recent.mjs` passes 13 themes at 390/1280px, icons, long-text overflow, ordinary entry and no-JavaScript keyboard pagination/recovery. Local cold request responseEnd 380ms / first contentful paint 752ms (single Chromium observation). `php tests/Support/recent_scale.php`: 1k/10k/40k posts, 50 rows and 51,200 projected body bytes throughout, PHP peak 2MiB; 6.55/2.05/4.50ms per read during concurrent checks. SQLite memory is outside PHP allocator measurement. Index query plan uses posts_recent_idx; no production latency claim.
- Regression command: `php tests/run.php RecentPostTest ReadModelSchemaTest AppNavigationPlacementTest AuthNavigationTest LocalAppSmokeTest StandardActivityPaginationTest ForteActivityReadModelRecoveryTest ProfilePublicDetailsTest PresentationProfileMatrixTest OfflineNavigationWorkerTest HighTrafficTest` ran 213 checks: 208 passed, five failed. Two progress-count assertions corrected and rerun 2/2. Three remaining failures independently reproduced at original baseline 3a21fab67 in an isolated worktree: private lobby pending-copy assertion, private Forte approval-copy assertion, and missing InstancePageController::repositoryArchiveDownloadFilename reflection target. They remain outside this feature. Additional new high-traffic fallback test passed 1/1.
- Limits: This is affected-suite coverage, not the full repository suite. Guest/pending/approved navigation and shared profile consumers covered by renderer/navigation suites; physical phones, production latency and production deployment remain unverified. No additional first-paint personalization introduced. Existing standard/Forte Activity contracts pass; their separate live latency report is not resolved by this work. Estimates unchanged.

*— Codex · 2026-10-30 UTC*

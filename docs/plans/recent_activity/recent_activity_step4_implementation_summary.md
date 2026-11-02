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

## Stage 7 - Release documentation and closeout

**Original estimate: 0.05**
**Retrospective stage work: 0.05**

- Changes: Recent/post-presentation concepts, static publication/read-model notes, planning index, UI verification record and PF-20261021-F04 local evidence. Study ledger reconciled; no production closure. S6 commit 183b9ddf7.
- Verification: Touched concept/source and plan links resolve; whitespace/scope checks passed; dependency delta zero. Recent mockups absent from repository. All seven stages retain original and retrospective estimates with corresponding commits; final stage commit completes the required planning-plus-seven-stage sequence.
- Notes: No merge, push or deployment. Three reproduced baseline test failures remain as recorded in S6. Maintainer fit/acceptance and physical-device/production evidence pending.

*— Codex · 2026-10-30 UTC*

## Release and recovery

Status: Implemented locally on feature/recent-activity; deployment not performed.
Upgrade: Schema 42 (numbered 39 on the branch; renumbered at the 2026-10-31 merge behind unrelated versions 39–41) changes only derived SQLite state. Ordinary live startup detects older schema/fingerprint and rebuilds; canonical records need no migration. Deploy code and use the full static build for a consistent read model and navigation/assets release.
Command: `./v3 build-static [repository_root] [database_path] [artifact_root]` using the instance's configured paths. Defaults come from FORUM_REPOSITORY_ROOT, FORUM_DATABASE_PATH and FORUM_STATIC_HTML_ROOT (otherwise the CLI's documented local defaults); the artifact root is the release root containing current, not the public source-assets directory. Run under the deployed site's existing profile/environment.
Later shell refresh: Once the read model is current and a complete release is active, `./v3 build-static --shared-only [repository_root] [database_path] [artifact_root]` republishes navigation/shared pages/assets. It does not upgrade the model; do not substitute it for the full upgrade build.
Failure recovery: Inspect a failed build's phase/error, correct the cause, then rerun the same full build. Candidate rendering failure leaves the prior active release in place. Never hand-edit or point current at an incomplete candidate. Missing/invalidated Recent artifacts fall back to PHP; cursor URLs always use live pagination. Verify `/recent/`, a Next URL, exact-post opening and Back after release activation.
Reader recovery: Invalid positions expose First page. After a network failure, reconnect and reload or use Back; an uncached Recent URL does not fall back to an unrelated offline first page. New contributions appear on a fresh first-page read under existing publication rules.
Limits: Production and physical phones unverified; no production Activity latency resolution, no full-suite-green claim. Existing QDB navigation remains unchanged. No dependencies, live polling, read tracking, RSS or filtering added.

## Study closeout

**Entry projection: 0.85**
**Retrospective whole-cycle scope assessment: 0.85**

Checkpoint: 2026-10-30 UTC; v3/recent_activity, v1/A, original baseline 3a21fab67708c07ef77712790218e12a92c61bcd, frozen R1/P2; Codex single exposed rater. Original 0.75 and feedback-only 0.5 preserved; pristine intake missing. Frozen R1 hashes reverified at closeout.
Stages: S1–S7 originals and separate retrospectives 0.10/0.18/0.15/0.10/0.15/0.15/0.05; unchanged, non-additive. Seven local stages complete; stage verification and omissions above. Measured labor unknown; test durations are not labor measurements.
Acceptance: Maintainer comfortable-fit judgment and user acceptance pending; no accepted-outcome increment or duplicate worktree observation. Study snapshot is retrospective scope assessment, not a claim of production recovery.

## Design revision — 2026-10-30 (Direct work)

Request: the delivered Recent page looked unlike the rest of the site. Reviewed next to the `list` board, thread and person pages at 390/1280px: a bare page heading over hairline dividers, a verbose "by guest on Oct 30, 2026 at 12:00 UTC" byline, a tiny context line and a large full-width link excerpt competed with each other and ignored the themes' panel styling.

- Reuse decision: Recent now renders the board's canonical list idiom, `.thread-rows` panel and `.thread-row` rows from `public/assets/site.css`, as the `list` board and the person page's Replies tab already do. Each row is the thread title linking to the thread, anchored at the reply (`/threads/{id}#post-{reply}`; a post whose thread is hidden or missing keeps `/posts/{id}`), the one-line ellipsized excerpt (omitted when it repeats the title, as `thread_row.php` does) and the compact `rowMeta` line ("guest · Oct 30 · Reply" or "· New thread"). Empty pages use the board's `.board-empty` block. `recent_entry.php` keeps `data-post-id` and the `recent-entry` test hook.
- Removed: `public/assets/recent.css` and its page-stylesheet mapping, the page `<h1>`, the long-form byline and the separate thread link; the row's single link opens the thread at the relevant card, matching the compose redirect's anchor shape. No new CSS, component, copy or dependency; themes style the rows through their existing variables and card rules.
- Verification: `php tests/run.php RecentPostTest ReadModelSchemaTest` 8/8 after updating the entry-class and empty-copy assertions. `node tests/browser/recent.mjs` passes 13 themes at 390/1280px, icons, overflow, no-JavaScript keyboard paging, anchored reply landing on the `:target` card, thread open/Back/reload and offline reconnect (link selector updated to `.recent-entry a`). AppNavigationPlacementTest, AuthNavigationTest, LocalAppSmokeTest, OfflineNavigationWorkerTest and HighTrafficTest: 184 run, 181 passed; the three failures are the same baseline failures recorded in Stage 6. Visually reviewed light, dark, Word97, Midnight, Chicago and Thermal screenshots at both widths, plus the invalid-position page; screenshots kept outside the repository.
- Limits: physical phones and production remain unverified; estimates and study records unchanged (Direct work, not a new cycle).

*— Claude · 2026-10-30 UTC*

## Main integration — 2026-11-01

Request: Finish the pending merge into main and push.
Integration: Completed the prepared Recent merge as 11e76faec, then integrated origin/main fd7e525df. Preserved both branches' planning records and canonical icon mappings in public/assets/theme-nav-icons.css. Existing Application routing, TemplateRenderer navigation and ReadModelSchema contracts remain shared; no new component or dependency.
Schema: Combined schema is version 42 because remote main already published version 41 for message recovery. RecentPostTest now exercises normal /recent/ startup from version 41 with the Recent index absent. ReadModelSchemaTest now includes remote main's history_keys_revision table and profiles_sync_keys index; its stale inventory failure was reproduced on fd7e525df before correction.
Verification: Affected PHP suite ran 228 checks, 223 passed initially. Three LocalAppSmokeTest failures (private lobby copy, private Forte approval copy, archive filename reflection) reproduced on fd7e525df. Renderer 50ms timeout assertion failed initially and on first retry, passed on remote baseline and subsequent local retries. After the inventory correction, RecentPostTest, ReadModelSchemaTest and HighTrafficTest passed 14/14. Full repository suite not run.
Browser: Final merged-tree Recent checks passed 13 themes at 390/1280px, icon mappings, no-JavaScript pagination, anchored replies, Back/reload and offline reconnect. Tools launcher passed 28 theme/width cases and eight tool bars, including icons in Word97, Chicago and Midnight. An earlier browser attempt overlapping integration failed its theme assertion; the completed-tree run passed.
CLI: ./v3 rebuild tests/fixtures/parity_minimal_v1 against an isolated /tmp database passed. Scale probe at 1k/10k/40k posts retained 50 rows and 51,200 projected body bytes, using posts_recent_idx.
Scope: Dependency delta zero. Untracked zenmemes feedback summary left untouched. Incoming todo.txt changes are remote history; no local todo edits. Diff against origin/main passes whitespace checks; two existing trailing blank lines in incoming Tools documentation remain unchanged.
Limits: Three reproduced baseline failures remain. Production deployment, production failure replay and physical phones are unverified; this task integrates and pushes source only.

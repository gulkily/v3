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

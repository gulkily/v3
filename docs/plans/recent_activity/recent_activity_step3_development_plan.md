> **Feature plan:** [Step 1](./recent_activity_step1_solution_assessment.md) · [Step 2](./recent_activity_step2_feature_description.md) · [Step 3](./recent_activity_step3_development_plan.md) · [Step 4](./recent_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/recent_activity; [ledger](../effort_estimation_trial.md#cycle-v3recent_activity), P2
Extended estimates: enabled
**Cycle estimate: 0.85**

## Completion Contract

Ordinary navigation → `/recent/` → newest public threads/replies → exact post → return/page onward. Maximum 50 entries; deterministic ordering, empty-state return, invalid-position First page, failed-navigation Reload/Back and uncached-offline reconnect recovery. Deliver through live and static front-controller paths, with no-JavaScript, phone/desktop and theme checks. Release requires compatible startup/rebuild and republished static output; local completion does not certify production latency or physical-device acceptance.

## Key Risks

- **High risk: visibility/attribution.** Profile history has identity/counting restrictions inappropriate for this public feed. Stage 1 fixes eligibility/context cases before selection; preserve existing hidden/retracted behavior and unsigned labels, without changing retraction policy.
- **High risk: stale/wrong pages.** Static generation, invalidation and query bypass are separate paths. Stage 5 proves publication/recovery before release; never serve page one at a cursor URL.
- **High risk: usability/performance.** Full post cards carry unrelated controls; existing post indexes do not provide the global date/post ordering. Stages 2–4 validate bounded selection, compact presentation and phone navigation before acceptance; failed gates block dependents.

## Stage 1 — Public-contribution contract

**Estimate: 0.10**

- Goal/Dependencies: Pin eligibility and fixtures; approved plan required.
- Expected changes: Shared test fixture covering roots, replies, unsigned/unapproved authors, hidden/system posts, expansions and unavailable parents; record expected context/fallbacks.
- Verification: Compare exact-post availability and existing visibility rules; do not exclude publicly readable unsigned posts merely because profile totals exclude them.
- Risks: Resolve hidden-parent/context leakage before Stage 2.
- Canonical components: `ThreadRepository`, `ThreadRowSupport`, `AuthoredContentRepository`, `ThreadAndPostPageController`.
- Estimate details: Eligibility investigation; unusual parent states are the uncertainty.

## Stage 2 — Bounded read and startup compatibility

**Estimate: 0.18**

- Goal/Dependencies: Fetch one stable page; Stage 1.
- Expected changes: `RecentPostRepository::page(PDO, ?array $cursor, int $limit = 50): array`; date/post-ID boundaries, bounded text projection and safe context; add the required ordering index. Increment read-model version from the implementation baseline and update its specification.
- Verification: Equal dates, both directions, deleted anchors, empty/malformed positions, arrivals and rebuilds; prior-version normal startup and actual isolated `./v3 rebuild`; growing-data query plans/read costs.
- Risks: No corpus materialization or full bodies/history enrichment; stop if indexing cannot bound ordinary reads.
- Canonical components: Existing posts/profiles, `PageNavigation`, `ReadModelSchema`, `ReadModelMetadata`.
- Estimate details: Cursor correctness and migration proof; sparse eligibility may affect scanning.

## Stage 3 — Readable route and recovery

**Estimate: 0.15**

- Goal/Dependencies: Read Recent and open a contribution; Stage 2.
- Expected changes: `RecentPageController::board(mixed $position = null): string`, Application/RouteServices wiring, page plus compact entry partial; shared metadata/excerpt helpers and `/posts/{id}` links.
- Verification: Six-contribution story, title/image-only content, long text, trust labels, native paging, invalid-position restart and empty return.
- Risks: Escape text; neutral unavailable-thread context must not expose hidden titles. No posting controls or fetched embeds.
- Canonical components: `TemplateRenderer::renderContentMeta`, `ThreadTitle`, `page_navigation.php`; full `post_card.php` remains unchanged.
- Estimate details: Small renderer integration; fallback compatibility needs checking.

## Stage 4 — Navigation and themes

**Estimate: 0.10**

- Goal/Dependencies: Reach Recent normally; Stage 3.
- Expected changes: Add Recent to canonical main navigation; extend `theme-nav-icons.css`, preserving existing destinations and app/legacy layouts.
- Verification: Guest/pending/approved, keyboard and no-JavaScript entry; phone/desktop all enabled themes, with explicit Word97/Chicago/Midnight icon and overflow checks.
- Risks: Extra navigation item must fit without moving primary controls after paint; reuse shell layout.
- Canonical components: `TemplateRenderer::navItems`, `nav.php`, app header, existing navigation tests.
- Estimate details: Shared navigation verification; phone fit is the uncertainty.

## Stage 5 — Publication and invalidation

**Estimate: 0.15**

- Goal/Dependencies: Ordinary serving stays correct after writes; Stages 3–4.
- Expected changes: Register first-page artifact in `StaticArtifactBuilder` and `FrontController`; extend `StaticArtifactInvalidator` for relevant post/identity/removal changes and targeted rebuilds; preserve cursor query bypass and uncached offline behavior.
- Verification: Actual static build/front controller, post/reply/rename/removal refresh, cursor reload, failed build retaining prior release, stale published shell refresh and service-worker fallback.
- Risks: Do not broaden the high-traffic allowlist or cache personal data; validate Recent under high-traffic-enabled configuration via its normal fallback.
- Canonical components: Existing static publication/invalidator, `HighTrafficRoute`, `service_worker.js`, `OfflineNavigationWorkerTest`.
- Estimate details: Several serving paths; invalidation coverage drives uncertainty.

## Stage 6 — End-to-end acceptance

**Estimate: 0.15**

- Goal/Dependencies: Demonstrate the completion contract; Stages 1–5.
- Expected changes: Focused browser/HTTP regression coverage and performance evidence; this stage owns shared acceptance setup and corrections.
- Verification: Navigation through exact posts, paging/reload/Back, failure/reconnect, first paint and long-content layouts; fixture scaling at 1,000/10,000/40,000 posts, including large bodies. Run affected navigation, schema, static, profile, standard/Forte Activity and offline checks.
- Risks: Record response/paint/memory and query evidence, not a universal latency promise. Replay available phone/slow-site conditions; explicitly leave physical-device/production recovery unverified when unavailable.
- Canonical components: Existing browser harness, pagination and Activity suites; isolated content fixtures.
- Estimate details: Integration acceptance; environment-specific failures may require rescoping.

## Stage 7 — Release documentation and closeout

**Estimate: 0.05**

- Goal/Dependencies: Reviewable release handoff; Stage 6.
- Expected changes: Document rebuild/republish and failure recovery, record tests/limits and source request evidence; update touched concepts, planning index and study ledger.
- Verification: Links, diffs, approved scope and zero dependency delta; deployment instructions identify configured artifact roots without mutating production.
- Risks: Never close the separate Activity latency report from local Recent checks.
- Canonical components: Existing publication/pagination guidance, PF-20261021-F04, cycle summary.
- Estimate details: Documentation reconciliation; production acceptance remains separate.

## Execution and study checkpoint

Approval: Step 2 approved; implementation awaits **Approved Step 3**. Then create `feature/recent-activity`, move Steps 1–3/debug log into `docs/plans/recent_activity/`, repair links/index and make the planning-only commit before creating Step 4's summary. Each stage requires checks, summary update and its own commit before the next; split an oversized stage before execution. Preserve the uncommitted catalog separately from the planning commit.

Concepts: Create Recent discovery and post-presentation/visibility entries; update static-publication/read-model entries only for delivered changes. Deferred requests from Step 2 stay deferred; no new dependencies, feed/API, read tracking, ranking or retraction redesign.

Checkpoint: 2026-10-30T20:44:52Z; v1/A; original baseline 3a21fab67708c07ef77712790218e12a92c61bcd; R1/P2, hashes reverified, Codex single exposed rater. Whole-cycle **0.85**, revising preserved **0.75** for inspected attribution, index/startup and static invalidation work; outcome unchanged. S1–S7 original stage-work estimates above are non-additive and exclude whole-cycle planning. Inherited 0.5 and missing pristine intake retained. Source inspection/document checks only; no runtime acceptance, measured labor, commits or implementation.

*— Codex · 2026-10-30 UTC*

## Approved mockup disposition — 2026-10-30

Request: User approved Step 3 and instructed deletion of the mockups rather than saving them in the repository.
Disposition: Deleted all mockup HTML, images and notes; retain the reviewed compact layout as design guidance only. Implementation authorized; v1/A and 0.85 projection unchanged.

*— Revised by Codex · 2026-10-30 UTC*

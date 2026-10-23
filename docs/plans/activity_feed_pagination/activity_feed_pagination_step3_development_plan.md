> **Feature plan:** [Step 1](./activity_feed_pagination_step1_solution_assessment.md) · [Step 2](./activity_feed_pagination_step2_feature_description.md) · [Step 3](./activity_feed_pagination_step3_development_plan.md) · [Step 4](./activity_feed_pagination_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/activity_feed_pagination`; [ledger](../effort_estimation_trial.md#cycle-v3activity_feed_pagination), scope v3, R1/P2.

## Completion Contract

Standard Activity → Next beyond 100 eligible entries → inspect source evidence → Previous/Back, with shareable positions, category resets and usable retry/restart. Deliver reusable page state/controls with standard Activity as the only adopter. Preserve date-descending order, membership, security and per-record likes. **No Forte changes**, including append, Commits, selection, APIs or preloads. Deliver project pagination implementation guidance. Verify phone/desktop/themes, normal serving and rebuild recovery; production deployment and other migrations are excluded.

## Key Risks

- **High risk:** ties, arrivals/deletions or rebuilt row IDs can corrupt traversal. Validate canonical-key ordering and reverse navigation before dependent serving work; avoid row-ID-only positions.
- **High risk:** static/offline responses or shared-service changes can expose incorrect pages or affect Forte. Preserve old interfaces, inspect routing early and require serving/security and Forte regressions.
- One initial adopter risks speculative infrastructure: separate reusable result/navigation/position handling from Activity queries; implement only capabilities used now.

## Stage 1

- Goal: ordinary standard Activity exposes a bounded next page through reusable controls/state.
- Dependencies: approved Step 2 v3; unchanged Forte baseline contracts captured.
- Expected changes: additive `ActivityService::fetchActivityPage(string $view, ?array $position = null, string $direction = 'next', int $limit = 100): array`; bounded page result, reusable navigation partial and context-bound URL positions. Wire standard controller/Application route; preserve existing methods. Advance read-model version 24→25 for the new core query contract, documenting unchanged table shape.
- Verification approach: >100-entry fixture through normal `/activity/` links without JavaScript; category preservation, source evidence, prior-version startup before the new query, `./v3 rebuild`, existing Forte/API/RSS contracts.
- Risks or open questions: impact—new cutoff or unintended caller changes; early validation—render real next-page links and compare existing callers; mitigation—additive interfaces and filtered queries before limiting.
- Canonical components/API contracts touched: `ActivityService`, `ActivityPageController::board` with optional paging input, Application standard route, reusable pagination result/controls, metadata/schema specification and startup regression.

## Stage 2

- Goal: bidirectional traversal, direct links/history and boundary recovery work reliably.
- Dependencies: Stage 1 normal forward flow and compatibility checks.
- Expected changes: Previous navigation using stable record time/action-key positions, restored display order and context validation; first-page restart/empty-page return links. Use native links/history; no enhanced-navigation layer required. Totals/numbered jumps remain optional and unused unless needed.
- Verification approach: >two pages traversed once in both directions; tied times, arrivals, deleted anchor/items, withdrawn contributions, rebuild changing row IDs, malformed/mismatched positions, category resets, refresh/Back/Forward and failed-request retry/return.
- Risks or open questions: impact—duplicates, omissions or stranded pages; early validation—compare traversals and live mutations; mitigation—derive boundaries independently of anchor existence and retain return/restart actions.
- Canonical components/API contracts touched: new standard-page query, reusable position validation/URL state and navigation partial; existing Forte cursor handling stays intact.

## Stage 3

- Goal: real serving respects page positions and current access.
- Dependencies: Stage 2 complete navigation/recovery contract.
- Expected changes: preserve query-sensitive static bypass, first-page publication/invalidation and authorization. Verify offline cache keys/fallback; adjust only standard-page serving where needed, with online recovery for unavailable pages. Do not prepublish all history.
- Verification approach: FrontController requests with/without cookies, category/position URLs, stale artifacts, protected access/history, offline available/unavailable destinations, fresh publication and invalidation. RSS/instance previews remain unchanged.
- Risks or open questions: impact—first-page substitution or disclosure; early validation—trace actual responses/cache identities; mitigation—position-aware routing and existing protected-response isolation.
- Canonical components/API contracts touched: `FrontController`, static builder/invalidator and offline routing only where standard Activity requires it; public/security response contracts.

## Stage 4

- Goal: complete acceptance and reviewable documentation.
- Dependencies: Stages 1–3 focused checks passed.
- Expected changes: browser acceptance fixture and focused regressions; developer guide `docs/reference/pagination.md` linked from `CONTRIBUTING.md`, schema/operator documentation, implementation summary, UI-copy verification checklist, migration checklist and scoped study ledger closeout.
- Verification approach: actual standard Activity links across first/middle/last/empty pages, categories, evidence, keyboard/focus, failed navigation/restart, 390/1280 widths and affected standard themes. Run existing Forte append/detail/selection/Commits browser checks unchanged; focused Activity/retraction/like/static tests, syntax and link checks. Validate the guide against the delivered standard Activity example: shared APIs, list adapters, stable ordering/positions, category/URL state, access/cache boundaries, retry/restart, test commands and justified exceptions (Forte unchanged). Record environment failures separately.
- Risks or open questions: impact—fixture-only success or accidental Forte migration; early validation—normal browser navigation plus diff/caller review; mitigation—explicit unchanged-Forte checks and documented omissions.
- Canonical components/API contracts touched: tests/browser harnesses, affected documentation and study artifacts; no Forte production edits, new destinations or navigation icon changes.

## Execution and study checkpoint

Four sequential stages targeting ≤1 hour each, including verification and a summary-bearing commit. Split/replan before dependent work if a stage exceeds its bound. After **Approved Step 3**, create a feature branch, organize this cycle's artifacts under `docs/plans/activity_feed_pagination/` and make the scoped planning commit before implementation; preserve unrelated drafts. Include only this cycle's authorized ledger/checklist changes at closeout.

2026-10-23 UTC; Step 2 v3 approved. Same original baseline `39be8293`, current inspected checkout `e5f4bce6`, single exposed Codex rater, R1/P2; earlier snapshots retained. Exposure now includes shared callers, schema version 24, existing paging/static paths and regression inventory. Total projection remains **unavailable pending exact frozen-reference recovery**, not measured effort or a rating inferred from stage count. No implementation or completed outcome. Await **Approved Step 3**.

### Developer guidance clarification

2026-10-23 UTC: user requested implementation guidance at completion. Stage 4 now explicitly delivers a discoverable developer guide checked against actual code and tests, not only an implementation summary. No new runtime feature or other-list migration; scope v3, prior approvals, baseline/R1/P2 and exposed rater remain unchanged. Projected total remains unavailable pending exact frozen-reference recovery. Await Approved Step 3 before implementation.

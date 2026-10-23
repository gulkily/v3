> **Feature plan:** [Step 1](./activity_feed_pagination_step1_solution_assessment.md) · [Step 2](./activity_feed_pagination_step2_feature_description.md) · [Step 3](./activity_feed_pagination_step3_development_plan.md) · [Step 4](./activity_feed_pagination_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/activity_feed_pagination`; [ledger](../effort_estimation_trial.md#cycle-v3activity_feed_pagination), scope v3, R1/P2.

## Entry

- Approved Step 3 received; branch `feat/activity-feed-pagination`; scoped planning commit `347df048` verified before creating this summary or implementation edits. Original baseline `39be8293`; implementation starts from `e5f4bce6`.
- Standard Activity is the sole adopter. Forte production code, append behavior, API contracts and preloads remain unchanged. Developer guidance at `docs/reference/pagination.md` is required at closeout.
- 2026-10-23 UTC; single exposed Codex rater; Step 3 projected total reaffirmed **unavailable pending exact frozen R1 reference recovery**. No numeric estimate, measured effort or acceptance inferred. Prior snapshots preserved; unrelated drafts/marker retained.

## Stage 1 — forward navigation and reusable boundary

- Changes: standard Activity now uses an additive bounded query and native reusable pagination links/context-bound positions. Existing Forte and RSS methods are untouched. Read-model version 25 and schema documentation require normal startup recovery from version 24 before the new query.
- Verification: five focused tests passed (`StandardActivityPaginationTest`, version-24 startup regression and shared classic/Forte evidence rendering). Normal category-filtered links reach entries beyond the initial 100 without JavaScript; output is capped at 100. Existing Forte return shapes, append control and RSS's 100-item contract pass. `./v3 rebuild tests/fixtures/parity_minimal_v1 /tmp/activity-pagination-cli.sqlite3` passed. Syntax and whitespace checks passed.
- Notes: reverse navigation and mutation/rebuild recovery are Stage 2. Canonical action keys are stable; inspection found source-less local Codex handoff events have null keys. Their temporary local-row fallback must be guarded against reindexing in Stage 2 instead of silently assuming all rows have canonical keys. No Forte production edits or original content writes.

## Stage 2 — reverse traversal and recovery

- Changes: native Previous/Next links now use record-time/key boundaries, independent of anchor existence; reverse queries restore normal display order. Invalid or context-mismatched positions show First page; deleted/empty pages retain available return navigation. Empty lists have an explicit empty state. Security-review enrichment is preserved in the new standard query.
- Verification: 15 focused pagination/retraction tests passed. A 205-item tied-time dataset traverses once and returns correctly; arrival, deleted anchor, shifted SQLite IDs and a real canonical-record rebuild preserve forward positions. Empty/deleted pages, malformed/array/cross-category positions, category reset and source-less rows have actionable recovery. Existing Forte/RSS interfaces remain covered.
- Notes: source-less local events remain included through a local-row fallback. Cursors carry a fingerprint of unkeyed local rows; a changed/reindexed local history expires that position and offers First page rather than silently skipping entries. Canonical-only lists do not depend on disposable row IDs. This is the approved stale-position recovery boundary, not historical snapshot storage. Developer guidance will document it. Native links avoid a new browser navigation/cache layer.

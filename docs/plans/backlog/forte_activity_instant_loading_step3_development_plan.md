# Forte Activity Instant Loading — Step 3: Development Plan

## Stage 1 - Lightweight activity list contract
- Goal: Retrieve list rows without detail enrichment.
- Dependencies: Approved Step 2; existing filter/cursor rules.
- Expected changes: Add lightweight list-page and single-item-detail contracts to `ActivityService`; preserve result membership/order; no database changes.
- Verification approach: Service tests compare list membership/order/cursors with current Activity results and prove list retrieval omits detail metadata.
- Risks or open questions: Deep-selected items can be outside the initial batch.
- Canonical components/API contracts touched: `ActivityService::fetchActivity()`, new list/detail contracts, `fetchCommits()`.

## Stage 2 - Bounded server bootstrap and APIs
- Goal: Serve one small requested-view batch and its selected detail.
- Dependencies: Stage 1.
- Expected changes: Extend `ForteActivityController`, row paging, and a new activity-detail API; preserve Commit detail and access rules; no database changes.
- Verification approach: HTTP tests cover default, filtered, sorted, deep-selected, row-only, and invalid-detail requests.
- Risks or open questions: Filter totals must remain accurate before preload finishes.
- Canonical components/API contracts touched: `/forte/activity/`, `/api/forte_activity_page`, activity-detail API, row/detail partials.

## Stage 3 - Minimal first-paint markup
- Goal: Keep initial markup to visible rows and one selected detail.
- Dependencies: Stage 2.
- Expected changes: Update Forte Activity page/list/detail partials for incomplete lists and detail-loading state; no database changes.
- Verification approach: Render tests assert bounded initial rows/articles, correct selection/totals, and no hidden detail pool.
- Risks or open questions: Empty and non-date-sort views retain the safe selection fallback.
- Canonical components/API contracts touched: `forte_activity.php`, Activity list/detail/filter/status partials.

## Stage 4 - On-selection Activity details
- Goal: Fetch and cache full Activity metadata only when selected.
- Dependencies: Stages 2-3.
- Expected changes: Extend reader selection with per-visit detail cache, loading/error state, and stale-response protection; retain Commit behavior.
- Verification approach: Browser tests cover click/keyboard/history/deep-link detail selection and one request per cached item.
- Risks or open questions: A stale detail response must not replace the newer selection.
- Canonical components/API contracts touched: `paned_activity_reader.js`, activity-detail API, `/api/forte_commit_detail`.

## Stage 5 - Automatic complete row preload
- Goal: Preload every Activity and Commit filter's rows after first paint.
- Dependencies: Stages 2-4.
- Expected changes: Add an interruptible cursor scheduler that deduplicates activity rows, records filter membership, and restarts on sorting; no database changes.
- Verification approach: Browser/API tests prove each filter exhausts automatically, later filter switches need no list request, and input takes priority.
- Risks or open questions: Large histories require yielding and must leave the visible page usable after failures.
- Canonical components/API contracts touched: `paned_activity_reader.js`, `/api/forte_activity_page`, row-merge/status contracts.

## Stage 6 - Performance and regression proof
- Goal: Prove near-instant first use without regressions.
- Dependencies: Stages 1-5.
- Expected changes: Add performance coverage and documentation; no database changes.
- Verification approach: Cold-cache representative benchmark reaches visible/interactable Activity within 500 ms; smoke/browser coverage retains Board, Users, classic Activity, RSS, backup, sort, deep links, and Commit detail.
- Risks or open questions: The benchmark environment must be stable enough for the threshold.
- Canonical components/API contracts touched: Forte Activity performance coverage and existing smoke/browser suites.

Share this document for review — **Approved Step 3** is required before Step 4.

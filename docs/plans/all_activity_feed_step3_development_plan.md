# All Activity Feed

## Stage 1
- Goal: Define the complete activity contract and action/record inventory.
- Dependencies: Approved Step 2.
- Expected changes: Classify persisted frontend writes; define kind, label, timestamp, actor, resource link, source evidence, visibility, and ordering.
- Verification approach: Cross-check all frontend write routes, services, and canonical loaders.
- Risks or open questions: Decide whether state-only files appear directly or through their creating action; define public treatment of internal events.
- Canonical components/API contracts touched: Activity item contract; `LocalWriteService`; canonical record families.

## Stage 2
- Goal: Extend the read-model activity representation.
- Dependencies: Stage 1 contract.
- Expected changes: Add/adjust activity fields and indexes for non-post events while preserving source, commit, authorship, and resource references.
- Verification approach: Confirm a full rebuild can represent every mapped event family.
- Risks or open questions: Existing local database/schema-version compatibility.
- Canonical components/API contracts touched: `ReadModelBuilder`; `ReadModelMetadata`; SQLite `activity` model.

## Stage 3
- Goal: Reconstruct complete activity during full rebuilds.
- Dependencies: Stage 2; canonical parsers for posts, identities, approvals, labels, reactions, and instance records.
- Expected changes: Index all approved activity-bearing families, including bootstrap/approval rows; assign stable kinds, labels, timestamps, and source links.
- Verification approach: Rebuild representative data and compare results with the Stage 1 inventory.
- Risks or open questions: Historical records may lack timestamps or actors and need documented fallbacks.
- Canonical components/API contracts touched: `ReadModelBuilder::indexActivity()`; canonical repository loaders.

## Stage 4
- Goal: Publish activity immediately after successful frontend writes.
- Dependencies: Stages 2–3.
- Expected changes: Extend incremental updates for post, identity/bootstrap, approval, label, reaction, feature-flag, and other supported writes; prevent duplicates for multi-record actions.
- Verification approach: End-to-end writes assert immediate feed visibility without rebuild, including explicit stale/error handling.
- Risks or open questions: Coordinating activity with multi-record commits.
- Canonical components/API contracts touched: `LocalWriteService`; `IncrementalReadModelUpdater`; write API responses/timings.

## Stage 5
- Goal: Render the complete projection consistently.
- Dependencies: Stages 3–4.
- Expected changes: Update All Activity filters, event-to-resource links, category subsets, RSS, backup-preview scope, static artifacts, and invalidation.
- Verification approach: Render mixed event data and exercise writes; verify ordering, labels, links, RSS, static output, and preview consistency.
- Risks or open questions: Non-post events may require source links instead of post/thread links; backup may remain content-only.
- Canonical components/API contracts touched: `Application::fetchActivity()`; `activityViewSql()`; `activity.php`; `renderActivityRss()`; backup/static builders.

## Stage 6
- Goal: Prove coverage and rebuild parity.
- Dependencies: Stages 1–5.
- Expected changes: Add fixture and end-to-end coverage for every supported write family, hidden records, mixed ordering, source links, RSS, and static output.
- Verification approach: Run targeted tests, full PHP suite, and representative rendered-feed checks.
- Risks or open questions: Fixtures may need canonical records for currently unrepresented families.
- Canonical components/API contracts touched: `WriteApiSmokeTest`; `LocalAppSmokeTest`; read-model/parser tests.

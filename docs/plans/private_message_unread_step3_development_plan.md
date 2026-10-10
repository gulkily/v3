# Private message unread state Step 3 development plan

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md)

## Completion Contract

- Deliver count → unread row → visible conversation → acknowledgment → consistent refreshed devices, including retry, intact drafts/history, and Step 2 semantics/exclusions.
- Require isolated upgrade/rollback, authorization, regression, and browser checks; no production deployment/external service. Cycle 3 rollback retains messages, drafts, and new metadata for re-upgrade.
- Six ≤1-hour stages; split oversized stages, rescope beyond eight/day. After approval: feature branch, planning-only commit, then verified summary/commit per stage. No merge/push. Group four artifacts when the implementation summary is created.

## Key Risks

- **High risk:** false clearing. First test ties/backdating, forged boundaries, and concurrent devices; require protected snapshot boundaries and forward-only progress.
- **High risk:** reset/disclosure. First test concurrent initialization/rollback; require one atomic private baseline, scoped metadata, and validated insertion anchors.
- **High risk:** unintended acknowledgment. First test hidden tabs, early scrolling, failed reads/restart; require settled reads and visible/focused latest-region presentation. Unresolved risks block dependents.

## Stage 1

- Goal: durable, username-scoped unread state.
- Dependencies: approval; initialization/race fixtures first.
- Expected changes: private baseline/signing secret and viewer/counterpart progress; initialize once before new deliveries, without changing envelopes.
- Verification approach: empty/existing stores, simultaneous first use, own sends, ties/backdating, stale/concurrent progress, rollback/re-upgrade.
- Risks or open questions: reject invalid anchors; never silently rebaseline.
- Canonical components/API contracts touched: Store `unreadStateFor(string $viewer, array $counterparts = []): array`, `markSeenThrough(string $viewer, string $counterpart, string $messageId): void`; initialization/send.

## Stage 2

- Goal: authenticated state retrieval and bounded acknowledgment.
- Dependencies: Stage 1 invariants pass.
- Expected changes: no-store count/bounded row-state retrieval; fresh-window read tokens binding received boundaries; preserve pagination contracts.
- Verification approach: token tampering/replay, foreign identities/counterparts, approval loss, cross-origin writes, snapshot arrivals, compatibility.
- Risks or open questions: history cursors are not mutation authority; verify tokens, preserving retry boundaries.
- Canonical components/API contracts touched: service `unreadState(array $viewer, array $counterparts = []): array`, `acknowledge(array $viewer, string $counterpart, string $readToken): array`; controller/router GET `/api/private_messages/unread`, POST `/api/private_messages/read`; conversation page/API metadata.

## Stage 3

- Goal: find unread conversations through normal navigation.
- Dependencies: Stage 2 authorized state contract.
- Expected changes: shared refresh coordinator, accessible indicators, zero/loading/stale states; refresh on entry/visible focus without polling or count-related decryption.
- Verification approach: two conversations/count 2; >25 counterparts, outgoing latest previews, unavailable counterparts, mobile/keyboard, non-message pages.
- Risks or open questions: reject obsolete refreshes; reconcile count/rows without resetting pagination.
- Canonical components/API contracts touched: `TemplateRenderer`, nav, Messages controller/list/row/styles; new unread coordinator, absent today.

## Stage 4

- Goal: acknowledge presented conversations safely.
- Dependencies: Stage 3 indicators; visibility fixtures first.
- Expected changes: connect settled reads, visibility/focus, and token; confirm before clearing indicators. Separate acknowledgment feedback; replace restart candidates only after success/settlement.
- Verification approach: count 2→1; background tabs, early navigation, failed reads, old-history loads, restart, inline sends, later arrivals.
- Risks or open questions: bind acknowledgment to its window; serialize retries despite repeated events.
- Canonical components/API contracts touched: conversation, reader completion, history restart, unread coordinator/API; composer unchanged.

## Stage 5

- Goal: recover and converge across devices.
- Dependencies: Stage 4 normal journey passes.
- Expected changes: failed/uncertain acknowledgment retry, refresh recovery, identity/view invalidation; preserve drafts and confirmed progress.
- Verification approach: lost acknowledgment, reversed responses, two devices/identities, stale tokens, access loss, pending send/newer draft, focus/back navigation.
- Risks or open questions: retain stale/unavailable feedback; never imply zero or revive another identity's state.
- Canonical components/API contracts touched: shared coordinator, conversation feedback/lifecycle, existing authentication and browser fixtures.

## Stage 6

- Goal: verify release and hand off.
- Dependencies: Stages 1–5 committed; risks resolved.
- Expected changes: API/rollout documentation, summary, master checklist, plan index/links.
- Verification approach: normal-entry two-device browser journey, >25 rows, initialization/rollback fixtures, messaging/shared-composer regressions, no-store/static/offline exclusion, syntax/links and commit audit.
- Risks or open questions: record browser evidence and physical-device limitations; no production migration.
- Canonical components/API contracts touched: messaging suites/browser harness, API reference, FDP artifacts and index; preserve deferred widget/key work.

> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/message_sync_activity — [ledger](../effort_estimation_trial.md#cycle-v3message_sync_activity), standing P2 enrollment
Extended estimates: enabled
Status: Step 1 Option A approved on 2026-10-30 by “I agree, please go ahead” after selecting sync first; Step 2 authorized, implementation unapproved

**Cycle estimate: 0.85**

## Original Query

“Yes, it should sync in recent activity order, ideally. That is definitely a lot of keys though; we should do something about that too, or one could easily DoS the server.”
“Sounds like we should write three separate Step 1 assessments.”
“The messages are now synced, but still a lot of scrolling happens in my network log.”

## Understood intent and problem

Fix slow new-device recovery and continuing request floods without requiring key cleanup first. The user reports 49 requests per heartbeat; the matching local account has 115 approved keys. Recovery now completes, but traffic persists. [Diagnostic evidence](../private_message_sync_idle_reload_diagnostic.txt) identifies fingerprint-ordered target scans and repeated per-message candidate lookups; the exact remaining post-recovery traffic breakdown still needs measurement.

## Option A — Activity-prioritized recovery with bounded, deduplicated work

**Estimate: 0.85**

Prioritize same-account destinations by recent authenticated activity, with explicit fairness for older devices. Reuse recovery results within bounded work, avoid repeated empty candidate queries and duplicate receipts, and make settled discovery cheap. Continue known unfinished work in bounded visible turns, stopping at completion or lack of new recoverable work.

- Benefits: Directly addresses both symptoms; works for existing oversized accounts and before retirement/caps ship.
- Costs: Needs private per-key activity semantics, durable progress/invalidation rules and request/byte/CPU budgets; these are the main effort uncertainties. Activity cannot be updated by an unauthenticated claim or endlessly refreshed by background work itself.

## Option B — Durable recovery-demand queue

**Estimate: 1.10**

Represent each device's requested recovery as bounded server-side work, ordered by authenticated activity and consumed by eligible donors, with deduplicated processing and explicit completion.

- Benefits: Clear demand and progress; can avoid repeatedly enumerating every destination.
- Costs: Adds queue lifecycle, cleanup, concurrency and restore obligations. Above the comfortable cycle size; would need a split before planning implementation. No server-side decryption is implied.

## Recommendation and acceptance boundary

Choose A. Replay ordinary Messages reloads with at least the observed 115-key scale: a recently active new key must recover both sent and received history without repeated manual refresh calls. Verify old devices are not permanently starved. Once settled, ordinary idle refresh is one small combined check, without repeated transfer lookups, duplicate receipts or resending originals/key lists; no-history accounts retain zero dedicated sync calls. Measure endpoints, response bytes and server/browser work, not just successful HTTP responses. Define hard numeric budgets and stale-state invalidation in Step 2, including concurrent tabs, missing donors, new messages/keys and failed requests.

Bound authenticated request handling even when clients ignore browser limits. Repeated unrecoverable messages must not trigger endless follow-ups; new donor evidence and explicit reading retry must reopen recovery. Preserve browser-only secrets, existing APIs/foreground reading and messaging disable behavior. Do not rank cross-account sender targets by recipient activity or reveal recipient recovery/read state. Activity metadata stays private; old/unknown activity gets a fair fallback. No automatic retirement, cap enforcement, key deletion or new dependencies in this cycle.

Dependencies: Ship independently and first. [Retirement](../key_retirement/key_retirement_step1_solution_assessment.md) and [cap](../active_key_limit_step1_solution_assessment.md) reduce future key growth but do not substitute for bounded sync at or above the cap. This is a distinct corrective follow-up to the merged [idle-sync cycle](../private_message_sync_idle/private_message_sync_idle_step4_implementation_summary.md), with its earlier evidence/limits retained.

Concepts touched: [History recovery and sender assistance](../../concepts/private_message_history.md) changed scheduling/processing; eligibility and unread meaning used unchanged. Private authenticated key activity is a proposed inventory addition during implementation. Reuse current sync, unread and authentication surfaces; do not invent a new public activity feed.

## Study snapshots and approval

Scope: v1/A; baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; R1/P2; Codex single exposed rater. All three frozen R1 source hashes verified. Prior discussion, related implementation/diagnostics and initial source inspection visible; pristine pre-inspection intake unavailable, not reconstructed.
Intake: 2026-10-30T11:27:22Z; 0.85, exposed estimate.
Step 1 handoff: 2026-10-30 UTC; 0.85 reaffirmed for the recommended complete slice, including recovery, verification and documentation. Option estimates are whole cycles, not additive, time estimates or measured labor. Include this cycle's concept/docs/debug/ledger upkeep; no runtime checks performed for this assessment. Prior diagnostic runs are evidence, not new acceptance. Maintainer acceptance pending; planning remains uncommitted. Await Approved Step 1 for this named cycle before Step 2.

*— Codex · 2026-10-30 UTC*

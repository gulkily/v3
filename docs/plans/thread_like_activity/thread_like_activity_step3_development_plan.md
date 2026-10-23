> **Feature plan:** [Step 1](./thread_like_activity_step1_solution_assessment.md) · [Step 2](./thread_like_activity_step2_feature_description.md) · [Step 3](./thread_like_activity_step3_development_plan.md) · [Step 4](./thread_like_activity_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/thread_like_activity`; [ledger](../effort_estimation_trial.md#cycle-v3thread_like_activity). Step 2 scope v1 approved.

## Completion Contract

Normal **Like → accepted result → All Activity** reports a later like even when the thread is already liked. Each eligible canonical like contribution appears once with its own actor, record time and source evidence; scores and vote eligibility remain unchanged. Startup/rebuild recovers historical omissions, and existing retry/fallback preserves accepted writes. Verify classic/Forte lists, details, counts, pagination and RSS on phone/desktop. Replay the reported local thread's two-contribution failure in isolation; inspect original records read-only. Release requires focused tests, real browser acceptance, rebuild/startup recovery and recorded limitations; no production deployment or user-content mutation is required. Unlike controls, the withdrawn-source duplicate guard and complete repository reporting remain separate.

## Key Risks

- **High risk: altered vote semantics.** Activity identity must follow canonical action identity, not score deduplication. Validate repeated records/actors and mixed labels in Stage 1; leave write validation and score math untouched.
- **High risk: false audit attribution.** Incremental refresh currently assigns its current commit to all reconstructed label events. Validate older/newer evidence against full rebuild in Stage 2; use each record's existing path-history resolution, leaving unavailable history explicitly unknown.
- **Stale history or disclosure:** existing version-23 models can remain apparently fresh. Stage 3 forces startup refresh; every stage preserves current hidden/retracted/withdrawn-source filters rather than widening historical visibility.

## Stage 1

- Goal: later accepted likes appear through normal writes and rebuilds.
- Dependencies: approved Step 2; fixtures distinguish action identity from score eligibility.
- Expected changes: extend existing full/incremental label-event derivation to include `like` for each eligible like-bearing record, independently of first effective label appearance; retain one mixed-label item per record.
- Verification approach: signed button likes from two identities, distinct same-author source comments, repeated-record replay, mixed labels, withdrawn-source likes, and unchanged labels/scores/votes; compare incremental and rebuilt event sets.
- Risks or open questions: duplicate activity or changed scoring; validate record-key identity first and keep existing contribution/visibility filtering.
- Canonical components/API contracts touched: `ReadModelBuilder::indexThreadLabels`, `IncrementalReadModelUpdater::deriveThreadLabelState`; existing `thread_label_add` event contract and Like APIs remain intact.

## Stage 2

- Goal: each listed contribution retains correct evidence and stable pagination identity.
- Dependencies: Stage 1 event inclusion and fixtures.
- Expected changes: resolve the source commit for each label event during incremental refresh using existing path-history support; preserve actor, original timestamp, signature enrichment and stable action key. Reuse existing list/count/detail/feed consumers.
- Verification approach: accept a later like and verify earlier actors/times/commit links stay unchanged; compare full rebuild, same-time events, replay/retry, counts and cursor pages. Missing history must not borrow the current unrelated commit.
- Risks or open questions: historical misattribution or repeated history scans; establish parity before dependent startup/browser work and reuse/cache existing source resolution.
- Canonical components/API contracts touched: incremental label Activity refresh/path resolution, `ActivityService`, classic/Forte detail/count/cursor contracts and existing RSS formatting.

## Stage 3

- Goal: existing installations recover historical omissions automatically and safely.
- Dependencies: Stages 1–2 parity.
- Expected changes: advance read-model version 23 to 24 for corrected Activity derivation; document unchanged table shape and corrected row semantics. Use existing startup rebuild and write-fallback/retry paths; do not create replacement canonical likes or new recovery controls.
- Verification approach: seed a version-23 model missing the second like, start the normal application and verify recovery before Activity queries; run `./v3 rebuild`; exercise incremental failure/full-rebuild fallback and retry with one event per record and no extra canonical vote.
- Risks or open questions: accepted writes with stale presentation; verify existing partial/failure feedback and invalidation, repairing only wiring needed for this outcome.
- Canonical components/API contracts touched: `ReadModelMetadata`, startup freshness, read-model specification, existing write synchronization and Activity artifact invalidation.

## Stage 4

- Goal: demonstrate the reported recovery and close the scoped cycle.
- Dependencies: Stages 1–3 and their focused tests.
- Expected changes: isolated local ZenMemes incident fixture/snapshot, browser acceptance and affected Activity/operator documentation; summarize verification and study bookkeeping. No new navigation or unrelated checklist implementation.
- Verification approach: preserve original local repository read-only; recover the later `78e1f3f` contribution pattern for `thread-20261010002251-6cb36993` in a temporary copy/fixture. Use real Like actions, classic/Forte Activity/detail/RSS/count/pagination, phone/desktop loaded/empty/expanded/failure states and long metadata in affected themes. Run focused Activity/reaction/retraction checks, syntax and document-link checks; distinguish environment failures from regressions.
- Risks or open questions: fixtures passing without the user's ordinary flow; require browser discovery of the accepted later like and accurate historical links, and document remaining deployment/device limits explicitly.
- Canonical components/API contracts touched: existing signing/browser test harnesses, Activity/reaction regression suites, feature summary, read-model/Activity documentation and study ledger.

## Execution and study checkpoint

Four sequential stages, each targeting approximately one hour including focused verification and a summary-bearing commit before the next stage. Replan before dependent work if a stage exceeds that bound or scope changes. After `Approved Step 3`, create a distinct feature branch and organize this cycle's plans/debug log under `docs/plans/thread_like_activity/` before its scoped planning commit; preserve unrelated drafts and `.test_self_approval`.

2026-10-23 UTC; scope v1 unchanged; start `39be82932fcc36c5017fcf368655ef449bf042da`; Codex single exposed rater; R1/P2. Projection remains unavailable pending exact frozen calibration report/diff recovery; prior missing checkpoints remain missing. Added exposure: existing incremental history resolver, stable Activity keys, read-model migration tests and consumers. No measured effort, retrospective score or completed outcome. Await `Approved Step 3`; no implementation, branch or commit authorized yet.

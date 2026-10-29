> **Feature plan:** [Step 1](./fastmod_virtual_tags_step1_solution_assessment.md) · [Step 2](./fastmod_virtual_tags_step2_feature_description.md) · [Step 3](./fastmod_virtual_tags_step3_development_plan.md) · [Step 4](./fastmod_virtual_tags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_virtual_tags; [ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.70**
Status: Steps 1–2 approved; Step 3 proposed. Eight stages planned, none implemented.

## Completion Contract

Existing Fastmod scores supply effective root tags through normal reading, discovery, APIs and refreshed static/offline output. Human tags and legacy exclusions survive; disable/re-enable, stale/pruned/missing scores and ordinary refresh provide the approved recovery behavior. No provider calls on reads, canonical tag writes or new classification lifecycle. Release requires all stages and normal-browser acceptance; production deployment/activation and live-model accuracy are outside scope. No added dependencies; documents and ledger remain uncommitted until the approved workflow permits their commits.

## Key Risks

- **High risk:** Legacy corrections disappearing: fixture old suppressed/pending/active rows first; retain only suppression as read-only exclusions and preserve old tables for recovery.
- **High risk:** Removing tag freshness checks could remove unrelated safety: trace publication wrappers before edits; preserve atomic replacement, access policy and ordinary PublicationLock. Follow the retraction handoff; add no replacement ledger.
- Whole-store scans and inconsistent score selection: bulk-read private candidates, reuse content hashing/current rubric revision, filter before pagination; verify query count with fixture growth. A failed overwritten score is unavailable, not recoverable historical success.

## Stage 1 — Select derived mappings
**Estimate: 0.12**
- Goal/Dependencies: One reusable selector; none.
- Changes: Define mapping-to-public-root-label output; meta only, threshold/current revision/content matching, read-only legacy exclusions.
- Contracts: FastmodMetaRead, FastScoreContextFactory, FastScoringRubricRevision, FastmodTaggingPolicy; private scores never attached to public SQL.
- Verify: Threshold, missing/invalid/stale results, overwritten failure, historical roots and suppression fixtures.
- Risk/mitigation: Accidental writes; open private input read-only and test fresh/legacy/missing stores.
- Estimate driver/uncertainty: Selection semantics; legacy schema variants.

## Stage 2 — Use effective tags in live reads
**Estimate: 0.15**
- Goal/Dependencies: Normal discovery uses selector; S1.
- Changes: Generalize existing temporary overlay, bulk-match current roots, deduplicate human tags, wire shared readers. Any new core-dependent query follows CONTRIBUTING's schema-version/spec/prior-version-startup requirements in this stage.
- Contracts: RouteServices, ThreadRepository, TagGrouping; cards, Tags/Forte, text/detail APIs and public query paths.
- Verify: Filtering/counts/pagination, access/replies, off/on, score pruning/replacement, missing-store recovery, query-count growth; rebuild/startup regression as required.
- Risk/mitigation: Private leakage or stale hashes; public-label-only output and shared hash function.
- Estimate driver/uncertainty: Shared query integration; prior-schema fixture compatibility.

## Stage 3 — Stop classification writes
**Estimate: 0.08**
- Goal/Dependencies: Publication/scoring only save ordinary work/results; S2.
- Changes: Remove eligibility/evidence/recovery hooks without changing provider retry/backfill behavior.
- Contracts: RouteServices enqueue, FastScoreSweepService, completeWork, task_queue/fast_score worker construction.
- Verify: Ordinary/backfill completion, retry limits, unchanged legacy rows and canonical HEAD.
- Risk/mitigation: Score transaction regressions; focused store/sweep tests before removing callback machinery.
- Estimate driver/uncertainty: Bounded hook removal; shared callback consumers.

## Stage 4 — Simplify static builds/releases
**Estimate: 0.12**
- Goal/Dependencies: Ordinary static rebuild captures dynamic labels; S2–S3.
- Changes: Remove meta token/sidecar validation from building, serving and release activation; preserve existing atomic publication.
- Contracts: StaticArtifactBuilder, StaticArtifactReleasePublisher, FrontController.
- Verify: Build/activate/read, old sidecars tolerated, changed threshold reflected on rebuild, failed publication retains usable prior artifact.
- Risk/mitigation: Broader publication checks removed; review nested wrappers and retain unrelated checks.
- Estimate driver/uncertainty: Multiple build paths; existing release fixtures.

## Stage 5 — Simplify high-traffic/docs integration
**Estimate: 0.08**
- Goal/Dependencies: Cache refresh uses ordinary freshness; S4.
- Changes: Remove classification generation/lock dependencies from high-traffic render/mode changes and docs reuse identity.
- Contracts: HighTrafficCache, HighTrafficMode, DocsPublication; existing access and refresh policies.
- Verify: Mode toggle/refresh, private-instance refusal, docs reuse and refresh failure recovery.
- Risk/mitigation: Routing safety regression; retain existing safety locks/tests; no tag-specific invalidation replacement.
- Estimate driver/uncertainty: Narrow wrapper removal; concurrency fixture behavior.

## Stage 6 — Refresh offline labels
**Estimate: 0.08**
- Goal/Dependencies: Offline snapshots reflect effective labels; S2, S4.
- Changes: Keep shared read overlay, retire meta publication wrapping, retain ordinary snapshot publication.
- Contracts: PublicOfflineSnapshotBuilder, offline reader/export.
- Verify: Root tag discovery after rebuild; disable/prune/rebuild; downloaded snapshot stability, public-score exclusion and interrupted build recovery.
- Risk/mitigation: Export privacy; inspect exported tables/fields rather than only rendered badges.
- Estimate driver/uncertainty: Snapshot integration; shared fixture setup.

## Stage 7 — Retire CLI lifecycle and dead code
**Estimate: 0.08**
- Goal/Dependencies: Operators see the new contract; S3–S6.
- Changes: Read-only meta-status; retired remove/rollback/retry return actionable errors (disable shared tagging or repair scores/refresh as applicable). Remove unused runtime/store-writer/artifact classes; retain legacy data and exclusion reader.
- Contracts: fast_score.php, v3 help, Fastmod reference/runbook and CLI reference.
- Verify: CLI exit/output, non-default instance paths, no state initialization/writes; repository-wide retired-reference scan.
- Risk/mitigation: Unsafe downgrade; document stopped workers, retained private backup and rebuilding artifacts, not automatic rollback.
- Estimate driver/uncertainty: Compatibility cleanup; remaining test-only references.

## Stage 8 — Normal-flow acceptance and closeout
**Estimate: 0.12**
- Goal/Dependencies: Verify complete replacement; S1–S7.
- Changes: Adapt existing Fastmod browser journey to dynamic semantics; finish operations, UI audit and study records.
- Contracts: Existing signed flag/scoring/browser fixtures, shared tag views; no new UI or navigation.
- Verify: Signed enable → publish/score → browse meta → disable → refresh static/offline; historical score and preserved suppression cases. Phone/desktop relevant themes, no first-paint shift/overflow; focused PHP/browser suites and syntax/diff/link checks. Count provider calls and verify no canonical writes.
- Risk/mitigation: Old tests encode removed behavior; replace assertions with approved outcomes, preserving access/privacy regressions.
- Estimate driver/uncertainty: Cross-surface acceptance; existing environment failures reported separately.

## Study checkpoint and execution boundary

2026-10-29T18:44:39Z: Step 3 projection 0.70, revised from preserved Step 1/2 0.60 because source tracing found classification coupling in static activation, high-traffic mode locks and docs reuse, beyond the main read overlay. Scope remains v1/A; baseline 60518d9e7ce80b34c93587d73a04d999b3b462b8, current checkout c5783108; R1/P2, all three frozen hashes verified. Codex single exposed rater; missing pristine intake retained. S1–S8 estimates are original stage-work judgments, non-additive; whole-cycle planning is included only in the independent total, shared final acceptance/docs owned by S8. No measured labor or accepted outcome. Stages are intended as bounded review units; split any stage that inspection during implementation shows cannot fit the workflow's approximate one-hour bound before dependent work. No source changes/tests performed for this plan. Await Approved Step 3; then organize approved artifacts and make the planning-only commit on a feature branch before implementation.

*— Codex · 2026-10-29 UTC*

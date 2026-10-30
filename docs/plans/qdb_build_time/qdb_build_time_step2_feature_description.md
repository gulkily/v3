> **Feature plan:** [Step 1](./qdb_build_time_step1_solution_assessment.md) · Step 2 · [Step 3](./qdb_build_time_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_build_time; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_build_time)
Extended estimates: enabled

**Cycle estimate: 1.25**

## Problem and users

QDB now completes build-static but takes 314.961 seconds: commit indexing takes 39.1605 seconds, activity evidence 44.503 seconds, and 5215 profiles are prebuilt. Profile generation duration is unknown, so artifact counts cannot establish its share of elapsed time.

- As an operator, I want less unnecessary publication/history work so that full builds finish sooner without losing information or recovery.
- As a reader or moderator, I want profiles, commit counts and evidence to remain correct and accessible regardless of prebuilding choices.

## Core requirements

- Add a separate, explicit profile-prebuild control through the existing configuration/feature-flag system. Preserve existing defaults on all sites and the meaning of the thread/post detail flag. Disabled profile prebuilding omits profile artifacts from new releases while keeping normal profile URLs, author links and access checks working at runtime; enabled mode retains static-only delivery. Do not disable profiles or identity/approval derivation.
- Reduce unnecessary work in commit indexing while preserving every existing commit row, metadata field, file count and ordering contract, including root/merge commits, renames, binary changes and missing-history behavior. Do not replace counts with placeholders or omit history.
- Reduce repeated commit-manifest work across activity batches while preserving relevant files, signature status, attribution and error/missing-history semantics. Keep normal rebuild and incremental maintenance compatible; improvements must not introduce corpus-sized memory retention or an unbounded manifest cache.
- Preserve publication/recovery: no obsolete profile artifact may override the chosen delivery mode; full builds, shared refresh, profile changes, access-state changes, mode reversal and failed-candidate rollback remain coherent. Keep the last valid release usable if a candidate fails; an operator can retry without manual data repair.
- Measure profile publication, commit indexing and activity evidence separately against the same baseline, then measure the combined normal build. Keep PHP memory at or below the existing 128 MiB limit on representative fixtures; report process/child memory separately where available. No new dependencies, canonical-format changes, trust suppression, raised limits or unrelated optimization.

## Delivery and shared components

Work type: Application performance changes, focused regressions/benchmarks, operator guidance, catalog/concepts, cycle summary and study ledger. Reuse feature flags/configuration presentation, StaticArtifactBuilder/ReleasePublisher, FrontController, ProfilePageController/ProfileHistoryView and profile templates. Reuse ReadModelBuilder, ActivityEvidenceIndex and ActivityService for standard Activity, Forte Activity/Commits, source/commit details and related API readers; verify these consumers rather than creating an alternative history subsystem. Existing caches/signature handling remain authoritative. No new navigation destination or profile redesign.

Concepts touched: [Static publication](../../concepts/static_publication.md) and [read-model rebuild](../../concepts/read_model_rebuild.md) updated; profile delivery and history/evidence derivation entries proposed during implementation.

## Flow and completion

1. Operator explicitly selects runtime or prebuilt profile delivery, then runs the existing full build command.
2. Build derives equivalent history/evidence with less work, renders the selected artifact set and activates successfully.
3. Readers follow existing profile/evidence links; operator can switch back and rebuild, or retry after a failed candidate.

Known environment: /home/qdb/v3 on PHP 8.3, 128 MiB; baseline release-20261030201011-af62aa46. After separately authorized deployment/configuration, rerun the original command/settings on a recorded corpus revision, compare total/phase timings and artifact counts, and inspect profile/evidence links. Report corpus/settings/cache differences and exact errors. Local tests are not host acceptance; unavailable host access leaves a documented operator replay pending.

## Risks and early validation

- Wrong file counts/evidence can alter review or trust: compare complete outputs on root/merge/rename/binary and multi-batch commit fixtures before choosing the faster history path; block dependent integration on mismatches.
- Skipping artifacts can expose stale content or remove static-only access: validate frontend precedence, access states and both mode transitions before enabling the control; preserve enabled defaults and rebuild guidance.
- Reuse can trade time for memory or stale evidence: test cold/missing cache, a large commit crossing many activity batches and changed repository/key state; bound retained data and preserve fallback behavior.
- Combined scope may exceed one cycle: retain all three objectives but validate a maximum eight-stage/day plan before coding; propose a split if necessary rather than silently dropping an optimization.

Success criteria: Equivalent commit/evidence outputs and profile behavior; disabled mode generates zero profile HTML artifacts while enabled mode preserves them. Record at least three comparable local runs per variant with medians/ranges, separating cold/first and warm repeats; each target must show lower measured phase cost beyond observed noise on its representative workload, not merely fewer calls. Track Git invocations/manifest repetitions, artifact counts, peak memory and runtime profile latency; set fixtures and regression tolerances in Step 3 before implementation. Combined full-build/activation and retry pass without raising limits. Report measured improvement, not an invented production time target.

## Approval and study checkpoint

Step 1 Option C explicitly approved, 2026-10-30 UTC. Scope v2/C, baseline 4abda3dbd144370a3353781b29e8690e46d47d88 and R1/P2 retained; debug/extended enabled, Codex single exposed rater, three frozen hashes reverified. Whole-cycle 1.25 reaffirmed; original v1/A 0.50 remains historical. No implementation, measured labor, benchmark result, deployment or accepted outcome. Await Approved Step 2.

*— Codex · 2026-10-30 UTC*

Approval update: 2026-10-30 UTC. User approved Step 2 for combined Option C; Step 3 planning proceeds without expanding scope. Implementation remains gated.

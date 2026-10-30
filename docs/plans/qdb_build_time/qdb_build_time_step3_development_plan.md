> **Feature plan:** [Step 1](./qdb_build_time_step1_solution_assessment.md) · [Step 2](./qdb_build_time_step2_feature_description.md) · Step 3 · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_build_time; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_build_time)
Extended estimates: enabled

**Cycle estimate: 1.25**

## Completion Contract

Deliver all selected C objectives: optional profile prebuilding with unchanged defaults and working runtime/static-only delivery; equivalent commit counts/history with less diff work; bounded activity evidence reuse with unchanged trust/recovery. Measure each separately and the normal combined build. Preserve the previous release on failure. No new dependencies, canonical changes, wider feature removal, memory-limit increase or production configuration/deployment. Local release requires all eight stages and acceptance gates below; PHP 8.3 QDB replay remains explicitly pending without host access.

## Key Risks

- **High risk:** Faster Git output can change merge/rename/binary file counts. Freeze legacy outputs in Stage 1; Stage 4 must match before replacing indexing.
- **High risk:** Evidence reuse can lose relevant paths or retain huge manifests. Stage 5 uses operation-scoped disk-backed scratch lookup populated from a bounded stream; Stage 6 proves trust/error parity and cleanup before combined acceptance. No persistent cross-rebuild cache or schema for canonical records.
- **High risk:** Runtime mode can expose stale artifacts or break static hosting. Preserve enabled defaults; test public-root/current-release precedence, mode reversal and access checks in Stage 3.
- Noisy timing may obscure gains. Fix workloads and comparison protocol before optimization; inconclusive/failing results block performance completion rather than justify deleting work or raising limits.

## Stage 1 — Reproducible baselines and parity fixtures

**Estimate: 0.15**

- Goal/dependencies: Freeze comparative evidence; none.
- Changes: Isolated fixtures for 5215 profiles, 1000 ordinary commits with repeated substantial text changes, and 10000 activity events sharing a large 20000-path commit; smaller root/merge/rename/delete/binary/empty/missing-history and signed-evidence cases. Preserve a baseline checkout and full expected outputs; add per-phase and profile-render timing observations where absent.
- Verify: Legacy full build, output determinism, isolated destinations and repeatable measurement protocol; all fixture setup excluded from timing.
- Risk: Synthetic workload bias; report dimensions separately and never equate local seconds with host savings.
- Contracts: Existing CLI progress/timings, builder/evidence test fixtures; no behavior optimization yet.
- Estimate details: Shared baseline/fixture ownership; uncertainty: generating valid signed history efficiently.

## Stage 2 — Explicit profile delivery control

**Estimate: 0.16**

- Goal/dependencies: Enable fewer artifacts without losing profiles; Stage 1.
- Changes: Register FORUM_STATIC_PROFILE_PAGES_ENABLED, default true, through existing feature-flag/config machinery; place beside existing detail-page control without reordering other flags. Guard only profile enumeration; normal direct rendering remains. Register private-config/template behavior where applicable.
- Verify: Default/enabled artifact parity, disabled zero profile artifacts, direct author/profile links, unchanged profile/approval data and thread/post flag; config creation/refresh preserves settings.
- Risk: Accidental default/flag coupling; assert both controls independently across profiles.
- Contracts: FeatureFlagRegistry/evaluator, PrivateConfigSchema as applicable, StaticArtifactBuilder, normal profile controller and tests.
- Estimate details: Configuration-to-build vertical slice; uncertainty: flag/config propagation.

## Stage 3 — Profile transitions and recovery

**Estimate: 0.15**

- Goal/dependencies: Safe enabled/disabled delivery and rollback; Stage 2.
- Changes: Align frontend artifact eligibility and refresh/invalidation with the selected mode; preserve static-only enabled behavior. Add operator toggle/rebuild guidance and targeted UI verification.
- Verify: Full build and shared refresh, stale public-root/current artifacts, both mode transitions, cold/warm direct requests, profile changes and authorization, failed candidate retaining prior release; phone/desktop feature control and profile links.
- Risk: Stale or inaccessible profiles; resolve precedence/access failures before integration.
- Contracts: FrontController, release publisher/invalidator, feature-flag presentation and profile history/template.
- Estimate details: Serving/recovery matrix; uncertainty: legacy artifact precedence.

## Stage 4 — Cheaper streamed commit indexing

**Estimate: 0.20**

- Goal/dependencies: Equivalent commit rows without unused line-stat computation; Stage 1.
- Changes: Introduce CommitHistoryReader::rows(string repositoryRoot): iterable; stream metadata and changed-file counts, retaining legacy diff/merge policy and bounded parsing/error handling. Feed existing commit inserts; no renamed columns or truncated history.
- Verify: Complete legacy/new row equality for all fixture commit types and filenames, Git failure/missing history, ordering, empty repository, memory bounds and phase timings. Validate actual Git mode before replacing --shortstat.
- Risk: Counting changes differently; any discrepancy blocks integration. Do not disable rename detection as an unreviewed speed shortcut.
- Contracts: ReadModelBuilder::indexCommits, existing commits consumers and shared process facilities; platform facilities only.
- Estimate details: Streaming and Git semantic parity; uncertainty: Git configuration-dependent rename/merge behavior.

## Stage 5 — Bounded manifest reuse in evidence refresh

**Estimate: 0.23**

- Goal/dependencies: Avoid rereading one commit for successive 200-row activity batches; Stage 1, integrate after Stage 4.
- Changes: Add an operation-local manifest lookup, conceptually prepareCommit(string): void and filesForPaths(string, array): array. Stream one commit at a time to file-backed SQLite scratch storage; retain only requested source/signature/key entries in PHP. Reuse that commit across adjacent batches and release scratch on completion/failure. Preserve batched processing of small commits; avoid one process per event.
- Verify: Large commit crossing batches has equivalent per-row evidence and one successful manifest read per contiguous commit, with bounded PHP retention; signed/identity-bootstrap cases retain key-path details.
- Risk: Scratch I/O offsets gains or partial results appear complete; atomically mark loaded commits, test interrupted load, and do not publish partial evidence.
- Contracts: ActivityEvidenceIndex::refresh, ActivityService evidence construction; full commit-detail view keeps its contract. Scratch is disposable and absent from exported read model.
- Estimate details: Reuse without heap growth; uncertainty: scratch overhead and signature-path discovery.

## Stage 6 — History maintenance and failure parity

**Estimate: 0.16**

- Goal/dependencies: Correct evidence through normal maintenance and recovery; Stages 4–5.
- Changes: Wire operation lifecycle through full/incremental refresh, preserve missing-history fallback and changed key/repository behavior, and close cleanup/error gaps. Apply CONTRIBUTING schema/version/spec/prior-version startup requirements if core queries change; no version bump solely for private scratch storage.
- Verify: Activity/Forte/Commits/source/API readers, signature/attribution decisions, rename/path roles, repeated refresh, failed Git/temp storage, interrupted candidate and successful retry. No stale operation state crosses refreshes.
- Risk: Optimized full rebuild diverges from incremental maintenance; compare complete resulting evidence and relevant counts before combined release.
- Contracts: Existing read-model recovery, evidence/signature services, normal consumers and regression suites.
- Estimate details: Compatibility/failure evidence; uncertainty: inherited missing-history cases.

## Stage 7 — Separate and combined performance acceptance

**Estimate: 0.15**

- Goal/dependencies: Demonstrate real reduction rather than presumed savings; Stages 2–6.
- Changes: Run frozen baseline, profile-only, commit-only, evidence-only and combined variants on the same fixtures. Record invocation/manifest counts, artifacts, wall/CPU time, phase time, PHP peak and process/child RSS where available. No optimization outside approved targets.
- Verify: Protocol below, full build/activation, memory ceiling, complete output parity and direct profile latency; include many small commits and one large shared commit. Keep all failed/noisy samples.
- Risk: Speedup is hidden by noise or memory/disk regression; repeat matched runs without changing the gate or silently substituting a favorable fixture; replan if objectives fail.
- Contracts: Stage 1 harness, normal ./v3 rebuild/build-static and profile frontend.
- Estimate details: Comparative measurement ownership; uncertainty: host/cache variance.

## Stage 8 — Documentation and host replay handoff

**Estimate: 0.10**

- Goal/dependencies: Reviewable complete delivery and unaided QDB retry; Stage 7.
- Changes: Finalize findings/catalog, concepts, configuration/CLI guidance, implementation summary and scoped ledger; record every stage commit and dependency delta.
- Verify: Focused configuration/profile/history/static checks, touched doc links and final behavior; report browser evidence and limits. Provide exact control name, unchanged original build arguments and recovery steps for /home/qdb/v3 PHP 8.3; compare with 314.961s / 39.1605s / 44.503s baseline and record corpus/config differences. Host operation only with separately supplied access/authority.
- Risk: Local success mistaken for host acceptance; leave host timing/links pending until operator evidence, never promise a target runtime.
- Contracts: Static-publication/read-model concepts updated; new profile-delivery/history-evidence entries, catalog and operator guidance. Owns shared closeout documentation.
- Estimate details: Consolidation and replay instructions; uncertainty: unavailable live host.

## Measurement and execution gates

Three matched fresh-process runs per baseline/variant, alternated on the same prepared corpus; record first-run and warm repeats separately, without global cache flushing. For each target workload, require lower median phase wall time by more than the larger observed within-variant range; otherwise report inconclusive and repeat matched runs. Memory ceiling is 128 MiB, with PHP peak no more than 4 MiB above baseline on ordinary matched fixtures; large-commit proof must independently stay under 128 MiB. Disabled mode must generate zero profile HTML while preserving normal outputs elsewhere. Runtime profile median must stay within 10 percent plus 10 ms of the same baseline forced-runtime route; report loss of static-hit speed separately. Combined build must activate and retain parity/retry behavior; timing gates are local evidence, not production guarantees.

Before implementation: new feature branch; organize this cycle under docs/plans/qdb_build_time/, repair links/index and make its planning-only commit including the existing findings/debug artifacts. Create summary afterward; commit each completed stage with its summary before the next. Exclude unrelated draft audits and prior-cycle host update from the planning commit. Include only this cycle's ledger changes at closeout. Eight stages target at most one working day, each about an hour; if feasibility or a dependent risk fails, stop and propose a split/revised plan before overrun.

## Study checkpoint

Step 2 Option C approved, 2026-10-30 UTC. Scope v2/C, baseline 4abda3dbd144370a3353781b29e8690e46d47d88, R1/P2 and single exposed Codex rater retained; frozen hashes reverified. Whole-cycle 1.25 reaffirmed, independently judged; original stage-work estimates 0.15/0.16/0.15/0.20/0.23/0.16/0.15/0.10 are non-additive. Earlier v1/A 0.50 preserved. Combined scope preference honored with explicit eight-stage ceiling; no implementation, measured labor, benchmark results or accepted outcome. Await Approved Step 3.

*— Codex · 2026-10-30 UTC*

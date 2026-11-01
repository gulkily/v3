> **Feature plan:** [Step 1](./application_capacity_study_step1_solution_assessment.md) · [Step 2](./application_capacity_study_step2_feature_description.md) · [Step 3](./application_capacity_study_step3_development_plan.md) · [Step 4](./application_capacity_study_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/application_capacity_study; [P2 ledger](.././effort_estimation_trial.md#cycle-v3application_capacity_study)
Extended estimates: enabled

**Cycle estimate: 0.90**

## Completion Contract

- Documentation-only investigation within Step 2's approved cycle artifacts, archived probe source/results, concept corrections and cycle-specific ledger/debug records. Disposable execution stays outside the repository. Deliver reproducible normal-entry measurements, explicit family dispositions, recovery evidence, a qualified capacity envelope and ranked follow-ups; retain failed samples and reset/resume instructions. No application/test/configuration changes, dependencies, production load or fixes.
- Completion requires every family and matrix dimension to be tested, inapplicable or explicitly deferred with reason and consequence. Missing host evidence limits claims. Deployment/schema/UI-change checks are inapplicable; normal-flow correctness remains applicable. S7 owns report integrity and operator handoff.

## Key Risks

- **High risk:** Accidental real-state mutation: S1 proves isolated repository/database/artifact/image/queue paths, loopback-only targets and disabled external providers before writes; failure blocks dependent runs.
- Misleading capacity: S1 pins fixture/revision/environment and measurement overhead; S6 separates actual parallel workers from serialized servers. Unavailable metrics/host access remain gaps, never inferred successes.
- Excessive matrix cost: bounded representative samples, one-factor variations and explicit deferrals; if meaningful coverage cannot fit seven roughly hour-sized stages within a day, stop and rescope before execution expands.

## Stage 1 — Reproducible measurement contract

**Estimate: 0.12**

- Goal: Establish fixtures, measurement validity and safe execution; dependencies: approved plan.
- Expected changes: Archive fixture recipe, coverage matrix, runner source and Key: value sample format in cycle docs; temporary harness only where existing probes lack metrics.
- Verification approach: Smoke-test isolation, teardown and parity on a tiny fixture. Pin 1000/10000/40000-record sets, fixed result sizes, separate body/reaction/history/pin variations and exact maximum-valid-record boundary. Record cold/warm definitions, three repeats, PHP/RSS/I/O/time and loaded/used counts or explicit unavailable reasons.
- Risks or open questions: Before runs, cap each command at 600 seconds, concurrency at 1/2/4, request windows at 30 seconds, PHP at 128 MiB; stop on semantic mismatch, aggregate sampled RSS above min(1 GiB, 25% initial available RAM), or scratch use above 2 GiB. Missing supervision blocks overlap. Ordinary-read p95 target 1 second and zero unexpected errors are study criteria, not host promises.
- Canonical components/API contracts touched: Reuse archived QDB probes, `scripts/qdb_capacity_probe.js` workload definitions and existing fixture helpers; no k6 installation. S1 owns common harness overhead/reset validation.
- Estimate details: Stage work; driver: reproducibility; uncertainty: instrumentation availability.

## Stage 2 — Reads and authenticated writes

**Estimate: 0.16**

- Goal: Measure ordinary reading/posting; dependencies: S1.
- Expected changes: Family results for QDB `/`, `/latest`, `/top`, `/search`, shared thread/profile/API reads and signed session/posting; include anonymous, hint and authenticated distinctions.
- Verification approach: Fixed-page IDs/order/body parity, static versus PHP delivery, large-thread/record cases and duplicate-safe retry; reuse QdbBoardPolicyTest, AgentPostingApiTest and SingleRequestPostingTest expectations.
- Risks or open questions: Auth setup can bypass real work; verify actual HTTP responses and persisted bytes before accepting samples.
- Canonical components/API contracts touched: `Http/ApiRouteCatalog.php`, ThreadRepository and QdbBoardPolicy; existing auth challenge/session and create_signed_post/get_post routes, unchanged.
- Estimate details: Stage work; driver: normal-flow fixtures; uncertainty: signing setup.

## Stage 3 — Rebuild, publication and history

**Estimate: 0.18**

- Goal: Measure operator maintenance through activation; dependencies: S1.
- Expected changes: Rebuild/static/offline and Activity/Git evidence results, including large commits and interrupted candidate/build retry.
- Verification approach: Run `./v3 rebuild`, `build-static`, `build-static --shared-only`, `offline publish` against isolated paths; verify activated output and retained prior release after interruption. Reuse ReadModelCandidateBuilderTest, PublicOfflineSnapshotBuilderTest and ActivityProjectionTest expectations.
- Risks or open questions: Historical QDB failures may be fixed; replay archived cases at current revision and retain original provenance. Supply PHP 8.3/FastCGI host replay/retry instructions; unavailable actual host evidence remains unverified.
- Canonical components/API contracts touched: ReadModelBuilder, StaticArtifactBuilder/ReleasePublisher, PublicOfflineSnapshotBuilder and history-evidence contracts from Step 2; no publication-guard redesign.
- Estimate details: Stage work; driver: lifecycle verification; uncertainty: full-build duration.

## Stage 4 — Import, export and images

**Estimate: 0.14**

- Goal: Measure transfer costs and recovery; dependencies: S1.
- Expected changes: Download/upload/import family report and archived round-trip/reset commands.
- Verification approach: `/downloads/repository.tar.gz`, `/downloads/repository.zip`, `/downloads/images.zip`; `./v3 import-repository`, loopback `import-instance --resume`, `images verify|restore`; exact record/image parity and interrupted retry, using ArchiveExportTest, ContentImportRecoveryTest and ImageUploadValidatorTest contracts.
- Risks or open questions: Archive layouts differ; preserve format distinctions and report unsupported round trips rather than silently repacking as success. Upload through multipart `/api/create_prepared_post`; preserve drafts on refusal.
- Canonical components/API contracts touched: InstancePageController, WritePostAndIdentityApiController, ContentImportRunner, ImageBackup/validator; unchanged formats.
- Estimate details: Stage work; driver: transfer parity; uncertainty: fixture compatibility.

## Stage 5 — Scoring and queue maintenance

**Estimate: 0.10**

- Goal: Measure local worker lifecycle; dependencies: S1.
- Expected changes: Scoring/queue results and retry guidance, separately labeling provider execution unavailable.
- Verification approach: `./v3 fast-score status|audit` and `task-queue status|run` on isolated queues; repeated batches, interruption/retry and duplicate-job outcomes against TaskQueueWorkerTest/FastmodScoreTransferTest contracts.
- Risks or open questions: Workers may launch external calls; inspect selected jobs and local test doubles first, block any provider-enabled run. A double measures local overhead only.
- Canonical components/API contracts touched: TaskQueueWorker, SqliteFastScoreStore and existing local provider fixtures; no scoring semantics changes.
- Estimate details: Stage work; driver: worker recovery; uncertainty: available local seams.

## Stage 6 — Controlled overlap and persistence

**Estimate: 0.14**

- Goal: Measure shared-resource headroom; dependencies: S2–S5 dispositions and S1 supervision.
- Expected changes: 1/2/4-client reads, reads overlapping one maintenance job, and persistent-worker samples where available.
- Verification approach: Repeat 30-second windows at each level; record achieved throughput, p95/errors, total sampled RSS, child processes and post-run correctness/retry. Compare isolated baselines; qualify sampling gaps and PHP-only limits.
- Risks or open questions: Serialized PHP development servers cannot establish FastCGI capacity; require verified parallel processes or label overlap unavailable. Stop at the first resource/semantic failure and retain evidence.
- Canonical components/API contracts touched: Same ordinary HTTP/CLI entries; reuse S1 collector without production instrumentation.
- Estimate details: Stage work; driver: attribution; uncertainty: worker model.

## Stage 7 — Capacity report and handoff

**Estimate: 0.10**

- Goal: Deliver reproducible conclusions; dependencies: S1–S6.
- Expected changes: Envelope, budget comparisons (public 16/32 MiB; maintenance 48/64 MiB), ranked remediation and limitations; update scoped concepts and study/debug records.
- Verification approach: Reproduce one representative result and one recovery; check all coverage dispositions, commands, links, provenance and zero dependency/runtime delta. Distinguish local results, historical user-reported recovery and unverified host replay.
- Risks or open questions: Unsupported extrapolation: restrict conclusions to measured configurations and durations; corrections append evidence without erasing failures.
- Canonical components/API contracts touched: Step 2's seven linked concepts unchanged; add proposed resource-measurement inventory entry. No UI/theme changes; shared role/consumer outcomes belong in measured-family evidence.
- Estimate details: Stage work; driver: evidence integrity; uncertainty: gaps requiring narrower conclusions.

## Study checkpoint

Date: 2026-10-31 UTC. Step 2 v1/B approved unchanged; Step 3 awaits approval. R1/P2 and original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08 retained; current inspection 3dc2c3feaa17af591eb6e819a055d26ab6990efb. All three frozen hashes verified; Codex single exposed rater. Whole-cycle 0.90 independently reaffirmed for bounded local investigation with explicit unavailable-family/host evidence; S1–S7/v1 stage-work originals above are non-additive and not hours. S1 owns shared setup, S7 consolidation; implementation begins only after branch and planning commit. Prior scores/exposure retained; no campaign, measured labor or accepted outcome. Plans remain uncommitted.

*— Codex · 2026-10-31 UTC*

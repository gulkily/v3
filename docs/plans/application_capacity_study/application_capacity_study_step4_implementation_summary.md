> **Feature plan:** [Step 1](./application_capacity_study_step1_solution_assessment.md) · [Step 2](./application_capacity_study_step2_feature_description.md) · [Step 3](./application_capacity_study_step3_development_plan.md) · [Step 4](./application_capacity_study_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/application_capacity_study; [ledger](../effort_estimation_trial.md#cycle-v3application_capacity_study)
Extended estimates: enabled

**Cycle estimate: 0.90**

## Authorization and entry

Date: 2026-11-01 UTC. User “approved step 3” authorizes v1/B unchanged. Branch: feature/application-capacity-study. Planning commit: e22df83f. Original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08 retained; measurement source is the planning commit parent (recorded in environment evidence). Documentation-only boundaries remain in force. No merge/push/deployment.

Step 4 entry: 0.90 whole-cycle projection reaffirmed; R1/P2 exact three hashes reverified. Codex single exposed rater; prior inspection/plans exposed, no independent rating or reliable labor measurement. Maintainer fit and study acceptance pending. Stage originals copied unchanged: S1 0.12, S2 0.16, S3 0.18, S4 0.14, S5 0.10, S6 0.14, S7 0.10; scopes and uncertainties remain in Step 3, non-additive. S1 owns shared setup, S7 consolidation.

Reuse: QDB archived probe provides canonical record recipe; tests/fixtures/parity_minimal_v1 provides small contract fixture; scripts/qdb_capacity_probe.js supplies routes and 30-second workload convention. Existing CLI/router/controller contracts remain unmodified. New archived standard-library collector is necessary because those probes do not collect bounded process-tree RSS/I/O or supervise ordinary CLI/HTTP entry. No dependencies added.

*— Codex · 2026-11-01 UTC*

## Stage 1 - Reproducible measurement contract

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Archived setup/collector source, fixture recipe, bounds, metric definitions and environment. Source copy and sanitized configuration isolate all writes; canonical CLI reused.
- Verification: Tiny normal rebuild exited 0; all three synthetic bodies/counts matched. Three collector overhead samples retained. Scope/whitespace/navigation checks passed. No dependencies or runtime changes. UI/deployment/schema checks inapplicable.
- Notes: OS-cold cache and exact internal loaded counts unavailable. Process sampling misses short-lived children. Larger fixture execution and variant dispositions follow; no capacity inference from the smoke test. Same R1/P2/Codex exposure; estimate unchanged.

*— Codex · 2026-11-01 UTC*

## Stage 2 - Reads and authenticated writes

**Original estimate: 0.16**
**Retrospective stage work: 0.20**

- Changes: Archived normal HTTP read/signing/session drivers and raw samples. Three requests per route at 1000/10000 records; fixed 25 synthetic IDs for latest/top/search with stable order; numeric quote/API/activity successful. Source/database body parity passed. Exact 16 MiB source rebuild and quote/API reads passed separately.
- Verification: Signed first post plus two identical retries preserve bytes and commit; challenge authentication, profile and exact API body checks pass. Unapproved session correctly stays unapproved (auth_status 401). Existing helper generates/signs disposable key material; HTTP still handles writes. S1 commit: 0b391173. Scope/links/whitespace pass; no product changes.
- Findings: 40000-record ordinary rebuild exhausts 128 MiB in ReadModelBuilder.php:211 during parsing. Dependent reads deferred; 10000-record preparation already consumes 108 MiB PHP, exceeding maintenance targets. Smaller reset/rebuild succeeds. Maximum-record path is separate, not a guarantee for many large bodies.
- Limitations: Anonymous reads and signed/unapproved session tested; approved-member, hint-only, theme/browser, long-thread, reaction-density and pin variations deferred to avoid claiming broad role/shape capacity from a representative corpus. Consequence: no claims for those populations. Server static release delivery belongs to S3; actual FastCGI remains unavailable.
- Harness corrections: Initial nonexistent /thread route failed 404; corrected to canonical numeric quote path. Search changed from subject-only term to body term with 25-ID assertions. Initial router ignored auto_prepend_file; HTTP allocation samples absent until a disposable wrapper required telemetry then the unchanged router. Explicit session.save_path and TMPDIR added before final runs; preliminary sessions used PHP defaults and are excluded from isolation claims. An accidentally overlapping fixture reset invalidated signed-session-http and initial max-record-rebuild; retained, excluded, rerun serially. Profile initially asserted body text instead of identity; corrected to identity plus get_post body contract and rerun. Initial repeated prepare/read labels overwrote stdout; failed read log was preserved separately, cumulative metrics retain attempts, final collector refuses overwrites.
- Notes: Reproduction uses final drivers, setup and collector; s2.py is the initial campaign, not an instruction to run failed preliminary campaigns again. Stage retrospective reflects harness correction; whole-cycle projection remains 0.90, no scope change. Runtime/UI/deployment checks outside evidence claims inapplicable.

*— Codex · 2026-11-01 UTC*

## Stage 3 - Rebuild, publication and history

**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Three ordinary rebuild samples each at 1000/10000, three 1000-record full/static/shared/offline publications, and three normal rebuilds of existing 1000-commit history fixture. Canonical builders/publishers reused. S2 commit: 66d316c1.
- Verification: All measured rebuilds passed count/every-body parity. Full static builds completed activation; normal HTTP reads passed afterward. Forced SIGKILL during candidate work retained exact prior active HTML hashes; same command retried and activated matching page paths. Offline 201 selected post bodies match source. History contains all 1001 commits; HTTP activity/shared thread/API passed. Source/scope/link/whitespace checks pass.
- Limitations: 40000 rebuild remains failed, so large-scale static/offline downstream cases deferred. Historical 100000-path commit and long-thread/reaction/pin matrix deferred: valid-content generation and growth would add a different workload beyond the bounded representative runs; existing direct-method evidence is not rebranded normal-entry acceptance. PHP 8.3/FastCGI incident replay remains unverified. No throughput claim for history or deployment.
- Harness correction: Initial recovery comparison included every retained release, making the post-retry path-set assertion fail; corrected to static/current. Prior active content retention had passed. Initial history fixture collector hit a transient du missing-file race; fixture child finished, but that attempt lacks timing and is excluded. Collector now accepts transient du disappearance and kills/reaps on other supervision errors; clean repeat passed. No application fixes.
- Notes: Same R1/P2/exposed rater; stage estimate unchanged. UI/theme/deployment/migration checks inapplicable to documentation-only work.

*— Codex · 2026-11-01 UTC*

## Stage 4 - Import, export and images

**Original estimate: 0.14**
**Retrospective stage work: 0.18**

- Changes: Normal HTTP TAR/ZIP/image downloads repeated three times; 1025 canonical file hashes match both content archive formats. Approved multipart image preparation/upload and retry pass exact record/image/commit parity after invalid bytes rejected. Initial unapproved image request correctly refused. S3 commit: 2e531995.
- Verification: Loopback import-instance dry run passed. Final import interrupted after saved journal, source server stopped, then ordinary --resume completed; 1000 synthetic records match exact bytes and database count, journal cleared, repeated resume reports no pending import. Images verify/restore/repeated restore/verify all pass and restored bytes match. Scope/links/whitespace passed; zero dependencies.
- Finding: import-repository rejects the unchanged /downloads/repository.tar.gz layout (repository/records); error expects records/ or local_repository/records/. Retained failure, no silent repacking. import-instance supports this source and provides the demonstrated recovery. Distinguish full snapshot restore from additive import: authority/settings are excluded and images transfer separately.
- Collector correction: InvocationSupervisor uses setsid children; process-group-only sampling/kill missed them. Initial interruption let the child finish, so resume correctly found no pending work; excluded from recovery acceptance. Final collector discovers PPID descendants and clean-destination rerun passed. Earlier RSS/I/O remain explicitly incomplete lower bounds. Public/maintenance PHP allocations and completed command timings remain valid where separately recorded.
- Limitations: One tiny image, not maximum-size/image-count capacity; browser draft recovery unverified (driver retains draft). Import content parity is synthetic subset plus explicit exclusions, not proof that authority is transferred. Large import and interruption during archive download/image restore deferred; this small restore completed too quickly for a meaningful interruption. Same R1/P2/exposed rater; whole-cycle 0.90 retained. Runtime changes/deployment/UI/schema checks inapplicable.

*— Codex · 2026-11-01 UTC*

## Stage 5 - Scoring and queue maintenance

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Three normal fast-score status/audit samples and local rebuild queue batches on a fresh isolated queue. Explicit zero synthetic pricing inputs permit audit without configured provider/pricing; no external evaluations run. S4 commit: 033c7804.
- Verification: Duplicate enqueue reports already outstanding. Three normal workers complete rebuild and exact 1000-body/count parity. Interrupted worker leaves a running claim; ordinary retry does not immediately reclaim it. Backdating only that disposable claim simulates expiry, then ordinary worker reclaims/completes attempt 2. All final jobs completed. Scope/link/whitespace checks pass.
- Findings: Interrupted claims use a 900-second abandonment threshold; recovery test simulates time rather than waiting 15 minutes. Immediate run completed another local job, not the interrupted claim. Do not tell operators reset-recovery resets arbitrary running jobs: it resets automatic schema-recovery state. Initial audit without price options failed with actionable guidance and is retained.
- Limitations: Empty score store; historical audit inspects source content but provider scoring and populated score-store scaling are deferred because external calls are prohibited. Local doubles would measure only adapter overhead and were not substituted for provider capacity. No long-duration queue soak or real-time 900-second wait. Same R1/P2/exposed rater, estimates unchanged. UI/deployment/schema checks inapplicable.

*— Codex · 2026-11-01 UTC*

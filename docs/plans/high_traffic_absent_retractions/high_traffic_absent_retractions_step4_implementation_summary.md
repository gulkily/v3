> **Feature plan:** [Step 1](./high_traffic_absent_retractions_step1_solution_assessment.md) · [Step 2](./high_traffic_absent_retractions_step2_feature_description.md) · [Step 3](./high_traffic_absent_retractions_step3_development_plan.md) · [Step 4](./high_traffic_absent_retractions_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort`, cycle `v3/high_traffic_absent_retractions`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_absent_retractions), R1/P2.

## Entry

Step 3 approved. Branch `fix/high-traffic-absent-retractions`, planning commit `bf659506f`, original baseline `bce2c29e3`. **0.75 total reaffirmed** before implementation; scope v1, Codex single exposed rater, prior exposure and 0.65 snapshots retained. Extended estimates not enabled. Five stages pending. No dependencies, schema changes, deployment or production re-enable authorized. Production remains on ordinary PHP recovery. Unrelated README changes and standalone investigation remain outside stage commits.

## Stage 1 - Scoped evidence admission

- Changes: HTML-only context reuses safe absent-target classification and pins evidence, repository head and canonical-content fingerprint. Reappearance, uncommitted edits and unrelated changes invalidate HTML candidates; strict publication/snapshot semantics remain unchanged.
- Verification: `php tests/run.php HighTrafficPublicationTest AbsentRetractionSnapshotTest` passed 14/14; production IDs, malformed/unsafe evidence, changed inputs and unchanged strict rejection covered. No dependency/schema change.
- Notes: projection remains 0.75. New context is not wired into cache until render isolation is verified. Stage 2 will use a fresh disposable derivation only for absent-target rendering, avoiding stale live rows and preserving the canonical source.

## Stage 2 - Safe HTML render input

- Changes: absent-target rendering derives a disposable full read model from canonical records, with build-only filtering of excluded posts, explicitly linked generated responses and source-linked labels. Content-free root structure preserves direct surviving-reply access under existing board policy. Existing live model and canonical records are not modified; ordinary indexing has no exclusions. Missing canonical sources throw the existing typed error without redundant PHP warnings.
- Verification: focused HTML/candidate/snapshot checks passed 21/21; final HTML/parser checks passed 51/51 after adding independent-label preservation. Actual homepage/thread rendering contains no stale root/body/generated secrets; surviving reply and independent Like remain; live SQLite hash unchanged. Initial fixture setup/board-policy assertions corrected before passing final checks.
- Notes: Stage 1 commit `89f593d99`. Fresh derivation occurs only in cold/refresh renderer processes for absent-target contexts; existing 30-second worker limit will bound it. No schema/dependency changes; no cache wiring yet.

## Stage 3 - Normal cache and refresh publication

- Changes: parent and isolated worker agree on a content/evidence identity; absent-target workers render the excluded disposable model, revalidate after rendering and before atomic publication, and clean model/assets workspace on exit. Unaffected strict publication remains strict. Private SQLite models never enter public asset copying.
- Verification: `php tests/run.php HighTrafficTest HighTrafficPublicationTest` passed 9/9, including actual normal front-controller homepage with both production IDs, every eligible route class, configured CLI JSON refresh, no live-model mutation, private-model exclusion and temporary cleanup. Existing capacity/slot/atomic replacement checks pass.
- Notes: Stage 2 commit `e8caa8b8c`. No unlimited fallback or production changes. Parent/worker mismatch rejects safely; existing per-render time/output limits remain. Five-stage plan and 0.75 projection unchanged.

## Stage 4 - Races and shared-consumer recovery

- Changes: deterministic real-worker wrappers mutate canonical sources/evidence or withdraw mode after rendering; rejected candidates preserve the previous page and leave no temporary model/assets. Tests cover later valid source reappearance, repaired evidence, worker failure and retry. Missing tracked retractions and unsafe source symlinks remain rejected.
- Verification: focused high-traffic/retraction/snapshot/queue suites passed 55/55. Strengthened race suite passed 7/7. `python3 tests/high_traffic_publication_test.py` passed 2/2 import/static-publication interruption/private-destination regressions against real code with bounded frozen documentation inventory; not a full-corpus/full-suite claim.
- Notes: Stage 3 commit `75b8c876e`. Strict source-removal and offline callers retain their contracts. No dependency/schema or production changes; 0.75 projected total preserved.

## Stage 5 - Apache acceptance and operator handoff

- Changes: actual Apache and browser fixtures include both production absent-target IDs. Apache replay deliberately leaves stale secret rows in the live SQLite model; cold HTML excludes them, warm pages bypass PHP, refresh advances output and live SQLite hash stays unchanged. Browser checks cover signed controls/actions, loaded phone/desktop thread pages and all shared control themes. Runbook includes deploy-while-disabled, enable then explicit warm, cache-hit verification, once-per-minute cron installation, failure disable/restore and host acceptance.
- Verification — passed: final focused PHP command (HighTrafficTest, HighTrafficPublicationTest, AbsentRetractionSnapshotTest, ReadModelCandidateBuilderTest, CanonicalRecordParsersTest, RetractionSweepTest, TaskQueueWorkerTest, PublicOfflineSnapshotBuilderTest, PublicOfflineSnapshotManifestTest, OfflineSnapshotPublishCommandTest) **108/108**. `python3 tests/high_traffic_apache_test.py` passed real cache/cold/refresh/private/import/recovery acceptance; existing 20-page refresh completed within 3.913 seconds on this local fixture. Expected injected `/bin/false` refresh logs an error while preserving old HTML. `node tests/browser/high_traffic_control.mjs` passed real signing, cached actions/hints, unauthorized/failure/retry, 13-theme phone/desktop controls and loaded thread overflow checks. Visually inspected phone/desktop screenshots at `/tmp/high-traffic-browser-KbZzgQ/absent-target-thread-{375,1150}.png`; controls/content fit. Bounded static/import recovery passed 2/2 in Stage 4. Diff and syntax checks passed.
- Refinement: final acceptance retained stale-tolerant routine writes for ordinary contexts with no absent targets, while pinning the complete canonical fingerprint/head for excluded derivations. This preserves existing no-starvation behavior for ordinary builds; excluded builds conservatively reject changed inputs and retry. Stage 4 commit `d4b41fe94`.
- Additional resource check: disposable excluded-mode indexing against the actual local 1,794-post canonical corpus completed in 3.187 seconds within a 30-second process limit. This checks local derivation cost, not DreamHost latency; its corpus had no absent retractions, so production absent-target semantics remain covered by the targeted fixtures. Original corpus/live model were not modified.
- Pending/not claimed: DreamHost deployment, production enable/warm/cache-hit/cron verification, physical devices, full-corpus/full-suite and broad throughput benchmarks. No UI copy/navigation changes; no icon mapping changes required. No new dependencies, schema migration or canonical repair. Production remains on user-confirmed ordinary-PHP recovery until operator deploys/enables the fix.

## Study closeout and release limits

2026-10-24T05:21:41+00:00: all five approved local stages complete. Original baseline `bce2c29e37be4a06a25e2a3c6e022249e8d90101`, planning `bf659506f`, stage commits `89f593d99`, `e8caa8b8c`, `75b8c876e`, `d4b41fe94`, and Stage 5 commit recorded by Git handoff. Scope v1, stable `v3/high_traffic_absent_retractions`, R1/P2, Codex single exposed rater. Preserve intake/Step 2 0.65, Step 3/entry **0.75 projected total**. Separate retrospective scope assessment: **0.75**, reflecting concentrated HTML derivation, safety races and existing Apache/browser acceptance reuse. Measured active labor unavailable; maintainer comfortable-fit/acceptance pending, no accepted-count increment or duplicate snapshot-study outcome. Ordinary fixture/assertion corrections and no-absent stale-write compatibility are corrective work inside scope; no new requests or scope split. Dependency delta zero. Read-model core schema unchanged. Deferred: generic offline HTTP bootstrap fatal-error handling and same-host deployment verification. Standalone investigation and pre-existing unrelated README changes remain outside cycle commits.

Release from this branch requires normal maintainer review/integration and the [production checklist](../../runbooks/high_traffic_mode.md#zenmemes-production-recovery-checklist). Do not call local acceptance production recovery; record host checks separately.

Branch provenance: original study baseline remains `bce2c29e3`. The planning commit’s actual parent is `75b8ec6db`, a concurrent documentation-only retraction-diagnostics commit; it is preserved, not attributed to this implementation. Unrelated README edit remains unstaged.

## Merge handoff — 2026-10-24T05:24:56+00:00

User explicitly requested merging and pushing. Integrated current origin/main `4f75d2a1a` and local Railspike/documentation work before merging the tested feature. Resolved only additive plan-index/test-registration conflicts by retaining both entries. Merged-tree verification passed: focused runtime 108/108; independent documentation-publication 23/23; actual Apache acceptance (including both absent production targets and stale SQLite) and signed browser/phone/desktop checks. No new dependency change introduced by this fix. Production deployment/re-enable/cron verification remain pending; pushing main does not perform them. Unrelated workspace documentation was temporarily stashed for integration and is restored separately from the merge.

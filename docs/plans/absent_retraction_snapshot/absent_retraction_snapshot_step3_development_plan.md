> **Feature plan:** [Step 1](./absent_retraction_snapshot_step1_solution_assessment.md) · [Step 2](./absent_retraction_snapshot_step2_feature_description.md) · [Step 3](./absent_retraction_snapshot_step3_development_plan.md) · [Step 4](./absent_retraction_snapshot_step4_implementation_summary.md)

Debug mode: enabled (inherited).
Effort study: `railspike-effort`, cycle `v3/absent_retraction_import`; [host ledger](../effort_estimation_trial.md#cycle-v3absent_retraction_import), standing enrollment P2.

## Completion Contract

Existing direct command and queued worker publish usable base/update snapshots despite valid retained retractions for absent targets; the normal offline reader excludes those targets, including old base copies, while retaining surviving replies and privacy boundaries. Target reappearance stays excluded for that build; later builds validate present sources normally. Unsafe/unisolatable evidence retains actionable failure and existing publication recovery. Reproduce task-952 conditions in isolated fixtures, never retry live tasks or repair canonical data. Release requires actual reader/CLI/worker acceptance, shared-publication regressions and documented limits; deployment remains outside scope.

## Key Risks

- **High risk:** stale base/derived content can leak: reader `applyUpdate` only replaces included threads. Resolve before dependent work with explicit target exclusions, including targets outside the update window; validate actual base-plus-update consumption.
- **High risk:** global absence tolerance could manufacture authority or hide corrupt evidence. Keep snapshot-specific exclusion separate from ordinary authority validation; establish flat/dated absence and unsafe-path distinctions first, then test later-build author/protected-post behavior.
- **High risk:** target reappearance can invalidate generation checks or permit unrelated changes. Bind exemptions to the build's confirmed absent IDs, retain unrelated-change checks, and resolve concurrency semantics before snapshot publication wiring.

## Stage 1 — Bound snapshot validation

- Goal: distinguish confirmed absence without relaxing other consumers.
- Dependencies: approved Step 3 and initial planning commit.
- Expected changes: introduce snapshot-scoped evidence/exclusion context alongside existing strict `ExchangeRetractionState::read(string $root): ?array`; preserve ordinary callers and canonical authority. Define context-aware generation validation for initially excluded IDs; no permanent removal evidence.
- Verification approach: focused absent flat/dated target, tracked missing retraction, malformed/unreadable/unsafe evidence and strict-caller regression fixtures; exercise target reappearance and unrelated evidence change deterministically.
- Risks or open questions: absence and concurrency semantics must pass before Stage 2; unresolved safe-isolation design blocks dependent work.
- Canonical components/API contracts touched: ExchangeRetractionState, CanonicalPathResolver, RetractedPosts, PublicationGuard; existing strict public contracts retained, snapshot context extension documented.

## Stage 2 — Publish filtered base snapshots

- Goal: direct base publication succeeds with usable surviving content.
- Dependencies: Stage 1 verified and committed.
- Expected changes: carry build-local exclusions through snapshot guard/assembly/publication; filter posts, affected retraction data and derived presentation, recalculate surviving thread metadata; retain canonical files and existing failure cleanup.
- Verification approach: direct publisher fixtures with stale read-model rows, absent reply/root, surviving replies, privacy restrictions, same-build reappearance and subsequent valid/mismatched/protected sources; inspect artifact and manifest.
- Risks or open questions: never mutate source/read model to apply temporary exclusions; inspect excluded-root/dependent-content behavior before finalizing fixtures.
- Canonical components/API contracts touched: PublicationGuard snapshot context, PublicOfflineSnapshotBuilder, OfflineSnapshotPublisher; existing build/publish entry signatures retained where possible.

## Stage 3 — Remove stale base copies through updates

- Goal: applying an update cannot leave excluded target content visible.
- Dependencies: Stage 2 verified and committed.
- Expected changes: add minimal snapshot exclusion metadata and apply it through the canonical offline reader, independently of bounded included threads; preserve surviving replies and handle derived thread presentation. Version/document the portable artifact contract if changed; leave canonical retraction files intact.
- Verification approach: apply generated updates to older bases using actual `snapshotPresentation.applyUpdate`, including excluded targets outside the update window, excluded roots, expansions, repeated application and legacy artifacts.
- Risks or open questions: existing thread replacement alone is insufficient; explicit target exclusion must establish safe consumption before worker acceptance. If required changes exceed this approved outcome, replan rather than silently widen scope.
- Canonical components/API contracts touched: buildUpdate, offline reader applyUpdate, snapshot version/manifest and portable snapshot specification; reuse existing reader, no new dependency.

## Stage 4 — Queue and shared-publication recovery

- Goal: queued publication reaches the same safe result and genuine failures remain recoverable.
- Dependencies: Stage 3 verified and committed.
- Expected changes: wire existing queue/direct orchestration as needed, preserve existing statuses/retry policy; add end-to-end regressions for base/update publication and compatibility checks for static release, high-traffic cache and import-triggered publication.
- Verification approach: queued task completion after prior failure, both reported absent-target IDs, actionable unsafe-evidence failure, repair/rerun, unchanged usable prior artifact where guaranteed, manifests and worker results.
- Risks or open questions: shared callers retain strict behavior; verify the worker's complete publication sequence rather than assuming builder tests cover it.
- Canonical components/API contracts touched: TaskQueueWorker, direct offline publication command, OfflineSnapshotPublisher, shared PublicationGuard consumers.

## Stage 5 — Acceptance and closeout

- Goal: demonstrate normal entry, consumed outcome and recovery; document operator boundaries.
- Dependencies: Stage 4 verified and committed.
- Expected changes: operator/spec documentation, acceptance evidence, implementation summary and study ledger; record dependency delta (zero proposed) and all verification limits.
- Verification approach: isolated direct CLI and real queued-worker replay of task-952-equivalent records; actual browser offline base/update reader on phone/desktop including long content and overflow; focused PHP/reader regressions, shared-publication checks, syntax/diff checks. Run broader existing tests once and attribute failures accurately. Verify canonical bytes unchanged.
- Risks or open questions: no production deployment/live retry evidence claimed; missing reader or recovery evidence leaves completion pending.
- Canonical components/API contracts touched: existing CLI/help/output, worker status, manifest/download/bootstrap and offline reader; relevant operator/spec docs and cycle artifacts.

## Execution and study checkpoint

Application scope v3; Steps 1 and 2 explicitly approved, Step 3 pending. Five bounded stages, each targeted at about one hour; split oversized stages before dependent implementation. After Approved Step 3: create a fresh feature branch, organize this cycle under `docs/plans/absent_retraction_snapshot/`, repair links/index and make the approved planning commit before creating Step 4 summary. Each stage requires verification, summary update and its own commit before the next stage. No dependency changes approved.

No core read-model schema change intended. If a core table/index/constraint or dependent query changes, apply CONTRIBUTING.md's complete schema-version/spec/prior-version startup-rebuild regression contract and verify `./v3 rebuild` plus the affected normal flow; do not confuse portable snapshot metadata with the core read model.

2026-10-23T19:43:15Z: stable `v3/absent_retraction_import`, scope v3 clarified, baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2, Codex single exposed rater. **0.70 total reaffirmed**, including prior work and all planned verification/recovery/documentation; explicit stale-base exclusion is required by approved Step 2, not added scope. Exact R1 hashes verified. Additional static exposure: shared guard/evidence reader, snapshot publisher/builder, service worker and reader update application. No runtime feasibility result, measured labor or independent rating; historical snapshots preserved. No pending scope requests; abort-on-reappearance remains rejected. Unresolved implementation safety findings block dependent stages. Await **Approved Step 3**.

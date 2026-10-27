> **Feature plan:** [Step 1](./docs_independent_publication_step1_solution_assessment.md) · [Step 2](./docs_independent_publication_step2_feature_description.md) · [Step 3](./docs_independent_publication_step3_development_plan.md) · [Step 4](./docs_independent_publication_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_independent_publication`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Completion Contract

Internal maintenance: normal WSL import/resume, forum build and shared refresh publish forum content with zero docs rendering or full-library scan/validation/copy, regardless of docs/code changes or absent/legacy/damaged docs. A dedicated `./v3 docs publish` operation independently publishes the complete docs library with readable/JSON output, initial setup and retry guidance. Existing docs survive forum activation/invalidation and failed docs refresh; URLs, retained assets, safe downloads, search/browse/no-JS behavior and docs-specific notifications remain usable. Docs publication cannot replace forum/read-model/offline state. Acceptance requires actual reported WSL resume/failure recovery, legacy transition, concurrent operations, complete-library CLI and served dynamic/static phone/desktop evidence. No deployment, schema, worker, navigation or new reader UI. Dependency inventory: **zero additions/updates**, no package/lock/vendor/CDN changes.

## Key Risks

- **High risk: lost docs or weakened forum cleanup.** Invalidation/sweeps currently include docs; prove separate ownership and safe legacy retention before detaching forum publication. Restrict any legacy exemption to docs/public assets; keep forum suppression/cleanup effective.
- **High risk: docs publication mutates forum state.** `Application::handle()` normally ensures the read model; reuse the canonical docs controller/renderer through an anonymous isolated context and verify database/offline/release bytes remain unchanged.
- **High risk: inconsistent assets, versions or activation.** Prove retained asset resolution, independent version/storage identity and separate locks/pointers; reject unsafe paths/changing inputs and test races before acceptance.

## Stage 1 — Prove ownership, migration and rendering boundaries

- Goal: Resolve feasibility before dependent changes.
- Dependencies: Approved Step 3 and this cycle's planning-only commit.
- Expected changes: Record release/asset/guard ownership, constant-work legacy retention, anonymous renderer seam and frozen baseline.
- Verification approach: Probe current docs startup/database effects, invalidation/sweeps, version state and private/public profiles in isolated snapshots.
- Risks or open questions: Legacy pinning could retain withdrawn forum output; prove docs-only ownership without bulk import work or return to Step 2 before proceeding.
- Canonical components/API contracts touched: Catalog/controller, RouteServices/TemplateRenderer, builder, FrontController, invalidator and generation guards.

## Stage 2 — Publish independent complete documentation

- Goal: Provide the dedicated docs publication operation.
- Dependencies: Stage 1 isolation/ownership proof.
- Expected changes: Reuse canonical rendering/integrity with independent storage/lock; planned `DocsReleasePublisher::publish(?Closure $progress = null): string`; wire `./v3 docs publish` help, destination/profile options, readable/JSON success/errors.
- Verification approach: Cold/changed/failed publication, anonymous/private-profile context, full inventory/assets/downloads and unchanged forum/database/offline state.
- Risks or open questions: Ordinary application rendering rebuilds the read model; use the proved anonymous controller seam and assert byte-stable forum state.
- Canonical components/API contracts touched: DocsPublication, PlatformDocsController, RouteServices/TemplateRenderer, asset helpers, v3 launcher/docs CLI.

## Stage 3 — Serve coherent docs and update identity

- Goal: Readers use the independent publication end to end.
- Dependencies: Stage 2.
- Expected changes: Planned `DocsReleaseLocator::active(): ?array`; canonical readers/search/downloads/assets select one docs release; `/api/docs-version` and shared version checker use independent notification state while forum version semantics stay intact.
- Verification approach: Retained old assets after app/forum changes; docs refresh notification/reload, no false forum-import prompt; cookies/private gates and exact downloads/no-JS routes.
- Risks or open questions: Shared assets or pending-version storage can mix releases; verify whole reader journeys with distinct docs identity and retained files.
- Canonical components/API contracts touched: FrontController, Application docs/version routes, TemplateRenderer/layout metadata, version_check.js, docs templates/search.

## Stage 4 — Preserve legacy docs across forum retirement

- Goal: Existing installations transition without losing their published library.
- Dependencies: Stages 1–3.
- Expected changes: Planned `DocsReleaseLocator::pinLegacyRelease(string $releaseRoot): void` captures bounded metadata; retain only docs-owned resources through existing invalidation/sweeps; explicit docs publication completes transition.
- Verification approach: Legacy release without evidence, first forum replacement, pointer invalidation/retraction, source/assets changed, failed docs refresh and unsafe/symlink references; no full-library work while pinning.
- Risks or open questions: A legacy exemption could preserve forum payload; test retained docs alongside removal of protected forum artifacts and reject escaping roots.
- Canonical components/API contracts touched: Docs locator, StaticArtifactInvalidator, Retraction ArtifactSweep/factory and existing release/meta guards.

## Stage 5 — Remove docs work from forum publication

- Goal: Import/resume and forum build/refresh no longer depend on documentation.
- Dependencies: Successful legacy retention and reader integration.
- Expected changes: Remove docs bulk paths, evidence/version checks and copied docs from forum builders/publishers; preserve forum rendering/guards and needed initial-docs guidance.
- Verification approach: Real CLI cold/new-record/duplicate imports, forum build/shared refresh and changed/broken docs renderer; instrument zero docs inventory/validation/copy/render operations.
- Risks or open questions: A hidden shared helper still invokes docs; trap docs bulk entry points while normal forum commands complete and retained docs remain readable.
- Canonical components/API contracts touched: StaticArtifactBuilder/ReleasePublisher, ImportedContentPublisher, full/shared CLI, existing DocsPublicationFlowTest contracts.

## Stage 6 — Replay WSL recovery and concurrent failures

- Goal: Prove the reported normal resume and independent failure boundaries.
- Dependencies: Stages 2–5.
- Expected changes: Extend CLI/import/publication regressions for docs/code changes, absent/legacy/damaged docs and overlapping docs/forum publication.
- Verification approach: Interrupted committed import, same-options remote-free resume, single commit/cleared journal/current forum content; failed docs publish/retry preserves previous docs and forum bytes; private/retraction/classification cases.
- Risks or open questions: Locks or retries can cross-couple state; test independent processes and failure checkpoints, keeping expensive rendering outside forum writer/publication locks.
- Canonical components/API contracts touched: ImportInstanceCommandTest, ImportedContentPublicationTest, ContentImportRecoveryTest, docs flow tests and guard regressions.

## Stage 7 — Verify complete readers and import work

- Goal: Complete local acceptance of both independent operations.
- Dependencies: Stage 6 passes.
- Expected changes: Record immutable full-inventory CLI/browser evidence and normal-path work/timings.
- Verification approach: Actual WSL resume, `build-static`, shared refresh and docs publish; dynamic/static desktop/phone search/filter/page/history/download/missing/failure/expanded states, no-JS, themes/overflow, docs update/reload; exact inventory agreement after publication.
- Risks or open questions: Small fixtures hide library/asset issues; use full served snapshots and zero-bulk instrumentation, disclosing timings and limitations without a fixed speed promise.
- Canonical components/API contracts touched: Existing docs/catalog/static/import tests and browser suites; independent publication CLI/serving integration.

## Stage 8 — Document and close out

- Goal: Operators can set up, migrate, publish and recover independently.
- Dependencies: Stage 7 acceptance.
- Expected changes: Update import/deploy/Markdown regeneration guidance and help; finalize summary/debug/study ledger and evidence.
- Verification approach: Follow documented fresh/legacy/retry steps; check links, approved file/dependency inventory, stage commits, omissions and study outcome.
- Risks or open questions: Old guidance still calls forum builds to refresh docs; reconcile affected commands before declaring completion and retain explicit verification limits.
- Canonical components/API contracts touched: Runbooks, v3/docs help, cycle artifacts and effort ledger; copy checklist only if reader copy changes.

## Execution and Study Checkpoint

UTC 2026-10-23T10:27:18Z: user explicitly approved Step 2; no changed scope or dependency request. Step 3 approval pending. After Approved Step 3, create `feat/docs-independent-publication` from current `ddc9d2c` (predecessor implementation included), organize this cycle under `docs/plans/docs_independent_publication/`, repair references/index and commit only approved Steps 1–3, this cycle's debug log and necessary organization. Keep the ledger and predecessor follow-up bookkeeping outside that initial planning commit. Create the new Step 4 summary afterward. Verify, summarize and commit every stage before starting the next; no amendment of completed commits. Eight bounded stages target about one hour each; split/replan an oversized stage before executing it.

Study `railspike-effort / v3/docs_independent_publication`, scope v1 clarified / R1 / P2, start `ddc9d2c020addbc41b19f543d0247b83259c6f69`, predecessor `v3/docs_publication_policy`. **0.85 projected total**, revised from 0.75 for newly detailed read-model startup isolation and legacy docs ownership within forum invalidation/sweeps, including their recovery/concurrency verification. This is new understanding within approved requirements, not expanded outcome; earlier 0.75 intake/Step 1/Step 2 remain preserved. Includes this cycle's prior work and remaining planning/implementation/verification/docs, not remaining effort. Codex single exposed rater; exact R1 report/diff/frozen feedback hashes verified; prior exposure/non-pristine intake disclosed. No measured active effort, independent rating, implementation, completed or accepted outcome. Dependency delta proposed zero; debug remains enabled.

## Step 3 Approval

UTC 2026-10-23T14:20:24.240795+00:00: user replied **“Approved Step 3”**. All eight stages are approved for this cycle; no dependency/scope addition.

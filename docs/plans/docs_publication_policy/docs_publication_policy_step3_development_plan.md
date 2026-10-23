> **Feature plan:** [Step 1](./docs_publication_policy_step1_solution_assessment.md) · [Step 2](./docs_publication_policy_step2_feature_description.md) · [Step 3](./docs_publication_policy_step3_development_plan.md) · [Step 4](./docs_publication_policy_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_publication_policy`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

Current plan: **scope v2**, Step 2 approved; renewed Step 3 approval pending. Historical v1 Stage 1 audit is complete; Stage 1 below revalidates it on current main before Stages 2–8.

## Completion Contract

Internal application maintenance: ordinary `./v3 import-instance` publishes imported forum records while compatible repeat publication renders zero docs documents, landing/browse pages or search/missing shells. Full builds and shared refreshes preserve the complete dynamic/static library, safe downloads, assets and existing no-JS behavior. Reuse refreshes only the embedded repository-version marker to match destination `/api/version`, without rerendering; disabled notifications retain marker absence. All other output-affecting changes invalidate reuse. Cold/legacy/incompatible/damaged documentation rebuilds automatically. A failed fallback retains the prior complete release; `--resume` with identical destination options completes safely. Acceptance requires normal CLI import/retry and desktop/mobile dynamic/static browser evidence plus representative cold/repeat timings. Verify locally with isolated destinations; production deployment is outside scope. No database/schema changes, new delivery modes or navigation changes. Dependency inventory: **zero additions or updates**, no manifest/lock/vendor/CDN changes; use existing PHP/project facilities. Include import runbook, cycle artifacts and effort ledger; copy checklist only if copy changes.

## Key Risks

- **High risk: stale or unsafe reuse.** Audit shared chrome, app version, feature flags, profile/configuration, eligible sources and referenced assets first; vary inputs and conservatively invalidate uncertain compatibility. Exclude destination HEAD only from compatibility identity; validate and refresh its marker during transfer. Reject malformed, missing, duplicate or unexpected markers; notification-mode changes invalidate reuse. Unresolved import-dependent output blocks dependent stages and requires returning to Step 2 if its contract cannot be met.
- **High risk: partial publication or rollback loss.** Validate all reused outputs/assets, reject escaping paths/symlinks, avoid mutable links to the active release, and preserve retraction/meta/access guards. Inject corruption, changed-input-during-build and rebuild failures before acceptance; rebuild or fail without activating a partial candidate.
- **Performance:** remeasure current-main inventory/render/copy cost in Stage 1 and compare the full representative inventory in Stage 7; zero rendering is required, but validation/copy costs must be disclosed. No fixed speed ratio is promised.

## Stage 1 — Revalidate dependencies and baseline on current main

- Goal: Reconfirm the historical audit against current source before reuse implementation.
- Dependencies: Renewed Approved Step 3 and a revised planning-only commit on a fresh feature branch from current main; retain old branch/history as recovery.
- Expected changes: Update the dependency/output inventory and isolated full-inventory cold baseline; preserve historical Stage 1 evidence and original cycle identity.
- Verification approach: Frozen-source real import before/after content-only changes; profile, notification and asset changes; inspect shared chrome and high-traffic rendering boundaries. Record inventory and cold timing for Stage 7 comparison.
- Risks or open questions: Changed main may invalidate historical findings; compare normal docs bytes and dependencies now, resolve unknown inputs before Stage 2, and return to Step 2 for incompatible requirements.
- Canonical components/API contracts touched: Catalog/controller, TemplateRenderer/layout, profile/config/flags, AssetFingerprint, builders.

## Stage 2 — Define compatibility evidence

- Goal: Separate documentation identity from unrelated record commits.
- Dependencies: Stage 1 audit passes.
- Expected changes: Canonical Docs helper and versioned compatibility evidence; planned API `inputSignature(): string` captures publication context and notification mode, excluding only destination HEAD under the approved marker exception.
- Verification approach: Changed/added/deleted docs, renderer/templates/assets, profile/flags/config and notification mode invalidate. A content-only destination HEAD change preserves compatibility; other record-derived output still invalidates.
- Risks or open questions: Missing inputs cause stale output: deterministic conservative evidence; uncertainty prevents reuse.
- Canonical components/API contracts touched: New Docs helper, existing inventory and rendering context; no duplicate eligibility policy.

## Stage 3 — Validate and transfer documentation

- Goal: Require complete, isolated output before reuse.
- Dependencies: Stage 2.
- Expected changes: Helper APIs `recordPublication(string $candidateRoot): void`, `reuseInto(string $sourceRoot, string $candidateRoot): bool`; integrity evidence, guarded candidate copying, marker refresh to current destination version and evidence for the refreshed bytes.
- Verification approach: Validate original integrity before mutation; refresh only expected version markers across aliases/documents/shells; disabled mode stays absent. Missing/duplicate/malformed/unexpected markers, corruption, traversal/symlinks or changing inputs prevent reuse. Source stays unchanged; refreshed output remains reusable.
- Risks or open questions: Unsafe rewrites or partial copies corrupt releases; check marker shape/count and source version before copying, preserve all other bytes, clean owned candidate output on failure, and retain the active release.
- Canonical components/API contracts touched: Docs aliases/index/downloads/browse shells, referenced assets and shared helper.

## Stage 4 — Wire full publication

- Goal: Optimize ordinary imports end to end.
- Dependencies: Stage 3.
- Expected changes: Optional builder reuse-source context; split docs from forum shared pages; reuse/rebuild, record evidence, report accurate progress; preserve callers.
- Verification approach: Real CLI cold, duplicate and new-record imports; compatible repeats render zero docs and agree with destination `/api/version`; changed/legacy docs rebuild; import forum content remains current.
- Risks or open questions: Stale forum output or guard bypass: retain content rendering and guarded complete-release activation.
- Canonical components/API contracts touched: StaticArtifactBuilder, StaticArtifactReleasePublisher::build, ImportedContentPublisher and CLI tests.

## Stage 5 — Wire shared refreshes

- Goal: Use the same policy on both publication paths.
- Dependencies: Stage 4.
- Expected changes: Replace unconditional docs deletion with validated reuse or complete owned-output rebuild; update progress assertions.
- Verification approach: Compatible refresh skips all docs rendering and refreshes version markers; changed/deleted docs remove stale files/results; assets and untouched detail pages remain valid.
- Risks or open questions: Seeded stale output: verify aliases/downloads and missing-URL recovery; retain full-build-after-retraction rules.
- Canonical components/API contracts touched: buildSharedRefresh, refreshSharedPagesFromReadModel, PlatformDocsStaticTest and affected LocalAppSmokeTest cases.

## Stage 6 — Replay failure and recovery

- Goal: Prove normal CLI recovery and access safety.
- Dependencies: Stages 4–5.
- Expected changes: Extend existing publication/CLI recovery regressions; register new tests if necessary.
- Verification approach: Malformed marker/corrupt evidence forces fallback; injected rebuild failure after import commit preserves active release; identical-options `--resume` completes with one import commit and cleared journal. Test private destinations and version/input changes during publication.
- Risks or open questions: Duplicate records or exposure: preserve ownership/locking/access and retraction/meta guards; block acceptance on failures.
- Canonical components/API contracts touched: ImportInstanceCommandTest, ImportedContentPublicationTest, ContentImportRecoveryTest and existing checkpoints/workspaces.

## Stage 7 — Verify readers and performance

- Goal: Complete dynamic/static acceptance.
- Dependencies: Stage 6.
- Expected changes: Record representative full-inventory cold/repeat timings and browser evidence.
- Verification approach: Focused docs/import/recovery/smoke tests; isolated rebuild/build-static; phone/desktop dynamic/static Tools → Docs, search/filter/page/history/links/downloads/no-JS/missing recovery, themes/overflow/focus. Reused pages match `/api/version`, show no false reload prompt, and detect the next version normally; disabled mode stays inactive. Compare full-inventory timings and zero docs-render callbacks.
- Risks or open questions: Small fixtures hide cost/routing gaps: real served hosts/full inventory; disclose validation/copy cost and limitations, block unresolved failures.
- Canonical components/API contracts touched: Existing docs tests, FrontController/controller/templates/search assets; no navigation changes.

## Stage 8 — Document and close out

- Goal: Deliver recovery guidance and auditable evidence.
- Dependencies: Stage 7 passes.
- Expected changes: Import runbook reuse/fallback/progress/retry guidance; summary/debug/ledger closeout; copy checklist if applicable.
- Verification approach: Compare guidance with CLI/resume evidence; verify links, file scope, stage commits, omissions, inherited scores and actual dependency delta against the approved zero inventory.
- Risks or open questions: Overstated completion: distinguish local acceptance, no deployment and pending maintainer study judgment.
- Canonical components/API contracts touched: instance_content_import.md, cycle artifacts, effort_estimation_trial.md; copy checklist only if needed.

## Execution and Study Checkpoint

UTC 2026-10-23T08:05:54Z: user explicitly approved revised Step 2 v2. No subsequent scope additions. Revised Step 3 approval is pending; implementation is paused. After approval, create a fresh branch from current main (`1a358bc9` at planning), make the revised Steps 1–3/debug/index planning-only commit, then include restored historical Step 4 evidence in the Stage 1 revalidation commit. Do not merge old history or commit the summary/ledger in the planning-only commit. Each completed stage needs verification, summary and its own commit before the next stage. Keep ledger bookkeeping uncommitted until closeout.

Eight bounded stages target about one hour each, using existing helpers/test fixtures; split/replan any oversized stage before implementation. Stage 1 revalidation is required despite the completed historical audit. Stage 3 resolves marker/integrity risks before wiring; Stages 4–6 prove the import/shared-refresh/recovery flows; Stage 7 supplies reader/version/performance acceptance.

Study `railspike-effort / v3/docs_publication_policy`, scope v2 / R1 / P2; original start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`. **0.70 projected total reaffirmed**, covering prior work, current-main revalidation and remaining planning/implementation/verification/documentation. Codex single exposed rater; all earlier scores visible. R1 report/diff hashes verified; exact feedback recovered from recorded Git revision and hash verified. Missing intake, historical v1 0.65 and v2 0.70 remain preserved. No independent rating, measured actual or accepted outcome.

## Historical v1 checkpoint — superseded for execution

The original v1 plan is preserved verbatim at `a6110024:docs/plans/docs_publication_policy/docs_publication_policy_step3_development_plan.md` on retained branch `feat/docs-publication-policy`. Its approval authorized the completed dependency audit, which discovered the marker conflict. Current stage text above revises v1 for approved v2; it does not alter the historical completed audit or its approval. The original handoff text follows as history:

Step 2 approved by the user; no new requests or scope changes. Stages target at most about one hour each; replan before an oversized stage. After Approved Step 3: create a feature branch, organize this cycle under `docs/plans/docs_publication_policy/`, repair navigation/index links and make the approved planning-only commit; then create the implementation summary. Each stage requires verification, summary and its own commit before the next stage. Ledger updates accumulate uncommitted until closeout; do not include them in the planning-only commit.

UTC 2026-10-21T20:42:55Z; cycle `v3/docs_publication_policy`; scope v1; start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`; R1/P2; Codex single exposed rater. **0.65 projected total reaffirmed** for the complete cycle, including investigation/planning and all eight stages, not remaining work. The staged dependency audit and verification clarify the approved outcome without expanding it. Frozen R1 hashes reverified; prior scores and read-only source exposure disclosed in Steps 1–2. Pre-inspection intake remains missing. Implementation, measured effort and accepted outcome remain unclaimed; Step 3 approval pending.

## Renewed Step 3 Approval — Scope v2

UTC 2026-10-23T08:16:13.139216+00:00: user replied **“Approved Step 3”**. The current v2 plan is approved for implementation on a fresh branch from current main. Earlier pending states remain historical. No dependency additions or scope changes.

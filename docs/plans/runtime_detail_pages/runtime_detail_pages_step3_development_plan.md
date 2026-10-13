> **Feature plan:** [Step 1](./runtime_detail_pages_step1_solution_assessment.md) · [Step 2](./runtime_detail_pages_step2_feature_description.md) · [Step 3](./runtime_detail_pages_step3_development_plan.md) · [Step 4](./runtime_detail_pages_step4_implementation_summary.md)

## Completion Contract

- Entry/outcome: normal full-build CLI defaults to skipping both detail batches, activates a fresh release without thread/post/quote-alias HTML, and direct detail links render through PHP; explicit static opt-in remains functional.
- Recovery: preserve candidate-build failure protection; operators can explicitly enable static details and rebuild. Shared-only refresh retains existing artifacts and cannot perform this transition.
- Deployment/verification: exercise CLI and local HTTP entry paths for forum and QDB profiles in isolated fixtures; document PHP routing and static-only opt-in. Live deployment is outside this change.
- Release condition: focused checks and the repository test suite pass; document matching-corpus timings/page counts for both modes and any measurement limits. Preserve other page categories, offline snapshots, visibility, and access rules.
- Scope: existing flag, rendering/build paths, relevant tests, and operator docs; no schema or public signature changes. Each stage is at most one hour; split before implementation if it grows.

## Key Risks

- **High risk: broken reader links/static-only hosting.** PHP fallback and QDB routes already exist; validate through HTTP before release. Mitigate with direct-link coverage and explicit static-host opt-in guidance.
- **High risk: stale artifacts/recovery.** Full builds use fresh candidates; shared refresh copies the active release. Validate a static-to-runtime activation and failed-build behavior; retain activation logic and require full rebuild for transition.
- **Overrides retaining slow builds.** Evaluator precedence is environment, instance, default. Verify both explicit sources and document precedence; do not overwrite operator choices.

## Stage 1

- Goal: deliver the runtime default with working explicit static opt-in.
- Dependencies: approved Step 3; begin Step 4 on a feature branch with approved Step 1–3 files as the first commit.
- Expected changes: disable the registry default, clarify existing flag description, update default-build expectations, and make tests requiring static details opt in explicitly.
- Verification approach: focused flag evaluator, full-build CLI, and QDB builder checks; assert skipped batches/absent detail artifacts by default, preserved shared/offline output, and enabled detail output via instance/environment settings.
- Risks or open questions: implicit static-test assumptions may fail; inspect builder callers early and use explicit per-test opt-in with restored environment state rather than changing test-runner defaults.
- Canonical components/API contracts touched: flag registry/evaluator, static builder, full-build CLI; existing evaluator, smoke, and quote-display tests.

## Stage 2

- Goal: verify readers and operators can complete the static-to-runtime transition.
- Dependencies: Stage 1 verified and committed with its Step 4 summary; resolve route/release failures before continuing.
- Expected changes: focused integration coverage for an enabled static release followed by a disabled full release; exercise canonical thread/post URLs and QDB numeric links through the front controller/local HTTP entry.
- Verification approach: confirm old detail files are absent from the new active release, links return expected content, hidden/member-only access remains enforced, shared pages/offline snapshot remain available, and shared-only refresh preserves its scope; run existing failed-build/activation recovery coverage.
- Risks or open questions: stale output can mask fallback or alias failure; begin with known static artifacts, assert new-release absence and runtime responses, and keep failure recovery unchanged.
- Canonical components/API contracts touched: release publisher, front controller, application detail/QDB routes, canonical renderer, and existing integration fixtures; extend runtime wiring only if these checks expose a scoped defect.

## Stage 3

- Goal: finish operator handoff and quantify the removed work.
- Dependencies: Stage 2 verified and committed with its Step 4 summary.
- Expected changes: update CLI reference and affected operator docs with default/override precedence, PHP hosting, explicit static opt-in, and full-rebuild transition guidance; record verification and timings in the Step 4 summary.
- Verification approach: run both full-build modes against the same isolated representative corpus, record thread/post counts and elapsed times; run the repository suite, document/link checks, and diff checks.
- Risks or open questions: local timing may not represent the reported 618-thread/1,149-post corpus; record actual fixture size and environment limits without claiming a production speedup. Static hosting requires opt-in before deployment.
- Canonical components/API contracts touched: operator CLI documentation and existing flag controls; reuse build/release commands without adding a new mode.

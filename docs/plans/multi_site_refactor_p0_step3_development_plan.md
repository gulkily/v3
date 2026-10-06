# Multi-Site Refactor P0 — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_p0_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p0_step2_feature_description.md) · [Step 3](./multi_site_refactor_p0_step3_development_plan.md) · [Step 4](./multi_site_refactor_p0_step4_implementation_summary.md)

## Completion Contract

- Normal entry: web and operational callers resolve a known, absent, or unknown `FORUM_SITE_ID`.
- End-to-end outcome: validated descriptor and one default presentation/static-output-root rule when no explicit root is supplied.
- Required recovery: absent/unknown selection uses Zenmemes and its unsuffixed legacy root; explicit roots still win.
- Deployment/external verification: confirm an existing Zenmemes deployment finds its artifacts and Chouse/QDB defaults are separate.
- Release condition: all P0 fields and four consumers are covered; shared instance state has no profile owner.

## Key Risks

- **High risk: Zenmemes root change hides artifacts.** Early validation: assert its exact legacy path. Mitigation: make unsuffixed output an explicit resolver compatibility case.
- **High risk: invalid/colliding browser namespaces leak state.** Early validation: validate every descriptor. Mitigation: enforce one browser-safe, unique identifier rule.
- **High risk: migration overrides operator intent.** Early validation: command tests cover explicit roots. Mitigation: retain current override precedence.

## Stage 1

- Goal: establish the complete, validated declarative profile contract.
- Dependencies: approved Step 2.
- Expected changes: extend `SiteProfileRegistry` with display identity, default/permitted themes, browser/offline namespace, editorial-content key, and enabled experiences; validate required/unique/browser-safe values without adding instance state.
- Verification approach: extend `SiteProfileRegistryTest` for all fields, known selection, and absent/unknown fallback.
- Risks or open questions: Impact: malformed descriptors break consumers. Early warning / validation: exercise every registered profile. Mitigation: deterministic validation and Zenmemes-only active-profile fallback.
- Canonical components/API contracts touched: `SiteProfileRegistry::all()` and `SiteProfileRegistry::active()`.

## Stage 2

- Goal: centralize profile-derived presentation/static-output-root selection.
- Dependencies: Stage 1's validated descriptor.
- Expected changes: add `PresentationPathResolver::staticHtmlRoot(string $projectRoot, array $profile): string`; preserve Zenmemes' unsuffixed legacy root and assign other profiles distinct roots.
- Verification approach: add resolver tests for Zenmemes, Chouse, and QDB roots.
- Risks or open questions: Impact: collisions mix artifacts. Early warning / validation: compare all three derived roots. Mitigation: derive non-legacy suffixes only from validated namespaces.
- Canonical components/API contracts touched: new `PresentationPathResolver`; `SiteProfileRegistry` descriptor read contract.

## Stage 3

- Goal: migrate web and task-queue default static-root selection to the resolver.
- Dependencies: Stage 2 resolver tests.
- Expected changes: replace the local suffix branches in `public/index.php` and `scripts/task_queue.php`; preserve explicit root precedence.
- Verification approach: focused web/task-queue coverage for Zenmemes, a non-Zenmemes profile, and an override.
- Risks or open questions: Impact: callers use the wrong release. Early warning / validation: assert effective default/override roots. Mitigation: change only fallback expressions.
- Canonical components/API contracts touched: web entry-point static-root configuration; task-queue static-root configuration; `PresentationPathResolver`.

## Stage 4

- Goal: migrate offline publication and diagnosis defaults, then verify the full P0 slice.
- Dependencies: Stage 3 consumer migration.
- Expected changes: replace the repeated branches in `scripts/publish_offline_snapshot.php` and `scripts/diagnose_offline_reading.php`; extend command coverage.
- Verification approach: verify legacy fallback, Chouse/QDB isolation, unknown recovery, and explicit `--static-html-root`/environment overrides; run `php tests/run.php`.
- Risks or open questions: Impact: offline commands disagree on artifact location. Early warning / validation: exercise both against one derived root/profile. Mitigation: no hand-written suffix rule remains in the four consumers.
- Canonical components/API contracts touched: offline snapshot publication and diagnosis commands; `PresentationPathResolver`; profile and command test coverage.

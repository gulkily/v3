# Proposed case studies

For maintainer review. These are candidates, not published case studies. Source paths are relative to `~/v3/`. The recommendation is to lead with 1 and 2, use 3 and 4 together for planning and checklists, and use 5 to demonstrate test design. Candidate 6 is a technical alternative.

Evidence can show how options and scope changed between artifacts; it does not establish a verbatim user–agent conversation. Do not invent dialogue or attribute a revision to the user without evidence.

## 1. Turn a missing-PHP crash into useful startup guidance

- **Shows:** a small bug fix, bounded options, and a standalone regression suite.
- **Story:** compare friendly guidance, broader command checks, and automatic installation; choose the small fix and defer installation.
- **Evidence:** `docs/plans/missing_php_startup_guidance/`, Steps 1–4; delivery commits `d84a47b9` and `2937b11b`.
- **Limit:** a static-asset HTTP probe verified startup; missing data fixtures prevented treating it as full application verification.

## 2. Build an extension cookbook without changing application code

- **Shows:** documentation as the deliverable, with reviewed structure and staged verification.
- **Story:** compare an architecture tour, isolated examples, and a shared lifecycle cookbook; deliver the cookbook in four documentation stages.
- **Evidence:** `docs/plans/extension_improvement_cookbook/` and `docs/examples/extension_improvement_cookbook.md`; final stage commit `a030ae09`.
- **Limit:** proposed recipes are documentation, not implemented features.

## 3. Plan dependency installation for a later session

- **Shows:** planning as useful work in its own right, scope decisions, and a persistent handoff.
- **Story:** assess installation approaches, narrow the initial target, preserve explicit confirmation, and surface a missing validation environment before implementation.
- **Evidence:** `docs/plans/local_server_dependency_install/`, Steps 1–3.
- **Limit:** these are local, uncommitted drafts. Step 3 awaits approval; no installation feature was delivered. Review and approve any excerpts before publication.

## 4. Write a platform-support checklist that separates ambition from evidence

- **Shows:** checklist design and explicit evidence requirements.
- **Story:** distinguish runtime verification, manual setup verification, and installer verification; retain future platform candidates without promising support.
- **Evidence:** `docs/plans/local_server_dependency_install/local_server_dependency_platform_support_checklist.md`.
- **Limit:** also local and uncommitted. An unchecked platform is a candidate, not a tested platform. Best paired with candidate 3.

## 5. Test a CLI wait notice while the command is still waiting

- **Shows:** test suites that verify timing and behavior, not just final output.
- **Story:** hold a lock in a separate process, assert the notice arrives before release, then verify normal completion and the quiet uncontended path.
- **Evidence:** `docs/plans/status_lock_wait_notice/`; stage commits `cc1dd80a`, `876d5c73`, and refinement `0b3110dd`.
- **Limit:** the recorded full run had 859 passes and one known failure; do not describe it as fully green.

## 6. Fix a feature flag that caused unnecessary rebuilds

- **Shows:** a deeper bug investigation, a corrected assumption, and regression coverage.
- **Story:** investigate inconsistent repository metadata, then handle a stale marker that prevents the expected incremental path.
- **Evidence:** `docs/plans/feature_flag_readmodel_rebuild_fix/`; commits `cf86ccca`, `15d3d2f1`, and `4764f8a3`.
- **Limit:** full-suite runs included pre-existing failures and a flake. No measured production performance claim is justified.

## Selection

Pending maintainer review. Case-study writing is the last item in the [positioning checklist](./positioning-checklist.md).

> **Feature plan:** [Step 1](./repository_archive_readme_step1_solution_assessment.md) · [Step 2](./repository_archive_readme_step2_feature_description.md) · [Step 3](./repository_archive_readme_step3_development_plan.md) · [Step 4](./repository_archive_readme_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/repository_archive_readme`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates disabled.

## Original Query

The readme file that we put inside repository.tar.gz and repository.zip needs improvement.

## Understood Intent

Give recipients a useful introduction to the downloaded content repository: what it contains, how to inspect it, and what else is needed to use or restore it.

## Problem

Both archive exporters copy the repository's existing README, which in the inspected download is obsolete sample-fixture documentation rather than a guide to the actual archive.

## Option A — Maintain the README inside each content repository

Replace the seeded README and update existing installations' repository copies so ordinary exports naturally include the new text.

- Simple archive behavior; README stays consistent with the repository's Git history.
- Requires updates to existing content repositories; future wording changes need propagation, and fixture documentation should remain distinct.

## Option B — Supply a shared README at export time

Include an application-maintained download guide as the archive's README in both export paths, preserving any different repository-specific README separately.

- Reaches existing installations when application code updates; both formats receive identical guidance.
- Needs archive integration and extraction checks; the supplied README is export documentation and may differ from the tracked Git version. Preserve original notes without filename collisions.

## Recommendation

Option B is one bounded vertical slice: a concise guide plus consistent inclusion in both formats and both export paths. Proposed guide content:

- **What this is:** a content-repository snapshot containing records and Git history; application code is obtained separately.
- **Where to start:** open `records/` in a text editor; describe posts, identities, public keys and instance information without claiming fixed counts or that every optional family exists.
- **History:** explain the hidden `.git/` directory and basic read-only inspection, distinguishing current files from historical versions.
- **Using the backup:** distinguish importing records from restoring the full repository; point to verified application instructions. Explain the separate image backup and matching repository revision where applicable.
- **Limits:** the repository archive alone is not a complete running-site backup; avoid unsupported completeness or privacy promises.

Verify extracted tar and ZIP contents, original-note preservation, unchanged canonical records/history, and source-repository non-mutation. No new dependencies, schema changes, UI changes, history rewriting or retraction redesign. Existing downloaded files are not rewritten. Inspected sources: `InstancePageController::buildRepositoryArchive`, `ImageBackup::export`, `LocalRepositoryBootstrap`, and the local `repository.tar.gz` README.

## Study snapshots and approval

2026-10-25T18:19:32Z; scope v1; starting revision `99a0def2af1f0b19b4b5cc284c1eac920da9464d`; reference R1 (all three frozen source hashes verified). Codex, single exposed rater; exporter/bootstrap/test and existing archive inspection occurred before enrollment, so no pristine pre-inspection estimate is claimed. No inherited cycle score; reference scores were visible.

- Intake after inspection: **0.25 projected total** for investigation, planning, guide, both export integrations, extraction checks and documentation. Main uncertainty is preservation of custom repository notes.
- Step 1 handoff: **0.25 reaffirmed**, same scope and rationale. Relative whole-cycle scope, not hours or measured labor; acceptance pending, no implementation or completed outcome.

Step 1 approved on 2026-10-25. Planning records remain uncommitted. No incidental enhancement requests.

Post-approval baseline correction: remote commit `042e684ae` removed Git history and unified exports. [Step 2](./repository_archive_readme_step2_feature_description.md) retains Option B but supersedes the history/two-path assumptions above; the original assessment is preserved as inspected-checkout evidence.

*— Revised by Codex · 2026-10-25 UTC*

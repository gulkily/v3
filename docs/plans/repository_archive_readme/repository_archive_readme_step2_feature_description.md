> **Feature plan:** [Step 1](./repository_archive_readme_step1_solution_assessment.md) · [Step 2](./repository_archive_readme_step2_feature_description.md) · [Step 3](./repository_archive_readme_step3_development_plan.md) · [Step 4](./repository_archive_readme_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/repository_archive_readme`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; R1.

Extended estimates: disabled (inherited beta-1 cycle state).

**Cycle estimate: 0.25**

## Problem and approved direction

Downloaded repositories inherit obsolete fixture instructions. Step 1 Option B was approved on 2026-10-25: provide a shared export README while preserving repository-specific notes. Subsequent remote inspection found that commit `042e684ae` removed Git history and unified exports; this description supersedes Step 1's history/two-export-path assumptions without changing that approach.

## User stories

- As a download recipient, I want to identify the files and read the records so I can use the archive without knowing the application internals.
- As an operator, I want accurate import and image-backup guidance so I can distinguish a content snapshot from a complete site backup.

## Core requirements

- Both `.tar.gz` and `.zip` downloads contain the same concise, application-maintained `README.md`, including for existing repositories with stale fixture text or no README.
- Describe the content snapshot, `records/` and common record families without fixed counts or guaranteed optional families. Explain plain-text inspection, absence of `.git` history, and that application code is obtained separately.
- Explain the purpose of `repository-snapshot.json`, retaining it with unchanged records, and the separately downloaded matching image archive. Distinguish importing content from restoring a running site; verify any commands and documentation destinations against current application behavior.
- Preserve differing original repository notes under a collision-safe name, with a short pointer in the guide. Do not modify the source repository, exported records, manifest semantics, or export eligibility rules.
- If guide preparation fails, use the existing failed-download recovery path; never return an apparently successful incomplete archive. Keep guidance focused on the recipient's next action.

## Delivery scope and completion boundary

Application change: shared guide content, integration into the current archive producer, focused download/extraction tests, and this cycle's planning/implementation records and ledger. Normal entry is Backup or either repository download URL; completion is an extracted, usable guide in both formats, with original notes and content preserved. Verify failure/retry and image matching. No dependencies, schema/UI changes, Git-history restoration, retraction changes, deployment, or rewriting previously downloaded files. Release requires approved implementation and passing focused checks.

## Shared surfaces and flow

Reuse `InstancePageController` repository downloads and `ImageBackup::export`; respect `RepositorySnapshot` and image restore/import consumers. Backup and Codebase links already reach these endpoints; their UI requires no new explanatory copy. Retain the fixture README for fixture users.

1. Download either content archive from the existing controls.
2. Extract it and open the root README.
3. Inspect records, follow verified usage instructions, or obtain the matching image backup when needed.

## Risks and success criteria

- **Stale baseline:** wrong history/restore claims; validate against current remote exporters before Step 3 and implement on a baseline containing the archive fix, without merging unrelated local read-model work.
- **Lost custom notes:** validate an existing README and preservation-name collisions; require byte-preserved originals with no overwrites.
- **Misleading restoration:** validate ordinary import/image-restore instructions and mismatch recovery; avoid claiming the archive alone restores a site.

Acceptance: extract real responses for both formats; verify identical guide content (for the same source), preserved original notes, unchanged source files, intact snapshot identity and import/image compatibility. Include missing-README and custom-note cases. No application changes are authorized by this handoff.

## Study checkpoint and approval

2026-10-25T18:26:38Z: Step 2 handoff, scope v2 corrects the baseline to current remote history-free exports (`6bce35f21` inspected); original starting revision `99a0def2af1f0b19b4b5cc284c1eac920da9464d` retained. R1 hashes reverified; Codex single exposed rater. **0.25 total reaffirmed**: shared export reduces wiring, while manifest/import guidance needs verification. Earlier 0.25 snapshots remain historical; no measured labor or accepted outcome. Awaiting `Approved Step 2`; records remain uncommitted.

*— Codex · 2026-10-25 UTC*

Step 2 approved 2026-10-25; requirements unchanged. Development plan prepared for review.

*— Revised by Codex · 2026-10-25 UTC*

Implementation completed locally; see [verification and closeout](./repository_archive_readme_step4_implementation_summary.md). Step 3 approval was received after the concrete plan was delivered; earlier awaiting-approval text records its historical handoff state.

*— Revised by Codex · 2026-10-25 UTC*

> **Feature plan:** [Step 1](./repository_archive_readme_step1_solution_assessment.md) · [Step 2](./repository_archive_readme_step2_feature_description.md) · [Step 3](./repository_archive_readme_step3_development_plan.md) · [Step 4](./repository_archive_readme_step4_implementation_summary.md)

Debug mode: disabled

Effort study: `railspike-effort` / `v3/repository_archive_readme`; [ledger](../effort_estimation_trial.md). P2 / R1; scope v2.

Extended estimates: disabled (inherited).

**Cycle estimate: 0.25**

## Completion Contract

Both existing repository download endpoints produce an extractable archive with the shared recipient README, preserved custom notes and intact content/snapshot identity. Failures return existing actionable download errors, with no partial success. Verify actual downloads, import and matching/mismatched image recovery. No UI, schema, dependency, deployment or history-policy changes. Implement from current remote main containing the history-free export fix; preserve unrelated local work. Release requires focused checks, documentation and staged commits.

## Key Risks

- **High risk: overwritten notes.** Inspect collision cases before integration; preserve originals under the first unused filename and never edit the source repository.
- **High risk: misleading restoration.** Verify commands against current import/image recovery contracts; state that Git history and application code are absent and ZIP is not accepted by the tar.gz importer.
- Baseline divergence: recheck remote before branching; isolate this feature from local registration/read-model changes. Retain the existing exporter and snapshot manifest contracts.

## Stage 1 — Deliver the guide in both formats

- Goal: replace fixture-oriented instructions in ordinary downloads.
- Dependencies: approved plan; clean feature worktree based on current remote; organized planning commit first.
- Expected changes: shared guide resource and snapshot-only preparation helper; preserve differing README bytes under an unused name with a guide pointer; integrate before packaging.
- Verification approach: focused exports in both formats; compare extracted guides, records, manifests and source files; cover absent README, custom notes and naming collisions.
- Risks or open questions: note loss or duplicate preservation; validate cases before integration, reuse matching guide without creating extra copies.
- Canonical components/API contracts touched: new `RepositoryArchiveReadme::write(string $snapshotRoot): void`; `ImageBackup::export`; unchanged `RepositorySnapshot` identity and download routes.

## Stage 2 — Verify usage and failure recovery

- Goal: prove recipients can use the documented paths and retry failures.
- Dependencies: Stage 1.
- Expected changes: focused route/extraction and preparation-failure regression coverage; refine instructions only where ordinary-flow evidence warrants it.
- Verification approach: real GET tar/ZIP responses, `import-repository --dry-run`, matching image verification and mismatch recovery; exercise preparation failure and successful retry. Run relevant archive/import/image suites.
- Risks or open questions: helper-only coverage misses delivery errors; verify the normal controller response and no partial archive. Avoid implying importing records restores the full site or original revision.
- Canonical components/API contracts touched: existing `InstancePageController`, archive import command, `ImageRestore`, and their tests; no production contract changes.

## Stage 3 — Close out and preserve evidence

- Goal: hand off the verified archive guide and its limits.
- Dependencies: Stage 2 passing.
- Expected changes: implementation summary, feature index/navigation and authorized cycle ledger updates; commit each stage with its summary.
- Verification approach: final scoped diff/whitespace/link checks, required tests from preceding stages, zero-dependency inventory, source non-mutation and clean feature status. Record deployment as out of scope and maintainer acceptance as pending.
- Risks or open questions: unrelated ledger/worktree edits; transfer only this cycle's entries and retain all historical scores and unrelated changes.
- Canonical components/API contracts touched: host planning index, cycle artifacts and effort ledger only.

## Approval and study checkpoint

Step 2 approved 2026-10-25. The earlier “Approved Step 3” preceded this plan; await review of this concrete artifact before implementation. No incidental scope additions. 2026-10-25T18:29:42Z: **0.25 whole-cycle projection reaffirmed**, same scope v2/original baseline `99a0def2af1f0b19b4b5cc284c1eac920da9464d`, R1 hashes reverified, Codex single exposed rater. Export integration is bounded; preservation and recovery drive verification. Prior snapshots retained; no measured labor or completed outcome. Planning remains uncommitted.

*— Codex · 2026-10-25 UTC*

Implementation completed locally; see [verification and closeout](./repository_archive_readme_step4_implementation_summary.md). Step 3 approval was received after the concrete plan was delivered; earlier awaiting-approval text records its historical handoff state.

*— Revised by Codex · 2026-10-25 UTC*

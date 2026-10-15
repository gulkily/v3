> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Documentation-only internal maintenance for developers/maintainers: all seven root documents remain discoverable from README navigation, with filenames and substantive contents preserved.
- Allowed scope: the seven moves below; `README.md`; relocation/discovery repairs in host `docs/{architecture,examples,research,specs,runbooks,product,reference}/`; affected links/indexes in `docs/plans/`; this feature's planning artifacts. Preserve essential root files, runtime structure, vendored workflows, and local/generated data. No unrelated organization, consolidation, or deletion.
- Evidence: seven source-to-destination comparisons, working changed links, classified remaining old-path mentions, and a scope-limited diff. Correct misplaced files and links before handoff; runtime/UI/deployment/migration checks are inapplicable because executable files and behavior remain unchanged.
- Step 4 prerequisite: create a feature branch; organize this feature's approved plans/log into `docs/plans/repository_directory_cleanup/`, repair navigation and index it; commit only approved planning/organization work before implementation. Each stage then records verification in the summary and commits before proceeding.

## Key Risks
- **High risk:** relocation loses content or navigation. Current inventory found seven free destinations and active links listed below; recheck before moves, compare contents against baseline, and repair links within each stage. Any conflict blocks its move.
- Scope expansion through path dependencies: tracked-file search found documentation links and illustrative strings, with no runtime dependency found. Recheck all seven names before implementation; classify historical mentions rather than blindly replacing them. Any required executable change blocks work pending renewed Step 2/3 approval.

## Move inventory
All paths are relative to the repository root; existing categories supply the homes.

| Root source | Destination directory | Purpose |
| --- | --- | --- |
| `sfenc.md` | `docs/specs/` | Encoding specification |
| `zenmemes_rules.txt` | `docs/product/` | Site copy |
| `docs_page_changes_revised.txt` | `docs/plans/backlog/` | Docs-page proposal |
| `improve_messaging.txt` | `docs/plans/backlog/` | Messaging proposal |
| `qdb_todo.txt` | `docs/plans/backlog/` | QDB work notes |
| `related_feature_requests.txt` | `docs/plans/backlog/` | Reported friction |
| `todo.txt` | `docs/plans/backlog/` | General work notes |

## Stage 1
- Goal: Relocate specification/product copy with working discovery (≤1 hour).
- Dependencies: Planning commit; fresh destination/reference checks.
- Expected changes: First two moves; repair README and `docs/reference/key_backups.md` links; adjust README's root-notes heading to reflect the destinations while retaining `BLESSING.md`.
- Verification approach: Compare both documents byte-for-byte to baseline except necessary relative-link repairs; resolve changed link targets and inspect README navigation; check whitespace and allowed-file diff.
- Risks or open questions: Broken references or overwritten contents; detect through destination checks and baseline comparisons, and correct before committing.
- Canonical components/API contracts touched: README and key-backup documentation; no UI/API changes.

## Stage 2
- Goal: Relocate five planning notes with working discovery (≤1 hour).
- Dependencies: Stage 1 verified, summarized, and committed.
- Expected changes: Remaining moves; link all five in `docs/plans/backlog/README.md` and ensure README reaches that index. Repair `docs/product/v3_feature_priorities.md`, `docs/plans/media_embed_support_checklist.md`, `docs/plans/private_messaging_usability_master_checklist.md`, and `docs/plans/backlog/server_cooperative_content_deletion_discussion.md` links.
- Verification approach: Compare five contents to baseline; validate all seven README-to-document routes and moved-document links; classify residual old paths, preserving historical prose and parser examples. Verify seven root absences, seven destination files, allowed scope, and whitespace; summarize and commit.
- Risks or open questions: Root-relative prose inside notes could be mistaken for document-relative links; preserve repository-path examples, repairing only navigable links whose resolution changes. Newly discovered active links require review within the approved scope.
- Canonical components/API contracts touched: Existing README/backlog index and listed documentation references; no new component or runtime contract.

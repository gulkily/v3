> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Approval adjustment: user approved Step 3 with `todo.txt` retained at the root; leave its content and incoming links unchanged.

- Documentation-only internal maintenance for GitHub visitors/contributors: eight content-preserving moves, six fewer tracked root files, discoverable destinations, and repaired affected live links.
- Allowed scope: the moves below; mechanical reference repairs in host `docs/` and `README.md`; directly related discovery/index additions; this feature's artifacts/log and required `docs/plans/README.md` entry. Exclude vendored Railspike/FDP, unrelated plan organization, substantive rewrites, application/runtime, tests, CI, deployment, generated files, configuration, and ignored directories.
- Evidence: move/content comparisons, affected-link resolution, tracked-reference classification, root-count and changed-path checks. Runtime/browser/deployment/migration checks are not applicable; no runtime behavior or deployment outcome is claimed. SFENC becomes eligible for the existing Markdown viewer without catalog/policy changes.
- Recovery: correct scoped links or reverse the affected move without losing content. Completion requires evidence in the Step 4 summary and committed stages; scope expansion returns to Step 2/3.

## Key Risks

- **High risk: broken discovery.** Five live Markdown links identified before drafting; repair them with their moves and check destination-relative references. Preserve historical quotations, completed-plan records, and parser examples.
- **Exposure/content loss:** reviewed SFENC as a public format specification; the existing viewer admits `docs/*.md` paths, excludes `.txt`/HTML, and requires real files beneath `docs/`. Preserve its policy; compare content and recheck destination absence before moving. No collisions found; the similarly named backlog proposal remains separate.

## Move Map

| Source | Destination directory (filename preserved) |
|---|---|
| `qdb_todo.txt`, `improve_messaging.txt`, `docs_page_changes_revised.txt`, `related_feature_requests.txt` | `docs/plans/backlog/` |
| `sfenc.md` | `docs/specs/` |
| `zenmemes_rules.txt` | `docs/product/` |
| `docs/feature-flags-redesign-spec.md`, `docs/feature-flags-redesign.html` | `docs/plans/backlog/` |

## Stage 1

- Goal: Finish root cleanup with usable discovery (≤1 hour).
- Dependencies: Approved Step 3; feature branch; organize this feature's Steps 1–3/log into `docs/plans/repository_directory_cleanup/`, repair navigation/index, and commit approved planning content before implementation; create summary afterward.
- Expected changes: Move six root files per map; repair links in `README.md`, `docs/reference/key_backups.md`, `docs/product/v3_feature_priorities.md`, and the private-messaging master checklist under `docs/plans/`. Link four relocated notes from the backlog README and link that index from the root README.
- Verification approach: Compare each moved file with its original bytes; permit only necessary reference repairs. Resolve five affected live links plus new discovery links; inspect moved-file references and classify repository-root paths versus relative links; confirm root count drops by six and excluded files stay unchanged.
- Risks or open questions: Broken links/content loss; validate targets and compare content before commit; reverse individual moves if necessary. Pre-existing stale references are recorded, not rewritten as part of this cleanup.
- Canonical components/API contracts touched: Root README, existing backlog index, SFENC specification location; no application component/API changes.

## Stage 2

- Goal: Finish selective second-level cleanup and handoff (≤30 minutes).
- Dependencies: Stage 1 verification, summary update, and stage commit complete.
- Expected changes: Move the feature-flags pair together per map; add backlog index links distinguishing the implementation brief/mockup from `feature_flag_redesign_spec.md`; preserve the same-folder mockup reference.
- Verification approach: Compare pair bytes; resolve brief/mockup and new index links; confirm all eight destinations, absence of old sources, preserved substantive content, allowed change scope, and completed Step 4 plan navigation. Record evidence and commit with summary.
- Risks or open questions: Similar proposal names can confuse discovery; use distinct link labels and retain both. The mockup is self-contained; recheck for relative assets before moving.
- Canonical components/API contracts touched: Existing backlog index and documentation mockup pair; viewer/catalog remain unchanged.

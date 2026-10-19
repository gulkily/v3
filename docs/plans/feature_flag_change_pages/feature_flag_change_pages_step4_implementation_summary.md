> **Feature plan:** [Step 1](./feature_flag_change_pages_step1_solution_assessment.md) · [Step 2](./feature_flag_change_pages_step2_feature_description.md) · [Step 3](./feature_flag_change_pages_step3_development_plan.md) · [Step 4](./feature_flag_change_pages_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/feature_flag_change_pages`, based on `fix/backup-page-tools-nav-order` (unmerged; carries the Backup layout fix that Stage 5 depends on). The base was not specified at approval; this was the recommended option.

## Stage 1 - Shared activity link mapping
- Changes:
  - `ActivityService::activityItemLink()` maps an item to `{href, label}`: signed flag change to `/tools/feature-flags/changes/{id}`, legacy flag change to its commit page (generic flags page only if no valid commit), other kinds unchanged.
  - `tests/ActivityItemLinkTest.php` (registered in `tests/run.php`).
  - No surface uses the helper yet, and the change page route arrives in Stage 2.
- Verification:
  - `php tests/run.php ActivityItemLinkTest`: passed, 4 of 4.
  - Census of the local read model: 6 signed flag items, 0 legacy. Production data not checked, so the legacy fallback stays in place.
  - Full suite not run in this stage (over two minutes); focused result only.
- Notes:
  - Items already carry `source_signature_status` and `source_signature_href`, which Stage 2 can show instead of inventing signature wording.

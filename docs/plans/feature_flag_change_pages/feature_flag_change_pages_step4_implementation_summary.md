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

## Stage 2 - Change page
- Changes:
  - Route `/tools/feature-flags/changes/{id}` (Application route plus the application-route allowlist), `FeatureFlagChangePageController` modeled on `RootApprovalPageController`, template `feature_flag_change.php` with the tools nav, `tool-details.css` mapping.
  - Page shows flag (label and key), new value, time, operator, and source evidence through the existing `source_metadata` partial (path, commit, signature link/status as the activity item already derives them). It states that it does not re-verify the signature.
  - Unknown, malformed, traversal-style or unparseable IDs render "Feature flag change unavailable" with 404.
  - `tests/FeatureFlagChangePageTest.php` (registered in `tests/run.php`).
- Verification:
  - `php tests/run.php FeatureFlagChangePageTest ActivityItemLinkTest`: passed, 6 of 6.
  - `php tests/run.php LocalAppSmokeTest`: passed, 141 of 141.
  - Visual check of the page: pending until Stage 6.
- Notes:
  - Correction to earlier work on `fix/backup-page-tools-nav-order`: running `php tests/LocalAppSmokeTest.php` directly only loads the class and runs nothing, so "smoke test passes" claims made that way were not real checks. The suite was rerun through `tests/run.php` above and passes, including the tab-bar ordering and empty-link assertions from that branch.
  - A record ID must start with `feature-flag-change-`; the test fixture initially used an invalid ID.

## Stage 3 - Activity page and RSS
- Changes:
  - `ActivityPageController` attaches `link` (from `activityItemLink`) to each item; `activity.php` renders it, replacing its four-way branch. The RSS feed uses the same helper's `href`.
- Verification:
  - `php tests/run.php LocalAppSmokeTest FeatureFlagChangePageTest ActivityItemLinkTest ForteActivity`: passed, 147 of 147 (filters match whole classes plus the Forte activity tests).
  - New assertions: the Activity page and RSS link a signed flag change to its change page and no longer to the generic flags page.
  - Full suite not run in this stage.
- Notes:
  - Test method renamed to `testSignedChangeRendersPageAndActivityAndRssLinkToIt` since it now covers all three.

## Stage 4 - Forte Activity
- Changes:
  - `ForteActivityController::activityItemBoardLink` delegates the `site_feature_flag` branch to `activityItemLink`; post, thread and profile branches are unchanged (they link into the Forte board, which the shared helper does not do).
  - Corrected the comment in `paned_activity_detail_article.php` about where flag items point.
- Verification:
  - `php tests/run.php LocalAppSmokeTest FeatureFlagChangePageTest ActivityItemLinkTest ForteActivity ForteBoard`: passed, 147 of 147.
  - New assertion: the Forte activity detail fragment (`/api/forte_activity_detail`) links a signed change to its change page.
- Notes:
  - The Forte profile_name_change branch still duplicates part of the shared mapping; left alone because it is outside this feature's scope.

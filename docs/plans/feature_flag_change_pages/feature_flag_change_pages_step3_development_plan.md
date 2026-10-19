> **Feature plan:** [Step 1](./feature_flag_change_pages_step1_solution_assessment.md) · [Step 2](./feature_flag_change_pages_step2_feature_description.md) · [Step 3](./feature_flag_change_pages_step3_development_plan.md) · [Step 4](./feature_flag_change_pages_step4_implementation_summary.md)

Debug mode: enabled

# Step 3: Development Plan — Feature-Flag Change Pages

## Completion Contract

- Normal entry: click a feature-flag item in Activity, Forte Activity, RSS or the Backup preview.
- End-to-end outcome: signed changes open a readable change page (flag, value, time, operator, evidence links); legacy changes open their commit page; no flag item links to `/tools/feature-flags/`.
- Recovery: unknown or malformed change ID renders the standard unavailable page with a 404.
- Deployment/external verification: none beyond a read-model rebuild check; no schema change, so none is required.
- Release condition: smoke tests pass; each surface checked in dark, light and one retro theme at phone width.

## Key Risks

- **High risk:** Legacy items have no per-change record. Impact: dead or misleading links. Validation: Stage 1 census of live activity source paths. Mitigation: commit-page fallback in the shared mapping.
- **High risk:** `instance.php` also changes on the unmerged `fix/backup-page-tools-nav-order`. Impact: merge conflicts or a regression of that fix. Validation: confirm that branch's status before Stage 5. Mitigation: branch the feature from it, or merge it first.
- Signature evidence is not in the record fields. Impact: overstated "verified" wording. Validation: Stage 2 review of what is derivable. Mitigation: show only record and commit links plus facts in the record.
- Route registration lives in several lists (Application, static artifacts, front controller). Impact: page 404s in some hosts. Validation: Stage 2 grep. Mitigation: register everywhere `/tools/feature-flags/` is.

## Stage 1
- Goal: one shared mapping from an activity item to a link and label, with a legacy census.
- Dependencies: none.
- Expected changes: helper (e.g. `activityItemLink(array $item): array{href, label}`) on the activity service; signed items map to the change page via the record ID in `source_path`, legacy to the commit page; census of live items recorded in the summary.
- Verification approach: unit assertions for signed, legacy, post, thread and profile-name items.
- Risks or open questions:
  - Impact: other kinds regress.
  - Early warning / validation: existing activity smoke assertions.
  - Mitigation: helper preserves current output for non-flag kinds.
- Canonical components/API contracts touched: ActivityService item shape (read only).

## Stage 2
- Goal: the change page renders and fails safely.
- Dependencies: Stage 1.
- Expected changes: route `/tools/feature-flags/changes/{id}`; controller modeled on `RootApprovalPageController`; template using the tools nav and Feature Flags classes; unavailable page.
- Verification approach: smoke tests for valid, unknown and malformed IDs.
- Risks or open questions:
  - Impact: path traversal via the ID.
  - Early warning / validation: malformed-ID test.
  - Mitigation: strict ID pattern and `SourcePathValidator`.
- Canonical components/API contracts touched: `CanonicalRecordRepository::loadFeatureFlagChange`, source viewer hrefs.

## Stage 3
- Goal: Activity page and RSS use the shared mapping.
- Dependencies: Stage 1, 2.
- Expected changes: `activity.php` and `ActivityPageController::rss` call the helper; duplicate branches removed.
- Verification approach: smoke assertions on both outputs.
- Risks or open questions:
  - Impact: RSS link format change.
  - Early warning / validation: RSS assertion.
  - Mitigation: absolute-path format unchanged.
- Canonical components/API contracts touched: activity template, RSS feed.

## Stage 4
- Goal: Forte Activity uses the shared mapping.
- Dependencies: Stage 3.
- Expected changes: `ForteActivityController::activityItemBoardLink` and the paned detail partial.
- Verification approach: smoke assertions for Forte activity.
- Risks or open questions:
  - Impact: Forte board-link behavior for posts changes.
  - Early warning / validation: existing Forte tests.
  - Mitigation: only the flag branch delegates.
- Canonical components/API contracts touched: Forte activity link contract.

## Stage 5
- Goal: Backup preview uses the shared mapping.
- Dependencies: Stage 3; base branch includes the Backup layout fix.
- Expected changes: replace the local mapping in `instance.php`.
- Verification approach: smoke test and rendered preview check.
- Risks or open questions:
  - Impact: conflict with the layout fix.
  - Early warning / validation: branch diff.
  - Mitigation: see Key Risks.
- Canonical components/API contracts touched: `instance.php`.

## Stage 6
- Goal: acceptance across all surfaces.
- Dependencies: Stages 1-5.
- Expected changes: tests only, plus summary evidence.
- Verification approach: grep shows no remaining `site_feature_flag` link copies; full smoke test; visual check in dark, light and one retro theme at phone width; click-through from each surface.
- Risks or open questions:
  - Impact: a missed surface.
  - Early warning / validation: grep.
  - Mitigation: fix before closing.
- Canonical components/API contracts touched: none new.

## Approval Gate

Reply **Approved Step 3** to start implementation on a feature branch.

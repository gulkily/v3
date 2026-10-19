> **Feature plan:** [Step 1](./feature_flag_change_pages_step1_solution_assessment.md) · [Step 2](./feature_flag_change_pages_step2_feature_description.md) · [Step 3](./feature_flag_change_pages_step3_development_plan.md) · [Step 4](./feature_flag_change_pages_step4_implementation_summary.md)

Debug mode: enabled

# Step 2: Feature Description — Feature-Flag Change Pages

Approved direction: Step 1 Option B (dedicated change page; legacy items fall back to their commit page).

## Problem

Feature-flag changes appear in Activity and Backup but have no page of their own, so every link lands on the current-state flags page. Readers cannot see who changed which flag, when, or the evidence.

## User stories

- As a site member, I want an activity item for a flag change to open a page about that change so that I can see what changed and who did it.
- As an operator, I want each change page to link to the signed record and commit so that I can verify the change independently.
- As a reader of the Backup preview, I want the feature-flag line to link to that change so that I am not sent to a generic page.

## Core requirements

- Each signed flag change has a stable, readable page showing flag, new value, time, operator, and signature status.
- The page links to the canonical record and its commit using the existing source viewer.
- Legacy changes without a per-change record link to their commit page and are not given a broken page.
- Activity, RSS, Forte Activity and Backup link to the new page through one shared mapping.
- No change to flag evaluation, storage, or the change/signing flow.

## Delivery scope

- Work type: application change.

## Completion boundary

- Normal entry: open Activity (or the Backup preview) and click a flag-change item.
- End-to-end outcome: the change page renders with attribution and evidence links; back navigation returns to Activity.
- Recovery: an unknown or malformed change ID shows the standard not-found page; a legacy item still lands on its commit page.
- Release condition: smoke tests pass and each linking surface is checked in dark, light and one retro theme at phone width.

## Risks

- **Legacy items lack records** — impact: broken or misleading links. Earliest validation: list live activity items by source path before Step 3. Mitigation: commit-page fallback.
- **Signature status wording** — impact: implying verification the server did not perform. Validation: review against how signed records are verified today. Mitigation: show only status already derivable from the record.
- **Four duplicated link mappings** — impact: one surface missed and left pointing at the old page. Validation: grep for `site_feature_flag` in Step 3. Mitigation: introduce one shared mapping first.
- **Read-model fields** — impact: needing a rebuild if the page needs data not in activity rows. Mitigation: prefer reading the canonical record directly; avoid schema change.

## Shared component inventory

- Activity page (`activity.php`): extend with the shared mapping.
- Activity RSS (`ActivityPageController::rss`): extend.
- Forte Activity (`ForteActivityController`) and paned detail partial: extend.
- Backup preview (`instance.php`): extend; currently links to `/tools/feature-flags/`.
- Source viewer (`/source/current/…`, `/source/commits/…`): reuse for evidence links.
- Root approval evidence pages (`/approvals/root/…`): reuse layout and signature-status presentation as the precedent.
- Change page: new, since no existing surface presents a single change.

## Simple user flow

1. User opens Activity or Backup.
2. User clicks a feature-flag change item.
3. The change page shows flag, value, time, operator and signature status.
4. User follows the record or commit link to verify, or goes back.

## Success criteria

- Every `site_feature_flag` item on all four surfaces links somewhere specific; none link to the generic flags page.
- Items with a record open a page showing all five fields; legacy items open a commit page.
- Smoke tests cover the page, the fallback, and not-found, and pass.
- Mapping lives in one place; no duplicate copies remain.

## Enhancement Requests

- **Request:** Shared helper for the activity kind-to-link mapping. **Disposition:** now in scope as a core requirement, since it is how the page is linked everywhere. **Approval status:** pending Approved Step 2. **Outcome:** Step 3.

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.

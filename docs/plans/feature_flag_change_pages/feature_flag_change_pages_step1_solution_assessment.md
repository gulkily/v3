> **Feature plan:** [Step 1](./feature_flag_change_pages_step1_solution_assessment.md) · [Step 2](./feature_flag_change_pages_step2_feature_description.md) · [Step 3](./feature_flag_change_pages_step3_development_plan.md) · [Step 4](./feature_flag_change_pages_step4_implementation_summary.md)

Debug mode: enabled

# Step 1: Solution Assessment — Feature-Flag Change Pages

## Original Query

OK, there's no pages for feature change items, I guess? Write Step 1 for that.

## Understood Intent

Feature-flag changes appear as Activity items (and in the Backup page's recent-items preview), but they have no page of their own to link to; today they all point at the generic `/tools/feature-flags/` page. Give each change a linkable destination, as posts, threads and profile name changes already have.

## Problem

A feature-flag change is logged as activity with a canonical record and commit, but no page presents that individual change, so every link lands on the current-state flags page.

## Options

### Option A: Link to the existing source viewer

Activity and Backup items link to the change's canonical record (`/source/current/records/feature-flag-changes/…`) or its commit page, which already exist.

- Pros: no new page or route; shows the exact signed record.
- Cons: raw file view, not a readable page; legacy items have no per-change record, only the snapshot commit; wording and layout stay unfriendly.

### Option B: Dedicated change page per item

A readable page for one change (flag, new value, operator, time, signature status, links to the record and commit); Activity, Backup, RSS and Forte items link to it.

- Pros: matches how other activity kinds work; permalinkable; presents attribution meaningfully.
- Cons: new route, template and tests; legacy changes without a record need a fallback (commit page); touches several link sites.

### Option C: Change history on the Feature Flags page

Add a history section (per flag or global) on `/tools/feature-flags/`; items link to an anchor for their change.

- Pros: one place to review all changes; no new route.
- Cons: anchors are fragile as history grows; no single-change permalink; adds weight to an already dense page.

## Recommendation

Choose **Option B**, with legacy items falling back to their commit page. One vertical slice: an activity item for a signed flag change opens a readable change page that shows who changed what, when, and links to the record. Option A's source links become the page's evidence links, so the two are compatible.

## Enhancement Requests

- **Request:** Backup page recent-items preview linked feature-flag items. **Disposition:** done on `fix/backup-page-tools-nav-order` (`c7535a96`), pointing at `/tools/feature-flags/` until this feature exists. **Approval status:** n/a. **Outcome:** retarget in this feature's stages.
- **Request:** Shared helper for the activity kind-to-link mapping (four copies today). **Disposition:** separate work, not requested. **Approval status:** pending user decision. **Outcome:** none yet.

## Approval Gate

Reply **Approved Step 1** to proceed to the feature description.

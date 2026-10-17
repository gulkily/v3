> **Feature plan:** [Step 2](./forte_activity_parity_step2_feature_description.md) · [Step 3](./forte_activity_parity_step3_development_plan.md) · [Step 4](./forte_activity_parity_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Forte Activity and the default Activity page share activity data but differ in controls and presentation of evidence. Forte's security-view row membership is missing from rendered/client-loaded rows, and its policy explanation appears outside the activity window.

## User stories

- As a reader, I want equivalent activity categories, event information, and evidence links in either view so that choosing Forte does not hide information.
- As an operator, I want reliable security-view selection and policy context so that I can inspect grants without losing rows or mixing restricted details into ordinary views.

## Core requirements

- Retain Forte's split-pane layout, keyboard navigation, sorting, pagination, and separate Commits view; match the default view's shared event information, categories, and eligible RSS access.
- Keep event labels, dates, author information, content destinations, signature/source/commit evidence, and operator acceptance details consistent with the default view using existing shared data and partials.
- Make security filtering, switching, initial selection, lazy detail loading, and pagination work together; show the same policy explanation within Forte's activity window.
- Preserve authenticated operator authorization, no-store behavior, and exclusion of security data from RSS/public caches; restricted details must not persist when switching to ordinary views.
- Preserve responsive behavior and avoid loading complete large commit manifests for every activity item.

## Delivery scope and completion boundary

Application change. Scope covers Activity controllers, Forte templates/client behavior, shared activity presentation where useful, styling, and focused tests. Enter through Forte Activity, choose a category, inspect an event and its evidence, page/sort/switch views, and recover from fetch failures. Complete after browser checks and role/cache regression checks pass. Root approval pages, canonical authority changes, production deployment, and a replacement of Forte with card layout are outside this proposal.

## Risks

- Restricted detail leakage across client-side switches: validate operator-to-ordinary view transitions first; keep authorization and response/cache boundaries intact.
- Missing/duplicated rows or stale selection after sorting/paging: compare shared-category results early and verify keyboard/mouse navigation and lazy loading.
- Large historical manifests can make the page unusable: retain bounded item loading, relevant-file summaries, and on-demand commit details.

## Shared component inventory

Reuse ActivityService and default Activity's category/evidence contracts. Extend ForteActivityController, paned activity filter/list/detail partials, and paned_activity_reader.js rather than creating a second data source. Reuse approval_security_review, source_metadata, and activity_commit_manifest partials. Use existing activity/Forte CSS and theme variables.

## User flow and success criteria

1. Open `/forte/activity/`; select the same shared categories available on `/activity/` and inspect corresponding events.
2. Select another event, sort, load more, and change categories; rows, details, counts, and destinations stay consistent.
3. As an authenticated operator, open security review and inspect acceptance/policy evidence; ordinary readers remain denied and security has no RSS.

Success: shared-category event sets and facts agree under equivalent query limits; desktop/phone and keyboard flows work; security switch/paging/detail tests and authorization/cache tests pass.

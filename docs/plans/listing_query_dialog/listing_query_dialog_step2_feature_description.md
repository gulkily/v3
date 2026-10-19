> **Feature plan:** [Step 1](./listing_query_dialog_step1_solution_assessment.md) · [Step 2](./listing_query_dialog_step2_feature_description.md) · [Step 3](./listing_query_dialog_step3_development_plan.md) · [Step 4](./listing_query_dialog_step4_implementation_summary.md)

Debug mode: enabled

## Approved delivery split

The user approved this split with `Approved Step 2` after the sizing review. The original broader scope remains recorded below as follow-up context; the first-release boundary here governs Step 3.

- **First release:** `/tools/codebase/` SQL displays and standard online board listings (excluding Forte and QDB variants). Complete the shared dialog, editable automatic execution, saved public read-model reuse/refresh/provenance, per-context drafts across same-tab navigation, background-state retention, accessibility, and failure recovery. Preserve standalone viewer access. Cover every standard board filter/order and empty listing with fixed-data parity checks.
- **Required follow-up scope:** activity/tags/content details, directories/profile lists, QDB variants/search/random, Forte dynamic panes, and offline reader equivalents. Carry forward the original parity, source selection, and state/recovery requirements. Keep the approved unavailable-source classifications; place their explanations when their listing families are delivered. The broader “every listing” goal remains incomplete until these follow-ups are delivered.
- **First-release acceptance:** codebase and standard board entry → automatic local results → edit/rerun → close/reopen and unchanged background; saved-data reuse, explicit refresh, navigation draft recovery, cold/warm timings, keyboard/small-screen checks, and retry/storage-denial tests. No new exports/schema or private databases. The reader snapshot lifecycle stays a follow-up integration dependency.
- **Request record:** Split the delivery after Step 3 sizing / Changed scope to preserve releasable outcomes within the Railspike day/eight-stage limit / Approved by renewed `Approved Step 2` / Draft Step 3 for this first release; later cycles require their own plans and approvals.

## Problem

Listing pages and existing SQL displays do not provide a quick way to reproduce, edit, and rerun their queries against saved data while preserving the user's place.

## User stories

- As a reader, I want to open a listing's query and results in one action so that I can explore its data.
- As a query author, I want my edits and the underlying page retained so that I can switch between reading and investigation.

## Core requirements

- Provide a contextual **View query** action for each covered listing, including empty listings and existing SQL displays in `/tools/codebase/`. Opening loads editable SQL and automatically executes it once the selected local database is ready. Preserve active filters, deterministic ordering, and the source page's pagination scope; randomized listings reproduce the selected items and their order. Viewer pagination applies within that scope.
- Use one shared dialog and the existing viewer capabilities, including rerun, sorting, pagination, and effective-query inspection. Show the shell before loading completes; subsequent opens reuse the ready database. Keep the standalone viewer available.
- Retain editor text, results, viewer controls, and scroll on close/reopen. Restore background scroll, selection, forms, and focus on close or Escape; support keyboard focus containment and small screens. Revisiting the same listing resumes its draft; switching listings retains drafts separately. Retain drafts through same-tab navigation/reload, with graceful recovery when storage is unavailable; reopening after navigation may rerun results.
- Prefer the already saved database for the listing's source: published read-model data for online pages, reading-snapshot data for offline pages. Reuse loaded data; when absent, fetch and save the appropriate source if online. Display source and available revision/time, explain possible live/saved differences, and offer explicit refresh. Missing offline data, incompatible data, query errors, or load failures retain SQL and provide an actionable retry or refresh.
- Execute read-only queries locally. Query payloads and saved data must respect the existing public/private boundary. Unsupported sources show a specific explanation rather than a misleading runnable query.

## Delivery scope and completion boundary

**Work type: application change.** Cover read-model-backed boards (including Forte/QDB variants), activity, tags and tag results, user/pending-user directories, profile/username content lists, thread/post reply lists, QDB search/random results, offline reader equivalents, and codebase SQL displays. Include nested listings and dynamically changed filters. Static navigation, prose, forms, and file/shell-source displays are not data-query targets.

**Proposed explicit coverage gaps:** private-message lists/conversations, outbox, visitor statistics, LLM exchange lists, feature flags, invitation/approval operational lists, and repository/file-derived listings lack equivalent data in the public saved databases. Explain unavailability beside these listings; adding exports or new local databases for them is deferred and requires separate scope approval. Read-model-backed portions of mixed pages remain covered. Approval of this step accepts this boundary, not completion of the broader eventual goal for those sources.

Release requires the complete open → automatic results → edit/rerun → close/reopen → return-to-page flow across the covered inventory, including recovery from unavailable data. If the inventory cannot fit one day/eight stages, return for an explicitly approved delivery split before implementation.

## Shared component inventory

- Reuse/extend the standalone SQLite viewer, query editor/results/explorer, preset catalog, and database download surface.
- Reuse board/thread/post cards, activity lists, tag groups, directory/profile lists, and Forte paned listings; add shared query entry behavior rather than separate viewers.
- Extend codebase SQL source displays; retain their file/shell-source explanations.
- Reuse offline reader data loading, saved snapshot, and offline health/source information; avoid a second conflicting snapshot lifecycle.
- Add the retained dialog host and listing-query context contract: existing surfaces have no common dialog or contextual auto-run interface. Existing listing APIs retain their access rules.

## Risks

- **Coverage drift:** presentation filters, derived counts, and random ordering can diverge from SQL. Validate representative variants against fixed data before dependent rollout; require a per-listing coverage and parity checklist in Step 3.
- **Different saved sources:** stale or partial snapshots can yield missing rows/tables. Validate source compatibility early; identify data provenance and recover through explicit refresh or an honest unsupported state.
- **Latency and lost state:** cold initialization or synchronous queries can delay interaction. Measure cold/warm opening and run close/navigation recovery checks early; retain the viewer and drafts, avoid repeat loads, and revise execution strategy if interaction blocks.
- **Unavailable/private data:** some lists cannot be reproduced from public data. Confirm source classification before wiring actions; use the explicit gaps above and never broaden public exports implicitly.

## Simple user flow and success criteria

1. Open a listing, choose **View query**, and see the dialog immediately with source context and automatically produced results.
2. Edit SQL and rerun; close and reopen to recover the same work, then return to the unchanged background page.
3. Change listings or navigate and recover the appropriate draft; recover failed loads using the visible retry/refresh action.

Acceptance: covered listings match row identities, ordering, filters, and page scope on a fixed matching dataset; warm reopening performs no database download or initialization; the shell opens without awaiting network/runtime work; keyboard and small-screen checks pass; all declared coverage gaps have a specific explanation. Report cold/warm timings on the test environment rather than claiming universal latency.

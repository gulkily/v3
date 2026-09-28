# Forte Activity Instant Loading — Step 2: Feature Description

## Problem

Opening Forte Activity delays the first usable view because it eagerly prepares rows and technical details that are not yet visible. It should open in the same near-instant range as Board and Users while still automatically making every Activity list complete after rendering.

## User stories

- As a Forte reader, I want Activity's first visible list and detail to appear immediately so that opening the section does not interrupt my flow.
- As a Forte reader, I want all Activity filter lists to finish preloading automatically so that switching filters is ready without a wait or a manual load action.
- As a Forte reader, I want full technical detail when I select an item so that faster loading does not remove existing information.

## Core requirements

- Render only a small selected-filter batch and its selected detail in the initial response.
- After first paint, automatically preload every remaining list row for every Activity filter, including older paginated rows, without awaiting reader interaction.
- Keep the page responsive: preload work must yield to direct reader input and never block navigation, selection, sorting, or keyboard controls.
- Retrieve technical detail only when its item is selected, then retain it for the visit.
- Preserve Activity's existing filters, sort order, deep links, pagination continuity, Commit rows, and access behavior.

## Shared component inventory

- **`ActivityService` activity/commit queries:** reuse as the canonical source for Forte, classic Activity, RSS, and backup consumers; extend only as needed for lightweight list data, leaving non-Forte consumers unchanged.
- **Forte Activity page and list/detail partials:** extend the existing canonical Forte shell and row rendering; change the initial page to the small visible batch rather than fork a second Activity UI.
- **`/api/forte_activity_page`:** extend the existing Forte paging surface for automatic list preload; retain its current cursor/sort contract.
- **`/api/forte_commit_detail`:** reuse for selected Commit detail; no eager Commit manifest rendering.
- **`paned_activity_reader.js`:** extend its existing filter, selection, history, keyboard, sort, and Load more behavior to manage preload and per-visit caches.

## User flow

1. Reader opens Activity and immediately sees the selected filter's first rows and selected detail.
2. The page automatically preloads all remaining filter rows after first paint while remaining interactive.
3. Reader switches filters with their rows already available and selects an item to view its technical detail.

## Success criteria

- On a production-representative read model, the selected Activity view becomes visible and interactive within 500 ms on the agreed local benchmark, including a cold metadata cache.
- The first render's row/detail count remains bounded regardless of total Activity history.
- Without reader action, every filter's complete row list finishes loading; subsequent filter switches use the preloaded list.
- Deep links, sorting, keyboard navigation, Load more continuity, Commit detail, and classic Activity/RSS/backup behavior remain correct.

Share this document for review — **Approved Step 2** is required before Step 3.

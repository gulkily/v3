# QDB Tag View Filter Scope — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_tag_view_filter_scope_step1_solution_assessment.md) · [Step 2](./qdb_tag_view_filter_scope_step2_feature_description.md) · [Step 3](./qdb_tag_view_filter_scope_step3_development_plan.md) · [Step 4](./qdb_tag_view_filter_scope_step4_implementation_summary.md)

## Problem

QDB quote eligibility must not narrow shared tag pages. A QDB visitor to `/tags/general` needs every visible thread with that tag, while named QDB quote collections remain quote-only.

## User Stories

- As a QDB reader, I want `/tags/general` to show all visible general-tagged content so that tags remain complete discovery pages.
- As a QDB reader, I want latest, top, leetness, random, search, and RSS to show only quotes so that quote browsing remains consistent.
- As a maintainer, I want the tag and quote-collection boundaries protected by tests so that future filtering changes cannot blur them.

## Core Requirements

- On the QDB profile, `/tags/` and `/tags/{tag}` include every visible tagged root, including roots without QDB quote IDs.
- QDB-specific quote collections admit only roots recognized by the existing quote-ID contract before rendering, counting, sorting, pagination, shuffling, search, or syndication.
- Shared tag URLs retain the same all-content meaning across profiles; tag navigation and generated tag artifacts continue to resolve.
- Do not add tag routes, alter the database, or change direct thread permalink behavior.

## Delivery Scope

- Work type: application change — preserve the existing route-policy separation and add profile-specific regression coverage.
- Out of scope: tag taxonomy, new quote-tag views, offline-reader behavior, database changes, and generic-profile behavior changes.

## Completion Boundary

- Normal entry: a QDB visitor opens `/tags/general` with both quote and non-quote roots carrying `general`.
- End-to-end outcome: that page renders both roots, while the QDB quote collections render only the quote root.
- Recovery: an unknown tag remains not found; direct thread routes and static tag artifact resolution retain their current outcomes.
- Release condition: targeted QDB tag, QDB collection, and static-route regression checks pass with no new relevant-suite failures.

## Risks

- Quote filtering could be introduced into shared tag grouping. Impact: non-quote tagged content disappears. Earliest validation: QDB-profile mixed-tag fixture. Mitigation: assert both roots on `/tags/general`.
- A QDB collection path could bypass quote eligibility. Impact: non-quotes appear as quotes. Earliest validation: the same fixture across every QDB collection family. Mitigation: retain one policy-driven eligibility boundary.
- Dynamic and generated tag pages could diverge. Impact: deployed tag links return stale or incomplete content. Earliest validation: generated `/tags/general` route check. Mitigation: cover the shared rendered path and its static artifact.

## Shared Component Inventory

- `TagsPageController`, `ThreadRepository`, `TagGrouping`, and the tag templates — reuse as the canonical all-visible-content tag path; do not apply QDB quote eligibility.
- `QdbBoardPolicy` — reuse as the sole quote-eligibility contract for QDB collections.
- `BoardPageController` and `QdbExperience` — retain their policy-driven QDB listing, random, search, and RSS surfaces; no separate filter.
- Static artifact generation and front-controller tag resolution — reuse unchanged; extend regression coverage for QDB-profile tag output.
- Offline tag results — existing separate saved-snapshot UI; out of scope because it has no QDB profile-specific collection policy.

## Simple User Flow

1. A QDB visitor follows `/tags/general`.
2. The shared tag path groups all visible `general` roots and renders both quote and non-quote content.
3. The visitor opens a QDB-specific collection or feed.
4. The QDB policy renders only roots with recognized quote IDs.

## Success Criteria

- A QDB-profile mixed fixture shows both a quote root and a non-quote root at `/tags/general`.
- The same fixture shows only the quote root on each applicable QDB collection surface and feed.
- Tag directory links and dynamic/static `/tags/general` routing remain available.
- The equivalent shared tag behavior remains unchanged outside QDB.

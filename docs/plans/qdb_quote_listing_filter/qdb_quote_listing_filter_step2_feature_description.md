# QDB Quote Listing Filter — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_quote_listing_filter_step1_solution_assessment.md) · [Step 2](./qdb_quote_listing_filter_step2_feature_description.md) · [Step 3](./qdb_quote_listing_filter_step3_development_plan.md) · [Step 4](./qdb_quote_listing_filter_step4_implementation_summary.md)

## Problem

QDB collection surfaces currently include top-level threads without QDB quote IDs, which presents generic or legacy threads as quotes. The QDB experience needs one collection rule while preserving generic-profile behavior and direct historic permalink recovery.

## User Stories

- As a QDB reader, I want every browsed, searched, shuffled, counted, or syndicated item to have a quote ID so that QDB consistently contains quotes.
- As a reader of an old direct link, I want a valid legacy thread permalink to continue resolving so that existing references do not break.
- As a maintainer, I want one quote-eligibility rule so that a new QDB surface cannot accidentally expose non-quotes.

## Core Requirements

- Treat a top-level thread as a QDB quote only when the existing canonical quote-ID contract recognizes it.
- Apply that rule before QDB collection counts, sorting, pagination, and rendering on welcome, latest, top, leetness, random, search, and RSS surfaces.
- Keep full direct permalinks to existing non-quote threads resolvable as historic recovery; do not expose them from QDB collections or numeric quote routes.
- Leave generic profile listings, shared instance data, existing thread IDs, and the database schema unchanged.
- Use the same eligibility rule for all QDB collection surfaces; do not add independent ID parsing or a per-page exception.

## Delivery Scope

- Work type: application change — QDB collection behavior, regression coverage, and its implementation summary.
- Out of scope: migrating legacy thread IDs, deleting or hiding direct historic permalinks, generic-profile behavior, database changes, and new QDB route families.

## Completion Boundary

- Normal entry: a visitor opens any QDB collection surface or subscribes to its feed.
- End-to-end outcome: only recognized quote-ID roots are listed, counted, sorted, paginated, shuffled, searched, or syndicated.
- Recovery: a full valid legacy thread permalink remains available; unknown numeric quote paths and missing threads retain their current rejection behavior.
- Release condition: targeted QDB collection and permalink tests pass, the relevant suite has no new failures, and the implementation summary records the evidence.

## Risks

- A collection surface may be missed. Impact: a non-quote remains visible. Earliest validation: enumerate QDB routes and RSS before planning. Mitigation: route-level matrix with a quote and a legacy root.
- Filtering after pagination or counting can create sparse pages or inflated totals. Impact: misleading navigation. Earliest validation: a mixed fixture spanning a page boundary. Mitigation: filter before all collection operations.
- A second ID parser can drift from quote routing. Impact: inconsistent eligibility. Earliest validation: malformed and non-quote IDs. Mitigation: reuse the existing quote-ID contract only.

## Shared Component Inventory

- `QdbQuoteNumbers`: reuse as the sole quote-eligibility contract; no new ID format.
- `QdbBoardPolicy`: extend as the canonical QDB collection policy for filtering and count behavior.
- `BoardPageController`: reuse for latest, top, leetness, pagination, and RSS; pass the selected QDB policy rather than fork generic listing behavior.
- `QdbExperience`: reuse for welcome, random, and search; obtain eligible threads through the same policy.
- Direct thread routing and quote-card rendering: reuse unchanged for historic permalink recovery.

## Simple User Flow

1. A visitor selects the QDB profile and opens a collection page, search, random page, or feed.
2. The QDB policy admits only roots with recognized quote IDs before the surface processes them.
3. The surface renders quotes and an accurate count or pagination result.
4. A reader following a valid historic full permalink can still reach that thread directly.

## Success Criteria

- Every QDB collection surface excludes a mixed-fixture root without a quote ID.
- Counts and pagination reflect only eligible quotes.
- A valid numeric quote route and full historic legacy permalink retain their existing outcomes.
- The same mixed fixture remains visible through the applicable generic-profile collection path.

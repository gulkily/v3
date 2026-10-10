# QDB Tag View Filter Scope — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_tag_view_filter_scope_step1_solution_assessment.md) · [Step 2](./qdb_tag_view_filter_scope_step2_feature_description.md) · [Step 3](./qdb_tag_view_filter_scope_step3_development_plan.md) · [Step 4](./qdb_tag_view_filter_scope_step4_implementation_summary.md)

## Original Query

On the QDB site, if I access `/tags/general`, I want to still see all the content. I only want it filtered for only quotes on the QDB-specific views. Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

Keep the shared tag directory and per-tag pages complete on QDB; retain quote-only eligibility exclusively on QDB-specific collection surfaces.

## Problem

Quote eligibility can be applied too broadly, causing a shared tag URL such as `/tags/general` to omit valid non-quote content on the QDB profile.

## Options

### Option A — Preserve shared tag aggregation and scope quote filtering to QDB collections

Keep `/tags/` and `/tags/{tag}` on their shared, all-visible-content path; use the existing QDB quote-eligibility policy only for named QDB quote views.

- Pros: matches the requested `/tags/general` outcome; preserves one shared tag meaning across profiles; reuses the existing QDB collection boundary.
- Cons: requires focused regression coverage so a later QDB filtering change does not leak into tag routes.

### Option B — Filter tag routes when the QDB profile is active

Pass QDB quote eligibility into `/tags/` and `/tags/{tag}` only on the QDB site.

- Pros: makes every QDB-branded listing quote-only.
- Cons: directly hides the content the request requires `/tags/general` to retain; gives the same tag URL profile-dependent meaning.

### Option C — Introduce separate quote-tag routes

Keep shared tags complete and add a distinct QDB-only route family for quote-tag results.

- Pros: makes all-content and quote-only tag results explicit.
- Cons: adds routes and navigation beyond the requested scope when QDB-specific collection views already provide quote-only browsing.

## Recommendation

Adopt Option A. Treat tag routes as shared all-content discovery, even on QDB, and keep quote filtering within QDB-specific views such as latest, top, leetness, random, search, counts, pagination, and feeds. This is a releasable vertical slice: a mixed tagged fixture remains complete at `/tags/general` while every QDB quote collection remains quote-only.

## Continuation Handoff

- Current evidence: `TagsPageController` groups all `ThreadRepository` results, while `QdbBoardPolicy::eligibleQuotes()` is used by QDB collection paths.
- Scope boundary: no new tag route family, database change, or change to generic-profile tag behavior.
- Resume point: define the exact route matrix and regression coverage in Step 2. Do not create Step 2 until the user responds `Approved Step 1`.

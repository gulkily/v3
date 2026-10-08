# QDB Site News — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_site_news_step1_solution_assessment.md) · [Step 2](./qdb_site_news_step2_feature_description.md) · [Step 3](./qdb_site_news_step3_development_plan.md) · [Step 4](./qdb_site_news_step4_implementation_summary.md)

## Original Query

For the recent activity page on the welcome page of the QDB site, can you please change it to be site news:

- Only display things tagged #news; doesn't have to be with a quote ID.
- Display in reverse-chronological order.
- Display with title and date.
- I think that's it?

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Understood Intent

Replace the QDB welcome panel's quote-derived “Recent activity” list with a “Site News” list of #news-tagged entries, without requiring a QDB quote ID.

## Problem

The QDB welcome panel currently selects quote-ID threads by last activity and shows an ID plus a body preview, so it cannot serve as a chronological news list.

## Options

### Option A — QDB welcome-only news-thread selection

Select #news-tagged top-level threads for the QDB welcome panel, newest authored first, and render each linked title with its authored date.

- Pros: directly matches the panel's existing thread/link model; permits non-quote IDs; uses existing tags, subjects, and timestamps; no schema change.
- Cons: limits news to top-level entries; entries without a title need an explicit display fallback in later planning.

### Option B — Reuse a general tagged-content surface

Drive the panel from a generic #news tag listing and adapt that result to the QDB welcome layout.

- Pros: establishes a reusable tagged-content source for later surfaces.
- Cons: expands the feature beyond one panel; generic listing semantics may not guarantee the requested title/date news presentation.

### Option C — Filter the activity event stream by #news

Keep the current activity concept, filtering activity records by tag and relabeling the panel.

- Pros: preserves an activity-oriented source and could include non-content events.
- Cons: activity labels/events are not reliably titled news entries; event timestamps represent activity rather than necessarily news publication; adds no value over the existing content model.

## Recommendation

Adopt Option A: make the QDB welcome panel a QDB-only, reverse-chronological list of #news-tagged top-level threads, linked by title and dated by their authored timestamp. It is a releasable vertical slice: #news entries with any valid thread ID become visible as Site News, while quote collections and generic profile surfaces remain unchanged.

## Continuation Handoff

- Existing evidence: QDB already filters quote collections with `QdbQuoteNumbers`, while thread rows already provide `board_tags`, `subject`, and `root_post_created_at`.
- Scope boundary: no database migration, new news authoring workflow, generic tag-page redesign, or change to quote-list eligibility.
- Resume point: review this Step 1. Do not create Step 2 until the user responds `Approved Step 1`.

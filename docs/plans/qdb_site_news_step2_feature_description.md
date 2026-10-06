# QDB Site News — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_site_news_step1_solution_assessment.md) · [Step 2](./qdb_site_news_step2_feature_description.md) · [Step 3](./qdb_site_news_step3_development_plan.md) · [Step 4](./qdb_site_news_step4_implementation_summary.md)

## Problem

The QDB welcome panel is a five-item, quote-ID-only activity list ordered by recent activity, rather than a concise source of current site news. Readers need the three newest #news entries, identified by title and date, with a way to reach older news.

## User Stories

- As a QDB visitor, I want the Welcome page to show the latest site news so that I can quickly see current announcements.
- As a QDB reader, I want each news item to show its title and publication date so that I can judge and open it.
- As a QDB reader, I want a link to all #news entries when more exist so that I can find older announcements.

## Core Requirements

- Rename the welcome-panel heading from “Recent activity” to “Site News.”
- Include only visible top-level entries tagged `news`, regardless of whether their IDs are QDB quote IDs.
- Order entries reverse-chronologically by their authored date and render no more than the three newest.
- Render each item as its linked title and authored date, using the site’s established title/date conventions.
- When additional #news entries exist, offer a continuation link to `/tags/news`; preserve a clear empty-state outcome when none exist.

## Delivery Scope

- Work type: application change — QDB Welcome data selection, presentation, regression coverage, and implementation summary.
- Out of scope: a news authoring workflow, tag taxonomy changes, quote-list eligibility, generic tag-page redesign, database changes, and a new route or API.

## Completion Boundary

- Normal entry: a visitor opens the QDB Welcome page.
- End-to-end outcome: the visitor sees up to three newest #news entries with title/date links and can continue to `/tags/news` when older entries exist.
- Recovery: an empty news collection renders a clear no-news state; regular quote navigation and non-news QDB content retain their current behavior.
- Release condition: QDB welcome and tag-continuation coverage pass, applicable static rendering remains valid, and the implementation summary records verification.

## Risks

- A quote-ID filter could remain in the panel. Impact: valid non-quote news is omitted. Earliest validation: fixture with a #news root lacking a quote ID. Mitigation: assert it appears in the panel.
- Ordering could use reply activity instead of publication date. Impact: old news reshuffles after replies. Earliest validation: a fixture where those dates disagree. Mitigation: assert authored-date descending order.
- The continuation link could be missing or lead nowhere in static output. Impact: readers cannot access older news. Earliest validation: fixture with four #news roots and a generated route check. Mitigation: reuse the existing `/tags/news` route and static artifact handling.

## Shared Component Inventory

- `QdbExperience` welcome selection: extend as the canonical QDB-only source for the panel; do not alter quote collection policy.
- `qdb_welcome.php`: extend the existing welcome-panel presentation for its new heading, title/date items, empty state, and continuation link.
- `ThreadRepository`: reuse its visible top-level thread rows and existing tag/title/date data; no new content API.
- `TagsPageController` and `/tags/news`: reuse unchanged as the all-news destination; its existing static-route support remains the continuation surface.

## Simple User Flow

1. A visitor opens QDB Welcome.
2. The panel shows the newest three #news entries by authored date, each with title and date.
3. If more news exists, the visitor follows “all news” to `/tags/news`.
4. If no news exists, the panel communicates that state without affecting quote browsing.

## Success Criteria

- A mixed fixture displays only #news-tagged roots, including a #news root without a quote ID.
- Four #news roots render only the three newest by authored date and expose a `/tags/news` continuation link.
- Each displayed news entry contains its title, authored date, and destination link.
- Zero #news roots produce the agreed empty state; existing QDB quote lists remain unchanged.

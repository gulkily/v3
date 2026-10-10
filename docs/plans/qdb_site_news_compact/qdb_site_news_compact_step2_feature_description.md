# QDB Site News Compact — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_site_news_compact_step1_solution_assessment.md) · [Step 2](./qdb_site_news_compact_step2_feature_description.md) · [Step 3](./qdb_site_news_compact_step3_development_plan.md) · [Step 4](./qdb_site_news_compact_step4_implementation_summary.md)

## Problem

The QDB Welcome page's Site News panel shows only a linked title and long-form timestamp per item, so readers must click through to read any news. It should instead show each full item compactly, like the original qdb.us site.

## User Stories

- As a QDB visitor, I want to read the full text of recent site news on the Welcome page so that I don't need to open each item.
- As a QDB visitor, I want each item to be dense (date, small title, text, no author) so that the panel stays short and scannable.

## Core Requirements

- Show the entire body of each news item, not just its title.
- Per item, show only: `yyyy-mm-dd` authored date, the title in small (non-heading) type, and the body text. No username or other metadata.
- Titleless items omit the title rather than showing a generated excerpt.
- Keep the existing selection and framing: newest three #news items, reverse-chronological, "All news" link when more exist, empty state when none.
- Apply to the QDB theme/profile only; keep spacing tight.

## Delivery Scope

- Work type: application change — QDB Welcome news panel markup, QDB theme styling, regression test updates, and implementation summary.
- Out of scope: news authoring, `/tags/news` page changes, other themes, schema changes, item-count changes.

## Completion Boundary

- Normal entry: a visitor opens the QDB Welcome page.
- End-to-end outcome: the visitor reads up to three full news items in compact date/title/text form.
- Recovery: empty news still shows "No site news yet."; "All news" still reaches `/tags/news`; items with long or multi-line bodies remain readable.
- Release condition: updated welcome and static-artifact tests pass and the implementation summary records verification.

## Risks

- Body rendering may differ from the thread page (line breaks, links, embeds). Impact: inconsistent or unsafe output. Earliest validation: fixture with multi-line body, URL and HTML characters. Mitigation: reuse the site's existing body renderer, which escapes content.
- Long bodies may swamp the panel. Impact: welcome page loses its balance. Earliest validation: fixture with a long body viewed at desktop and mobile widths. Mitigation: compact typography and spacing; revisit a height cap only if it proves necessary.
- The archived qdb.us reference could not be fetched (web.archive.org blocked in this environment). Impact: styling may differ from the original. Earliest validation: user review of the rendered panel. Mitigation: follow the stated compact date + text layout and adjust on feedback.

## Shared Component Inventory

- `qdb_welcome.php` news panel: extend in place; the only surface rendering the welcome news list.
- `QdbExperience::welcome()` data: reuse; thread rows already include the root post body.
- `BoardPageController::welcome()`: second route into the same template without news data; confirm unused or handled in Step 3.
- Shared body renderer (`$br` in `TemplateRenderer`): reuse for body text rather than a new formatter.
- `/tags/news` page: unchanged.
- `theme-qdb.css`: extend with compact news-item rules.

## Simple User Flow

1. A visitor opens the QDB Welcome page.
2. The Site News panel lists up to three items, each as date, small title, and full text.
3. If more news exists, the visitor follows "All news" to `/tags/news`.

## Success Criteria

- A fixture news item's full body appears on the Welcome page.
- Each item shows a `yyyy-mm-dd` date and contains no author name or long-form timestamp.
- The title appears in non-heading markup; a titleless item shows no title.
- Newest-three ordering, "All news" link, and empty state still pass their tests.

# QDB Site News Compact — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_site_news_compact_step1_solution_assessment.md) · [Step 2](./qdb_site_news_compact_step2_feature_description.md) · [Step 3](./qdb_site_news_compact_step3_development_plan.md) · [Step 4](./qdb_site_news_compact_step4_implementation_summary.md)

## Original Query

For the site news module on the qdb theme, the entire news item should be displayed, not just the title. Make it very compact: just the yyyy-mm-dd date and the text, no username; include the title but don't make it big. See the original site for reference: https://web.archive.org/web/20190324160200/http://qdb.us/about

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Understood Intent

Change the QDB welcome page's Site News panel from a linked-title list into compact full entries: `yyyy-mm-dd`, a small inline title, and the full body text, with no author. The archived page is the visual reference (not yet reviewed in this session).

## Problem

The panel (`templates/pages/qdb_welcome.php`) shows only a linked title and a long-form timestamp per item, so readers must click through to read a news item. `newsThreads` rows already carry `root_post_body`, so the data is available.

## Options

### Option A — Inline full body in the existing welcome template

Render each item as one compact block: ISO date, small bold title, then the body text, styled by new `.qdb-welcome-recent` rules in `theme-qdb.css`. Keep the "All news" link and the 3-item limit.

- Pros: smallest change; uses existing data and template; theme-scoped; no schema change.
- Cons: long bodies could dominate the panel unless limited or styled; body formatting (line breaks, embeds) needs a decision in Step 2.

### Option B — Reuse the full post/card partial with compact CSS

Render each news item through the shared post-body partial, hiding author and meta via the QDB theme.

- Pros: consistent body rendering (media embeds, links) with the rest of the site.
- Cons: partials carry author/vote/action markup that must be suppressed by CSS; heavier and more fragile than the panel needs.

### Option C — Truncated preview with "more" link

Show date, title, and the first N characters, linking to the thread for the rest.

- Pros: bounded panel height.
- Cons: contradicts the request that the entire item be displayed.

## Recommendation

Adopt Option A. It meets the request directly (entire item, date + title + text, no username) as a single-template and theme-CSS change. Step 2 should settle the title treatment (small inline vs. block), the date format (`yyyy-mm-dd` from the authored timestamp, UTC), whether a titleless item omits the title, body rendering (escaped text with line breaks vs. shared renderer), and whether the 3-item limit and "All news" link stay. Existing smoke tests asserting linked titles will need updating in Step 4.

## Continuation Handoff

- Existing evidence: `QdbExperience::welcome()` passes up to 3 `newsThreads`; thread rows include `subject`, `root_post_created_at`, and `root_post_body`; the panel styling lives in `public/assets/theme-qdb.css`.
- Scope boundary: no schema change, no news authoring workflow, no change to the `/tags/news` page or other themes. Note that `BoardPageController::welcome()` has a second, news-less path to `qdb_welcome.php` that Step 2/3 should confirm is unused or handled.
- Resume point: review this Step 1. Do not create Step 2 until you respond `Approved Step 1`.

# QDB Site News Compact — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_site_news_compact_step1_solution_assessment.md) · [Step 2](./qdb_site_news_compact_step2_feature_description.md) · [Step 3](./qdb_site_news_compact_step3_development_plan.md) · [Step 4](./qdb_site_news_compact_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a visitor opens the QDB Welcome page.
- End-to-end outcome: up to three newest news items each show a `yyyy-mm-dd` date, a small non-heading title, and the full body text, with no author or long-form timestamp.
- Required recovery: empty state, "All news" link, and three-item cap are unchanged; titleless items show no title; multi-line and HTML-character bodies render safely.
- Deployment/external verification: none; no config change. Confirm the static `index.html` build contains the compact items.
- Release condition: updated welcome and static-artifact tests plus the existing suite pass; the Step 4 summary records evidence.

## Key Risks

- **High risk:** body rendering could emit unescaped content. Early validation: fixture body with `<`, `&`, a URL and newlines. Mitigation: use the shared `$br` renderer, which the quote cards already use.
- Long bodies could swamp the panel. Early validation: long-body fixture checked visually at desktop and mobile widths. Mitigation: compact typography only; no height cap unless it proves necessary.
- `BoardPageController::welcome()` also renders the template without news variables, but has no callers. Early validation: grep for callers before editing. Mitigation: leave it unchanged unless a caller exists.

## Stage 1

- Goal: render each news item as date, small title, and full body.
- Dependencies: approved Step 2; thread rows from `QdbExperience::welcome()` already carry `root_post_body`.
- Expected changes: rewrite the news list in `qdb_welcome.php` into compact per-item blocks with an ISO date (from `root_post_created_at`), a small inline title element shown only when the subject is non-empty, and the body via `$br`; remove the thread link and author/meta output; keep heading, empty state, and "All news" link.
- Verification approach: update the QDB welcome tests in `tests/QuoteCardDisplayNumberTest.php` to assert full bodies, `yyyy-mm-dd` dates, no long-form timestamp or author, no title on a titleless item, escaping of special characters, and unchanged order, cap, empty state, and continuation link; run that test file.
- Risks or open questions:
  - Impact: the subject-only title rule diverges from the old excerpt fallback.
  - Early warning / validation: the titleless fixture asserts no title element.
  - Mitigation: behavior is specified in the approved Step 2.
- Canonical components/API contracts touched: `qdb_welcome.php`, `$br` body renderer, `QdbExperience::welcome()` data contract (unchanged).

## Stage 2

- Goal: style the items compactly and verify the static build.
- Dependencies: Stage 1 markup.
- Expected changes: add QDB-scoped rules in `theme-qdb.css` for the news items: tight vertical spacing, small bold inline title, muted date, no bullets, and wrapping of long text; no changes to other themes.
- Verification approach: view the rendered welcome page at desktop and ≤560px widths; run the static-artifact test and the full test suite; confirm the built `index.html` includes the compact items.
- Risks or open questions:
  - Impact: styling regresses the two-column welcome layout.
  - Early warning / validation: visual check at both widths.
  - Mitigation: scope selectors under `.qdb-welcome-recent` and leave existing rules untouched.
- Canonical components/API contracts touched: `theme-qdb.css`, `StaticArtifactBuilder` output (unchanged).

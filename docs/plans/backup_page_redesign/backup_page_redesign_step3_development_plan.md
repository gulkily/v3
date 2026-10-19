> **Feature plan:** [Step 1](./backup_page_redesign_step1_solution_assessment.md) · [Step 2](./backup_page_redesign_step2_feature_description.md) · [Step 3](./backup_page_redesign_step3_development_plan.md) · [Step 4](./backup_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

# Step 3: Development Plan — Backup Page Redesign

## Completion Contract

- Normal entry: Tools, then Backup.
- End-to-end outcome: a "Text content" section and, when available, an "Images" section, each with its own download table, notes and working links; no standalone Downloads module.
- Required recovery: with image backup unavailable, only the text section renders and nothing is empty or broken.
- Deployment/external verification: none; no route, schema or backup-logic change. The browser image-backup test runs once as an external check.
- Release condition: smoke and browser image-backup tests pass; checked in dark, light and one retro theme at 375px with no horizontal scroll.

## Key Risks

- **High risk:** Step 2 said to reuse `codebase-facts`, but it is a two-column key/value table, not a four-column download table. Impact: wrong component, cramped phone layout. Validation: Stage 1 renders the new table at 375px. Mitigation: add a small dedicated table style and stack cells on narrow screens.
- **High risk:** `instance.php` also changes on `feature/feature_flag_change_pages`. Impact: merge conflict or lost link mapping. Validation: confirm that branch's status before Stage 1. Mitigation: branch from it, or merge it first.
- `image_content_browser.mjs` finds links by `href` prefix. Impact: broken external test. Validation: run it in Stage 4. Mitigation: keep hrefs unchanged.
- Explainer text could be orphaned when the Downloads card goes. Impact: lost guidance. Validation: Stage 3 diff. Mitigation: move it intact to a closing section.

## Stage 1
- Goal: "Text content" section with its download table.
- Dependencies: base branch includes the feature-flag change pages work.
- Expected changes: `instance.php` groups freshness and a table of the three text downloads (item, format, description, link) under a "Text content" heading; controller `downloads` rows gain `item` and `format`; small `.backup-downloads` table style in `tool-details.css`, stacking at narrow widths.
- Verification approach: render at 375px and desktop; smoke test asserts each text link appears once in the table.
- Risks or open questions:
  - Impact: horizontal scroll on phones.
  - Early warning / validation: 375px screenshot and scroll-width check.
  - Mitigation: stacked cells.
- Canonical components/API contracts touched: `InstancePageController` download rows (data only), `tool-details.css`, section heading classes.

## Stage 2
- Goal: "Images" section with a one-row table and the pairing note.
- Dependencies: Stage 1.
- Expected changes: replace the prose Image backup block with an "Images" section using the same table (archive, .tar, description, link) and the existing pairing and refresh notes beside it; still shown only when `imageBackupAvailable`.
- Verification approach: smoke test with and without image backup; note sits right after the table.
- Risks or open questions:
  - Impact: pairing guidance lost.
  - Early warning / validation: acceptance check on note adjacency.
  - Mitigation: keep note inside the section.
- Canonical components/API contracts touched: `instance.php`, `imageBackupHref` (unchanged).

## Stage 3
- Goal: remove the old Downloads module and reorder the page.
- Dependencies: Stages 1-2.
- Expected changes: drop the Downloads card; keep Recent included items; move "Why this matters", "Explain it like I'm five" and "For technical users" intact into a closing section.
- Verification approach: diff shows explainer text unchanged; smoke test asserts no standalone Downloads heading and each link appears once.
- Risks or open questions:
  - Impact: a missed link or lost text.
  - Early warning / validation: link-count assertion.
  - Mitigation: fix before commit.
- Canonical components/API contracts touched: `instance.php`.

## Stage 4
- Goal: tests and external check.
- Dependencies: Stage 3.
- Expected changes: strengthen `LocalAppSmokeTest` Backup assertions (sections, order, tables, no Downloads module, no images section when unavailable).
- Verification approach: `php tests/run.php LocalAppSmokeTest`; run `tests/browser/image_content_browser.mjs` once and record the result.
- Risks or open questions:
  - Impact: browser test cannot run locally.
  - Early warning / validation: first attempt.
  - Mitigation: record as blocked with the reason rather than passed.
- Canonical components/API contracts touched: tests only.

## Stage 5
- Goal: acceptance.
- Dependencies: Stages 1-4.
- Expected changes: none beyond fixes found.
- Verification approach: full suite compared against the known two baseline failures; dark, light and `word97` at 1100px and 375px; click each download link; run with and without image backup.
- Risks or open questions:
  - Impact: a theme-specific table defect.
  - Early warning / validation: screenshots.
  - Mitigation: fix in `tool-details.css`.
- Canonical components/API contracts touched: none new.

## Approval Gate

Reply **Approved Step 3** to start implementation on a feature branch.

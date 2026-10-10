> **Feature plan:** [Step 1](./youtube_embed_layout_step1_solution_assessment.md) · [Step 2](./youtube_embed_layout_step2_feature_description.md) · [Step 3](./youtube_embed_layout_step3_development_plan.md) · [Step 4](./youtube_embed_layout_step4_implementation_summary.md)

## Stage 1 - Iframe layout rules
- Changes:
  - Added a `.media-embed-card__iframe` rule to `public/assets/site.css`: block display, full width, 16:9 aspect ratio, auto height, no border.
- Verification:
  - Selector grep: only the embed iframe class is targeted; no generic `iframe` or `details` rules added.
  - `php tests/run.php`: 9 failures with the change and 9 without it (stashed baseline), none in media-embed tests (LocalAppSmoke x2, OfflineSnapshot x4, QuoteCardDisplayNumber, WriteApiSmoke x2); no regression.
- Notes:
  - Visual browser check is pending and is covered with the theme pass in Stage 3.

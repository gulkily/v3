> **Feature plan:** [Step 1](./youtube_embed_layout_step1_solution_assessment.md) · [Step 2](./youtube_embed_layout_step2_feature_description.md) · [Step 3](./youtube_embed_layout_step3_development_plan.md) · [Step 4](./youtube_embed_layout_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader opens a post with a YouTube link (media-embeds and inline-player flags on) and expands the "Watch on YouTube" card.
- End-to-end outcome: the player is full width of its container, borderless, and 16:9 at desktop and phone widths, in each shipped theme.
- Recovery: CSS-only change; reverting the stylesheet edit restores today's look. Renderer markup, JS and Instagram/plain cards are untouched.
- Deployment/external verification: none beyond serving the updated `site.css`; a visual check on a real post with an embed.
- Release condition: visual check passes on desktop and about 360px width across themes, and the existing media-embed tests pass.

## Key Risks

- **Theme drift:** some themes may render the expando or summary awkwardly. Early validation: view an expanded embed in every theme. Mitigation: theme-neutral values in the shared stylesheet; theme overrides only if a theme breaks.
- **Style conflicts:** an unscoped rule could leak into other `details` or `iframe` elements. Early validation: grep for existing selectors (none today) and inspect computed styles. Mitigation: scope every rule to `.media-embed-card` class names.
- **Empty reserved box:** the 16:9 box shows blank before the video loads. Early validation: expand on a throttled connection. Mitigation: accept as expected; the box matches the final player size, so there is no layout shift.

## Stage 1
- Goal: the expanded YouTube iframe fills its container, has no border, and keeps 16:9 at any width.
- Dependencies: none; no `.media-embed-card` rules exist in `public/assets/` today.
- Expected changes: add scoped rules for the embed card iframe in `public/assets/site.css` (full width, zero border, 16:9 aspect ratio, block display so no inline gap). About 10 lines.
- Verification approach: expand an embed on a local post at desktop and 360px width; confirm computed width, border 0, and 16:9; run the media-embed test files.
- Risks or open questions:
  - Impact: a generic selector could restyle unrelated iframes.
  - Early warning / validation: computed styles on a non-embed `details` and any other iframe are unchanged.
  - Mitigation: use only `.media-embed-card__iframe` and `.media-embed-card--inline-player` selectors.
- Canonical components/API contracts touched: `MediaEmbedRenderer::renderYoutubeExpando` classes (read-only, unchanged); `public/assets/site.css`.

## Stage 2
- Goal: the expando summary and spacing look finished and the card never overflows on narrow screens.
- Dependencies: Stage 1.
- Expected changes: add scoped rules in `public/assets/site.css` for the inline-player card (summary cursor and spacing, margin around the player, `max-width: 100%` and no horizontal overflow). About 10–15 lines.
- Verification approach: check the collapsed and expanded states at 360px and desktop width; confirm there is no horizontal page scroll; confirm the plain and Instagram cards look unchanged.
- Risks or open questions:
  - Impact: spacing changes could shift the collapsed card from today's look, breaking the "unchanged" requirement.
  - Early warning / validation: compare collapsed-card screenshots before and after.
  - Mitigation: restrict spacing rules to the expanded (`[open]`) state.
- Canonical components/API contracts touched: `public/assets/site.css`; `media-embed-card--inline-player` class (read-only).

## Stage 3
- Goal: confirm the result in every shipped theme and fix any theme that breaks.
- Dependencies: Stages 1 and 2.
- Expected changes: none expected; if a theme breaks, add a minimal override in that theme's file under `public/assets/theme-*.css`.
- Verification approach: switch through all themes with an expanded embed; run the media-embed test suite (renderer, detector, inline-player script, beacon templates).
- Risks or open questions:
  - Impact: a theme's global `details` or `iframe` styling could win over the scoped rules.
  - Early warning / validation: per-theme visual check, flagging borders or padding that reappear.
  - Mitigation: a per-theme override scoped to the embed card, noted in the Step 4 summary.
- Canonical components/API contracts touched: `public/assets/theme-*.css` (only if a fix is needed); media-embed tests (run only, not modified).

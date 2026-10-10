> **Feature plan:** [Step 1](./youtube_embed_layout_step1_solution_assessment.md) · [Step 2](./youtube_embed_layout_step2_feature_description.md) · [Step 3](./youtube_embed_layout_step3_development_plan.md) · [Step 4](./youtube_embed_layout_step4_implementation_summary.md)

## Problem

An expanded YouTube embed renders with browser defaults: a small fixed-size iframe with a default border that does not fill its post container. It looks unfinished next to the rest of the post.

## User Stories

- As a reader, I want the expanded YouTube player to fill the width of the post it sits in so that the video is easy to watch without leaving the thread.
- As a reader on a phone, I want the player to scale to the screen with correct proportions so that it never overflows or looks squashed.
- As a site member, I want the player to look consistent with the current theme so that embeds feel like part of the page.

## Core Requirements

- The iframe fills the full width of its container module.
- The iframe has no border.
- Height follows width at a 16:9 ratio at every container width.
- No horizontal overflow or layout shift on narrow screens.
- The collapsed card, the Instagram card, and sites with the media-embed flags off look exactly as they do today.

## Delivery Scope

- Work type: application change (stylesheet only; no renderer markup, schema, or provider changes).
- Includes light expando polish: summary styling and spacing, consistent across themes.

## Completion Boundary

- Entry: a reader opens a post containing a YouTube link, with the media-embeds and inline-player flags on, and expands the card.
- Outcome: the player appears full width, borderless and 16:9, on desktop and phone widths, in each shipped theme.
- Recovery: the change is CSS only, so reverting the stylesheet edit restores today's look; a failed video load behaves as before.
- Release: visual check passes on desktop and phone width in the shipped themes, and existing media-embed tests still pass.

## Risks

- **Theme drift.** Impact: some themes may render the expando or summary awkwardly. Earliest validation: view an expanded embed in each theme. Mitigation: put the rules in the shared site stylesheet with theme-neutral values; list any theme-specific overrides in Step 3.
- **Rule conflicts with existing styles.** Impact: generic `details`/`iframe` rules could override or be overridden. Earliest validation: inspect computed styles on a live post. Mitigation: scope rules to the embed card's own class names only.
- **Iframe `sandbox` or lazy-load behavior shifts with sizing.** Impact: a reserved-height box could show empty before the video loads. Earliest validation: expand the card on a throttled connection. Mitigation: accept the reserved box as expected, since it matches the aspect ratio.

## Shared Component Inventory

- `MediaEmbedRenderer::renderYoutubeExpando`: the single source of the YouTube expando and iframe markup; reused unchanged, styled by class.
- `media_embed_inline_player.js` (toggle sets the iframe `src`): reused unchanged.
- `MediaEmbedRenderer` plain and Instagram cards: out of scope; they must not change.
- Post bodies render through shared template partials (`thread_card`, `thread_root_card`, reply views) via `TemplateRenderer`, so one stylesheet rule covers every surface.
- Stylesheets: `public/assets/site.css` is the shared base; no existing rule targets the embed card, so the feature adds new rules there and creates no new component.

## User Flow

1. Reader opens a thread containing a YouTube link.
2. Reader sees the collapsed "Watch on YouTube" card.
3. Reader expands it.
4. The player appears full width, borderless and 16:9, and the video loads.
5. Reader plays the video, or collapses the card again.

## Success Criteria

- Expanded iframe width equals its container width at desktop and phone widths.
- Computed iframe border width is 0.
- Rendered iframe aspect ratio is 16:9 at multiple widths.
- No horizontal page scroll introduced on a 360px-wide viewport.
- Collapsed cards, Instagram cards, and flag-off pages are visually unchanged, and the existing media-embed tests pass.

Waiting for "Approved Step 2" before drafting Step 3.

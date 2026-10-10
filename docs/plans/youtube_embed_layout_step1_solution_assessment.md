> **Feature plan:** [Step 1](./youtube_embed_layout_step1_solution_assessment.md) · [Step 2](./youtube_embed_layout_step2_feature_description.md) · [Step 3](./youtube_embed_layout_step3_development_plan.md) · [Step 4](./youtube_embed_layout_step4_implementation_summary.md)

## Original Query

YouTube embeds:
- should be full width of the container module
- should remove border from iframe
- what else?

## Understood Intent

- Follow-on polish to the shipped inline-player cycle (`mitrapclub_media_embeds_inline_player_*`), where YouTube links render as a `<details>` expando containing an iframe.
- Checked the current code: no stylesheet in `public/assets/` targets the embed card, the expando, or the iframe, so the player renders at the browser's default iframe size with the default border. The todo item is not done.
- "What else?" asks for adjacent layout/appearance gaps worth fixing in the same pass.

## Problem Statement

The expanded YouTube player uses unstyled browser defaults, so it is a small, bordered box that does not fill its post container or match the site's look.

## Solution Options

- **Option A: Minimal CSS for the two asked items.** Add a rule for the iframe: full container width, no border. Nothing else changes.
  - Pros: smallest possible change; directly satisfies both stated requirements; very low regression risk.
  - Cons: width alone leaves a fixed default height, so the video is letterboxed or distorted at different container widths; does not answer "what else?".

- **Option B: Responsive player styling in the existing stylesheet.** Full-width iframe with no border, a 16:9 aspect ratio so height tracks width, plus light polish of the expando (summary styling, spacing, consistent look across themes, no horizontal overflow on narrow screens).
  - Pros: delivers both asked items and answers "what else?" with the changes that actually make a full-width video look right; CSS only, with no markup, schema, or provider changes; works for every theme from shared rules.
  - Cons: a few more visual decisions to review (aspect ratio, spacing, per-theme appearance); needs a visual check across themes and phone width.

- **Option C: Wrapper-element redesign of the embed card.** Change the renderer's markup to add a dedicated responsive wrapper, a thumbnail poster, and a unified card style shared with the Instagram preview card.
  - Pros: most consistent result across providers; the most flexible base for future embed work.
  - Cons: touches the renderer and its tests; expands into the thumbnail/preview scope that earlier cycles deliberately kept separate; largest scope for a polish request.

## Recommendation

**Option B.** It is the smallest change that makes full-width actually look correct (aspect ratio), and it covers the "what else?" in one CSS-only pass. Option A leaves a visibly broken-height player; Option C is a redesign beyond this request.

**Vertical-slice viability:** Yes. Entry is any viewer opening a post with a YouTube link under the existing media-embeds and inline-player flags; the outcome is an expanded player that fills its container, borderless and correctly proportioned on desktop and phone; recovery is trivial, since a stylesheet-only change leaves collapsed cards, Instagram cards, and flag-off sites unchanged.

Waiting for "Approved Step 1" before drafting Step 2.

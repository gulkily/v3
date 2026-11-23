Cycle: `v3/zen_hero_fit`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [zen_hero](../zen_hero/zen_hero_step1_solution_assessment.md) (PR #350, merged) and [image_loading](../image_loading/image_loading_step1_solution_assessment.md).

Debug mode: disabled

Effort study: `railspike-effort` / `v3/zen_hero_fit`; host ledger [zen_hero_fit.md](../effort_ledger/zen_hero_fit.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.20**

Status: planned; next permitted action: implementation.

Original request (2026-11-23): "when i go to the home page, i want the large image to be sized to appear fully in the viewport. the extra thumbnails can go to the side of it if that improves composition. but right now the taller images are cut off on a wide screen."

Authorization: the request asks for the change directly; per AGENTS.md this runs as a one-shot (bounded layout fix, clear outcome). Exclusions: no change to which meme is featured, to the gallery/All page, to image loading or to phones' layout beyond what the wide rule needs.

Outcome and work type: application change. On a wide column (36rem or more), the featured Zen meme on `/` is never taller than the viewport minus room for the page header, so the whole image shows without scrolling. When the image is upright or square (ratio under 1.2), the thumbnails move from the row underneath into the space to its right, filling that space in rows and cut off at the image's height. Landscape images keep the row underneath and are centred when the height cap narrows them. Narrow columns (phones) are unchanged.

Approach and risks:
- The card's width is set in CSS from the image's stored aspect ratio (`--zen-ratio`, written by the template from the width and height it already emits), so the box has its final size before the image loads and the blurred stand-in still fills it. Risk: the header height differs by theme; mitigation: a fixed allowance, checked in Gloss, Word 97 and Bubblegum at 1920×1080 and 1440×900.
- Beside the image the thumbnail area uses `contain: size` so its many thumbnails can't stretch the row taller than the image; overflow is clipped as the row already does.
- Upright vs landscape is decided on the server from the stored ratio (a modifier class), keeping first paint final with no script.

Reuse decisions: extend the existing `zen-strip` partials and rules in `public/assets/site.css` (inside the critical CSS block); no new component. The `.zen-strip__row` link and thumbnails are reused as they are; only their layout changes in the beside case. Container query on `.zen-strip`, following the `.gallery-page` container pattern.

Completion conditions: opening `/` on a wide screen shows the whole featured image, upright or landscape, with the thumbnails beside an upright one; narrow screens look as before; text-only featured memes are unchanged.

Concepts touched: none.

Verification and recovery plan: `./v3 test --smoke BoardListTest ZenStripTest` after updating the featured-card markup assertion; local server plus headless Chrome screenshots of `/` with an upright and a landscape featured meme at wide and phone widths, in Gloss, Word 97 and Bubblegum. Full suite not run (maintainer runs it).

Starting revision and branch: `feat/zen-hero-fit-viewport` from `origin/main` `93295c828`.

Planning commit: pending.

Study history:
- 2026-11-23 UTC, intake: **0.20** whole cycle. Exposure: the template and CSS had been read before the estimate. Claude, single exposed rater.
- 2026-11-23 UTC, execution entry: **0.20** reaffirmed.

*Claude · 2026-11-23 UTC · Model: claude-opus-5-5*

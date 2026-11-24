Cycle: `v3/zen_hero_square`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [zen_hero_fit](../zen_hero_fit/zen_hero_fit_one_shot.md), which left phones unchanged.

Debug mode: disabled

Effort study: `railspike-effort` / `v3/zen_hero_square`; host ledger [zen_hero_square.md](../effort_ledger/zen_hero_square.md). Standing P2 enrollment.

**Cycle estimate: 0.10**

Status: implemented; PR pending; next permitted action: maintainer review.

Original request (2026-11-24): "the big Zen meme on the homepage ... fits on the page on desktop, but it doesn't fit on the page on mobile. It needs to fit on the page on mobile too, please." Then: "solution, or part of the solution, would be to just square the images."

Authorization: direct request; one-shot per AGENTS.md. Exclusions: wide columns, the gallery, image loading, which meme is featured.

Outcome: on a narrow column (under 36rem, i.e. phones) the featured Zen meme's box is square. The whole image shows inside it (`object-fit: contain`, never cropped, so captions stay readable); the blurred stand-in already set on the image fills the sides. Thumbnails stay in the row underneath.

Approach and risks: one container-query block in `public/assets/site.css` next to the wide rule; the box size comes from CSS alone, so it's final before the image loads. Risk: letterboxing on very tall or very wide memes; accepted since cropping would cut captions.

Reuse decisions: existing `zen-strip` container and stand-in; no new component. Concepts touched: none.

Verification: `./v3 test --smoke BoardListTest ZenStripTest`: 492 run, 492 passed (full suite not run). Headless Chrome screenshots of the production home page with the new rule injected, at 390×844 and 375×667 (iPhone SE): the featured meme, its caption, the thumbnail row and the visitor intro all show on the first screen. Wide columns untouched (rule is scoped to the narrow container).

Starting revision and branch: `feat/zen-hero-square-phone` from `origin/main` `9f73088e2`.

Study history:
- 2026-11-24 UTC, intake and closeout: **0.10** whole cycle; retrospective **0.10**. Claude, single exposed rater (CSS read first). Measured time unknown.

*Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

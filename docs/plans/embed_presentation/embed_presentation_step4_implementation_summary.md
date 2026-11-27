> **Feature plan:** [Step 1](./embed_presentation_step1_solution_assessment.md) · [Step 2](./embed_presentation_step2_feature_description.md) · [Step 3](./embed_presentation_step3_development_plan.md) · [Step 4](./embed_presentation_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./embed_presentation_debug_log.md))

Effort study: `railspike-effort` / `v3/embed_presentation`; host ledger [embed_presentation.md](../effort_ledger/embed_presentation.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.50**

Step 3 approved 2026-11-27 ("approved step 3"). Branch `feat/embed_presentation` from `a542d1e97`; planning commit `936267cd3`. Step 4 entry: **0.50**, reaffirmed from Step 3.

## Stage 1 - One bar per embed

**Original estimate: 0.10**
**Retrospective: 0.15**

- Changes:
  - `MediaEmbedRenderer.php`: each inline player is one `media-embed-player` bar. The summary holds a drawn play control and "Play" (or "Close" when open). The provider link ("YouTube ↗", labelled "Open on YouTube") sits beside the summary, never inside it. The "▶"/"♫" glyphs and the separate "Open on" line are gone.
  - `site.css`: pill-shaped bar from shared theme variables; disclosure marker hidden; triangle and cross drawn in CSS; the link sits over the bar's right end.
  - `theme-underground.css`: `--embed-accent` and `--embed-accent-ink` for Underground and Underground Dark.
  - New renderer test: one bar, provider named once, no glyphs, no link in the summary.
- Verification:
  - `./v3 test --smoke MediaEmbedRendererTest MusicMediaEmbedTest MediaEmbedInlinePlayerScriptTest TemplateRendererMediaEmbedsScriptTest`: passed (522 run). Full suite not run.
  - `node tests/browser/media_embed_browser.mjs`: passed, 27 themes at 360px and 1280px.
  - Screenshots at 380px of /music/ (closed and one open) and a thread, in Underground and Underground Dark: one bar per embed, no sideways scroll.
- Notes:
  - The browser test was already failing on `main` (`a542d1e97`), in three places. Fixed as part of this stage:
    - It expected no title beacon on untitled Spotify and TikTok threads; since mitrapclub_music fetches titles for every provider, one beacon is correct.
    - It ran themes mitrapclub doesn't permit on mitrapclub; the fixture now reports mitrapclub's themes and the test uses zenmemes for the rest, and the test router turns on the flag-gated Zalgo and Vaporgeist themes.
    - Zalgo skews cards on purpose; on the test's very tall card that moved players sideways, so the overflow check removes the skew first.
  - A first try coloured the provider link with `--ink-soft`; the Bandcamp palette reads that link's colour, so the link keeps the normal link colour.
  - Underground lowercases chrome text, but "Play" shows capitalized; left as is.
- Estimate details: original scope: markup and styles for one bar; uncertainty low. Retrospective 0.15: the pre-existing browser test failures took most of the extra work.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

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

## Stage 2 - One tap plays, one player at a time

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `media_embed_inline_player.js`: a tap on a player's bar marks it tapped and closes every other open player. A tapped player loads with the provider's start request: `autoplay=1` for YouTube and TikTok, `auto_play=true` for SoundCloud. Spotify and Bandcamp have no such setting and load as before.
  - Closing any player clears its frame, so a hidden player never keeps playing. Reopening loads it again.
  - Players opened by script (not by a tap) do not close others and do not autostart.
- Verification:
  - `./v3 test MediaEmbedInlinePlayerScriptTest`: passed (3 run), with a new check for the start request, closing others, and clearing the frame.
  - `node tests/browser/media_embed_browser.mjs`: passed twice in a row. It now checks: one open player after each tap; no closed player with a loaded frame; a tapped YouTube player requests `autoplay=1`; closing clears the frame; reopening a Bandcamp player keeps its palette.
  - iPhone check: pending, Stage 6.
- Notes:
  - Changed an earlier rule: "reopening must not reload a player". With the bar now saying Close, closing has to stop the sound, and a hidden frame keeps playing; so reopening loads the player again. The browser test was updated to match.
  - The Step 3 hint for providers that need a second tap ("Tap play in the player") is not built yet. Spotify and Bandcamp ignore start requests on every platform, so whether to add it waits for the phone check.
  - A Zalgo layout check failed once and passed twice after the stage's earlier fix also turned off Zalgo's card transition while measuring.
- Estimate details: original scope: per-provider start rules and one-at-a-time; uncertainty medium. Unchanged.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

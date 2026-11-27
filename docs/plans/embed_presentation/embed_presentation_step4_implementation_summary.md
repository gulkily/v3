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

## Stage 3 - Poster store, route and flag

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - New `View/MediaEmbedPosterStore.php`: keeps `<video id>.jpg` files in a `posters` folder beside the preview cache database. It fetches `hqdefault.jpg` by video ID from `i.ytimg.com` only, keeps only a JPEG under 512 KB, and leaves a `.failed` marker that blocks retries for an hour. Deleting the folder is safe.
  - `MediaEmbedPreviewController.php`: the warm-preview request gains `kind=poster` for YouTube links the detector accepts; a new `poster()` action serves a stored poster with the one-year header attached images use, or 404.
  - `Application.php`: route `/internal/media-embeds/posters/<id>.jpg`, answered before the read model loads; the poster store exists only when the flag is on.
  - `FeatureFlagRegistry.php`: `FORUM_MEDIA_EMBEDS_POSTERS_ENABLED` ("Media embed posters"), off by default, requires the inline player flag, in Rendering.
  - Posters stay out of `ImageStore`: that store holds signed post content with removal rules; posters are a disposable cache.
- Verification:
  - `./v3 test MediaEmbedPosterStoreTest MediaEmbedPreviewControllerTest FeatureFlagEvaluatorTest`: passed (46 run). New tests: one fetch then kept; non-JPEG answer kept as failure with an hour's wait; bad IDs never reach the disk; poster mode ignores non-YouTube links; posters off fetches and serves nothing.
  - One real fetch from the workspace: 22,712 bytes in 0.2s. DreamHost not tried; pending deploy.
- Notes:
  - `FeatureFlagEvaluatorTest` lists every public flag; the new flag was added to both lists.
- Estimate details: original scope: new store, route and flag; uncertainty medium. Unchanged.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Stage 4 - Poster on a post's page

**Original estimate: 0.05**
**Retrospective: 0.05**

- Changes:
  - `MediaEmbedRenderer.php` takes the poster store (null when posters are off). A YouTube video with a stored poster renders the poster as the closed player's summary; one without gets the bar plus a hidden poster request for next time. Shorts keep the bar.
  - `Application.php` passes the poster store to the renderer.
  - `site.css`: a closed poster player is a 16:9 poster with a large play control in the middle and the provider link on a small backing in the corner; open, it turns back into the bar with Close above the player.
- Verification:
  - `./v3 test MediaEmbedRendererTest MusicMediaEmbedTest MediaEmbedBeaconTemplatesTest TemplateRendererMediaEmbedsScriptTest`: passed (38 run). New test covers stored, missing, Shorts and posters-off.
  - `node tests/browser/media_embed_browser.mjs`: passed (posters off there).
  - Screenshots at 380px, Underground, posters on, real posters fetched from YouTube by the local server: the post page shows the poster, then the bar and player after a tap.
- Notes:
  - A page cached before its poster was stored shows the bar until the cache refreshes.
- Estimate details: original scope: one renderer branch; uncertainty low. Unchanged.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Stage 5 - Music rows

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `site.css`: on /music/, a row whose player is closed gets a 5.5rem first column. The player sits there as a 16:9 tile: the poster when stored, otherwise the plain play button, with the provider link under it. Opening the player moves it under the row at full width, as a bar with Close, and the row returns to its usual columns. Done with `:has()`, so it needs no script; no template change was needed.
  - `MediaEmbedRenderer.php`: the bar's wrapper no longer repeats `data-provider`. Stage 1 had doubled it, which broke a count in `music_share.mjs`.
- Verification:
  - `./v3 test --smoke MusicMediaEmbedTest MusicPageTest MediaEmbedRendererTest`: passed (525 run). Full suite not run.
  - `node tests/browser/music_share.mjs`: passed (it had failed after Stage 1 on the doubled attribute).
  - `node tests/browser/media_embed_browser.mjs`: passed.
  - Screenshots of /music/ at 380px with posters on, in Underground and Underground Dark, closed and with a Spotify player open: posters and the play tile line up beside the titles; the longest title wraps beside its tile; no sideways scroll.
- Notes:
  - The Spotify track player keeps its 352px height. A shorter one fits a single track better; left for a follow-up.
  - `:has()` needs iOS 15.4 or later; older browsers show the Stage 1 bar under the row instead.
- Estimate details: original scope: row layout at phone width; uncertainty low. Unchanged.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Stage 6 - Concepts, PR and phone check

**Original estimate: 0.05**
**Retrospective: 0.05**

- Changes:
  - New concept `docs/concepts/media_embed.md`: the bar, one tap, one player at a time, posters and their boundary.
  - `music_section.md`: rows show a tile and play in one tap. `post_presentation.md`: a pointer to the new concept. `image_serving.md`: posters are a separate cache, not stored images.
- Verification:
  - Local links in the touched concepts and this cycle's plans resolve; `git diff --check` clean.
  - Final diff review of the cycle (16 files outside docs): no duplicate component; posters reuse the preview cache's folder, the warm-preview request and the image cache header; the row tile reuses the row grid.
  - Shared consumers: Forte, reply context and QDB quotes/news render the same bar; covered by `media_embed_browser.mjs`.
  - Pending: iPhone SE check after deploy (one tap starts sound for YouTube; Spotify needs a tap in its player). Pending: a poster fetch from DreamHost.
- Notes:
  - To try it on mitrapclub.com after Deploy now: turn on "Media embed posters" in Tools (off by default). The bar and one-tap play need no new flag.
- Estimate details: original scope: documentation and handoff; uncertainty low. Unchanged.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Cycle closeout

**Retrospective scope assessment: 0.55** (projected 0.50). Stage 1 ran over on pre-existing browser-test failures. Maintainer comfortable-fit judgment: pending. Measured time: unknown.

Checks: the smoke set and every changed PHP test passed; `media_embed_browser.mjs` and `music_share.mjs` passed. The full suite was not run. Phone check and DreamHost poster fetch are pending deploy.

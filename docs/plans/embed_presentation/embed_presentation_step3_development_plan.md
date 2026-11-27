> **Feature plan:** [Step 1](./embed_presentation_step1_solution_assessment.md) · [Step 2](./embed_presentation_step2_feature_description.md) · [Step 3](./embed_presentation_step3_development_plan.md) · [Step 4](./embed_presentation_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./embed_presentation_debug_log.md))

Effort study: `railspike-effort` / `v3/embed_presentation`; host ledger [embed_presentation.md](../effort_ledger/embed_presentation.md). Standing P2 enrollment.

Extended estimates: enabled. Stage figures are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.50**

Step 2 approved 2026-11-27 ("approved step 2"); no scope added since.

Concepts touched: new **Media embed** (`docs/concepts/media_embed.md`, Stage 6). [Post presentation](../../concepts/post_presentation.md), [Music section](../../concepts/music_section.md) and [Image serving](../../concepts/image_serving.md): wording updated in Stage 6. [No-script presentation](../../concepts/no_script_presentation.md): checked, unchanged.

## Completion Contract

- Normal entry: a visitor opens /music/ or a post with a supported link on mitrapclub.com.
- Outcome: one bar or one poster per embed; one tap loads the player and starts it; starting one closes any other.
- Recovery: a missing poster shows the bar and is fetched in the background, with the existing one-hour backoff. `FORUM_MEDIA_EMBEDS_POSTERS_ENABLED` turns posters off. `FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED` still turns players off.
- Deployment: Deploy now on mitrapclub.com; the maintainer checks one YouTube and one Spotify track on his iPhone SE.
- Release: PR merged, deployed, phone check recorded in the Step 4 summary.

## Key Risks

- **High risk:** iOS Safari may not start sound in a frame loaded by a tap. Early validation: Stage 2's change, tried on the phone through the PR preview or after deploy. Mitigation: the bar tells the reader to tap play in the player when sound has not started.
- Posters for titled threads: the preview cache rarely holds a thumbnail. Mitigation: Stage 3 fetches posters by video ID from YouTube's image host, independent of oEmbed and the subject back-fill.
- Image fetch on DreamHost: early validation in Stage 3 by fetching one poster from the workspace; mitigation is a background fetch that never runs during page render.

## Stage 1 — One bar per embed

**Estimate: 0.10**

- Goal: every inline-player embed renders as one bar: play control, provider name, small "Open on YouTube ↗" link.
- Dependencies: none.
- Expected changes: `renderPlayerExpando()` in `MediaEmbedRenderer.php` puts the source link inside the bar and drops the "▶"/"♫" glyph from the summary text. CSS in `site.css` hides the browser disclosure marker and styles the bar; Underground and Underground Dark get theme colors.
- Verification approach: `MediaEmbedRendererTest` asserts one provider name and no duplicate glyph per embed. With JavaScript off, the bar still opens the player.
- Estimate details: driver: markup plus styles across themes; uncertainty: low.
- Risks or open questions:
  - Impact: a link inside a `<summary>` can toggle the disclosure on tap.
  - Early warning / validation: tap the ↗ link in the browser test.
  - Mitigation: place the link beside the summary inside the bar, not inside it.
- Canonical components/API contracts touched: `View/MediaEmbedRenderer.php`, `public/assets/site.css`, Underground theme files.

## Stage 2 — One tap plays, one player at a time

**Estimate: 0.10**

- Goal: opening a player starts playback, and opening one closes every other on the page.
- Dependencies: Stage 1.
- Expected changes: `media_embed_inline_player.js` adds the provider's autoplay parameter when it loads a frame after a tap (YouTube, SoundCloud, TikTok; Spotify and Bandcamp as they allow). On open it closes other open players and clears their frames so sound stops.
- Verification approach: `MediaEmbedInlinePlayerScriptTest`; `tests/browser/media_embed_browser.mjs` checks no frame has a source before a tap, and at most one open player after several taps.
- Estimate details: driver: per-provider autoplay rules; uncertainty: medium.
- Risks or open questions:
  - Impact: iOS blocks the start (see Key Risks).
  - Early warning / validation: phone check in Stage 6.
  - Mitigation: a short hint in the bar for providers that need a second tap.
- Canonical components/API contracts touched: `public/assets/media_embed_inline_player.js`.

## Stage 3 — Poster store, route and flag

**Estimate: 0.15**

- Goal: a YouTube poster is copied to our server once and served from there.
- Dependencies: none (parallel to Stages 1–2).
- Expected changes: a new `MediaEmbedPosterStore` keeps poster files beside the preview cache database, keyed by video ID, with the cache's backoff for failures. It fetches only from YouTube's image host. A GET route serves a stored poster with the one-year cache header images use. The existing warm-preview endpoint gains a poster mode. New flag `FORUM_MEDIA_EMBEDS_POSTERS_ENABLED`, requiring the inline player flag. Posters stay out of `ImageStore`, because they are a disposable cache, not signed post content.
- Verification approach: new `MediaEmbedPosterStoreTest`; `MediaEmbedPreviewControllerTest` covers poster mode and a rejected non-YouTube host. One real fetch from the workspace.
- Estimate details: driver: a new store and route; uncertainty: medium (host behavior on DreamHost).
- Risks or open questions:
  - Impact: a fetch slows a request.
  - Early warning / validation: the real fetch above, timed.
  - Mitigation: fetch only from the background beacon request, with a short timeout.
- Canonical components/API contracts touched: `MediaEmbedPreviewCacheStore.php` (directory reused), `MediaEmbedPreviewController.php`, `FeatureFlagRegistry.php`, route table.

## Stage 4 — Poster on a post's page

**Estimate: 0.05**

- Goal: a YouTube embed with a stored poster shows it at 16:9 with a play button; one tap plays.
- Dependencies: Stages 1–3.
- Expected changes: the renderer shows the poster inside the summary when it exists, otherwise the bar plus a poster beacon for next time.
- Verification approach: renderer test for both branches; flag off renders the Stage 1 bar.
- Estimate details: driver: one branch in the renderer; uncertainty: low.
- Risks or open questions: none beyond Stage 2.
- Canonical components/API contracts touched: `MediaEmbedRenderer.php`, `media_embed_warm_beacon.php`.

## Stage 5 — Music rows

**Estimate: 0.10**

- Goal: each /music/ row shows a small poster or a play tile in the first column, and the player opens under the row.
- Dependencies: Stages 1–4.
- Expected changes: `thread_row.php` and `music.php` place the poster or play tile in the `thread-row--thumb` column; the row's separate player line becomes the opened player only.
- Verification approach: `MusicMediaEmbedTest`; screenshots at 380px in Underground and Underground Dark, one row playing; the longest current title stays on screen with no sideways scroll.
- Estimate details: driver: row layout at phone width; uncertainty: low.
- Risks or open questions: a long title squeezed by the poster column; mitigation: reuse the 72px column width.
- Canonical components/API contracts touched: `templates/partials/thread_row.php`, `templates/pages/music.php`, `site.css` row rules.

## Stage 6 — Concepts, PR and phone check

**Estimate: 0.05**

- Goal: document the behavior, open the PR, and record the iPhone replay.
- Dependencies: Stages 1–5.
- Expected changes: new `docs/concepts/media_embed.md`; wording updates in the three related concept files; final diff review; PR with a checkout snippet.
- Verification approach: smoke set plus the changed tests. After deploy, the maintainer taps one YouTube and one Spotify track on the iPhone SE; the result goes in the Step 4 summary. A desktop pass alone does not satisfy this.
- Estimate details: driver: documentation and handoff; uncertainty: low.
- Risks or open questions: none new.
- Canonical components/API contracts touched: concept inventory.

## Study checkpoint

2026-11-27 UTC, Step 3 handoff: **0.50**, reaffirmed. Stage work 0.10, 0.10, 0.15, 0.05, 0.10, 0.05 (not summed). Single exposed Claude rater.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

> **Feature plan:** [Step 1](./embed_presentation_step1_solution_assessment.md) · [Step 2](./embed_presentation_step2_feature_description.md) · [Step 3](./embed_presentation_step3_development_plan.md) · [Step 4](./embed_presentation_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./embed_presentation_debug_log.md))

Effort study: `railspike-effort` / `v3/embed_presentation`; host ledger [embed_presentation.md](../effort_ledger/embed_presentation.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.50**

## Problem

Each media embed renders as three loose pieces that repeat the provider name, with a doubled arrow, so posts and music rows look cluttered. Playing a track takes two taps.

## Approved direction

Step 1 was approved on 2026-11-27 ("approved step 1"). No option was named, so this follows the recommended Option B: one player bar, YouTube posters, one tap to play. The poster question was not answered, so the Step 1 default applies: posters are copied to our server once **(default)**. Defaults are marked **(default)** below and can still be changed.

Concepts touched: [Post presentation](../../concepts/post_presentation.md) (changed: an embed is one bar or one poster). [Music section](../../concepts/music_section.md) (changed: rows show a small poster or play tile in the first column). [No-script presentation](../../concepts/no_script_presentation.md) (unchanged rule: the disclosure still opens the player). [Image serving](../../concepts/image_serving.md) (changed: serves copied posters). Proposed addition: a **Media embed** concept file, `docs/concepts/media_embed.md`, since the look and the tap rule now need one place. Feedback: no matching item; related, out of scope: PF-20261104-F60.

## User stories

- As a listener on /music/, I want each track to show one picture or one play button, so that I can scan the list and play a track with one tap.
- As a reader of a post with a video, I want to see what the video is before I play it, so that I can decide without loading YouTube.
- As a visitor, I want no YouTube or Spotify page to load until I press play, so that pages stay fast and providers don't see every visit.
- As a listener, I want starting a track to stop the one already playing, so that two tracks never play over each other.
- As a reader without JavaScript, I want embeds to still open and play, so that the site keeps working for me.

## Core requirements

- An embed with an inline player renders as one unit: a play control, the provider name, and a small "Open on YouTube ↗" style link. The doubled arrow and the separate "Open on" line are gone.
- A YouTube embed shows its poster with a play button over it. On a post's own page the poster spans the column at 16:9. In a list row it sits small in the first column, the way attached images do. Spotify, SoundCloud, Bandcamp and TikTok use the bar without a poster.
- One tap on a poster or a play control loads the player and starts playback. Nothing loads a provider frame before that tap.
- Starting a player closes any other open player on the page.
- Posters are copied to our server the first time they are needed and served from there **(default)**. A new feature flag turns posters off; with it off, YouTube uses the bar like the others. With the inline player flag off, embeds stay plain link cards, as today.

## Delivery scope

- Work type: application change. That covers the embed markup, a poster fetch and store, the tap-to-play script, list-row placement on /music/, styles in the shared stylesheet and the mitrapclub themes, the new flag, the concept file, and tests.
- Out of scope: cover art for Spotify, SoundCloud and Bandcamp (their oEmbed answers carry it; a candidate follow-up). Embeds in offline mode (the offline reader does not render embeds today). Posters on the Board and Recent (PF-20261104-F60's ground). A per-viewer "always load players" preference. Autoplaying the next track.

## Completion boundary

- Normal entry: a visitor opens /music/ or a post with a supported link on mitrapclub.com.
- End-to-end outcome: they see one poster or one bar per embed, tap once, and the track plays in place. Tapping another track closes the first.
- Recovery: a poster that fails to fetch or copy falls back to the bar, and the fetch is retried after the existing one-hour backoff. The poster flag turns posters off; the inline player flag turns players off.
- Release: merged to main, deployed to mitrapclub.com with Deploy now, and checked on the maintainer's phone.

## Known user or environment

The maintainer demos on an iPhone SE (3rd gen) in Safari, in the Underground theme. Entry: mitrapclub.com, then Music, then a track's page. Failure to handle: iOS Safari blocks starting playback inside a frame loaded by a tap, so the tap only loads the player and a second tap is still needed. Next action: none needed from him; the player still works with that second tap. Evidence: a recording or note from the phone after deploy saying whether one tap starts sound. Desktop browser tests do not settle this.

## Risks

- **One-tap playback on iOS.** Impact: the main promise holds on desktop but not on his phone. Earliest validation: load one YouTube and one Spotify player by tap on the phone before Step 3 stages are built on it. Mitigation: the YouTube embed takes an autoplay request after a user tap; if Spotify refuses, the bar says "Tap play in the player" rather than looking broken.
- **Posters for threads that already have titles.** Today the preview fetch runs only when a thread's subject is empty, so most titled tracks have no cached thumbnail. Impact: no posters on most rows. Earliest validation: count cached YouTube entries with a thumbnail on mitrapclub's copy. Mitigation: a poster request of its own, separate from the subject back-fill.
- **Copying images from YouTube.** Impact: a slow or failed copy on DreamHost delays a page. Earliest validation: copy one poster from the workspace and from DreamHost. Mitigation: the copy runs in the background request, never during page render; the page shows the bar until the copy exists.
- **List rows on a 380px screen.** Impact: a poster column squeezes long titles. Earliest validation: /music/ at 380px with the longest current title. Mitigation: the existing thumbnail column width from attached-image rows.

## Shared component inventory

- `View/MediaEmbedRenderer.php` (`renderPlayerExpando()`, `renderPlainCard()`): changed in place to render the single bar and the poster. No second renderer.
- `public/assets/media_embed_inline_player.js`: extended for tap-to-load-and-play and closing other players. It already loads frames on open.
- `View/MediaEmbedPreviewCacheStore.php` and `Http/MediaEmbedPreviewController.php`: reused for the thumbnail address YouTube's oEmbed returns; extended with a poster request that does not depend on an empty subject.
- `Images/ThumbnailStore.php`, `Images/ListThumbnails.php` and the `thread-row--thumb` rules in `site.css`: inspected for storing and showing posters. Reuse the row's thumbnail column; whether posters live in the image store or beside the preview cache is decided in Step 3 with the reason recorded.
- `templates/partials/thread_row.php` (`rowPlayer`) and `templates/pages/music.php`: extended so a music row puts the poster or play tile in the first column.
- `Support/FeatureFlags/FeatureFlagRegistry.php`: one new flag for posters, requiring the inline player flag.
- Tests: `MediaEmbedRendererTest`, `MediaEmbedInlinePlayerScriptTest`, `MusicMediaEmbedTest`, `MediaEmbedPreviewControllerTest`, `tests/browser/media_embed_browser.mjs`: extended.

## User flow

1. A visitor opens mitrapclub.com on a phone and taps Music.
2. Each track shows a small poster or a play tile beside its title.
3. They tap a poster; the row opens and the video plays.
4. They tap another track; the first closes and the second plays.
5. They open a track's page and see the large poster with a play button.
6. They tap "Open on YouTube ↗" to watch it there instead.

## Success criteria

- No page loads a YouTube, Spotify, SoundCloud, Bandcamp or TikTok frame before a tap, checked by the browser test.
- Every embed shows one provider name and one arrow-free control, checked by the renderer test.
- On desktop browsers one tap starts playback; on the maintainer's iPhone it takes one tap or the bar tells him why not.
- At most one player is open on a page after any sequence of taps.
- /music/ at 380px shows posters for YouTube tracks, with no sideways scroll.
- With JavaScript off, every embed still opens its player.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

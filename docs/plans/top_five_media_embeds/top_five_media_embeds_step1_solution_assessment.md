> **Feature plan:** [Step 1](./top_five_media_embeds_step1_solution_assessment.md) · [Step 2](./top_five_media_embeds_step2_feature_description.md) · [Step 3](./top_five_media_embeds_step3_development_plan.md) · [Step 4](./top_five_media_embeds_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

Great job, please write a step 1 for addressing the top 5 for now

## Understood Intent

- Address the [checklist's five highest-ranked URL types](../media_embed_support_checklist.md#expected-url-frequency): YouTube videos, Instagram posts/reels, Spotify songs/albums/playlists, TikTok clips, and YouTube Shorts. This is four providers; the ranking remains an audience-based estimate.
- Existing code supports YouTube watch/share links and Instagram post/reel previews. Spotify, TikTok, and Shorts are missing; live-provider and browser verification remains open.
- This assessment replaces the narrower [YouTube URL coverage proposal](../youtube_url_coverage_step1_solution_assessment.md) as the active planning direction. No implementation or prior proposal has been approved.

## Problem Statement

Members sharing the five most likely media URL types encounter inconsistent coverage, with three types lacking embeds and existing behavior still needing live verification.

## Solution Options

**Option A: Add link cards for missing types.** Recognize Spotify, TikTok, and Shorts URLs and provide labeled links while retaining existing YouTube and Instagram behavior.

- Pros: smallest change; no new playback integration or metadata dependency.
- Cons: Spotify and TikTok still require leaving the discussion; Shorts would lack playback despite the existing YouTube player.

**Option B: Extend existing players and previews across the five types.** Reuse YouTube playback for Shorts, add click-to-load Spotify and TikTok players, and verify Instagram's existing preview and fallback experience.

- Pros: delivers useful in-thread media access through shared components; preserves user-controlled player loading. Official [Spotify embeds](https://developer.spotify.com/documentation/embeds) and [TikTok's player](https://developers.tiktok.com/docs/en/embed-player) provide supported integration paths.
- Cons: provider playback restrictions and browser compatibility need real checks; different player proportions need layout validation; Instagram previews remain best-effort.

**Option C: Build a general rich-link system.** Add provider discovery, redirect resolution, fetched titles and thumbnails, and richer presentations for all five types.

- Pros: broader share-link coverage and a foundation for subsequent providers.
- Cons: adds network, caching, and failure-handling scope beyond a bounded first release; more work before readers gain new playback support.

## Recommendation

**Option B**, bounded to direct content URLs:

- YouTube: retain watch and `youtu.be` links; add `/shorts/` playback and preserve the existing fetched-title experience.
- Instagram: retain `/p/` and `/reel/` linked previews; verify live success and plain-card fallback. Inline Instagram playback is excluded.
- Spotify: add direct `open.spotify.com` track, album, and playlist links.
- TikTok: add direct `tiktok.com/@USER/video/ID` links.

Use the existing card/player feature flags, explicit provider URL validation, click-to-load players, and a visible source link even when playback fails. Preserve stored post text and existing title backfill; new Spotify/TikTok title fetching is deferred, and recognition must not leave their bare-URL threads permanently titled “Untitled.” Do not assume one provider's player permissions or sizing works for another.

Defer redirecting share links, additional providers, YouTube live/mobile/embed paths, playlist-only YouTube links, timestamp preservation, and a general preview framework. Document these exclusions as support boundaries.

**Vertical-slice viability:** A member pastes a supported URL into a thread or reply; readers get a playable expando or Instagram preview, with a usable source link when content is unavailable. Verify all five types in shared board/thread/Forte views, on mobile and desktop, with flags on/off. Validate new player compatibility early; split into provider-sized releases if planning exceeds one day or eight stages, retaining all five types as the overall objective.

Status: Step 1 drafted for review under [Railspike](../../railspike/RAILSPIKE.md); Steps 2–4 have not started.

> **Feature plan:** [Step 1](./music_media_embeds_step1_solution_assessment.md) · [Step 2](./music_media_embeds_step2_feature_description.md) · [Step 3](./music_media_embeds_step3_development_plan.md) · [Step 4](./music_media_embeds_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Let us continue with docs/plans/media_embed_support_checklist.md. Let's work on the music-specific things, please. Write Step 1.

## Understood Intent

Address the remaining music providers in the [checklist](../media_embed_support_checklist.md): SoundCloud tracks/playlists and Bandcamp tracks/albums. Spotify already supports direct tracks, albums, and playlists. This interpretation excludes additional music services and general URL cleanup; the scope remains open for review.

## Problem Statement

Members can listen to Spotify links inside discussions, but SoundCloud and Bandcamp releases still lack automatic provider cards or players.

## Solution Options

**Option A: Add direct-link cards for both providers.** Recognize public SoundCloud and Bandcamp content URLs and present labeled source links through the existing card system.

- Pros: small, independently releasable improvement; no metadata-fetching dependency.
- Cons: listening still requires leaving the discussion; limited benefit over ordinary links.

**Option B: Add direct-content players with card fallback.** Extend the existing click-to-load experience to SoundCloud and Bandcamp, validating each provider's integration before committing to playback coverage.

- Pros: useful in-thread listening; reuses shared presentation, flags, and source links. SoundCloud documents a track/set [oEmbed interface](https://developers.soundcloud.com/docs/oembed); Bandcamp documents an official [embedded player](https://get.bandcamp.help/en/articles/15263071-how-do-i-create-a-bandcamp-embedded-player).
- Cons: Bandcamp's documented manual embed flow does not establish automatic player discovery from pasted URLs; any required lookup adds network and failure-handling work. Playback availability and layout require live validation.

**Option C: Build broader music-link resolution.** Include short links, custom artist domains, Spotify URL gaps, smart links, and fetched release metadata alongside the two new providers.

- Pros: covers more real sharing habits and could support future music services.
- Cons: redirects, destination validation, and metadata dependencies substantially enlarge the work; delays a bounded listening improvement.

## Recommendation

**Option B, delivered in provider-sized vertical slices, with SoundCloud first.** Keep both providers as the objective, but establish Bandcamp URL-to-player feasibility before making playback a requirement.

- Scope public direct SoundCloud track and `/sets/` URLs, and artist-subdomain Bandcamp `/track/` and `/album/` URLs. Exact hosts, reserved paths, and accepted/rejected examples belong in Step 2.
- Validate official player interfaces, credential requirements, and any lookup dependency early. Bandcamp's manual embed documentation alone is insufficient evidence of an automated integration. If reliable discovery cannot be established, return for a scope decision between Bandcamp cards and deferred playback.
- Reuse the existing card/player flags and reader-controlled expansion, with no autoplay and a visible source link. Keep ordinary body-derived titles; fetched thread titles and richer metadata are deferred.
- If lookup is necessary, require bounded work, validated destinations, caching, and failure fallback; do not render arbitrary returned HTML. Preserve stored post text and meaningful URL parameters.
- Defer share redirects, private/tokenized links, profiles, Bandcamp custom domains, Spotify URL expansion, other music services, and general tracking cleanup. Do not mark either provider complete before its supported boundaries and verification are recorded in the checklist.

**Vertical-slice viability:** A member pastes a supported release URL into a thread or reply; readers can expand a player and retain a usable source link when playback is unavailable. Verify the shared board/thread/Forte views, mobile and desktop layout, keyboard use, and flag combinations. Each provider slice must fit one day or eight planning stages; split further or revisit scope if discovery work exceeds that bound.

Status: Step 1 drafted for review under [Railspike](../../railspike/RAILSPIKE.md); Steps 2–4 have not started.

> **Feature plan:** [Step 1](./music_media_embeds_step1_solution_assessment.md) · [Step 2](./music_media_embeds_step2_feature_description.md) · [Step 3](./music_media_embeds_step3_development_plan.md) · [Step 4](./music_media_embeds_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/music-media-embeds`. Planning commit: `d909b0f2`.

Implementation in progress; production flags remain unchanged.

## Stage 1 - SoundCloud tracks

- Changes: strict public track detection, reserved/private URL rejection, shared deferred player with autoplay disabled, responsive track sizing, unchanged source/title behavior; registered focused music regression suite.
- Verification: `php tests/run.php MusicMediaEmbedTest MediaEmbedDetectorTest MediaEmbedRendererTest ThreadTitleTest` — 42 passed. Isolated browser fixture created a track thread/reply: two cards, visible source, keyboard expansion, correct live artist/title and Play-to-Pause control transition.
- Notes: live listening received the expected playing control state; final browser acceptance will check playback progress and all shared views. No server lookup added.

## Stage 2 - SoundCloud sets

- Changes: direct set recognition and collection sizing reuse the track player path; profile tabs and private/extra-path variants remain excluded.
- Verification: focused music/detector/renderer/title suite — 43 passed. Isolated thread/reply live smoke for `soundcloud.com/justinhasanhickman/sets/forss-512437271`: two cards, visible source, keyboard expansion, collection track list, and Play-to-Pause transition.
- Notes: no lookup/cache added; live provider restrictions remain subject to final acceptance.

## Stage 3 - Bandcamp resolution foundation

- Changes: one shared release-URL policy; metadata parser accepting only matching Bandcamp player IDs; HTTPS fetch pins a validated public IPv4 address while verifying the artist TLS hostname, rejects redirects/non-HTML/oversized responses, and applies a three-second fetch deadline. Separate disposable per-release cache uses 24-hour successes, one-hour failures, and nonblocking locks.
- Verification: resolver/music suites — 7 passed, covering URL boundaries, destination addresses, malformed/foreign/wrong-kind metadata, status/challenge/size failures, cache expiry/backoff/corruption and lock deduplication. Native production fetcher returned album `1043068150` and track `3419983385` for the supplied pages.
- Notes: no canonical or read-model schema changes. System DNS resolution uses the host resolver; an over-deadline result is rejected before connecting. This prerequisite is not a releasable Bandcamp UI by itself.

## Stage 4 - Bandcamp albums

- Changes: album cards use the canonical renderer; expansion invokes a same-origin, both-flags-gated resolution endpoint, then loads the reconstructed player. Accessible pending/failure status and persistent source links survive lookup errors. No network work during HTML rendering or title writes.
- Verification: controller/resolver/music/script suites — 12 passed. Isolated live album thread/reply resolved the supplied album, loaded its track list, and played audio: native media element reported `paused=false`, `currentTime=3.24944`, no media error. Method/flag/invalid-input tests verify rejected requests do not fetch or create the read model.
- Notes: Bandcamp uses its visible artwork Play control in this layout; its separate `#play` element is hidden. Client validates returned player destinations and avoids loading a player closed during discovery.

## Stage 5 - Bandcamp tracks

- Changes: track detection/resolution uses the shared album path with track-only metadata and a compact 120px presentation. Track IDs cannot be confused with iframe fallback links to the parent album.
- Verification: controller/resolver/music/detector/renderer/script/title suites — 55 passed. Isolated live thread/reply for the supplied track displayed `PLZ PLZ Universal Soul`, resolved `track=3419983385`, and played audio: `paused=false`, `currentTime=3.195704`, no media error.
- Notes: album behavior remains covered. Shipped-theme and mobile checks follow in Stage 6.

## Stage 6 - Shared-view acceptance and coverage handoff

- Changes: extended existing fixtures/browser runner for all four new content types, deterministic discovery/failure caches, close-during-lookup and foreign-destination rejection checks; updated the support checklist's provider status, exact boundaries, caching/flags, frequency table, and repeatable verification command.
- Verification: `php tests/run.php` — **1,198 passed, 0 failed** (approximately 243 seconds). Expanded focused command in the checklist — **98 passed, 0 failed**, using a separate test-history database while the full suite ran. `node tests/browser/media_embed_browser.mjs` and `--live` — passed. Controlled checks covered 16 theme modes at 360px/1280px, keyboard focus for YouTube/SoundCloud/Bandcamp, deferred loading, flags, source fallback, concurrency, closing/reopening, and shared board/thread/root/reply/Forte/reply-context/QDB quote/news views.
- Verification: live run confirmed YouTube/Shorts, all three Spotify forms, TikTok, SoundCloud track/set playback progress, Bandcamp album/track audio progress after cold server discovery, Instagram post/reel previews, and Shorts title backfill. Isolated instances used temporary repositories/caches and were removed afterward; browser was headless Chrome 145 on Linux, October 15, 2026.
- Verification: inspected actual music player screenshots at mobile width, including SoundCloud waveform/collection controls, Bandcamp album track list, and the selected track title/controls; captured 360px and 1280px images under `/tmp/music-{provider-kind}-{width}.png`. No horizontal overflow. `git diff --check` and feature-plan local link validation passed.
- Notes: production flag enablement/deployment is outside this change and was not performed. No credentials, canonical records, or read-model schema changes are required. Both existing embed flags remain necessary. Clearing `state/cache/bandcamp_players/` invalidates the disposable discovery cache; unwritable storage or provider challenges retain source-link fallback. DNS resolution uses the host resolver, and only IPv4 fetch destinations are currently supported.

## Completion

All six stages completed on `feature/music-media-embeds`, with a planning commit and one verified commit per stage. SoundCloud and Bandcamp now provide normal post-to-player flows with recovery; additional music services, share redirects, custom domains, private links, richer metadata, and new-provider fetched titles remain excluded. Production acceptance is separate.

## Follow-up - Match Bandcamp to the selected theme

- Changes: small presentation fix authorized after the completed cycle. Bandcamp now chooses a standard white/dark background from the active theme's composited panel brightness and matches its source-link color, with a readable fallback below 4.5:1 contrast. Colors are applied in the browser on expansion; release discovery/cache remains theme-independent. An open player is preserved during theme changes, and reopening only reloads when its palette changes.
- Verification: focused music/resolver/controller/renderer/script/template suites — **29 passed, 0 failed**. Controlled browser runner — passed across all 16 themes at 360px/1280px, with explicit light/dark/club palette assertions, theme switching, automatic OS-theme changes, unchanged-palette reload prevention, translucent-panel selection, low-contrast fallback, and neutral cached-source preservation. Live recolored track playback reported `paused=false`, `currentTime=3.00636`, no media error. Inspected live album/track screenshots in light, dark, and club modes.
- Notes: the live provider's internal surfaces select its own light/dark palette even when arbitrary background hex values are supplied; the fix therefore uses supported `ffffff`/`333333` backgrounds and custom link colors rather than promising exact site-background matching. No new planning cycle, backend contract, or deployment settings were needed. Full PHP-suite results above remain from the original implementation; this JavaScript follow-up used focused and browser verification.

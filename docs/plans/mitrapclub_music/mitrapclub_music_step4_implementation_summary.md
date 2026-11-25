> **Feature plan:** [Step 1](./mitrapclub_music_step1_solution_assessment.md) · [Step 2](./mitrapclub_music_step2_feature_description.md) · [Step 3](./mitrapclub_music_step3_development_plan.md) · [Step 4](./mitrapclub_music_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./mitrapclub_music_debug_log.md))

Effort study: `railspike-effort` / `v3/mitrapclub_music`; host ledger [mitrapclub_music.md](../effort_ledger/mitrapclub_music.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.55**

Branch `feat/mitrapclub_music` from `origin/main` `b926c9a9f`; planning commit `5ab4adbad`.

Step 4 entry: **0.55**, Step 3 reaffirmed unchanged. Stage originals copied from Step 3 (0.15, 0.10, 0.10, 0.10, 0.05, 0.05, 0.10); copying is not a new rating.

## Stage 1 - Fetched titles for every music provider

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - New `View/OembedTitleFetcher.php`: one fetcher for Spotify, SoundCloud and TikTok oEmbed, keyed by provider, with the YouTube fetcher's timeout, transport hook and failure handling, plus a 256 KiB response cap. One class with an endpoint table instead of the three classes the plan named: the three differ only in their endpoint.
  - `View/BandcampPagePlayerFetcher.php`: `fetchTitle(string $url): ?string` reads `og:title` from the release page; the page fetch and parse moved into a private `page()` shared with `fetch()`.
  - `Http/MediaEmbedPreviewController.php`: `warmPreview()` accepts `spotify`, `soundcloud`, `tiktok` and `bandcamp`; `warmMusicTitle()` uses the same cache store, one-hour failure backoff and empty-subject-only write as YouTube. Spotify caches under `kind:id`.
  - `Support/ThreadTitle.php`: `bareMediaEmbedMatch(string $subject, string $body, bool $firstLine = false)` accepts all six providers (`TITLE_PROVIDERS`); with `$firstLine`, the link may be followed by a line break and more text, provided the post has no other embed. `displayTitle()` still shows "Untitled" only for YouTube and Instagram, so a music link reads as the link until its title lands, and stays that way if the fetch fails.
  - New flag `FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED` ("Media embed first-line titles"; off by default, site-mutable, RENDERING, requires Media embed cards), passed to templates as `mediaEmbedFirstLineTitles`.
  - `templates/partials/thread_card.php`, `thread_root_card.php`: the warm beacon now fires for any empty stored subject whose body matches, rather than only when the display title is "Untitled", and passes the flag.
  - Pre-existing failure fixed: `FORUM_INSTAGRAM_CLIP_ENABLED` sat in EXPERIENCE while its parent, Image content, is in AUTHORING, failing `FeatureFlagsBehaviorTest::testPresentationOrderCoversEveryFlagOnceInItsAssignedGroup` on `main`. Moved it to AUTHORING after Image gallery.
- Verification:
  - `./v3 test ThreadTitleTest MediaEmbedPreviewControllerTest MediaEmbedBeaconTemplatesTest MusicMediaEmbedTest BandcampPlayerResolverTest BandcampPlayerControllerTest MediaEmbedRendererTest FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest ReadModelThreadTitleRebuildTest`: 110 run, 110 passed.
  - New cases: one title per provider through its own endpoint with a cache hit on the second call; Bandcamp `og:title`; a failed fetch is cached and writes no subject; a Spotify title back-fills an empty subject; a provider/URL mismatch fetches nothing; first-line matching on and off (text before the link, text on the same line, a second embed and a set subject never match); the first-line beacon only with its flag.
  - Updated on purpose: assertions in `ThreadTitleTest`, `MusicMediaEmbedTest` and `MediaEmbedBeaconTemplatesTest` that said Spotify, SoundCloud, TikTok and Bandcamp never request a title. Their display-title assertions (the link, not "Untitled") are unchanged and pass.
  - Count on the public zenmemes.com archive (`/downloads/repository.tar.gz`, 1,701 thread records, 2026-11-25): 3 untitled bare-link threads (2 YouTube or Instagram, already fetched today; 1 music link, now fetched); 0 threads that the first-line flag would add.
  - Smoke run before the flag fixes: 555 run, 551 passed; the 4 failures were the registry list, the pre-existing Instagram clip ordering, and the two beacon assertions above, all fixed.
- Notes:
  - The count goes here, not in the flag's description as Step 3 said: a number in a description goes stale.
  - Workspace oEmbed checks (Step 3 Key Risks) still hold; DreamHost is checked in Stage 7.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 2 - The /music/ page

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `SiteProfileRegistry.php`: profile key `musicSection` (validated as a bool) and `musicSection(): bool`; the mitrapclub profile sets it to true.
  - `Application.php`: `/music/` and `/music` render through the new controller, answer 404 when the key is off, and join the live application routes beside `/gallery/`.
  - New `Http/MusicPageController.php`: `page(mixed $page = null): string` reads `TagPage::page($pdo, 'music', $n)` and numbered pagination at `/music/`; active section `music`.
  - New `templates/pages/music.php`: the board's `thread_row.php` rows in a `thread-rows music-tracks` list; "No tracks yet" or "on this page" when empty; no visible page title, as for other main-nav pages.
- Verification:
  - `./v3 test MusicPageTest SiteProfileRegistryTest GalleryPageTest`: 19 run, 19 passed.
  - New `MusicPageTest`: /music/ on mitrapclub lists exactly the IDs /tags/music lists, newest first, including a thread with `music` beside another board tag, and leaves an untagged lecture out; page 2 says "No tracks on this page"; an empty tag still renders; zenmemes, chouse and qdb answer 404.
- Notes:
  - A hand-written fixture can't use a `#music` reply or an unsigned label record to tag a thread: replies create label records at write time, and unsigned labels don't count under the existing approval rules. The test uses board tags; tag membership itself is TagPage's existing, separately tested behaviour.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

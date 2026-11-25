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

## Stage 3 - Track rows with players

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `templates/partials/thread_row.php`: optional `rowPlayer` (rendered embed) on its own line under the meta, and `rowPlayerLink`, which is left out of the excerpt. Other callers pass neither and render as before.
  - `Http/MusicPageController.php`: each row carries `music_link`, the first link `MediaEmbedDetector` recognizes in the opening post's preview.
  - `templates/pages/music.php`: renders the player through the existing `$br` body renderer, so it follows the media embed and inline player flags like any post.
  - `public/assets/site.css`: `.thread-row__player` spans the row and clamps the card to the column. The styles went in `site.css`, not the Underground theme files as planned: the rule is layout, and both Underground themes already style the cards.
- Verification:
  - `./v3 test MusicPageTest BoardListTest MediaEmbedRendererTest`: 41 run, 41 passed. New case: a Spotify row has a collapsed player and doesn't repeat the link as its excerpt; a text-only #music thread has no player.
  - Local server (`./v3 start`, `FORUM_SITE_ID=mitrapclub`, five tracks from YouTube, Spotify and SoundCloud), headless Chrome at 380 and 1280px in Underground and Underground Dark, collapsed and with three players open: no sideways overflow (scroll width minus client width: -15px, the scrollbar gutter, in all eight shots). Players are empty in the workspace because provider frames can't load here.
- Notes:
  - The row preview holds only the opening post's first line, so a note on the line after the link shows on the thread page, not in the row. Left as is; widening the stored preview is outside this cycle.
  - Spotify album players are tall (the provider's own height); this is existing card behaviour.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 4 - Share box

**Original estimate: 0.10**
**Retrospective: 0.15**

- Changes:
  - `templates/pages/music.php`: the shared New post form (`thread_compose_form.php`, full mode, as the calendar day page uses it) at the top, with `music` in Tags, `return_to` `/music/`, draft scope `music`, and the placeholder "Paste a link to a track". The Title is optional below Post, as on New post; the tag is visible and can be changed. The plan named the compact form with a Title through the lead-fields slot; the full form already has both, so no slot was needed.
  - `Http/MusicPageController.php`: passes the form's fields and the signing and Outbox scripts the calendar day page loads.
  - `Http/ComposeAndAccountKeyController.php`: `resolveComposeThreadReturnTo()` sends `/music/` back to `/music/` on a site with the section; elsewhere the new thread opens, as before.
  - New `templates/partials/media_embed_warm_beacon.php`: the title-fetch beacon, moved out of `thread_card.php` and `thread_root_card.php` (both now use it) and added to rows that carry a player. Without it, a track shared from /music/ stayed "Untitled" until someone opened its thread.
- Verification:
  - `./v3 test MusicPageTest MediaEmbedBeaconTemplatesTest BoardListTest ThreadRootCardRepeatedTitleTest CalendarPageTest`: all passed (MusicPageTest 6 of 6).
  - New `MusicPageTest` cases: the form carries the tag, return address, draft scope and placeholder; a no-JavaScript guest POST writes `Board-Tags: music` with no identity and redirects to `/music/?created_post_id=…`; the untitled share's row carries the YouTube beacon; on zenmemes a `/music/` return address is ignored.
  - New browser test `tests/browser/music_share.mjs` (mitrapclub, 390px): with JavaScript, paste, Title, Post, back on /music/ with the track first, its player and `Board-Tags: music`, no sideways scroll, no dialogs or errors; a draft typed on /music/ is not in the Board composer; without JavaScript, a Spotify share posts and returns to /music/. PASS.
  - `composers.mjs`, `compose_attempt_replay.mjs` (desktop and phone), `offline_compose.mjs`: PASS, unchanged.
- Notes:
  - Retrospective 0.15 against 0.10: the missing row beacon and the browser test were more than the stage planned.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 5 - Music in the nav

**Original estimate: 0.05**
**Retrospective: 0.10**

- Changes:
  - `View/TemplateRenderer.php`: `navigationItems()` adds Music after Gallery (or Calendar, or Recent) when `musicSection()` is true; Agents now follows Music when both are present. `music` joins `$barOrder` after `gallery`.
  - `public/assets/site.css`: the two `html[data-nav-slots~="music"]` rules, copied from Gallery's, so Music can hold a phone tab and name the More tab.
  - `public/assets/theme-nav-icons.css`: a beamed-notes icon in the pixel, smooth and cute sets, beside Gallery's, and Music in the shared icon spacing rule.
  - Pre-existing failure fixed: `tests/browser/nav_slots.mjs` timed out on `main` because Board links to `/threads/` since PR #385 and the test's in-memory server only served `/`. Added `/threads/` to its routes.
- Verification:
  - `./v3 test AppNavigationPlacementTest NavIconSetsTest BoardListTest MusicPageTest FastNavigationLayoutTest`: 39 run, 39 passed. New case: Music right after Gallery on mitrapclub, after Recent with Gallery off, and absent on zenmemes, chouse and qdb.
  - `nav_slots.mjs`: PASS after the fix (it failed the same way on a clean `origin/main` worktree). `viewer_nav_prepaint.mjs`: PASS, 100 page states.
  - Local mitrapclub server: `/music/` renders `<a class="nav-link is-active" href="/music/">Music</a>`.
- Notes:
  - Retrospective 0.10 against 0.05: the icons and the nav_slots fix.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 6 - Documentation and final review

**Original estimate: 0.05**
**Retrospective: 0.05**

- Changes:
  - New `docs/concepts/music_section.md` (purpose, scenario, sources, boundary).
  - `docs/concepts/shared_composer.md`: the Music page placement. `docs/concepts/navigation_slots.md`: Music as a destination.
  - `docs/plans/media_embed_support_checklist.md`: fetched titles for every provider, and the first-line flag.
- Verification:
  - Local links in the four files resolve.
  - `git diff --check` (worktree and `origin/main...HEAD`): clean.
  - Smoke set, `./v3 test`: 494 run, 494 passed.
  - Final review of the whole diff (33 files) against the implementation standards: every new surface reuses an existing component (TagPage, `thread_row.php`, the shared composer, `$br`, the preview cache and write path); the two new pieces, `OembedTitleFetcher` and `media_embed_warm_beacon.php`, replace three near-identical fetchers and three copies of the beacon. No new dependency.
- Notes:
  - With media embeds off, a row's player line shows the plain link instead of a card, as the post body would.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

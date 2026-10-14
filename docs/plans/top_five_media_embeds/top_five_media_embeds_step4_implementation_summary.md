> **Feature plan:** [Step 1](./top_five_media_embeds_step1_solution_assessment.md) · [Step 2](./top_five_media_embeds_step2_feature_description.md) · [Step 3](./top_five_media_embeds_step3_development_plan.md) · [Step 4](./top_five_media_embeds_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Provider compatibility and baseline

- Changes: Created `feature/top-five-media-embeds`; organized and committed approved plans in `696763d8`. Installed the existing declared Playwright dependency locally without generating a lockfile. Temporary probes served candidate sandboxed iframes from localhost; no application changes.
- Verification: Chrome 145.0.7632.109 on Linux, headless, October 14, 2026. `php tests/run.php` for the nine embed suites plus `ThreadTitleTest`: **71 passed, 0 failed**. Full baseline launched before source edits; final results will be recorded below when it finishes.
- Verification: Spotify track `11dFghVXANMlKmJXsNCbNl`, album `1A2GTWGtFfWp7KSQTwWOyo`, playlist `37i9dQZF1DX4JAvHpjipBk` entered preview playback (Pause controls). TikTok `6718335390845095173` and YouTube `M7lc1UVf-VE` each reached media ready-state 4 with playback time advancing beyond six seconds.
- Verification: Existing `InstagramPagePreviewFetcher` returned both title and thumbnail for public post `DXufGLmgRB3` and reel `DFSwVgSuunz` through real outbound requests. No credentials were used.
- Notes: Candidate sandbox permissions: scripts, same-origin, presentation, popups. Player `allow` includes autoplay, encrypted-media, fullscreen, picture-in-picture; loading the iframe still requires expansion and playback uses provider controls. Some alternate sample videos/playlists were unavailable, confirming provider-specific availability restrictions. Spotify anonymous playback is a preview, not a promise of full-track playback.
- Notes: Official player guidance checked: [Spotify](https://developer.spotify.com/documentation/embeds/tutorials/creating-an-embed), [TikTok](https://developers.tiktok.com/docs/en/embed-player). Live compatibility gate passed; no deployment or flag changes. Temporary probe files and screenshots are outside the repository.

## Stage 2 - YouTube and Shorts sharing

- Changes: Added explicitly validated Shorts/watch/share URLs, complete 11-character YouTube identifiers, and anchored Instagram paths. Rejected lookalike hosts, credentials, malformed IDs, and excluded formats. Tracking cleanup now preserves untouched query values, repeated parameters, encoding, and fragments.
- Changes: Reused one player expando renderer with an always-visible source link, validated permissions, and portrait sizing for Shorts. Normalized YouTube title-fetch targets to canonical watch URLs while preserving original source links. Corrected three existing controller fixtures from 10-character to valid 11-character IDs.
- Verification: Focused detector/renderer/controller/beacon/title/fetcher suites: **61 passed, 0 failed**. A real Short (`qwUM4Sk1_nE`) played through the actual renderer/script in Chrome at 360px: media time advanced, iframe was 360×640, source link remained visible, keyboard expansion made exactly one player request, and reopening made no additional navigation. Live oEmbed returned its title.
- Verification: Full baseline finished: **1161 run, 1160 passed, 1 failed** (`PrivateMessageHistoryCryptoTest::testCanonicalReaderRehydratesOnlyVerifiedHistory`, expected 0 rejected records, got 1). A concurrent focused run passed its assertions but hit a test-history SQLite lock; rerunning with an isolated `FORUM_TEST_HISTORY_DB_PATH` completed successfully. Subsequent runs use isolated history files.
- Notes: The provider compatibility and Shorts title gates passed. No schemas, flag defaults, or production settings changed.

## Stage 3 - Spotify content and title eligibility

- Changes: Added explicit 22-character Spotify track/album/playlist URLs, labeled plain cards, shared click-to-load players, always-visible source links, and 352px provider sizing. Existing title-backfill eligibility is restricted to YouTube/Instagram; Spotify retains ordinary body-derived titles and emits no preview beacon.
- Changes: A new bare-link template regression caught title/body de-duplication hiding Spotify cards entirely. Added the shared renderer's `hasEmbeds(string): bool` helper and used it in board/root templates so recognized media remains visible even when its URL is the displayed title. No provider-specific duplicate rendering path was added.
- Verification: Detector/renderer/beacon/title/controller/script-inclusion suites: **60 passed, 0 failed**. Real Spotify track preview entered playback through the actual renderer/script at 360px; iframe measured 360×352, source link stayed visible, keyboard expansion loaded once, reopening did not reload. Stage 1 also confirmed real album and playlist preview playback.
- Notes: Unsupported Spotify artists, podcasts, URI schemes, share redirects, and malformed IDs remain outside scope. No Spotify title fetching, subject writes, schema changes, or flag changes.

## Stage 4 - Direct TikTok videos

- Changes: Added explicit TikTok video URLs with complete 19-digit IDs and optional `www.`; reused plain cards and the shared expando/source-link renderer. The official player loads in a portrait frame with autoplay explicitly disabled. Profile/photo/redirect URLs remain unsupported.
- Verification: Focused detection/rendering/title/beacon/controller/script-inclusion suites: **64 passed, 0 failed**. Real TikTok `6718335390845095173` played through the actual renderer/script at 360px with advancing media time, a 360×640 frame, visible source link, one request on keyboard expansion, and no navigation on reopening.
- Notes: TikTok threads retain body-derived titles and no metadata beacon. No provider-controlled HTML/scripts, new fetch endpoint, schema, or flag changes.

## Stage 5 - Forum views, Instagram recovery, and themes

- Changes: Added an isolated browser fixture/router and repeatable `node tests/browser/media_embed_browser.mjs` acceptance test. It creates threads/replies through the actual write APIs and renders them through the real front controller, including bare Spotify/TikTok threads. Seeded successful/failed Instagram cache entries exercise deterministic preview/fallback behavior.
- Changes: Browser testing found 1280px Instagram thumbnails overflowing a 360px viewport. Added class-scoped preview wrapping and responsive thumbnail sizing; no unrelated theme changes.
- Verification: Browser acceptance passed for board All view, thread roots/replies, Forte (including actual toggle-to-load), reply-composer context, QDB quote cards/news, all **16 theme modes at 360px and 1280px**, and keyboard controls. Mocked unavailable player documents prove source-link recovery and delayed loading without depending on live content. Inspected MIT Rap Club mobile and dark desktop screenshots.
- Verification: Nine embed suites plus title, presentation-matrix, QDB routing, and quote-display suites: **112 passed, 0 failed**. Stage 1 separately verified real Instagram post/reel metadata. Cached thumbnails and failed-preview cards both remain usable in normal forum views.
- Notes: Browser tests account for the board's default Liked filter, body-preview truncation, asynchronous toggle dispatch, and Auto theme's resolved stylesheet. Actual provider playback remains independently verified and receives an application-level live round-trip in Stage 6.

## Stage 6 - Release verification and documented coverage

- Changes: Extended browser acceptance with all flag states, unsupported URLs, no reload on reopening, visible keyboard focus across themes, bare-URL title checks, and optional `--live` provider round-trips. Updated the support checklist's exact URL boundaries, completed local checks, ranked coverage, and verification commands; reconciled the club backlog and prior YouTube-only proposal links/status.
- Verification: `node tests/browser/media_embed_browser.mjs --live` passed through real forum pages: YouTube watch, YouTube Shorts, all three Spotify content types, TikTok playback, cold-to-warm Instagram post/reel previews, and Shorts subject backfill with the embed intact. Actual player widths fit the 360px mobile forum container (approximately 297px). Source links stayed accessible, initial collapsed players made no provider navigation, and reopening did not reload them.
- Verification: `node tests/browser/media_embed_browser.mjs` passed the deterministic network/flag/view matrix and visible focus checks for all 16 theme modes at 360px/1280px. Controlled unavailable players, recently failed Instagram metadata, and unsupported URLs preserve the expected recovery behavior. Existing human subjects and Spotify/TikTok body-derived titles are covered by PHP/template regressions.
- Verification: `FORUM_TEST_HISTORY_DB_PATH=/tmp/top-five-stage6-full-history.sqlite php tests/run.php`: **1174 run, 1174 passed, 0 failed**. The unrelated baseline crypto failure did not recur; no private-message code was changed. Focused integration checks previously passed **112/112**. `node --check`, `git diff --check`, and current feature/checklist local-link validation passed.
- Notes: Complete local implementation and release verification. No migrations, credentials, production flag changes, publishing, or merging were needed. Both flags retain their off-by-default behavior. Provider restrictions still govern availability and Spotify playback length; redirecting share links and other approved exclusions are documented in the checklist.
- Notes: Seven feature commits are required and recorded: one approved-planning commit, then six stage commits, each carrying this summary. Planning artifacts retain their approved substantive content and inherited disabled debug state.

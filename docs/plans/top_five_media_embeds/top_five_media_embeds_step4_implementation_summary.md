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

# Media embed support checklist

Use this checklist to verify existing embeds, close URL coverage gaps, and add
providers. Checked implementation items describe repository support, not live
deployment status. Unchecked expansion items are candidates to scope; no delivery
dates are committed. Coverage and local verification updated October 14, 2026. Production flag settings still need an operator check.

## Current support

| Provider | Recognized URL shapes | Available presentation |
| --- | --- | --- |
| YouTube | `youtube.com/watch?v=VIDEO_ID`, `youtu.be/VIDEO_ID`, `youtube.com/shorts/VIDEO_ID` | Link card or expandable inline player; portrait sizing for Shorts |
| Instagram | `instagram.com/p/SHORTCODE/`, `instagram.com/reel/SHORTCODE/` | Link card or cached thumbnail and title preview |
| Spotify | `open.spotify.com/track/ID`, `/album/ID`, `/playlist/ID` | Link card or expandable player; provider may offer previews |
| TikTok | `tiktok.com/@USER/video/ID` | Link card or expandable portrait player |

Both HTTP and HTTPS are recognized. YouTube, Instagram, and TikTok accept optional
`www.`; Spotify uses exactly `open.spotify.com` and short YouTube links use exactly
`youtu.be`. YouTube IDs must be 11 characters, Spotify IDs 22 alphanumeric
characters, and TikTok IDs 19 digits. Paths must match the listed content forms
completely; URL credentials and explicit ports are rejected. Instagram previews
link to the original post; they do not play Instagram video inline.

Redirecting share links, localized Spotify paths such as `/intl-en/track/ID`,
YouTube live/mobile/embed paths, and playlist-only YouTube links remain unsupported.
Player URLs do not carry start times or playlist context. Source links preserve
meaningful parameters and fragments while removing known tracking parameters.

- [x] Detect the URL shapes above in post bodies and render provider cards.
- [x] Strip known tracking parameters from card links.
- [x] Load YouTube, Spotify, and TikTok players only when the reader expands them; keep source links visible outside collapsed players.
- [x] Use a sandboxed `youtube-nocookie.com` player with responsive 16:9 sizing.
- [x] Fetch and cache Instagram title and thumbnail metadata, with a plain-card fallback.
- [x] Fetch titles for untitled threads containing exactly one YouTube/Shorts or Instagram URL, preserving existing subjects. Spotify/TikTok retain ordinary body-derived titles.
- [x] Gate cards behind `FORUM_MEDIA_EMBEDS_ENABLED` and players/previews behind
      `FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED`; both default off, and the second requires the first.
- [x] Pass the focused embed/title/presentation suites: 112 tests passed, 0 failed on October 14, 2026; browser and full-suite results are recorded in the [implementation summary](top_five_media_embeds/top_five_media_embeds_step4_implementation_summary.md).

Implementation: [detector](../../src/ForumRewrite/View/MediaEmbedDetector.php),
[renderer](../../src/ForumRewrite/View/MediaEmbedRenderer.php), and
[preview controller](../../src/ForumRewrite/Http/MediaEmbedPreviewController.php).

## Verification and rollout

- [x] Verify isolated local API fixtures with both embed flags enabled, cards only, and both effectively disabled.
- [ ] Record the production instance and its effective values for both embed flags before rollout.
- [x] Confirm basic cards with only the main flag enabled, and players/previews with both enabled.
- [x] Confirm real YouTube and Shorts playback after expansion; watch/share URL forms also pass detection and rendering regression tests.
- [x] Verify real Instagram post and reel previews against live pages, including a cold cache followed by a reload.
- [x] Confirm unavailable or inaccessible Instagram content retains a usable link card.
- [x] Verify a bare-URL thread receives a fetched title after reload and an existing title is preserved.
- [x] Check board listings, thread roots, replies, and the Forte view.
- [x] Check desktop and approximately 360px mobile width across shipped themes for overflow and player sizing.
- [x] Check keyboard expansion and visible focus for player controls across all themes; inspect mobile and desktop screenshots.
- [x] Record the instance, date, browser, outcomes, and unresolved issues when completing these checks.

Local checks used headless Chrome 145 on Linux and all 16 theme modes at 360px
and 1280px. Live players, Instagram previews, and Shorts title backfill passed
through real forum pages. These checks close the earlier local layout and
Instagram-fetching gaps; production acceptance remains separate.

## Expand URL coverage for existing providers

These are proposed follow-ups based on current detection gaps, not previously
committed roadmap items. Decide scope before implementing each group.

- [x] Support YouTube `/shorts/VIDEO_ID` links.
- [ ] Decide support for YouTube `/live/VIDEO_ID` and `/embed/VIDEO_ID` links.
- [ ] Decide support for mobile YouTube domains such as `m.youtube.com`.
- [ ] Decide whether playlist-only YouTube links should render a playlist player or a link card.
- [ ] Preserve supported YouTube start-time parameters in the inline player; currently only the video ID is used.
- [ ] Decide whether a video link's playlist context should carry into playback.
- [ ] Decide whether Instagram profiles, stories, and other share-link formats belong in scope.
- [x] Add accepted and rejected URL examples and detection tests for the selected direct content formats.

## Add providers from the existing backlog

The [provider backlog](mitrapclub_theme_and_features_checklist.md) prioritizes the
first group for the club's music content. Spotify and direct TikTok videos are
implemented; the remaining providers and redirecting share links are unscheduled.

| Complete | Priority | Provider | Content to scope |
| --- | --- | --- | --- |
| [ ] | Higher | SoundCloud | Tracks and playlists |
| [x] | Higher | TikTok | Direct video links implemented; shared redirect links deferred |
| [x] | Higher | Spotify | Direct tracks, albums, and playlists implemented |
| [ ] | Higher | Bandcamp | Tracks and albums |
| [ ] | Lower | X/Twitter | Individual posts |
| [ ] | Lower | Facebook | Posts and videos |
| [ ] | Lower | Vimeo | Videos |
| [ ] | Lower | Twitch | Channels, clips, and recorded videos |

For each selected provider, copy and complete this sequence in its implementation plan:

- [ ] List exact supported hosts, paths, identifiers, and share-link variants with examples.
- [ ] Verify the provider's current official embedding or metadata interface and any credential requirements.
- [ ] Choose the presentation: inline player, linked preview, or plain card fallback.
- [ ] Define query-parameter handling, redirect behavior, and unavailable-content behavior.
- [ ] Implement detection and rendering using the existing shared embed components.
- [ ] If metadata fetching is needed, define caching, timeouts, retries, and validated fetch destinations.
- [ ] Add meaningful tests for accepted/rejected URLs, rendering, and failure behavior.
- [ ] Verify real content in the browser across the shared post views and mobile layout.
- [ ] Document exact URL support and deployment flag requirements here before marking the provider complete.

## General URL cleanup

This is a separate backlog item in [todo.txt](backlog/todo.txt), broader than embed detection.

- [ ] Scope tracking-parameter cleanup for arbitrary providers while preserving meaningful parameters.
- [ ] Scope redirect resolution, including destination validation and bounded network work.
- [ ] Apply the selected cleanup rules consistently wherever URLs are displayed.

## Repeatable automated verification

Run from the repository root after changing embed behavior:

```bash
php tests/run.php MediaEmbedDetectorTest MediaEmbedRendererTest MediaEmbedPreviewCacheStoreTest MediaEmbedPreviewControllerTest MediaEmbedInlinePlayerScriptTest TemplateRendererMediaEmbedsScriptTest MediaEmbedBeaconTemplatesTest YoutubeOembedTitleFetcherTest InstagramPagePreviewFetcherTest ThreadTitleTest
```

For browser checks, install the declared dependency with `npm install --ignore-scripts --package-lock=false`, then run:

```bash
node tests/browser/media_embed_browser.mjs
node tests/browser/media_embed_browser.mjs --live
```

The default run uses controlled unavailable player documents; `--live` additionally
checks real playback, Instagram previews, and Shorts title backfill. Tests use an
isolated temporary repository and remove it afterward. Set `CHROMIUM_PATH` if
Chrome is not installed at `/opt/google/chrome/chrome`. Use a distinct
`FORUM_TEST_HISTORY_DB_PATH` when running PHP suites concurrently.

Include new provider tests as support grows. Automated passes do not replace
production acceptance.

## Expected URL frequency

Working estimate for the MIT Rap Club's rap and hip-hop discussions, ordered from
most to least likely. This is an audience-based planning hypothesis, not measured
post traffic or a ranking of internet-wide popularity. Adjacent positions are
uncertain. Likelihood also differs from implementation priority: SoundCloud may
be especially valuable for members' own releases even if Spotify links occur more
often. The existing [club backlog](mitrapclub_theme_and_features_checklist.md)
provides the audience context.

The examples below are URL shapes, not an exhaustive acceptance specification;
`ID`, `USER`, and `SLUG` are placeholders. HTTPS is omitted for readability.
“Unsupported” means no automatic media embed in the current detector.

| Rank | URL type | Representative shapes | Current embed support |
| --- | --- | --- | --- |
| 1 | YouTube videos and shared video links | `youtube.com/watch?v=ID`, `youtu.be/ID` | Supported; start time and playlist context are not passed into the player |
| 2 | Instagram reels and posts | `instagram.com/reel/ID/`, `instagram.com/p/ID/` | Linked preview or plain card, not inline playback |
| 3 | Spotify songs, albums, and playlists | `open.spotify.com/track/ID`, `/album/ID`, `/playlist/ID` | Supported: link card or click-to-load player |
| 4 | TikTok clips | `tiktok.com/@USER/video/ID` | Supported for direct video links; share redirects deferred |
| 5 | YouTube Shorts | `youtube.com/shorts/ID` | Supported: portrait click-to-load player |
| 6 | SoundCloud tracks and sets | `soundcloud.com/USER/SLUG`, `soundcloud.com/USER/sets/SLUG` | Unsupported |
| 7 | Articles, lyrics, and reference pages | News articles, interviews, Genius song pages, Wikipedia articles | Unsupported; general link previews are a separate candidate |
| 8 | Event, ticket, and venue pages | Eventbrite events, venue calendars, club event pages | Unsupported as media embeds; separate from structured event fields |
| 9 | Artist profiles and release landing pages | YouTube channels, Instagram profiles, artist websites, Linktree pages | Unsupported; a profile is not an individual video or post |
| 10 | Bandcamp releases | `ARTIST.bandcamp.com/album/SLUG`, `ARTIST.bandcamp.com/track/SLUG` | Unsupported |
| 11 | X/Twitter posts and Reddit discussions | `x.com/USER/status/ID`, `reddit.com/r/COMMUNITY/comments/ID/...` | Unsupported; Reddit is an additional candidate, outside the existing provider backlog |
| 12 | Other music and podcast services | Apple Music releases, podcast episode pages, Mixcloud mixes | Unsupported; additional candidates to scope only if demand appears |
| 13 | Shared files and direct media | Google Drive or Dropbox links; direct `.jpg`, `.png`, `.mp3`, `.mp4` URLs | Unsupported as URL embeds; owned image uploads are a separate feature |
| 14 | Facebook posts, videos, and events | Facebook post, reel, video, and event links | Unsupported |
| 15 | Livestreams and less common video links | `youtube.com/live/ID`, Twitch channels/clips, `vimeo.com/ID` | Unsupported; a YouTube stream shared as a recognized watch URL is detected |

Provider references confirm content types and sharing mechanisms, not the
frequency estimates: Spotify supports track, album, and playlist embeds
([Spotify documentation](https://developer.spotify.com/documentation/embeds/tutorials/creating-an-embed));
SoundCloud accepts track and playlist URLs
([SoundCloud documentation](https://developers.soundcloud.com/docs/oembed));
TikTok documents the individual-video URL shape
([TikTok documentation](https://developers.tiktok.com/doc/embed-videos/)).

### URL variants to account for

These overlap the ranked types; count them as variants of their destination,
not additional provider categories.

- [x] Cover tracking parameters, fragments, trailing punctuation, and URLs pasted beside ordinary text for recognized direct content URLs.
- [ ] Cover timestamped YouTube watch/share links. YouTube offers a “Start at” sharing option; our player currently drops its playback meaning ([YouTube sharing help](https://support.google.com/youtube/answer/57741)).
- [ ] Scope provider share redirects and music smart links separately from direct content URLs; current detection does not resolve redirects.
- [ ] Within YouTube, use this provisional format order: watch/short links, Shorts, playlist-only links, live links, mobile-domain watch links, then copied embed URLs. Treat timestamps as a cross-cutting variant.
- [x] Implement the approved top-five scope: YouTube videos, Instagram posts/reels, Spotify, TikTok, and YouTube Shorts. See the [implementation summary](top_five_media_embeds/top_five_media_embeds_step4_implementation_summary.md) for evidence and remaining exclusions.

### Replace estimates with observed frequency

- [ ] Select a representative set of real posts and replies and record the instance, date range, and sample size.
- [ ] Count URL occurrences by provider and content type, plus the number of distinct posts containing each type.
- [ ] Separate exact duplicates, direct content URLs, and unresolved share links so repeated links do not distort the ranking.
- [ ] Record the recognized versus unsupported share of URLs, including unsupported formats on supported providers.
- [ ] Update this table with observed counts and revisit provider priorities before starting the next provider cycle.

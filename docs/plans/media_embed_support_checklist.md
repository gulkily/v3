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
| 3 | Spotify songs, albums, and playlists | `open.spotify.com/track/ID`, `/album/ID`, `/playlist/ID` | Unsupported |
| 4 | TikTok clips | `tiktok.com/@USER/video/ID` | Unsupported; shared redirect links also need scoping |
| 5 | YouTube Shorts | `youtube.com/shorts/ID` | Unsupported |
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

- [ ] Cover tracking parameters, fragments, trailing punctuation, and URLs pasted beside ordinary text.
- [ ] Cover timestamped YouTube watch/share links. YouTube offers a “Start at” sharing option; our player currently drops its playback meaning ([YouTube sharing help](https://support.google.com/youtube/answer/57741)).
- [ ] Scope provider share redirects and music smart links separately from direct content URLs; current detection does not resolve redirects.
- [ ] Within YouTube, use this provisional format order: watch/short links, Shorts, playlist-only links, live links, mobile-domain watch links, then copied embed URLs. Treat timestamps as a cross-cutting variant.
- [ ] Keep the [YouTube URL coverage Step 1](youtube_url_coverage_step1_solution_assessment.md) as the current bounded proposal; this frequency estimate does not approve it or expand its scope.

### Replace estimates with observed frequency

- [ ] Select a representative set of real posts and replies and record the instance, date range, and sample size.
- [ ] Count URL occurrences by provider and content type, plus the number of distinct posts containing each type.
- [ ] Separate exact duplicates, direct content URLs, and unresolved share links so repeated links do not distort the ranking.
- [ ] Record the recognized versus unsupported share of URLs, including unsupported formats on supported providers.
- [ ] Update this table with observed counts and revisit provider priorities before starting the next provider cycle.

# Media embed support checklist

Use this checklist to verify existing embeds, close URL coverage gaps, and add
providers. Checked implementation items describe repository support, not live
deployment status. Unchecked expansion items are candidates to scope; no delivery
dates are committed. Baseline reviewed October 14, 2026.

## Current support

| Provider | Recognized URL shapes | Available presentation |
| --- | --- | --- |
| YouTube | `youtube.com/watch?v=VIDEO_ID`, `youtu.be/VIDEO_ID` | Link card or expandable inline player |
| Instagram | `instagram.com/p/SHORTCODE/`, `instagram.com/reel/SHORTCODE/` | Link card or cached thumbnail and title preview |

Both HTTP and HTTPS are recognized. The long YouTube and Instagram domains accept
optional `www.`. Instagram previews link to the original post; they do not play
Instagram video inline.

- [x] Detect the URL shapes above in post bodies and render provider cards.
- [x] Strip known tracking parameters from card links.
- [x] Load the YouTube iframe only when the reader expands the player.
- [x] Use a sandboxed `youtube-nocookie.com` player with responsive 16:9 sizing.
- [x] Fetch and cache Instagram title and thumbnail metadata, with a plain-card fallback.
- [x] Fetch titles for untitled threads containing exactly one supported URL, preserving existing subjects.
- [x] Gate cards behind `FORUM_MEDIA_EMBEDS_ENABLED` and players/previews behind
      `FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED`; both default off, and the second requires the first.
- [x] Pass the focused automated suite: 58 tests passed, 0 failed on October 14, 2026.

Implementation: [detector](../../src/ForumRewrite/View/MediaEmbedDetector.php),
[renderer](../../src/ForumRewrite/View/MediaEmbedRenderer.php), and
[preview controller](../../src/ForumRewrite/Http/MediaEmbedPreviewController.php).

## Verify existing support before expanding

- [ ] Record the target instance and its effective values for both embed flags.
- [ ] Confirm basic cards with only the main flag enabled, and players/previews with both enabled.
- [ ] Open a real YouTube watch link and a short link; confirm playback starts after expansion.
- [ ] Verify real Instagram post and reel previews against live pages, including a cold cache followed by a reload.
- [ ] Confirm unavailable or inaccessible Instagram content retains a usable link card.
- [ ] Verify a bare-URL thread receives a fetched title after reload and an existing title is preserved.
- [ ] Check board listings, thread roots, replies, and the Forte view.
- [ ] Check desktop and approximately 360px mobile width across shipped themes for overflow and player sizing.
- [ ] Check keyboard expansion and visible focus for the YouTube player control.
- [ ] Record the instance, date, browser, outcomes, and unresolved issues when completing these checks.

The existing [layout implementation summary](youtube_embed_layout/youtube_embed_layout_step4_implementation_summary.md)
leaves cross-theme and mobile visual verification open. Instagram live fetching
also needs verification beyond the synthetic HTML tests.

## Expand URL coverage for existing providers

These are proposed follow-ups based on current detection gaps, not previously
committed roadmap items. Decide scope before implementing each group.

- [ ] Decide support for YouTube `/shorts/VIDEO_ID`, `/live/VIDEO_ID`, and `/embed/VIDEO_ID` links.
- [ ] Decide support for mobile YouTube domains such as `m.youtube.com`.
- [ ] Decide whether playlist-only YouTube links should render a playlist player or a link card.
- [ ] Preserve supported YouTube start-time parameters in the inline player; currently only the video ID is used.
- [ ] Decide whether a video link's playlist context should carry into playback.
- [ ] Decide whether Instagram profiles, stories, and other share-link formats belong in scope.
- [ ] Add accepted and rejected URL examples and detection tests for each selected format.

## Add providers from the existing backlog

The [provider backlog](mitrapclub_theme_and_features_checklist.md) prioritizes the
first group for the club's music content. All providers below remain unscheduled;
the content types describe scoping candidates, not implemented support.

| Complete | Priority | Provider | Content to scope |
| --- | --- | --- | --- |
| [ ] | Higher | SoundCloud | Tracks and playlists |
| [ ] | Higher | TikTok | Video links and shared short links |
| [ ] | Higher | Spotify | Tracks, albums, and playlists |
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

This is a separate backlog item in [todo.txt](../../todo.txt), broader than embed detection.

- [ ] Scope tracking-parameter cleanup for arbitrary providers while preserving meaningful parameters.
- [ ] Scope redirect resolution, including destination validation and bounded network work.
- [ ] Apply the selected cleanup rules consistently wherever URLs are displayed.

## Repeatable automated verification

Run from the repository root after changing embed behavior:

```bash
php tests/run.php MediaEmbedDetectorTest MediaEmbedRendererTest MediaEmbedPreviewCacheStoreTest MediaEmbedPreviewControllerTest MediaEmbedInlinePlayerScriptTest TemplateRendererMediaEmbedsScriptTest MediaEmbedBeaconTemplatesTest YoutubeOembedTitleFetcherTest InstagramPagePreviewFetcherTest
```

Include new provider tests as support grows. Automated passes do not replace the
live-provider and browser checks above.


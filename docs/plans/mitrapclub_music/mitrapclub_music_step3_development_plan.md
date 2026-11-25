> **Feature plan:** [Step 1](./mitrapclub_music_step1_solution_assessment.md) · [Step 2](./mitrapclub_music_step2_feature_description.md) · [Step 3](./mitrapclub_music_step3_development_plan.md) · [Step 4](./mitrapclub_music_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./mitrapclub_music_debug_log.md))

Effort study: `railspike-effort` / `v3/mitrapclub_music`; host ledger [mitrapclub_music.md](../effort_ledger/mitrapclub_music.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.55**

Concepts touched: new [Music section](../../concepts/music_section.md) (Stage 6). [Shared composer](../../concepts/shared_composer.md) (new placement, Stage 4). [Navigation slots](../../concepts/navigation_slots.md) (new destination, Stage 5). [Tag browsing](../../concepts/tag_browsing.md) (unchanged). Media embeds: fetched titles widened (Stage 1; recorded in the [media embed checklist](../media_embed_support_checklist.md)). Feedback: none; PF-20261111-F31 stays out of scope.

## Completion Contract

- Normal entry: on mitrapclub.com a visitor, signed out or a member, taps Music in the nav.
- End-to-end outcome: /music/ shows a share box, then every thread tagged #music, newest first, 25 per page, each as a compact row with a collapsed player. Sharing a link makes an ordinary #music thread, back on /music/ with the new track at the top. An untitled share from YouTube, Spotify, SoundCloud, TikTok or Bandcamp gets that provider's title.
- Recovery: a refused share keeps the typed text and shows the reason, as the Board's composer does. With `FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED` off (the default), titles are fetched only when the whole post is one link, as today; a share with a line of text keeps the link as its title. A failed title fetch leaves the link as the title and retries after the existing one-hour backoff. Removing the tag or retracting the thread takes a track off. Turning off the profile setting removes the page and the nav item.
- Deployment: no schema change and no new command. /music/ renders live like /gallery/, so there are no static files to invalidate. Deploy now is enough. Title fetches go out from DreamHost: Stage 7 confirms that each provider answers from there.
- Release: merged to main, deployed to mitrapclub.com, the 12 existing tracks tagged #music by reply as "claude", and checked on the maintainer's iPhone SE.

## Key Risks

- **High risk:** widening the title match changes titles on every site. Today a title is fetched only when the whole body is one YouTube or Instagram link. A share with a line of text would never match. Impact: untitled threads on ZenMemes that start with a music link and then have text get a fetched title instead of their first line. Early validation: in Stage 1, count such untitled threads in a local import of zenmemes.com and record the count in the flag's description. Mitigation: the first-line rule (the link is the whole first line and the post has exactly one embed) sits behind a new site-mutable flag, `FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED`, off by default, so no site changes until its operator turns it on. (Revised 2026-11-25.)
- A provider refuses requests from DreamHost. The workspace check on 2026-11-25: Spotify, SoundCloud and YouTube oEmbed returned titles; TikTok returned 200 with no title for the sample video; Bandcamp release pages returned 200. Impact: no titles from that provider in production. Early validation: Stage 7 fetches one link per provider on the server. Mitigation: a failed fetch is already handled (link as title, retried later).
- Several open players on a phone. Impact: sideways scrolling at 380px, or sound from two tracks. Early validation: Stage 3 opens three players at 380px. Mitigation: players stay collapsed until tapped; the row clamps the player to the column width.
- The share box disturbs drafts on other composers. Impact: a lost Board draft. Early validation: `composers.mjs` and `compose_attempt_replay.mjs` in Stage 4. Mitigation: its own draft scope, `music`, as the calendar day and pledge placements have.

## Stage 1

**Estimate: 0.15**

- Goal: an untitled thread whose first line is one music link gets that provider's title, for every supported provider.
- Dependencies: none.
- Expected changes: new `SpotifyOembedTitleFetcher`, `SoundcloudOembedTitleFetcher` and `TiktokOembedTitleFetcher`, each shaped like `YoutubeOembedTitleFetcher` (same `fetch(string $url): ?string`, transport injectable for tests, same size and time limits). Bandcamp gains `title` from `og:title` in the page `BandcampPagePlayerFetcher` already fetches. `ThreadTitle::bareMediaEmbedMatch()` accepts all six providers, and also the first-line rule from Key Risks when the new flag is on. New `FeatureFlagRegistry::MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED` (`FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED`, "Media embed first-line titles", default off, site-mutable, group RENDERING, requires Media embed cards), passed to the match by its callers. `MediaEmbedPreviewController::warmPreview()` routes each provider to its fetcher and keeps the cache, the backoff and the empty-subject-only write. `MediaEmbedRenderer` emits the warm beacon for these providers on untitled threads, as it does for YouTube.
- Verification approach: `./v3 test --smoke ThreadTitleTest MediaEmbedPreviewControllerTest MediaEmbedBeaconTemplatesTest MusicMediaEmbedTest BandcampPlayerResolverTest` plus `FeatureFlagsBehaviorTest`, with new cases: one stubbed title per provider; with the flag off, a body with text after the link doesn't match and a bare link does; with it on, text after the link matches; text before the link, or two links, never match; a subject that is already set is never replaced; a failed fetch caches the failure. Count the untitled threads on a local zenmemes.com import that the flag would retitle (Key Risks).
- Estimate details (extended mode only): driver: three small fetchers, one Bandcamp field, a widened match behind a new flag, the beacon, and their tests; uncertainty: low.
- Risks or open questions:
  - Impact: titles change on existing threads once an operator turns the flag on.
  - Early warning / validation: the count on a local import, stated in the flag's description.
  - Mitigation: the flag is off by default and turning it off again stops new back-fills (titles already set stay).
- Canonical components/API contracts touched: `View/YoutubeOembedTitleFetcher.php` (pattern reused), `View/BandcampPagePlayerFetcher.php` (extended), `Support/ThreadTitle.php` (`bareMediaEmbedMatch()`, extended), `Http/MediaEmbedPreviewController.php` (extended), `View/MediaEmbedRenderer.php` (beacon, extended), `Support/FeatureFlags/FeatureFlagRegistry.php` (new flag, defined like `MEDIA_EMBEDS_INLINE_PLAYER_ENABLED`), `LocalWriteService::setThreadSubjectIfEmpty()` (unchanged), `/internal/media-embeds/warm-preview` (same route, more `provider` values).

## Stage 2

**Estimate: 0.10**

- Goal: /music/ lists the #music threads on sites that turn the section on, and is not found elsewhere.
- Dependencies: none.
- Expected changes: profile key `musicSection` (bool, validated in `SiteProfileRegistry::validate()`, read by a new `SiteProfileRegistry::musicSection(): bool`); mitrapclub sets it to true. In `Application.php`, the route `/music/` (and `/music`) is added to the live application routes and answers 404 when the key is off. A new `MusicPageController::page(mixed $page = null): string` reads `TagPage::page($pdo, 'music', $n)` and renders a new `templates/pages/music.php` with `PageNavigation::numbered('/music/', …)`, the active section set to `music`. An empty tag renders the page with the share box and no rows, not a 404.
- Verification approach: `./v3 test --smoke SiteProfileRegistryTest GalleryPageTest` plus a new `MusicPageTest`: 404 on a profile without the key; on mitrapclub the listed IDs equal /tags/music's for pages 1 and 2; an empty tag renders; `?page=` beyond the end follows the existing pagination error.
- Estimate details (extended mode only): driver: a profile key, a route and a thin controller over an existing query; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `SiteProfileRegistry.php` (`homePage`/`gettingStarted` pattern), `ReadModel/TagPage.php` (reused, unchanged), `Http/RecentPageController::gallery()` and `templates/pages/gallery.php` (shape followed), `Pagination/PageNavigation` (reused).

## Stage 3

**Estimate: 0.10**

- Goal: each track is a compact row with its player, and nothing scrolls sideways at 380px.
- Dependencies: Stage 2.
- Expected changes: `templates/partials/thread_row.php` gains an optional player slot, filled only when the caller passes the row's first music embed; other callers are unchanged. The music page passes it, using `MediaEmbedRenderer` with the inline player on, for the root post's first embed. Rows whose root has no recognized embed show without a player. Styles for the row and the share box go in `theme-underground.css` and the dark variant, using existing variables and clamping the player to the column.
- Verification approach: `./v3 test --smoke BoardListTest MediaEmbedRendererTest` plus `MusicPageTest` cases for a row with and without a player; `media_embed_browser.mjs` unchanged. Screenshots at 380px and 1280px in Underground and Underground Dark, collapsed and with three players open.
- Estimate details (extended mode only): driver: one optional slot, styles and screenshots; uncertainty: medium, from player sizing in the theme.
- Risks or open questions:
  - Impact: sideways scrolling with an open player.
  - Early warning / validation: the three-player screenshot.
  - Mitigation: the width clamp; no autoplay, so players never start on their own.
- Canonical components/API contracts touched: `templates/partials/thread_row.php` (extended; a new partial isn't needed because the row already takes optional thumbnail, pledge and event slots), `View/MediaEmbedRenderer::render()` (reused), `public/assets/theme-underground.css` and its dark variant (extended).

## Stage 4

**Estimate: 0.10**

- Goal: a visitor shares a link from /music/ and lands back on the page with the track at the top.
- Dependencies: Stage 2.
- Expected changes: `music.php` renders `thread_compose_form.php` in compact mode with `boardTags` set to `music`, `returnTo` set to `/music/`, `composeDraftScope` set to `music`, a body placeholder asking for a link, and an optional Title through the composer's lead-fields slot (the pledge form's mechanism). Signed, guest, Outbox and no-JavaScript submissions all go through the existing `/compose/thread`. No new write path.
- Verification approach: `MusicPageTest`: the form carries the tag, the return address and the draft scope; a no-JavaScript POST as guest creates a thread tagged music and redirects to /music/. Browser: `composers.mjs`, `compose_attempt_replay.mjs`, `offline_compose.mjs`, plus a new case in `composers.mjs`: share as guest and as a signed member, then check that a Board draft survives a share.
- Estimate details (extended mode only): driver: one composer placement with existing options; uncertainty: low.
- Risks or open questions:
  - Impact: a draft from the Board appears in the share box, or the other way round.
  - Early warning / validation: the draft case in `composers.mjs`.
  - Mitigation: the `music` draft scope.
- Canonical components/API contracts touched: `templates/partials/thread_compose_form.php` and `composer.php` (reused through existing parameters), `/compose/thread` (unchanged), [shared composer](../../concepts/shared_composer.md) (new placement noted).

## Stage 5

**Estimate: 0.05**

- Goal: Music appears in mitrapclub's nav after Gallery and can hold a phone tab slot.
- Dependencies: Stage 2.
- Expected changes: `TemplateRenderer::navigationItems()` inserts Music after Gallery (or after Calendar or Recent when Gallery is off) when `musicSection()` is true. `music` joins `$barOrder` and gets the `html[data-nav-slots~="music"]` rules in `site.css`, copied from Gallery's. A Music icon is added for themes that have nav icon sets.
- Verification approach: `./v3 test --smoke AppNavigationPlacementTest NavIconSetsTest`: Music after Gallery on mitrapclub; no Music on zenmemes, chouse and qdb, with their nav markup byte-identical. `nav_slots.mjs` and `viewer_nav_prepaint.mjs` with Music earning a slot.
- Estimate details (extended mode only): driver: one nav insertion, copied CSS rules and an icon; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `View/TemplateRenderer.php` (`navigationItems()`, `$barOrder`), `public/assets/site.css` (nav slot rules), nav icon sets.

## Stage 6

**Estimate: 0.05**

- Goal: the section is documented where contributors look.
- Dependencies: Stages 1–5.
- Expected changes: new `docs/concepts/music_section.md` (purpose, scenario, sources, boundary); notes in `shared_composer.md` and `navigation_slots.md`; the media embed checklist's fetched-titles line updated for all providers and the new flag. Final review of the whole diff against the implementation standards.
- Verification approach: links resolve; `./v3 test --smoke` passes.
- Estimate details (extended mode only): driver: one concept file and three short edits; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/` (inventory), `docs/plans/media_embed_support_checklist.md`.

## Stage 7

**Estimate: 0.10**

- Goal: the maintainer's phone shows the finished section on mitrapclub.com with the existing tracks in it.
- Dependencies: Stages 1–6, merged and deployed by the maintainer.
- Expected changes: no code. After deploy, post a `#music` reply as "claude" on the 12 existing track threads (the 13 music-link threads minus the Michael Levin lecture), checking the list with the maintainer first. Share one untitled bare link per provider on mitrapclub.com to check fetched titles from DreamHost, then retract those test threads. Whether to turn on first-line titles on mitrapclub is the maintainer's call in Tools → Feature flags; it stays off unless he does.
- Verification approach: replay the known environment: on an iPhone SE in Safari, Underground theme, open mitrapclub.com and tap Music, share a YouTube link signed out, open one player, and scroll the page without anything moving sideways. Evidence: the maintainer's check, plus 380px screenshots from the workspace. If something overflows, the profile setting turns the section off while it's fixed.
- Estimate details (extended mode only): driver: tagging replies, five test shares, and the phone check; uncertainty: medium, from the provider fetches on DreamHost.
- Risks or open questions:
  - Impact: a provider returns no titles from DreamHost.
  - Early warning / validation: the per-provider test shares.
  - Mitigation: record it in the media embed checklist; the title falls back to the link.
- Canonical components/API contracts touched: Quill (`public/quill.py`) for the tagging replies and test shares; post retraction for the test threads.

## Revised direction

- 2026-11-25, maintainer: "please make that a feature flag that's off by default", read as the first-line title rule. The rule is now behind `FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED`, off by default; the stop-and-ask on a non-zero count is replaced by the flag. Titles for a bare link from every provider stay on wherever media embeds are on. This revision does not approve the step.

*— Revised by Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Study snapshots

- 2026-11-25 UTC: **Approved Step 2** (revised version, "approved step 2"). Step 3 handoff **0.55**, reaffirmed after adding the first-line title rule and the DreamHost check. Stage work: 0.15, 0.10, 0.10, 0.10, 0.05, 0.05, 0.10 (not summed). Single exposed Claude rater.
- 2026-11-25 UTC revision: first-line titles behind a flag, off by default. Projected total **0.55**, unchanged; Stage 1 stays 0.15 (the flag replaces the stop-and-ask).

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

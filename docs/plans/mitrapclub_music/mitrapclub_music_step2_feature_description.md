> **Feature plan:** [Step 1](./mitrapclub_music_step1_solution_assessment.md) · [Step 2](./mitrapclub_music_step2_feature_description.md) · [Step 3](./mitrapclub_music_step3_development_plan.md) · [Step 4](./mitrapclub_music_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./mitrapclub_music_debug_log.md))

Effort study: `railspike-effort` / `v3/mitrapclub_music`; host ledger [mitrapclub_music.md](../effort_ledger/mitrapclub_music.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.55**

## Problem

mitrapclub has nowhere for people to share music. Tracks are ordinary threads mixed in with events and discussion, most are hidden from the default Board, and posting one means knowing the "Artist - Title" convention.

## Approved direction

Step 1 was approved on 2026-11-25 ("approved step 1"). No option was named, so this follows the recommended Option B: a Music page with a share box, with tracks chosen by tag. The open questions weren't answered, so the Step 1 defaults apply. Each is marked **(default)** below and can still be changed.

Concepts touched: [Tag browsing](../../concepts/tag_browsing.md) (unchanged; /tags/music lists the same threads). [Shared composer](../../concepts/shared_composer.md) (changed: a new placement with a preset tag, like the calendar day page). [Navigation slots](../../concepts/navigation_slots.md) (changed: Music becomes a destination that can hold a slot). Media embeds (unchanged and reused; no concept file). New concept: **Music section** (`docs/concepts/music_section.md`). Feedback: no matching item; related, out of scope: PF-20261111-F31.

## User stories

- As a visitor with a song in mind, I want to paste a link and share it, so that I don't have to learn how a track post is written.
- As a listener, I want a page of shared tracks I can play one after another without leaving it, so that I can find new music from the club.
- As a member, I want each track to stay an ordinary thread, so that I can like it, reply to it and link to it.
- As the maintainer, I want lectures and event videos kept off the page unless someone tags them, so that the page stays music.
- As a ZenMemes reader, I want nothing to change on that site, so that the section stays mitrapclub's.

## Core requirements

- A site-profile setting turns the section on. mitrapclub turns it on; with it off, /music/ is not found and the nav is unchanged.
- /music/ shows a share box at the top: a link, an optional title, an optional line of text, and Share. It makes an ordinary thread tagged #music **(default tag)**, posted as the reader's signed identity or as guest, the same as any other post. A link with no title gets its title fetched for every supported embed: YouTube (as today), Spotify, SoundCloud, TikTok and Bandcamp. If a fetch fails, the subject falls back to the link, and the sharer can type a title instead. (Revised 2026-11-25.)
- Below it, every thread tagged #music is listed newest first, 25 to a page, each as a compact row: title, who shared it, like and reply counts, a link to the thread, and the link's player, which starts collapsed and plays in place when expanded. Rows with no recognized music link still show, without a player.
- Music sits in the nav next to Gallery **(default name)**. Tracks still show on the Board, in Recent and on /tags/music **(default)**.
- Reading, paging and sharing work without JavaScript. The page is the same HTML for every reader and can be cached like the gallery.

## Delivery scope

- Work type: application change. That covers the profile setting and its validation, the /music/ route and page, a share-box placement of the shared composer, a compact track row, the nav item, styles in the mitrapclub themes, the concept file, and tests.
- Content task in the same cycle: tag the 12 existing track threads on mitrapclub.com #music by reply as "claude", after deploy **(default)**. The Michael Levin lecture is left out.
- Out of scope: autoplay and queues, background playback (PF-20261111-F31), charts or ratings, playlists, hiding tracks from the Board, the Board's Liked default, and the five missing images on mitrapclub.com.

## Completion boundary

- Normal entry: a visitor, signed out or a member, taps Music in mitrapclub's nav.
- End-to-end outcome: they paste a link and share. The page reloads with the new track at the top, and its player plays when expanded. The thread exists with #music and reads like any other thread.
- Recovery: a rejected or failed share keeps what was typed in the box, with the reason shown, the same as the Board's composer (drafts and Outbox included). A track that shouldn't be there leaves when the tag is removed or the thread is retracted. The profile setting turns the whole section off.
- Release: merged to main, deployed to mitrapclub.com with Deploy now, the 12 tracks tagged, and checked on the maintainer's phone.

## Known user or environment

The maintainer demos on an iPhone SE (3rd gen) in Safari, in the default Underground theme. Entry: mitrapclub.com, then Music. Failure to handle: the share box or a row wider than 380px, or an expanded player pushing the page sideways. Next action: the setting turns the section off while it's fixed. Evidence: 380px screenshots of /music/ in Underground and Underground Dark, collapsed and with one player open, then his own check after deploy.

## Risks

- **Several players on one page.** Each expanded player loads a provider frame. Impact: a slow page or noise from two tracks at once. Earliest validation: open three players on the local copy at 380px. Mitigation: players stay collapsed until tapped, as they are now; nothing autoplays.
- **Title fetches from more providers.** Spotify, SoundCloud and TikTok answer oEmbed like YouTube; Bandcamp has none, so its title comes from the page already fetched for its player ID. Impact: a provider changes or blocks the request, and titles stop appearing. Earliest validation: fetch one real link from each provider from the workspace and from DreamHost before building on it. Mitigation: a failed fetch leaves the subject empty, which shows the link, and is retried after the existing one-hour backoff; the thread is never blocked.
- **Guest spam with players attached.** The page invites guests to post. Impact: junk at the top of a public page. Earliest validation: the existing text rules on a guest share. Mitigation: the same rules, flags and retraction as the Board; nothing new is unlocked.
- **The share box breaks composer behaviour elsewhere.** Drafts, Outbox and signing are shared by every placement. Impact: lost drafts on other pages. Earliest validation: the existing composer browser tests plus the new placement. Mitigation: a separate draft scope for the share box, as the calendar day and pledge forms have.

## Shared component inventory

- `SiteProfileRegistry.php` (`homePage`, `gettingStarted`, `validate()`): extended with one more validated profile key for the section; mitrapclub sets it.
- `ReadModel/TagPage.php` and `Http/TagsPageController.php`: reused for which threads are listed, in what order and on which page. No second membership query.
- `Http/RecentPageController.php` (`gallery()`) and `templates/pages/gallery.php`: the precedent for a curated listing page with its own nav item and cache rules; the Music controller follows its shape.
- `templates/partials/composer.php`: reused through a new placement with a preset tag and its own draft scope, as `calendar_day.php` and the pledge form do. No new form.
- `View/MediaEmbedDetector.php` and `View/MediaEmbedRenderer.php`: reused for the player in each row and for deciding whether a link is music.
- Fetched titles: `View/YoutubeOembedTitleFetcher.php`, `Http/MediaEmbedPreviewController.php` (the preview beacon that back-fills an empty subject), `Support/ThreadTitle.php` (`bareMediaEmbedMatch()`) and `LocalWriteService::setThreadSubjectIfEmpty()`. Extended to every supported provider: one fetcher per provider on the YouTube fetcher's pattern, and the beacon and the title match widened beyond YouTube and Instagram. The empty-subject-only write path stays as it is. This applies on every site with media embeds, not just mitrapclub.
- `templates/partials/thread_row.php`: inspected first for the compact row. If it can take a player slot it is extended; otherwise a new partial, with the reason recorded in Step 3.
- `View/TemplateRenderer.php` (`navigationItems()`, `navSlotDefaults()`) and the `html[data-nav-slots~=…]` rules in `site.css`: extended with the Music destination, placed after Gallery.
- `theme-underground.css` and its dark variant: styles for the share box and rows, using existing theme variables.

## User flow

1. A visitor opens mitrapclub.com on a phone and taps Music.
2. They see the share box, then the shared tracks, newest first.
3. They tap a track's player and it plays in place; they tap another.
4. They paste a YouTube link into the box and tap Share.
5. The page shows their track at the top, titled from YouTube.
6. They open the track's thread, where someone can like it or reply.

## Success criteria

- On mitrapclub, /music/ lists exactly the threads /tags/music lists, in the same order.
- An untitled share from each of YouTube, Spotify, SoundCloud, TikTok and Bandcamp ends up with that provider's title; a share with a typed title keeps it.
- Signed-out and member shares both work, with and without JavaScript.
- At 380px in Underground and Underground Dark, nothing scrolls sideways, with or without a player open.
- On ZenMemes, chouse and qdb, /music/ returns 404 and their nav markup is unchanged.
- After deploy, the 12 existing tracks are on mitrapclub's Music page and the Levin lecture is not.

## Revised direction

- 2026-11-25, maintainer, after Step 2 was approved: "it's more than youtube, right? whatever embeds are supported?" Players already cover every supported embed. Titles were fetched only for YouTube, so title fetching now covers all supported providers (core requirements, risks, inventory and success criteria above). Since this widens approved scope, it needs Step 2 approval again.

*— Revised by Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Enhancement requests

- Carried from Step 1, still pending and out of this cycle: a per-site default Board view; restoring five images on mitrapclub.com.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Study snapshots and approval

- 2026-11-25 UTC: **Approved Step 1** by the maintainer ("approved step 1"). No option named; Option B taken as recommended, with the Step 1 defaults for the open questions.
- Step 2 handoff: **0.45**, reaffirmed. Added the existing-track tagging as a content task; it is small and doesn't change the total. Single exposed Claude rater.
- 2026-11-25 UTC: **Approved Step 2** ("approved step 2"), then revised the same minute to fetch titles for every supported embed. Revised projected total **0.55** (+0.10 for four title fetchers and the widened beacon). Awaiting re-approval.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

> **Feature plan:** [Step 1](./mitrapclub_music_step1_solution_assessment.md) · [Step 2](./mitrapclub_music_step2_feature_description.md) · [Step 3](./mitrapclub_music_step3_development_plan.md) · [Step 4](./mitrapclub_music_step4_implementation_summary.md)

Cycle: `v3/mitrapclub_music`. New cycle.

Debug mode: enabled ([debug log](./mitrapclub_music_debug_log.md))

Effort study: `railspike-effort` / `v3/mitrapclub_music`; host ledger [mitrapclub_music.md](../effort_ledger/mitrapclub_music.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.45

## Original Query

Ah, thank you for reminding me. We need a section where people just share music, please write Step 1.

## Understood Intent

This came up after we found that mitrapclub.com already has every #rapclub thread from ZenMemes, but its Board opens on Liked, which hides most of them. About a quarter of mitrapclub's 54 threads are tracks: an "Artist - Title" subject with a YouTube or Spotify link and no other text, posted under the [#rapclub track convention](https://zenmemes.com/threads/thread-20261120233312-e87f5511). Those tracks are mixed in with events, lectures and discussion, and nothing on the site invites a visitor to drop a song.

The goal is one place on mitrapclub, linked from the nav, where people share a track with as little effort as possible and others can browse and play what has been shared. It covers sharing and listening, not ratings, charts or a full music player.

## Problem

mitrapclub has no place for sharing music: tracks are ordinary threads mixed with everything else, hidden from the default Board unless someone likes them, and posting one means knowing a convention.

Concepts touched: [Tag browsing](../../concepts/tag_browsing.md) (A: unchanged, gains a nav link; B, C: unchanged). [Shared composer](../../concepts/shared_composer.md) (B, C: a link-first variant on the new page). Media embeds ([checklist](../media_embed_support_checklist.md); no concept file): reused as is for the players and fetched titles. [Navigation slots](../../concepts/navigation_slots.md) (A–D: one new destination). B and C add a new concept, Music section. Feedback: no matching item in `docs/feedback/items/`. Related but out of scope: [PF-20261111-F31](../../feedback/items/PF-20261111-F31.txt), background audio playback.

## Shared by every option

- It is turned on per site with a site-profile setting, the way `homePage` and `gettingStarted` are. mitrapclub turns it on; ZenMemes, chouse and qdb are unchanged.
- A shared track is an ordinary thread. It keeps likes, replies, tags and its own page, and it still shows on the Board and in Recent.
- Players are the existing media embeds: YouTube, Spotify, SoundCloud, Bandcamp and TikTok links play inline when expanded. They do not autoplay, and nothing plays one track after another.
- Guests can share, as they can post anywhere else ([keep participation open to people without a key](../../../PRINCIPLES.md)).
- Reading and sharing work without JavaScript.

## Option A — A music tag and a nav link

**Estimate: 0.10**
Main driver: a nav item that opens a fixed tag page, plus tagging the existing tracks. Uncertainty: low.

Tracks carry a tag (for example #music). A Music item in mitrapclub's nav opens that tag's page, which already lists tagged threads with their embed cards. The existing tracks are tagged by reply, the way #rapclub was spread.

- Pro: almost no new code. The tag page, its pagination and offline copies already work.
- Pro: easy to undo, and it doesn't rule out B later.
- Con: sharing is unchanged. A visitor still has to start a thread, know the subject convention and remember the tag.
- Con: the tag page is a list of thread cards with excerpts and reply counts, which reads like a forum list, not a place to listen.

## Option B — A Music page with a share box (recommended)

**Estimate: 0.45**
Main driver: a new page with its own list query, a link-first composer that adds the tag, a compact track row reusing the embed renderer, and checks across themes and phone width. Uncertainty: medium; the subject when a link has no fetched title.

A new /music/ page, linked from the nav. At the top is one box: paste a link, optionally add a line about it, and share. That makes an ordinary thread with the tag added, and the subject comes from the link's fetched title when there is one (YouTube today). Under the box, the shared tracks are listed newest first as compact rows: title, who shared it, like count, reply count and the player, ready to expand in place. Membership is the tag, the same as Option A, so the existing tracks join by being tagged, and a lecture or event video stays out unless someone tags it.

- Pro: sharing becomes pasting one link, with no convention or tag to remember.
- Pro: a page made for listening: several players on one screen, each one tap from playing.
- Pro: membership is explicit and visible on each thread, so it is easy to understand and fix.
- Con: a second composer and a second list to maintain next to the Board and the tag pages.
- Con: some links (Spotify, SoundCloud, Bandcamp) have no fetched title, so the sharer has to type one, or the subject falls back to the link.

## Option C — Option B, collected by link instead of tag

**Estimate: 0.55**
Main driver: Option B plus a derived "has a music link" field in the read model and a way to take a thread back out. Uncertainty: medium.

As Option B, but any thread whose opening post is mainly one music link joins the page automatically, with no tag. A tag such as #notmusic takes a thread out.

- Pro: every track already on the site joins at once, and a track posted through the Board joins too.
- Con: YouTube is also used for lectures and event videos (the Michael Levin talk is one of the 13 link threads on mitrapclub today), so the page needs regular removal by hand.
- Con: a rule nobody can see on the thread is harder to understand and trust than a tag.
- Con: it depends on the still-open #notX label task.

## Option D — One standing "share a track" thread

**Estimate: 0.10**
Main driver: a pinned thread and a nav item that opens it. Uncertainty: low.

A pinned thread where every reply is a track, like the agent cypher threads. Music in the nav opens it, and the reply box is already the share box.

- Pro: the fastest way to share that exists today: one reply with a link.
- Pro: no new page at all.
- Con: talk about a track lands in the same stream as the tracks themselves.
- Con: a track can't have its own thread, title or tags, and the thread grows into one long page.

## Recommendation

Option B. It is the only option that makes sharing as light as the request ("just share music") and gives listeners a page built for playing, while keeping each track an ordinary thread that is easy to tag, like and discuss. Membership by tag keeps lectures and events out without moderation. Option A is a reasonable first step if this should ship today, and B can be built on top of it without rework. C trades a visible rule for a hidden one on a site where YouTube isn't only music. D works but stops working once there are a few dozen tracks.

Vertical slice: on an iPhone SE, open mitrapclub.com signed out. Tap Music in the nav: the page shows the share box and the existing tracks (tagged during the cycle). Paste a YouTube link and share: the new track is at the top, titled from YouTube, posted as guest. Tap its player: it plays in place. Open its thread: it is an ordinary thread with the tag and can be replied to. On ZenMemes, /music/ is not found and the nav is unchanged.

## Open questions for the maintainer

- **Tag name.** Default: #music. Alternative: #track. On mitrapclub #rapclub doesn't separate anything, since everything there is rap.
- **Name in the nav.** Default: Music, next to Gallery. Alternatives: Tracks, Listen, Cypher.
- **Untitled links.** Default: if no title is fetched, the share box asks for "Artist - Title" and fills nothing in. Alternative: allow an empty title and show the link.
- **Board.** Default: tracks still show on the Board. Alternative: hide tagged tracks from the Board, so it stays for discussion and events.
- **Existing tracks.** Default: during the cycle, tag the 12 track threads already on mitrapclub (not the lecture), as replies from "claude".

## Enhancement requests

- The Board opening on Liked hides most mitrapclub threads (raised just before this request). Not in this cycle: it is a Board default for every site, and a Music page reduces the need for it on mitrapclub. Open to a separate one-shot (a per-site default view) if wanted.
- Five images missing on mitrapclub.com (the #rapclub photo, Harvard Radcliffe Orchestra, the Underground Showcase flyer, BlackPanthaa, Dom McLennon) are an operator task on the server, not part of this cycle.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Study snapshots and approval

- 2026-11-25 UTC, intake after inspecting `SiteProfileRegistry.php` (`homePage`, `gettingStarted`), `BoardPageController.php` (Liked default), the [tag browsing](../../concepts/tag_browsing.md) and [navigation slots](../../concepts/navigation_slots.md) concepts, the [media embed checklist](../media_embed_support_checklist.md), the [gallery home one-shot](../mitrapclub_gallery_home/mitrapclub_gallery_home_one_shot.md), and live mitrapclub.com content (13 of 54 threads open with a music link; 12 are tracks): 0.45 projected total for Option B. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `b926c9a9f`.
- Step 1 handoff: 0.45 for Option B (A 0.10, C 0.55, D 0.10; alternatives, not summed). No implementation.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

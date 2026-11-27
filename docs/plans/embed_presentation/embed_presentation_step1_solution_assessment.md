> **Feature plan:** [Step 1](./embed_presentation_step1_solution_assessment.md) · [Step 2](./embed_presentation_step2_feature_description.md) · [Step 3](./embed_presentation_step3_development_plan.md) · [Step 4](./embed_presentation_step4_implementation_summary.md)

Cycle: `v3/embed_presentation`. New cycle.

Debug mode: enabled ([debug log](./embed_presentation_debug_log.md))

Effort study: `railspike-effort` / `v3/embed_presentation`; host ledger [embed_presentation.md](../effort_ledger/embed_presentation.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.45

## Original Query

Let us improve the looks of how embeds are presented; these look very busy and unusable. Can you please write a Step 1 and also make a mockup of how it can look. Also, let's talk about whether they should all auto-expand by default or?

(Sent with two screenshots of mitrapclub.com: the /music/ page with one YouTube player open, and a thread page for a Spotify track.)

## Understood Intent

Each embed today renders as three stacked pieces: an "Open on YouTube" link, a disclosure row "▶ ▶ Watch on YouTube", and a hidden player. The doubled arrow is the browser's disclosure marker next to the summary's own glyph. The link and the row name the same provider twice. Playing takes two taps: one to open the row, one on the player's own button. On /music/ each row repeats this under a title that already names the track.

The goal is an embed that reads as one calm object: what it is, one obvious way to play it, and a small way out to the provider. The question of default expansion is part of this cycle.

## Problem

An embed is three loosely styled pieces that repeat each other, so posts and music rows look cluttered and playing a track takes two taps.

Concepts touched: [Music section](../../concepts/music_section.md) (B: rows gain a poster thumbnail). [Post presentation](../../concepts/post_presentation.md) (A–C: embed look). [No-script presentation](../../concepts/no_script_presentation.md) (A–C: the disclosure still works without script). [Image serving](../../concepts/image_serving.md) (B: only if posters are served from our server). Media embeds: no concept file ([checklist](../media_embed_support_checklist.md)). Feedback: no matching item in `docs/feedback/items/`. Related but out of scope: [PF-20261104-F60](../../feedback/items/PF-20261104-F60.txt), thumbnails for attached images in lists.

## Shared by every option

- The renderer in `MediaEmbedRenderer.php` and its flags stay; `FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED` still decides whether players exist at all.
- The provider link stays one tap away, as a small "YouTube ↗" style link.
- Without JavaScript, the embed still opens its player through the disclosure, as today.

## Option A — Tidy the existing pieces

**Estimate: 0.15**
Main driver: markup and CSS for one bar. Uncertainty: low.

Merge the link and the disclosure into one bar: a round play control, the provider name, and a small ↗ link at the end. Drop the doubled arrow. The player still opens under the bar, and playback still needs a second tap inside it.

- Pros: smallest change; removes the repetition and the doubled arrow; no new data or requests.
- Cons: still two taps to play; nothing shows what the video is until it opens; every embed looks the same grey bar.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Option B — One player bar plus posters, one tap to play

**Estimate: 0.45**
Main driver: a poster for YouTube embeds in two layouts and a tap that loads and starts the player. Uncertainty: medium (poster source, phone check).

Everything in Option A, plus three changes. First, a YouTube embed shows its poster image with a play button over it; the thumbnail is already fetched into the media embed preview cache. On a post's own page the poster spans the column at 16:9. On /music/ and other lists the poster sits small in the row's first column, the way `thread-row--thumb` shows attached images. Second, one tap on the poster or the bar swaps in the player and starts playback, so play is one tap. Third, starting a player closes any other open player on the page, so two tracks never play over each other. Spotify, SoundCloud, Bandcamp and TikTok have no poster yet and use the bar alone.

- Pros: one tap to play; a list of tracks becomes scannable by picture; the page loads no third-party player until someone asks for it.
- Cons: posters add one image per YouTube embed; the poster source needs a decision (see the open question below); a list layout and a post layout to check on the phone.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Option C — Players open by default

**Estimate: 0.10**
Main driver: opening the disclosure by default and loading frames on page load. Uncertainty: low.

Render every player already open, so a post or row shows the real YouTube or Spotify frame immediately.

- Pros: zero taps to see the player; tiny change.
- Cons: each frame loads the provider's scripts and cookies on page view, before the visitor chooses to play; /music/ with 12 tracks loads 12 frames, slow on an iPhone SE; a Spotify frame is 352px tall, so the list becomes mostly players; the clutter gets bigger, not smaller.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## On auto-expanding

Expanded should mean "looks expanded", not "loaded". Option B gets the expanded look for the price of one image. On a post's own page the large poster shows by default; in lists the small poster shows by default. The real player loads only on a tap, then plays at once. A per-viewer "always load players" preference could come later as its own cycle if anyone asks.

## Open question

Where should posters come from? Linking the provider's image directly is simplest, but each page view then contacts the provider's image server. Copying each poster to our server once keeps page views private and fits [image serving](../../concepts/image_serving.md), at about 0.10 more. The 0.45 estimate assumes the copy.

## Recommendation

**Option B.** It removes the repetition, makes play one tap, and answers the auto-expand question without loading third-party players on page view. Option A is the fallback if posters wait for a later cycle. Option C makes the problem worse.

**Vertical-slice viability:** Yes. Entry is any viewer on /music/ or a post page with a supported link under the existing embed flags. The outcome is one bar or poster per embed that plays in one tap. Recovery: turning off the inline player flag returns to plain link cards, as today.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

Waiting for "Approved Step 1" before drafting Step 2.

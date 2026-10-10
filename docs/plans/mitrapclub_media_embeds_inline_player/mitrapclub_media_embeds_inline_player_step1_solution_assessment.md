> **Feature plan:** [Step 1](./mitrapclub_media_embeds_inline_player_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_inline_player_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_inline_player_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_inline_player_step4_implementation_summary.md)

## Original Query

When I post a YouTube link, I see this: "▶ YouTube https://www.youtube.com/watch?v=..." but there is no embedded video. Is this by design? [After confirming it was by design (Step 1's deferred Option A) and being asked whether to keep the card or add an inline player, the user chose to add an inline player.] The embed should be behind an expando and not open on page load.

## Understood Intent

- This is **Cycle 6**, a direct follow-on to the just-shipped Cycle 5 (`mitrapclub_media_embeds_*`, link-preview card). Cycle 5's own Step 1 explicitly flagged this: "Option A is worth revisiting later if a card genuinely isn't enough." The user has now decided it isn't enough.
- "The embed should be behind an expando and not open on page load" is read as a hard requirement on whatever option is chosen here: the card/link stays as the default visible state; the actual player (iframe or provider markup) only loads once a viewer expands a disclosure (this codebase already uses `<details>/<summary>` for similarly deferred content, e.g. `post-analysis`, `codex-handoff-preview`). This bounds the new network/rendering cost to viewers who opt in by expanding, regardless of which option below is picked.
- Checked the current code before writing this: there is no `Content-Security-Policy` or `frame-src` infrastructure anywhere in this app, and no iframe anywhere in post rendering — Cycle 5 deliberately kept it that way. Any inline-player option is the first iframe (or first third-party script) this app's post rendering would ever contain.

## Problem Statement

Cycle 5's link-preview card confirms a post contains a YouTube/Instagram link but never plays it, so a viewer must always leave the thread to watch the club's own video content.

## Solution Options

- **Option A: oEmbed fetch, both providers.** On expand, the server (or client, calling through the server) fetches the provider's oEmbed endpoint and renders the returned embed markup inside the expando.
  - Pros: uses each provider's own current, officially-sanctioned embed shape for both YouTube and Instagram; adapts automatically if a provider changes its embed format.
  - Cons: a real outbound network dependency (new failure mode, rate limits, availability) on the expand path; the returned markup still has to be sandboxed before it touches the page, same trust question Cycle 5 avoided entirely; the most implementation and operational weight of the three options for a marginal gain over Option B on the provider Option B already covers cleanly (YouTube).
- **Option B: Direct sandboxed iframe via YouTube's own keyless embed URL; Instagram keeps Cycle 5's card.** `youtube.com/embed/{video_id}` is a stable, provider-published pattern that needs no fetch, no API key, and no oEmbed round-trip — the iframe `src` is built directly from the video ID the existing detector already isolates. Instagram has no equivalent plain, keyless iframe pattern (its only official embed paths are its oEmbed API or a JS widget script), so Instagram links stay exactly as Cycle 5 left them.
  - Pros: zero new outbound network dependency anywhere in the request path — matches the zero-outbound-call posture Cycle 5 established; the only new security surface is one well-known iframe `src` origin (`youtube.com/embed/`) with standard `sandbox`/`referrerpolicy`/`loading="lazy"` attributes, not open-ended provider-returned markup; ships the half of the complaint (YouTube) that's cheap and safe to do well.
  - Cons: asymmetric — Instagram links still never play inline, only YouTube's do; a visitor could reasonably ask "why does one embed and the other doesn't."
- **Option C: Add Instagram's JS embed widget alongside YouTube's iframe.** Loads Instagram's `embed.js` and renders their sanctioned `<blockquote>` markup so both providers get a true inline embed.
  - Pros: symmetric — both providers get a real inline embed.
  - Cons: executing a third-party-hosted script inside the page is a materially larger security surface than either A or B (a script can do far more than an iframe or a fetch result ever could); this is the single biggest trust-boundary change of any option considered across both cycles.
- **Option D: Fetch-and-cache a thumbnail/title preview card, symmetric for both providers, no playback.** On first view, fetch each provider's oEmbed metadata (title + thumbnail image URL only, not embed markup), cache it, and render a richer but still non-playing card — same shape and visual weight for YouTube and Instagram alike.
  - Pros: the safest option considered in either cycle — no iframe, no third-party script, just an image reference; symmetric, so there's no "why does one embed and the other doesn't" question; the fetch is one-time and cacheable rather than a per-view dependency.
  - Cons: doesn't actually answer the original question — a nicer thumbnail still isn't an embedded video, so it doesn't resolve the "no embedded video" complaint by itself; still introduces the oEmbed fetch/cache/timeout design work Cycle 5 explicitly deferred, just scoped to metadata instead of markup.

## Recommendation

**Option B, paired with Option D for Instagram.** B is the only option that actually answers the original complaint (YouTube plays inline, behind the expando, with no new network dependency). D alone doesn't deliver inline playback, so it's not a substitute for B — but it directly answers this question's follow-up (symmetry) for the provider B can't play inline: instead of Instagram staying a bare text link forever, its card gets the same cached thumbnail/title richness, so both providers look like equally finished, intentional cards even though only one of them plays. Option A's per-view fetch dependency and Option C's third-party script remain larger costs than this combination for no further gain.

The expando requirement applies regardless of option: the card/link is what renders by default; the iframe's `src` is only set (and the embed only loads) once a viewer expands the `<details>`, so page load cost and any embed failure are both scoped to viewers who opt in.

**Vertical-slice viability:** Yes. Entry is any `mitrapclub` member posting a thread/reply containing a YouTube or Instagram URL, with the existing media-embeds flag enabled; outcome is a YouTube post's card gaining a collapsed expando that plays the video inline once opened, and an Instagram post's card gaining a cached thumbnail/title even though it still only links out; recovery is graceful either way (a YouTube iframe that fails to load, is blocked, or isn't a recognized video ID leaves the card visible with nothing broken; a failed or slow thumbnail fetch for either provider just leaves today's plain card, never a blocked post or broken page); no regression to any site with the existing flag off, who keep Cycle 5's card exactly as shipped.

Waiting for "Approved Step 1" before drafting Step 2.

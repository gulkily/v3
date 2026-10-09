> **Feature plan:** [Step 1](./mitrapclub_media_embeds_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_step4_implementation_summary.md)

## Original Query

Please write Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for the MIT Rap Club website. Be mindful that I'll start a new chat before going to Step 2.

## Understood Intent

- **Context for a fresh chat with no history of prior work:** `mitrapclub` is not a standalone website — it's an existing site profile (`site_id`) inside this repo's shared multi-site forum application (`ForumRewrite` namespace), selected via the `FORUM_SITE_ID` env var, the same pattern as the `zenmemes`/`chouse`/`qdb` profiles already in `src/ForumRewrite/SiteProfileRegistry.php`. It already has its own theme, editorial copy, nav, and favicon/icon mechanism, built and shipped across four prior FDP cycles tracked in `docs/plans/mitrapclub_theme_and_features_checklist.md`:
  1. Branding fixes (done)
  2. De-genericize about-page copy (done)
  3. Structural identity — nav trim, theme-freedom decision, favicon mechanism (done); a deferred creative half (hero/banner, display typeface) remains unscheduled pending dedicated design attention
  4. Events experience — optional structured date/location/link on a thread (done)
- "The MIT Rap Club website" (the request's own framing, matching the very first request that started this whole effort) is read as **Cycle 5 ("Media embeds")** from that checklist — the next fully-unstarted, explicitly Step-1-flagged item, picked over Cycle 3's deferred creative half because this one is a technical-approach question (Step 1's actual purpose), not a visual-design question needing dedicated creative attention and probably a visual asset to work from.
- Checked the current code before writing this: there is **no existing media-embed, auto-link, or oEmbed infrastructure anywhere in this codebase**. Post bodies render today via `nl2br(htmlspecialchars(...))` only — plain escaped text, no raw HTML, no autolinking. Any embed mechanism starts from zero, which is exactly the kind of real technical fork Step 1 exists to resolve before Step 2 locks in requirements.

## Problem Statement

`mitrapclub`'s actual content (and the club's real YouTube/Instagram presence) is video/photo-heavy, but threads can only ever contain plain escaped text — a YouTube clip or Instagram post linked in a thread shows as a bare URL, never a preview or player.

## Solution Options

- **Option A: oEmbed fetch.** When a post body contains a YouTube/Instagram URL, the server fetches that provider's oEmbed endpoint and stores/renders the returned embed HTML (or a derived player) in a sandboxed, server-controlled way.
  - Pros: richest result — an actual inline player/rich card, matching what cypherposium.com-style rap-cypher sites show.
  - Cons: adds an outbound network call during post creation (or on render, cached) to a third-party endpoint — new failure mode, rate-limit/availability dependency, and the first real external-network dependency this app's posting path would have; needs careful sandboxing before any provider-returned HTML touches the page.
- **Option B: Link preview card (recommended).** Detect a known provider URL in a post body and render a lightweight, locally-rendered card (provider icon/label + the URL itself, maybe an oEmbed-fetched thumbnail/title cached asynchronously) instead of embedding the provider's own player/markup.
  - Pros: no third-party markup ever reaches the page (same trust boundary this app already treats as a hard line for post bodies); failure mode is graceful — worst case, it's just a link; much closer in scope/risk to this session's `event_block.php` work (detect a pattern in trusted-shape data, render a small server-controlled block) than to a new embed subsystem.
  - Cons: not a true inline player — a visitor still clicks through to watch/view; thumbnail fetching (if included) still needs *some* outbound call, though it can be deferred/cached/best-effort rather than blocking.
- **Option C: Raw iframe embed.** Detect a provider URL and emit the provider's standard `<iframe>` embed snippet directly.
  - Pros: simplest to implement; well-understood provider-sanctioned embed pattern.
  - Cons: the one approach that puts provider-controlled content directly into the page frame; needs real sandboxing/CSP work before it's safe, on an app that currently renders zero raw HTML inside any post — meaningfully larger security surface than A or B for the same visible outcome as A.

## Recommendation

**Option B.** It gets most of the visible benefit (a club's video/photo content reads as more than a bare link) without crossing this app's existing "post bodies never render raw/third-party HTML" line, and it's the closest in shape to work already proven out this session (a detected pattern → a small, trusted, server-rendered block). Option A is worth revisiting later if a card genuinely isn't enough. Option C's security surface isn't worth it for the marginal gain over B.

**Vertical-slice viability:** Yes. Entry is any `mitrapclub` member posting a thread/reply containing a YouTube or Instagram URL; outcome is that post rendering with a visible media card instead of a bare link, on the board and the thread page; recovery is graceful (an unrecognized or unreachable URL just renders as a plain link, never a broken page); no regression to `zenmemes`/`chouse`/`qdb`, who keep plain-link rendering unless they opt in.

Waiting for "Approved Step 1" before drafting Step 2.

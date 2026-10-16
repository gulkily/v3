> **Feature plan:** [Step 1](./music_media_embeds_step1_solution_assessment.md) · [Step 2](./music_media_embeds_step2_feature_description.md) · [Step 3](./music_media_embeds_step3_development_plan.md) · [Step 4](./music_media_embeds_step4_implementation_summary.md)

Debug mode: enabled

## Problem

SoundCloud and Bandcamp links lack the in-thread listening experience already available for Spotify. Members sharing their releases must send readers outside the discussion.

## User stories

- As a member, I want to paste a release link so that readers can listen within the discussion.
- As a reader, I want control over loading and a visible source link so that I can reach music when a player fails.

## Core requirements

- Recognize public HTTP/HTTPS SoundCloud `soundcloud.com/USER/TRACK` and `/USER/sets/PLAYLIST` URLs, optionally using `www.`, and Bandcamp `ARTIST.bandcamp.com/track/SLUG` and `/album/SLUG` URLs. Accept an optional trailing slash; require complete, nonempty path segments and a single artist subdomain. Examples of intended forms: `soundcloud.com/forss/flickermood` and `artist.bandcamp.com/album/release` (the latter is illustrative). Reject credentials, explicit ports, extra path segments, reserved service routes/subdomains, profiles, `/discover`, `/search`, `on.soundcloud.com` redirects, Bandcamp custom domains, and private/tokenized links. Exact reserved-name and slug validation rules must be established before Step 3 approval; URL shape alone does not prove content exists.
- With both existing embed flags enabled, offer reader-expanded players without autoplay, retaining a source link outside the collapsed player. With only the main flag enabled, show provider cards; with it disabled, retain ordinary body rendering. Unsupported URLs must not trigger provider requests.
- Preserve stored text, meaningful source-link parameters/fragments, existing provider behavior, and existing subjects. New providers use normal body-derived thread titles; fetched titles and richer previews are excluded.
- Any necessary discovery must use validated destinations, bounded network work, caching, and failure fallback without blocking post rendering or trusting arbitrary provider HTML. An unavailable player or failed lookup must leave a usable source link and readable post.
- Reuse shared post presentation and keyboard controls; accommodate track and collection players at approximately 360px and desktop widths without horizontal overflow. Load player documents only after expansion.

## Delivery scope

**Work type:** Application change, delivered in provider-sized slices with SoundCloud first. Bandcamp playback requires demonstrated URL-to-player discovery; otherwise return for approval of cards-only support or deferral. Exclude share redirects, private content, profiles, custom domains, Spotify expansion, other services, and general URL cleanup. Production enablement remains separate.

## Completion boundary

**Entry:** A member submits a supported URL in a thread or reply. **Outcome:** Readers expand the corresponding player. **Recovery:** Lookup/playback failure preserves the source link. **Release:** Each provider independently passes automated and real-browser checks, with supported boundaries and limitations recorded in the checklist. Do not mark unresolved Bandcamp support complete.

## Risks

- **Bandcamp discovery:** Manual embed documentation does not establish automated discovery. Before Step 3 approval, validate public track/album lookup, credentials, and repeatability; unresolved discovery blocks Bandcamp-dependent planning and requires a scope decision.
- **Provider compatibility:** Restrictions or collection sizing can break listening. Validate public SoundCloud tracks/sets and Bandcamp tracks/albums early, including player permissions; retain source links and block affected playback delivery on unresolved incompatibility.
- **URL/discovery ambiguity:** Reserved routes or private links may resemble releases; fetching can add unsafe destinations and latency. Before Step 3 approval, settle the acceptance matrix and lookup contract; constrain destinations, work, and fallback behavior.
- **Shared-view/title regressions:** New recognition can affect layout or title eligibility. Inspect canonical consumers before Step 3; keep title fetching limited to existing eligible providers and verify ordinary new-provider titles.

## Shared component inventory

- Extend `MediaEmbedDetector`, `MediaEmbedRenderer`, shared player script/styles, and existing embed flags.
- Reuse `TemplateRenderer` across board cards, thread roots/replies, Forte board/reply panels, QDB quote/news views, and reply-composer context, including regular/standalone script loading.
- Preserve `ThreadTitle` and its application, board/Forte, summary API, and template consumers. Existing title fetchers/beacons remain limited to their current providers. If discovery is needed, assess reuse of the preview cache/controller infrastructure before adding a provider-specific adapter; no parallel rendering system.

## Simple user flow

1. Submit a supported release URL in a thread or reply.
2. Expand the music player when ready to listen.
3. Follow the visible source link if listening is unavailable.

## Success criteria

- Accepted/rejected URL examples, flag combinations, title fallback, source links, deferred player loading, and lookup failure behavior pass meaningful automated checks.
- Real track and collection examples pass board/thread/Forte browser checks at mobile and desktop widths, with keyboard use and shipped-theme layout verified; other shared consumers pass regression checks.
- Record provider-specific evidence and exact coverage in the checklist before completion; retain all existing provider regressions passing.

Status: Step 1 approved; Step 2 drafted for review. Implementation has not started.

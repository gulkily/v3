> **Feature plan:** [Step 1](./top_five_media_embeds_step1_solution_assessment.md) · [Step 2](./top_five_media_embeds_step2_feature_description.md) · [Step 3](./top_five_media_embeds_step3_development_plan.md) · [Step 4](./top_five_media_embeds_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Members cannot reliably share the five prioritized media URL types within discussions. Spotify, TikTok, and YouTube Shorts lack embeds; existing YouTube and Instagram behavior needs live and browser verification.

## User stories

- As a member, I want to paste a supported media link so that readers can watch, listen, or preview it within the discussion.
- As a reader, I want control over player loading and an accessible source link so that unavailable embeds do not prevent access to the original content.

## Core requirements

- Recognize direct HTTP/HTTPS URLs for YouTube `youtube.com/watch?v=ID`, `youtu.be/ID`, and `youtube.com/shorts/ID`; Instagram `instagram.com/p/ID/` and `/reel/ID/`; Spotify `open.spotify.com/track/ID`, `/album/ID`, and `/playlist/ID`; TikTok `tiktok.com/@USER/video/ID`. Accept optional `www.` on `youtube.com`, `instagram.com`, and `tiktok.com`; validate hosts and complete identifiers, preserving meaningful URL parameters in source links.
- With both existing embed flags enabled, offer expandable YouTube/Shorts, Spotify, and TikTok players whose content loads only after expansion. Keep a visible source link outside every player. Instagram retains cached linked previews and a plain-card fallback; metadata fetching must not block page rendering.
- With only the main embed flag enabled, render provider link cards; with it disabled, preserve existing body rendering. Retain current flag defaults and stored post text. Unsupported links must not trigger provider fetches or players.
- Preserve existing subjects and YouTube/Instagram title fetching, including Shorts. Bare Spotify/TikTok threads retain the normal body-derived title fallback and must not enter the fetched-title “Untitled” state or request unsupported title fetching.
- Apply behavior consistently wherever shared post bodies render. Controls must work by keyboard; players must fit desktop and approximately 360px layouts without horizontal overflow, using proportions appropriate to their content.

## Delivery scope

**Work type:** Application change. Extend existing embeds for the five ranked types and document verified coverage. Exclude Instagram playback, new Spotify/TikTok metadata fetching, redirecting share links, additional providers, YouTube live/mobile/embed paths, playlist-only YouTube links, timestamp playback, and a general preview framework. Production enablement is separate from implementation.

## Completion boundary

**Entry:** A member posts a supported URL in a thread or reply. **Outcome:** Readers receive the corresponding player or Instagram preview. **Recovery:** A source link remains usable if playback or metadata fails; the surrounding post remains readable. **Release:** Automated regression checks and real-provider/browser checks pass, with unavailable-content behavior recorded. Provider restrictions may limit playback; successful recognition alone does not prove playback compatibility.

## Risks

- **Provider compatibility:** Permissions, login requirements, or availability can prevent playback. Validate representative Spotify/TikTok content early; use provider-compatible players and retain source links. Unresolved integration failures block release of the affected support.
- **Instagram metadata:** Live pages may not yield previews. Check public posts/reels and cold-cache reloads early; preserve bounded fetching and plain-card fallback, recording observed limitations.
- **Title regression:** Broader detection could strand Spotify/TikTok threads as “Untitled.” Check bare-URL threads before wiring new providers; keep title eligibility separate from embed recognition.
- **Layout and view drift:** Shared styles or missing scripts could break a presentation. Inspect all shared render consumers before Step 3; reuse canonical components and verify mobile, desktop, and shipped themes.

## Shared component inventory

- Extend `MediaEmbedDetector`, `MediaEmbedRenderer`, existing player controls, styles, and feature flags; no parallel provider-rendering system.
- Reuse `TemplateRenderer` for board cards, thread roots, replies, Forte board/reply panels, QDB quote/news views, and reply-composer context; retain script inclusion in regular and standalone layouts.
- Extend title eligibility in `ThreadTitle`; preserve its application, board, Forte board, Forte content-summary API, and template consumers. Reuse existing preview cache/controller/fetchers and board/root title beacons for YouTube/Instagram only.

## Simple user flow

1. Paste a supported direct URL into a thread or reply and submit.
2. Read the provider card or Instagram preview; expand a player when desired.
3. Use the source link if content is unavailable; reload to see asynchronously fetched existing-provider metadata.

## Success criteria

- Every URL family above passes accepted/rejected URL, flag, source-link, and applicable title tests.
- Real examples of all five types are checked across board/thread/Forte views, including mobile layout; other shared consumers pass regression checks.
- Player requests begin only after expansion; failed metadata does not block rendering; existing subjects remain unchanged; Spotify/TikTok bare-URL threads have usable titles.

Status: Step 1 approved; Step 2 drafted for review. Implementation has not started.

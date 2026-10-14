> **Feature plan:** [Step 1](./top_five_media_embeds_step1_solution_assessment.md) · [Step 2](./top_five_media_embeds_step2_feature_description.md) · [Step 3](./top_five_media_embeds_step3_development_plan.md) · [Step 4](./top_five_media_embeds_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Entry/outcome: Posting any Step 2 URL produces the approved card/player/preview across shared views under existing flags.
- Recovery: Source links survive failures; posts/subjects remain intact; Spotify/TikTok retain body-derived titles.
- Release: Automated regressions and real-provider/browser checks pass; record restrictions and unresolved checks. Approved exclusions/defaults remain; no schema or production flag changes.

## Key Risks

- **High risk: Broken playback.** Validate provider permissions and real content in Stage 1; unresolved compatibility blocks dependent work; retain source links.
- **High risk: Stranded titles.** Recognition currently triggers title fetching for every provider. Restrict eligibility before Spotify/TikTok; test absence of beacons/writes.
- **High risk: Layout/loading drift.** Shared CSS assumes 16:9. Validate provider proportions and actual browser requests, not script-text assertions alone.
- Instagram metadata may fail live. Check early; retain bounded fetching/plain-card recovery and record provider limitations.

## Stage 1

- Goal: Establish compatibility and baseline.
- Dependencies: Step 3 approval, Step 4 branch/planning commit.
- Expected changes: Record temporary browser probes and baseline in summary; consult official [Spotify](https://developer.spotify.com/documentation/embeds/tutorials/creating-an-embed) and [TikTok](https://developers.tiktok.com/docs/en/embed-player) guidance.
- Verification approach: Real Spotify track/album/playlist, TikTok, YouTube, Instagram post/reel; browser/network availability.
- Risks or open questions: Resolve permissions before implementation; mocks cannot prove compatibility.
- Canonical components/API contracts touched: Provider players and existing preview flow; inspection only.

## Stage 2

- Goal: Complete YouTube/Shorts sharing.
- Dependencies: Stage 1.
- Expected changes: Explicit host/complete-ID validation, Shorts playback/sizing, visible source links, canonical watch targets for title fetching.
- Verification approach: Watch/share/Shorts playback and titles; malformed/lookalike URLs, punctuation/query variants; Instagram regression.
- Risks or open questions: Preserve meaningful source parameters; reject unsupported forms without fetching.
- Canonical components/API contracts touched: Detector, renderer, YouTube fetch path, styles; retain signatures/match fields, add optional content-kind metadata if needed.

## Stage 3

- Goal: Deliver Spotify tracks/albums/playlists.
- Dependencies: Stages 1–2.
- Expected changes: Limit title eligibility to YouTube/Instagram; add Spotify detection/cards/players/source links/sizing.
- Verification approach: Three content types, flag matrix, keyboard controls, rejected URLs, body-derived titles without fetches/writes.
- Risks or open questions: Distinguish content types; apply validated permissions, not copied YouTube settings.
- Canonical components/API contracts touched: Detector/renderer/styles, ThreadTitle and beacon consumers; unchanged method signatures, no metadata service.

## Stage 4

- Goal: Deliver direct TikTok videos.
- Dependencies: Stages 1–3.
- Expected changes: Video detection, cards/players/source links, portrait sizing.
- Verification approach: Real playback, flag states, title fallback; reject profiles, malformed IDs, redirects.
- Risks or open questions: Separate unavailable content from integration failure; preserve source access.
- Canonical components/API contracts touched: Detector/renderer/styles and existing toggle behavior; no provider script injection.

## Stage 5

- Goal: Verify Instagram recovery and shared integration.
- Dependencies: Stages 2–4.
- Expected changes: Scoped preview, layout, or wiring fixes identified by verification.
- Verification approach: Cold/warm/failed Instagram metadata; board/root/reply/Forte, QDB quote/news, reply context; desktop/360px themes and keyboard focus.
- Risks or open questions: Preserve fallback; scraping redesign requires replanning.
- Canonical components/API contracts touched: TemplateRenderer, shared partials, preview/cache, script/styles, application/board/Forte title consumers.

## Stage 6

- Goal: Close release contract.
- Dependencies: All previous stages verified.
- Expected changes: Behavioral regression coverage; checklist and summary evidence/limitations.
- Verification approach: Browser network checks for delayed loading/no reopening reload, blocked content/source links, preserved titles; focused embed/title/flag suites, then full suite against baseline.
- Risks or open questions: Live/browser gaps block completion; identify unrelated baseline failures explicitly.
- Canonical components/API contracts touched: PHP/browser tests, support checklist, Step 4 summary.

Each stage targets one hour; split oversized work before implementation and replan above eight stages/one day. Step 4 organizes approved plans and commits them first, then commits each verified stage with its summary before continuing. Steps 1–2 approved; Step 3 awaits review. Plans remain uncommitted.

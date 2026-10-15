> **Feature plan:** [Step 1](./music_media_embeds_step1_solution_assessment.md) · [Step 2](./music_media_embeds_step2_feature_description.md) · [Step 3](./music_media_embeds_step3_development_plan.md) · [Step 4](./music_media_embeds_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Entry/outcome: post a public SoundCloud track/set or Bandcamp track/album URL; readers expand a player in shared views. Pending discovery, unavailable content, and failed players retain a visible source link.
- Release: focused PHP regressions and deterministic/live browser checks pass for both providers; record exact coverage, exclusions, flags, and evidence in the checklist. Production enablement and effective-flag acceptance remain an operator task.
- Boundaries: [acceptance matrix, discovery contract, and probe evidence](./music_media_embeds_feasibility_notes.md) govern this plan. Existing titles/providers remain unchanged; no read-model migration. Step 4 first organizes/commits approved plans on a feature branch; each stage is verified and committed before its dependent stage begins.

## Key Risks

- **High risk: discovery reliability.** Bandcamp PHP probes returned matching IDs without credentials, while Python received challenges. Early repeat probes establish feasibility; use bounded cached resolution and fallback, and block release on unresolved normal-flow failure.
- **High risk: listening usability.** Loaded player titles do not establish audio playback. Test actual track/set/album listening and sandbox compatibility early; retain links and stop affected delivery on failure.
- URL ambiguity/security: enforce the linked acceptance and destination rules before lookups; never render returned HTML. Unknown/private-shaped failures remain recoverable.

## Stage 1 — SoundCloud track slice (≤1 hour)

- Goal: submit and expand direct tracks through shared post rendering.
- Dependencies: Step 3 approval and Step 4 planning setup.
- Expected changes: detector/label/player integration, track sizing, source preservation; direct permalink widget, no server lookup.
- Verification approach: URL/flag/title regressions and posted thread/reply smoke checks, including unavailable-player fallback and actual playback.
- Risks or open questions: sandbox compatibility; validate early and stop if a new lookup becomes necessary.
- Canonical components/API contracts touched: detector match shape, renderer, shared styles/script and title eligibility tests.

## Stage 2 — SoundCloud collection slice (≤1 hour)

- Goal: listen to public sets using the same flow.
- Dependencies: Stage 1 verified/committed; select a real public set.
- Expected changes: set recognition/content kind and collection sizing.
- Verification approach: accepted/rejected sets, collection controls, flags, and live listening; track regression.
- Risks or open questions: collection overflow/unavailable sets; check early, preserve source access.
- Canonical components/API contracts touched: detector, renderer, shared styles and existing controls.

## Stage 3 — Bandcamp resolution foundation (≤1 hour)

- Goal: provide the bounded discovery prerequisite for the Bandcamp slices.
- Dependencies: Stage 2 verified/committed; linked contract and supplied IDs.
- Expected changes: isolated fetch/parser and disposable positive/negative cache; planned `fetch(string $url): ?array` returns validated release kind/ID. No title writes.
- Verification approach: supplied album/track metadata, mismatched IDs/kinds, challenges, redirects, destination validation, time/size limits, cache expiry and deduplication.
- Risks or open questions: fetch reliability; reject ambiguous metadata and cache failures. This internal prerequisite is not a releasable feature alone.
- Canonical components/API contracts touched: reuse preview transport/cache patterns where compatible; separate resolution cache, no repurposed title fields.

## Stage 4 — Bandcamp album slice (≤1 hour)

- Goal: submit album links and expand a resolved album player.
- Dependencies: Stage 3 verified/committed.
- Expected changes: Bandcamp detection/cards and gated same-origin resolution response; expansion invokes lookup, then uses shared player markup.
- Verification approach: real supplied album; flags, pending/unavailable states, no render-time lookup, repeat expansion, source link, normal title, and actual listening.
- Risks or open questions: endpoint/async wiring; validate before track integration, retain recovery on every error.
- Canonical components/API contracts touched: detector/renderer, player script, route/controller/service wiring; response contains constructed player URL or unavailable state.

## Stage 5 — Bandcamp track slice (≤1 hour)

- Goal: play the selected track, preserving album support.
- Dependencies: Stage 4 verified/committed.
- Expected changes: track-specific resolution/player presentation through existing album flow.
- Verification approach: supplied track ID `3419983385`, track-only metadata, wrong-kind rejection, light/dark layouts, fallback and playback.
- Risks or open questions: album/track confusion; validate selected title and ID rather than iframe fallback link text.
- Canonical components/API contracts touched: existing detector/renderer/resolver and shared styles; no separate player system.

## Stage 6 — Shared-view acceptance and handoff (≤1 hour)

- Goal: close release evidence for both providers.
- Dependencies: Stages 1–5 verified/committed.
- Expected changes: extend existing browser fixtures/checks and update support checklist.
- Verification approach: focused embed/title PHP suites; deterministic/live browser runs across board/thread/Forte, 360px/desktop, shipped themes, keyboard, flags, deferred loading and failure recovery; secondary shared-consumer regressions.
- Risks or open questions: live restrictions; record failures and block affected completion. Split before implementation if verification exceeds one hour.
- Canonical components/API contracts touched: existing browser fixture/router, TemplateRenderer consumers including QDB/composer/standalone views, support checklist.

Status: Steps 1–2 approved; Step 3 drafted for review. No implementation or commits started.

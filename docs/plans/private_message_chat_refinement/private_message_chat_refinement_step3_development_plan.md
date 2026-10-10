# Private message chat refinement Step 3 development plan

> **Feature plan:** [Step 1](./private_message_chat_refinement_step1_solution_assessment.md) · [Step 2](./private_message_chat_refinement_step2_feature_description.md) · [Step 3](./private_message_chat_refinement_step3_development_plan.md) · [Step 4](./private_message_chat_refinement_step4_implementation_summary.md)

## Completion Contract

- Outcome: Messages → conversation → verified transcript → inline reply; recover reads/sends without duplicates or lost drafts. Preserve other composers, encryption, authorization, and private-cache/static/offline isolation.
- Release: focused tests and isolated HTTP/browser journeys pass, including keyboard/mobile/zoom. No production deployment, external service, or migration. Rollback preserves messages/drafts and retry support.
- Boundary: latest 25 retained; no history pagination, unread state, or live updates. Seven stages ≤1 hour each; split oversized stages, rescope beyond eight/day. After approval: feature branch, planning-only commit, then verification/summary/commit per stage.

## Key Risks

- **High risk: duplicates/lost drafts.** First simulate lost acknowledgments, concurrent retries, and edits; preserve immutable encrypted attempts and newer drafts.
- **High risk: false trust/disclosure.** First test conflicting senders and invalid/missing signatures; require authorized acceptance and shared verified-only rendering.
- **High risk: obscured transcript.** Check keyboard/zoom/delayed decryption early; constrain composer growth, fall back inline, preserve navigation. Unresolved risks block dependents.

## Stage 1

- Goal: safely retry an uncertain send from existing composers.
- Dependencies: approval; conflict/race fixtures first.
- Expected changes: reuse ciphertext; atomic acceptance returns original metadata for matching sender identity, recipient, and envelope; conflicts fail without disclosure.
- Verification approach: dropped response, concurrent duplicates, mismatches, authorization, changed key eligibility.
- Risks or open questions: re-encryption defeats matching; never regenerate a dispatched attempt.
- Canonical components/API contracts touched: composer, send controller/service, Store `acceptEnvelope(array $attempt): array`; compatible `/api/private_messages`; existing schema.

## Stage 2

- Goal: preserve drafts and recover attempts across reloads.
- Dependencies: Stage 1 retry contract verified.
- Expected changes: sender/recipient-scoped recovery; persist encrypted attempts before dispatch, separate from drafts. Retain unresolved attempts; changed content gets new IDs. Clear only acknowledged snapshots; preserve redirects/newer text.
- Verification approach: pending edits, reload/retry, legacy drafts, identity changes, unavailable storage.
- Risks or open questions: storage failure prevents reload recovery; retain in-memory state and explain limitations.
- Canonical components/API contracts touched: shared composer draft storage and submit lifecycle; profile/aggregate-user entry points.

## Stage 3

- Goal: recover individual unreadable messages.
- Dependencies: signature/key failure fixtures.
- Expected changes: per-message retry/loading, corrected verification label, quiet success/prominent errors; expose initial settlement and confirmed-envelope rendering.
- Verification approach: mixed failures, restored keys/network, absent/invalid signatures, concurrent retries, list/legacy compatibility.
- Risks or open questions: stale requests or failed neighbors hide recovery; refresh failed dependencies and isolate results.
- Canonical components/API contracts touched: reader `readCard(mailbox, card, counterpart, message?)`; `bindMailbox` settlement event; existing key helper and `decryptEnvelope`.

## Stage 4

- Goal: read compact, clearly attributed conversations.
- Dependencies: Stage 3; capture baseline density.
- Expected changes: canonical message shell, sender grouping, direction cues, local dates/times; deterministic insertion tie-break for conversation timestamps.
- Verification approach: mixed senders, date boundaries, long/multiline text, same-second reload order; identical-viewport comparison targeting 3–4× density.
- Risks or open questions: density harms readability; preserve accessible attribution, wrapping, exact timestamps.
- Canonical components/API contracts touched: conversation template, reader rendering, shared styles/`message_time.js`, Store `conversationFor`; no schema change.

## Stage 5

- Goal: see confirmed replies without reloading.
- Dependencies: Stages 1–4 verified.
- Expected changes: conversation-only success event; render/decrypt authoritative metadata and retained envelope; deduplicate IDs, update empty/group states. Preserve other composers.
- Verification approach: first reply, repeated acknowledgment, failed local verification, newer draft, reload consistency.
- Risks or open questions: acceptance is not verification; keep confirmed delivery separate from read failure/retry.
- Canonical components/API contracts touched: composer confirmation event `{message, encryptedEnvelope}`; conversation controller, canonical shell/reader; existing send response.

## Stage 6

- Goal: reply comfortably without disrupting reading.
- Dependencies: Stage 5; keyboard/zoom checks first.
- Expected changes: labeled growing 2–3-row composer, placeholder, encryption note below; Ctrl/Cmd+Enter with composition protection. Sticky/inline fallback, settlement scrolling, latest-reply control, predictable focus.
- Verification approach: Enter/Shift+Enter, input methods, mobile keyboard, zoom, long drafts, early navigation, retry/send while reading earlier.
- Risks or open questions: viewport obstruction/focus theft; cap growth, preserve position, never autofocus.
- Canonical components/API contracts touched: shared composer partial/script, conversation controller, styles, reader settlement event.

## Stage 7

- Goal: verify and hand off the complete release.
- Dependencies: Stages 1–6 committed; risks resolved.
- Expected changes: regression/browser evidence, API reference, summary/checklist; organize four FDP artifacts and update index/links.
- Verification approach: messaging/key/navigation/isolation suites; controlled-identity Messages→send→retry, profile/aggregate compatibility, density/mobile evidence, syntax/diff checks.
- Risks or open questions: synthetic checks miss browser behavior; require real encryption and dropped-response recovery; report unavailable device checks.
- Canonical components/API contracts touched: existing test runner/browser fixtures, API docs, FDP artifacts; no production data.

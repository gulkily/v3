# Private message conversation list Step 3 development plan

> **Feature plan:** [Step 1](./private_message_conversation_list_step1_solution_assessment.md) · [Step 2](./private_message_conversation_list_step2_feature_description.md) · [Step 3](./private_message_conversation_list_step3_development_plan.md) · [Step 4](./private_message_conversation_list_step4_implementation_summary.md)

## Completion Contract

- Outcome: Messages → find/choose counterpart → conversation → encrypted send → refreshed list; recover failed loads/previews, preserve input/drafts, explain unavailable recipients.
- Release: keyboard/mobile usability, authorized verified-only previews, private-cache/static/offline isolation. Validate local HTTP/browser behavior; no production deployment, migration, or external dependency. Rollback restores code/assets, preserving messages.
- Cadence: six stages, ≤1 hour each; split oversized stages, rescope beyond eight/day. Feature branch and planning-only commit after approval; each stage ends with verification, summary, and commit. Cycles 2–4 excluded.

## Key Risks

- **High risk: disclosure.** First test unauthorized viewers/bad signatures; mitigate through viewer-scoped no-store retrieval and verified-only local decryption.
- **High risk: omitted/duplicate rows.** First test ties/intervening arrivals; freeze insertion boundary before grouping/pagination, reject unusable cursors with restart feedback.
- **High risk: stranded recipients.** First test key eligibility/loss and failed sends; preserve historical rows, input, and drafts. Resolve risks before dependent wiring.

## Stage 1

- Goal: open Messages with 25 counterpart summaries.
- Dependencies: approval; disclosure/snapshot fixtures pass.
- Expected changes: group both directions before limiting; wire page/API, sessions, route recognition, no-store headers, navigation, rows/empty state.
- Verification approach: ownership, older counterparts, ties, snapshots, empty lists, cache isolation.
- Risks or open questions: capped grouping hides users; validate complete discovery first.
- Canonical components/API contracts touched: Store `conversationsFor(string $viewer, ?string $cursor = null): array`; service `conversations(array $viewer, ?string $cursor = null): array`; Application/controllers/templates; `/messages`; `/api/private_messages/conversations` → `{status, conversations, next_cursor}`.

## Stage 2

- Goal: reach every counterpart using Load more.
- Dependencies: Stage 1; cursor tests pass.
- Expected changes: snapshot continuation, append/loading/retry/end states; refresh/return restarts listing.
- Verification approach: three pages, ties, intervening arrivals, malformed cursors, retries, refreshed ordering.
- Risks or open questions: duplicate loads; serialize requests, retain failed cursors, deduplicate counterparts.
- Canonical components/API contracts touched: list API/row renderer; new browser list controller.

## Stage 3

- Goal: recognize verified previews and local timestamps.
- Dependencies: Stage 2; signature/key fixtures pass.
- Expected changes: decrypt snapshot envelopes; one-line previews, failure/retry, friendly times with exact attributes/tooltips.
- Verification approach: mixed signatures, unavailable keys, retry, appended rows, unsafe text, time zones, no persistent preview writes.
- Risks or open questions: mismatched/unverified previews; use snapshot message IDs and verified-only text.
- Canonical components/API contracts touched: list renderer, reader `decryptEnvelope`, key helper, shared time formatter.

## Stage 4

- Goal: start a first conversation from Messages.
- Dependencies: Stage 3; eligibility checks pass.
- Expected changes: New message, autocomplete/direct entry, validation, retained input, empty-state action.
- Verification approach: eligible/self/invalid/keyless recipients, first send/return, failed-send draft recovery.
- Risks or open questions: suggestions imply authorization; revalidate keys when opening/sending.
- Canonical components/API contracts touched: directory repository, recipient-key API, conversation, shared composer, list controller.

## Stage 5

- Goal: navigate consistently and recover from unavailable conversations.
- Dependencies: Stage 4.
- Expected changes: legacy page redirects, titles/selected states, back/profile links, unavailable-conversation recovery, keyboard/mobile layout.
- Verification approach: legacy links, lost eligibility, keyboard/narrow viewport, existing APIs/composers.
- Risks or open questions: redirects break clients; redirect pages only, retain historical rows.
- Canonical components/API contracts touched: page controller/templates, navigation/styles; preserve existing APIs.

## Stage 6

- Goal: verify and hand off the complete release.
- Dependencies: Stages 1–5 committed; risks resolved.
- Expected changes: integration evidence, API reference, summary/checklist; organize four FDP artifacts and update links/index.
- Verification approach: messaging/key/navigation/offline suites, syntax/diff checks, controlled-identity browser send/return/failure/pagination journey, keyboard/mobile, headers, generated-artifact exclusion.
- Risks or open questions: unit tests miss browser/leakage failures; require journey and artifact checks.
- Canonical components/API contracts touched: test runner/browser harness, static/offline builders, API reference, planning documents.

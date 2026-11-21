# Optional thread author details

Cycle: v3/thread_author_details; Mode: one-shot; [Index](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; [ledger](../effort_ledger/thread_author_details.md)
Extended estimates: enabled
**Cycle estimate: 0.20**
Status: planned; next action: planning commit, then implementation.

## Request and authority

Ilya asked whether the proposed improvement fits one-shot execution, then said “please go ahead” and “aim to make a PR”.
This authorizes implementation, tests, documentation and a PR, not merge, deployment or a Zenmemes post.
Feedback: PF-20261030-F57. This delivers an author-details slice, not a new signature-verification system.

## Scope and approach

Add `include_authors=1` to the ordinary GET `/api/get_thread?thread_id=...` entry.
Default output stays byte-identical. Detailed output prefixes each existing post line with `Post-ID`, `Author` and applicable `Author-Identity-ID` headers.
Separate post blocks with blank lines. Keep the existing `[post-id] body` prefix so body text cannot masquerade as headers.

Reuse `ApiTextController::authorIdentityHeader`, including its existing signed-source-presence rule. This is not proof of cryptographic validity.
Extend `Application::fetchThreadPosts` to select the existing `source_signature_path` column. No schema migration or per-post query is needed.
Use the existing route catalog for discoverability and the agent API reference for the format contract.
Changes are limited to those surfaces, focused tests, feedback status, concept documentation and this cycle's records.
No UI, client rewrite, publication, retraction-policy changes or new dependencies.

## Risks and completion

- Compatibility: explicitly opt in; compare exact default output with the baseline and with explicit zero.
- Misattribution: reuse get_post's rule; test signed-source and unsigned claims, duplicate display names and absent identities.
- Framing: normalize author line breaks; keep each message on its existing prefixed single line, including header-like body text.
- Shared query: add only an existing column; test get_post parity and ordinary route integration, hidden posts and missing threads.

Completion means an agent can fetch speakers in one read request, with default clients unaffected and repeat reads safe.
Read failures remain existing 404 behavior; callers can retry this read without creating records.
Run the smoke set plus focused tests and existing text-route/catalog checks. Record failures and limitations honestly.
UI/theme, write retries and migration checks are inapplicable to this read-only API change. Deployment is not authorized.
GitHub sign-in is needed to publish the PR; local implementation proceeds independently.

## Concepts touched

Propose `docs/concepts/thread_text_api.md`: document existing post/thread read APIs and the opt-in author view.
Reuse attribution semantics unchanged; do not create a broader trust concept or alter policy.

## Baseline and study history

Starting revision: 22fc9f10a4d4f8cba9ecaed501aba2ea90d129e4.
Branch: agent/thread-author-details. Planning commit: pending.
Standing enrollment under P2 applies. R1's three frozen source hashes were verified against the host ledger.
2026-11-21 intake: 0.20 total scoped-cycle projection, orange single exposed rater; source and feedback inspection preceded rating.
No pristine pre-inspection score, independent rating or measured labor is claimed.
2026-11-21 execution entry: 0.20 reaffirmed; scope v1, baseline/R1/P2 and exposure unchanged.
No separate stages or alternative estimates are invented for this bounded pass. Maintainer comfortable-fit judgment remains pending.

*— orange · 2026-11-21 UTC*

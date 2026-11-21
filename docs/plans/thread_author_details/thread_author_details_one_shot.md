# Optional thread author details

Cycle: v3/thread_author_details; Mode: one-shot; [Index](../README.md)
Debug mode: enabled
Effort study: railspike-effort / R1 / P2; [ledger](../effort_ledger/thread_author_details.md)
Extended estimates: enabled
**Cycle estimate: 0.20**
Status: locally implemented and verified; next action: publish the PR after GitHub sign-in.

## Request and authority

Ilya asked whether the proposed improvement fits one-shot execution, then said “please go ahead” and “aim to make a PR”.
This authorizes implementation, tests, documentation and a PR, not merge, deployment or a Zenmemes post.
Feedback: PF-20261030-F57. This delivers an author-details slice, not a new signature-verification system.

## Scope and approach

Add `include_authors=1` to the ordinary GET `/api/get_thread?thread_id=...` entry.
Default output stays byte-identical. Detailed output prefixes each existing post line with `Post-ID`, `Author` and applicable `Author-Identity-ID` headers.
Separate post blocks with blank lines. Keep the existing `[post-id] body` prefix so body text cannot masquerade as headers.

Reuse `ApiTextController::authorIdentityHeader`, including its existing signed-source-presence rule. This is not proof of cryptographic validity.
Initial assumption, superseded during verification: select an existing `source_signature_path` posts column. The column does not exist.
The delivered query instead selects the earliest activity source path through the existing post-ID index.
Only opt-in reads resolve signature-file presence; missing activity paths use get_post's existing canonical-path fallback.
No schema migration is needed. Normal indexed reads avoid a separate SQL call per post.
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
Branch: agent/thread-author-details. Planning commit: e1dc2ef (confirmed before implementation).
Standing enrollment under P2 applies. R1's three frozen source hashes were verified against the host ledger.
2026-11-21 intake: 0.20 total scoped-cycle projection, orange single exposed rater; source and feedback inspection preceded rating.
No pristine pre-inspection score, independent rating or measured labor is claimed.
2026-11-21 execution entry: 0.20 reaffirmed; scope v1, baseline/R1/P2 and exposure unchanged.
No separate stages or alternative estimates are invented for this bounded pass. Maintainer comfortable-fit judgment remains pending.

*— orange · 2026-11-21 UTC*

## Execution history and request dispositions

2026-11-21: Downloaded no repository dependencies. Installed system PHP tooling and ZIP support for local verification.
The first tests failed because the assumed signature-path column does not exist. No migration was introduced to cover the mistake.
Reused `postSourcePath`, `sourceSignatureLink` and `authorIdentityHeader` instead; a framing-test normalization error was also corrected.

Request: Ilya asked whether the per-post lookup was an optimization opportunity.
Disposition: in-scope refinement of the authorized small feature; no trust change, cache or migration.
Outcome: fold indexed source-path selection into the existing SQL statement, and resolve file presence only for attributed posts in opt-in mode.
The missing-path fallback remains intentionally compatible and may perform additional lookups.
EXPLAIN QUERY PLAN confirms `posts_thread_sequence_idx` and `activity_post_id_idx` use, not a full activity scan.
This is query-shape evidence, not a large-thread latency benchmark.

## Verification and outcome

- Passed: `./v3 test --smoke ThreadAuthorDetailsTest ApiRouteCatalogTest LocalAppSmokeTest::testApplicationRendersTextApisAndRss`: final 485 run, 485 passed, zero failed.
- Earlier smoke run: 484/485; archive test also failed on untouched starting revision. Installing ZIP tooling resolved it; final run is green.
- Focused route tests cover exact opt-in, repeat reads, default-view reconstruction, duplicate names, anonymous/unsigned claims and get_post parity.
- Source fixtures cover both .asc and .sig, indexed dated paths and missing-path fallbacks. These test file presence, not cryptographic validity.
- Framing and visibility checks cover header-like bodies, author line breaks, hidden posts and 404 behavior.
- Passed local real HTTP: unchanged baseline and feature default responses were byte-identical; opt-in, explicit zero and missing-thread 404 worked.
- Passed PHP syntax checks and `git diff --check`. Full suite was not run, per repository policy.
- Feedback index refresh blocked: existing PF-20261104-F50 has an invalid Evidence value. No unrelated feedback record was changed; generated index remains unchanged.
- Documentation and touched concept paths checked against code. No UI, schema, canonical-record, dependency-manifest or trust-policy changes.

Technical scope is complete locally. Merge, deployment, production acceptance and PR publication are not claimed.
GitHub has no saved login or authenticated CLI session here; Ilya has been asked through the secure login card.
Feedback PF-20261030-F57 stays blocked: this slice adds author details, not full cryptographic signature-status reporting.
To use after deployment, append `&include_authors=1` to a get_thread request; omit it to retain the compact response.

## Study closeout and final review

2026-11-21 closeout: v1 / original baseline / R1 / P2. Original projection 0.20 retained.
Retrospective scope assessment: 0.25, orange single exposed rater, reflecting source-lookup correction and verification work.
No measured labor, independent rating, split outcome or accepted-count increment. Maintainer comfortable-fit judgment is pending.
Final review: retained canonical attribution helpers, inspected opt-in/default paths, no duplicated trust policy or unauthorized publication.
Implementation commit: pending completion commit containing this updated record and the scoped implementation.

*— orange · 2026-11-21 UTC*

# LLM exchange privacy after retraction

Retracting a post also withholds recorded LLM exchanges that included it as
input or context. This applies to author and operator retractions. Approved
users and operators use the same visibility policy; operator privileges do
not permit viewing withheld exchange payloads through the application.

## What viewers see

- Affected exchanges disappear from post links and the LLM Exchanges list.
- Opening a saved or guessed exchange URL returns HTTP 404 with
  “LLM exchange unavailable.” No request, response, error, or context payload
  is rendered.
- Proven unaffected exchanges remain available. Hidden rows do not consume
  visible result limits or offsets.
- Exchange pages use `Cache-Control: private, no-store`. The application does
  not serve exchange pages from generated static artifacts, and the offline
  worker provides no exchange-page fallback.

## Dependency evidence

New analysis, agent-response, and Fastmod calls retain an
`exchange_dependencies` manifest in their recording context. Version 1 names
all included post IDs and states whether provenance is complete. The manifest
must include the primary post when one is recorded; invalid IDs, incomplete
sets, and unsupported versions cannot establish visibility.

Dependencies include the target and any root/parent text, thread comments,
related posts, or derived summaries actually supplied to the provider. Cached
analysis summaries propagate their original dependencies through private
analysis metadata. Older summaries without such evidence make an exchange
uncertain. Agent prompts track only their actual input fields, rather than
unrelated analysis context they do not send. Provenance is not added to the
model prompt or inferred from arbitrary forum prose.

Recorded requests and responses remain exact diagnostic payloads. Added
provenance is recording context; existing exchange rows are not rewritten.

## Historical handling

Historical root-only Fastmod exchanges can be classified when they have the
standard system prompt and the exact known single-post input shape. Both
OpenAI-compatible and Anthropic request layouts are supported.

Other historical exchanges without complete provenance are withheld, including
calls with unidentified reply context and derived summaries. A primary-post
association alone is insufficient. Text matching cannot prove that a payload
contains no retracted content. This conservative rule can make older
conversations unavailable even when no affected dependency is known.

## Timing and recovery

Visibility uses current validated canonical retractions for every served
query. No visibility decision is cached across requests. A provider call
started before retraction can finish afterward; its newly recorded exchange
still goes through the same policy when inspected. Failed calls preserve
provenance and are subject to the same rule.

If canonical state is unreadable, malformed, missing a target needed for
validation, or lacks operator acceptance history, exchanges are withheld.
Repair or restore the trusted repository state, then retry; no exchange-row
mutation or manual visibility override is required. Rebuild/restart does not
restore access to affected or uncertain exchanges.

Deploy recording and reader changes together. Do not roll back to readers
that omit the policy: they would expose retained payloads again. Historical
classification is repeatable and leaves source rows unchanged. Preserve
canonical retraction history and public keys as described in
[Author and operator post retraction](author_post_retraction.md).

## Retention boundary

This feature suppresses application access; it does not erase diagnostic
storage. Server administrators with direct database/filesystem access can
still inspect raw logs. Backups, provider-held data, and previously saved or
already loaded browser copies are outside this suppression boundary.

Signed retraction evidence and Activity's Commits view remain available.
Published replies are separate posts and are not automatically retracted.
Other analysis tools are outside this feature's exchange-inspection scope.

## Verification

- `php tests/run.php ExchangeDependenciesTest SqliteLlmExchangeStoreTest LlmExchangeRecorderTest`
- `php tests/run.php WriteApiSmokeTest::testRetractionMakesDirectAndContextOnlyLlmExchangeDetailsUnavailable`
- `node tests/browser/llm_exchange_privacy.mjs`

Browser coverage uses local fixtures and Chromium (`CHROMIUM_PATH` or
`/opt/google/chrome/chrome`). It covers both retraction roles, signed actions,
links/lists, saved URLs, unknown historical records, late/error records,
cache headers, stale artifacts, state failure/recovery, and retained raw rows.

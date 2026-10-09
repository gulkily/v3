> **Feature plan:** [Step 2](./prepared_thread_event_fields_step2_feature_description.md) · [Step 3](./prepared_thread_event_fields_step3_development_plan.md) · [Step 4](./prepared_thread_event_fields_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a signed-in member on a profile with event support enabled fills in event fields and clicks "Create thread" (not "Create thread anonymously").
- **End-to-end outcome:** the resulting thread's canonical record carries the submitted event fields, exactly as the already-correct anonymous/`createThread()` path does; the event block renders correctly on the board and thread page.
- **Required recovery:** malformed event input (e.g. a bad `event_time`) is rejected with a clear error before a prepare token is issued — same fail-closed pattern `createThread()` already has, never a half-written prepared post.
- **Deployment/external verification:** not applicable.
- **Release condition:** full test suite (`php tests/run.php`) passes, including new tests for this fix; the anonymous-submit path and `createThread()`'s own tests are unchanged.

## Key Risks

- **High risk:** a client/server field-name mismatch would reintroduce a silent drop in one direction. Mitigation: both stages reuse the exact field names already shipped (`event_date`, `event_time`, `event_location`, `event_link`) — never invented fresh. Early validation: Stage 1's canonical-record assertion and Stage 2's field-collection assertion both check for these exact names.
- `prepareThread()` gains a gate call (`assertEventSupportAllowsInput()`) it never had — this is a behavior change (new rejection), not just a bugfix completion. Mitigation: Stage 1 adds a test proving it throws the identical message `createThread()` already throws, confirming the two write paths are now consistent rather than introducing a new, different gate.
- The actual OpenPGP browser-signing round trip can't run inside a PHP test. Mitigation: Stage 2 proves the full `prepare → sign → finalize` contract server-side using the existing `createSigningKey()`/`signCanonicalRecord()` test helpers (same pattern already used for approvals), and separately proves the client collects the right field names via the existing Node-VM harness (`tests/BrowserSigningNormalizationTest.php`) — together these close the loop without needing a real browser; final confirmation is the user retrying in their own browser session after this ships.

## Stage 1
- Goal: `LocalWriteService::prepareThread()` validates and forwards event fields into the canonical record it builds, and enforces the event-support gate, matching `createThread()` exactly.
- Dependencies: none (all reused validators — `normalizeEventDate()`, `normalizeEventTime()`, `normalizeAuthoredLine()`, `assertEventSupportAllowsInput()` — already exist).
- Expected changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`, `prepareThread()`: call `assertEventSupportAllowsInput($input)` at the top (mirroring `createThread()`); read and normalize `event_date`/`event_time`/`event_location`/`event_link` from `$input` the same way `createThread()` does; pass all four into `buildThreadPostRecord()`.
- Verification approach:
  - `php tests/run.php` full suite.
  - New test `testPrepareThreadIncludesEventFieldsInCanonicalRecord` (mirrors the existing `testPrepareThreadReturnsCanonicalRecordWithoutCommittingPost`): call `/api/prepare_thread` with all four event fields set and event support enabled; assert `canonical_record` contains `Event-Date:`, `Event-Time:`, `Event-Location:`, and `Event-Link:` with the submitted values.
  - New test mirroring `testEventFieldsRequireEventSupportBeforeWriting`: call `LocalWriteService::prepareThread()` directly with event support disabled and an event field set; assert it throws `RuntimeException('Event support is disabled for this site.')` — the same message `createThread()` already throws.
  - New test: call `/api/prepare_thread` with a malformed `event_time` (e.g. `99:99`); assert an error JSON response and that no prepared-post token file was written.
- Risks or open questions:
  - Impact / mitigation: both Key Risks items above (field-name consistency; the new gate being a real behavior change).
  - Early warning / validation: the three new tests above, before Stage 2 begins.
- Canonical components/API contracts touched: `LocalWriteService::prepareThread()`.

## Stage 2
- Goal: the client collects all four event fields for the signed thread-submit path, and the full `prepare → sign → finalize` round trip is proven correct server-side; confirm zero regression elsewhere.
- Dependencies: Stage 1 (the server must already read/forward the fields before a client fix is verifiable end-to-end).
- Expected changes:
  - `public/assets/browser_signing.js`, `collectThreadSubmitFields()`: add `event_date`, `event_time`, `event_location`, `event_link` to the returned field set, using the existing `composeFormFieldValue()` helper exactly as the other fields already do.
- Verification approach:
  - `php tests/run.php` full suite.
  - Update `tests/BrowserSigningNormalizationTest.php::testThreadSubmitTransportHelpersCollectFieldsAndParseResponses`'s expected `fields` object to include the four new keys (empty string when absent from the form, matching every other optional field's default).
  - Add a new Node-VM case (same harness) with event inputs present on the form; assert `collectThreadSubmitFields()` returns their exact values.
  - New test `testPrepareThreadWithEventFieldsRoundTripsThroughFinalizeAndReadModel`, modeled on `testFinalizePreparedApprovalVerifiesSignatureBeforeCreatingApproval`: `createSigningKey()` → `/api/prepare_thread` with all four event fields → `signCanonicalRecord()` → `/api/create_prepared_post` → assert the resulting thread page's rendered event block shows the correct date/time/location/link.
  - Confirm existing anonymous-path and `createThread()` tests are unchanged (part of the full suite run above — no edits to those tests).
- Risks or open questions:
  - Impact / mitigation: the OpenPGP-round-trip risk above — covered by the new finalize test plus the Node-VM field-collection test together.
  - Early warning / validation: both new/updated tests above, plus asking the user to retry their original failing scenario in their own browser session once this ships, as the final real-world confirmation.
- Canonical components/API contracts touched: `browser_signing.js` (`collectThreadSubmitFields()`).

Waiting for "Approved Step 3" before branching and starting Step 4.

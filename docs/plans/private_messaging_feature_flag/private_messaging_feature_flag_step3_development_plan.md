> **Feature plan:** [Step 1](./private_messaging_feature_flag_step1_solution_assessment.md) · [Step 2](./private_messaging_feature_flag_step2_feature_description.md) · [Step 3](./private_messaging_feature_flag_step3_development_plan.md) · [Step 4](./private_messaging_feature_flag_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Application change: signed operator toggle → messaging hidden/denied → re-enable/reload restores history and draft/pending-send recovery. Default enabled; deployment overrides win; existing authorization/storage preserved.
- Release: verify Step 2’s complete inventory on/off/re-enabled, operator permissions, overrides, and stale-page recovery. Test deployment settings with isolated servers; exclude production rollout.
- After approval: branch, organize/commit approved plans/index, then verify/commit each stage with its summary. Budget ≤1 hour per stage; split overruns before implementation.

## Key Risks

- **High risk:** Route gaps expose data. Routing inspection found legacy pages and separate sync endpoints; Stage 1 must verify complete coverage.
- **High risk:** Retries lose/duplicate sends. Composer persists pending envelopes; sync retries separately. Stages 2–3 verify shutdown and retained recovery.
- **High risk:** Stale availability defeats shutdown. Renderer injects background scripts; environment overrides saved flags. Stage 1 verifies script gating, fresh-request values, and override locks.

## Stage 1

- Goal: Operator disables/re-enables messaging through normal controls.
- Dependencies: Approved plans committed.
- Expected changes: Register site-mutable `FORUM_PRIVATE_MESSAGES_ENABLED`, default true; gate routes before private storage, navigation/composers, and startup scripts. Return no-store HTTP 403 with `code: messaging_disabled` for APIs; unavailable pages. Admitted requests may finish.
- Verification approach: Extend flag/routing/rendering tests: Step 2 inventory, signed permissions, overrides, unchanged private state after denied reads/writes.
- Risks or open questions: Route gaps or stale values; resolve the route matrix and request lifetime before dependent stages.
- Canonical components/API contracts touched: Flag registry/evaluator and signed flow; Application/RouteServices, TemplateRenderer, profile/username composers. No database/signature changes planned.

## Stage 2

- Goal: A stale conversation or composer stops safely when disabled.
- Dependencies: Stage 1 response contract verified and committed.
- Expected changes: Shared availability/request handling for keys, send/retry, list/previews, reader/history, composer. Block requests after detection until reload; retain drafts/pending envelopes.
- Verification approach: Composer/list tests and browser journey: disable before send, rejected send, lost acknowledgment then disable/re-enable; retained edits, exactly one delivery.
- Risks or open questions: Completion handlers restart actions; verify disabled handling through catch/finally and outstanding requests.
- Canonical components/API contracts touched: `private_messages.js`, messaging UI modules; Stage 1 response; unchanged send identity/idempotency.

## Stage 3

- Goal: Unread updates and history recovery honor the same shutdown.
- Dependencies: Stage 2 shared availability behavior committed.
- Expected changes: Gate unread/read and history-sync work/transfers/acknowledgments; stop retries/scheduling without resetting progress.
- Verification approach: Unread/seen/sync tests: foreground/background detection stops subsequent requests; re-enable/reload resumes.
- Risks or open questions: Independent retries bypass shutdown; audit fetch/schedulers and verify queued work stops.
- Canonical components/API contracts touched: Unread, seen, sync, reader/availability modules; preserve receipt/transfer contracts.

## Stage 4

- Goal: Demonstrate and document the complete operator/member journey.
- Dependencies: Stages 1–3 verified and committed.
- Expected changes: Integration coverage; API help, messaging architecture/operator guidance: defaults, overrides, admitted-request boundary, recovery.
- Verification approach: Run `php tests/run.php`, affected module checks, browser list and history-sync runners in `tests/browser/`; verify signed toggle → denial → recovery using isolated fixtures; check documentation/scope.
- Risks or open questions: Missing runtime/browser prerequisites block release; resolve and run real browser journeys.
- Canonical components/API contracts touched: Existing fixtures/journeys, ApiTextController, messaging/operator documentation.

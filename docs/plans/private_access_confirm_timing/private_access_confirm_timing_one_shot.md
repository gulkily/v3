Cycle: `v3/private_access_confirm_timing`; Mode: one-shot; Record/index: [plans index](../README.md). Second of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25); siblings `private_access_waiting_keys`, `private_access_one_evaluation` (stacked on this one), `private_access_signing_helper`, `private_access_card_settle`.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_confirm_timing`; host ledger [private_access_confirm_timing.md](../effort_ledger/private_access_confirm_timing.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.08**

Status: implemented and committed; next permitted action: maintainer review (PR).

Feedback item: none matches (searched `docs/feedback/items/`).

Original request (maintainer, 2026-11-25): Confirm on the waiting-keys card "performs nowhere as instant on the frontend as on the awaiting users page". Claude's review noted that the approval endpoints send `Server-Timing` and the confirm endpoints do not, so the two cannot be compared in the browser's network panel.

Authorization: "can you do it in 5 one-shots please" (2026-11-25), on Claude's list that named this fix. Exclusions: making Confirm faster (`private_access_one_evaluation` and a later, measured decision on the commit path); merging and deploying are the maintainer's.

Bounded outcome and work type: application change (instrumentation).
1. `/api/prepare_private_access_confirmation`, `/api/confirm_private_access` and `/api/private_access_status` send a `Server-Timing` header, as `/api/prepare_approval` and `/api/create_prepared_approval` do, and keep their `no-store` headers.
2. The writer times the confirm's phases: `policy` (the access evaluation) in prepare; `policy`, `verify` (signature), `commit` (the git transaction) and `status` (the access answer returned) in finalize. Lock wait and the other entries `withTimedWriteLock` already records stay.
3. As with approvals, the timings move from the JSON body into the header.

Approach and reuse decisions:
- Reuse `RouteServices::mergeResultTimings()`, `serverTimingHeaders()` and `noStoreHeaders()`, which the approval controller uses, and `LocalWriteService::timePhase()` for phases. No new helper.
- The confirm JSON body drops `timings`. `private_access_confirm.js` reads only `status`, `error`, `prepare_token`, `canonical_record` and `has_access`; nothing else calls these endpoints (checked `public/assets`, `public/quill.*`, `scripts/`).

Risks: none material. Timing calls cost microseconds; header names follow the existing `[a-z_]` filter.

Completion conditions: in the browser's network panel, a Confirm click shows the two requests with `Server-Timing` entries naming the phases. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Private key access](../../concepts/private_key_access.md) (used unchanged).

Verification and recovery plan: `./v3 test --smoke PrivateAccessConfirmationTest ApplicationServerTimingTest` with an assertion on the phase names; `git diff --check`. Full suite not run. The header itself is set by PHP's `header()`, which the CLI tests cannot read; recorded as verified only through the shared helpers' tests and code reading. Recovery: revert the commit.

Starting revisions and branch: `fix/private_access_confirm_timing` from `origin/main` `797971a`.

Planning commit: `be02096` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.08**. Recorded after inspecting the controller and writer, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `privateAccessConfirmation()` in `IdentityApprovalAndInvitationApiController` times `request_data`, merges the writer's timings with `mergeResultTimings()`, sends `Server-Timing` beside `no-store`, and drops `timings` from the body; errors send `request_data` and `total`. Addition.
- 2026-11-25: `preparePrivateAccessConfirmation()` times `policy`; `finalizePrivateAccessConfirmation()` times `policy`, `verify`, `commit` and `status` with `timePhase()`. A repeat of an accepted confirm reports `status` only. Addition.
- 2026-11-25: `PrivateAccessConfirmationTest::testRepeatOfTheAcceptedConfirmIsANoOp` asserts the phase names.
- 2026-11-25, discovery (local test repository, one run, printed and then removed): prepare `policy` 3.3 ms, lock held 3.8 ms; finalize lock held 311.6 ms: `policy` 4.1, `verify` 85.8, `commit` 127.1, `status` 94.2. `status` is about one more gpg run because `privateAccessStatus()` re-reads the confirm record just written, and `PrivateAccessConfirmation::read()` verifies its signature again, both directly and through `PrivateKeyAccess`. Handed to `private_access_one_evaluation`.

Study history, closeout:
- 2026-11-25 UTC: retrospective scope assessment **0.08**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. Confirm's two requests now show where their time goes in the network panel, named like the approval endpoints' timings.

Verification:
- Passed: `./v3 test --smoke PrivateAccessConfirmationTest ApplicationServerTimingTest`: 504 run, 504 passed. Full suite not run.
- Passed: `php -l` on the changed files; `git diff --check`.
- Unverified here: the `Server-Timing` header on a real request (PHP's `header()` is not observable in the CLI tests). The helpers it uses are covered by `ApplicationServerTimingTest`. Check in the network panel after deploying.
- Inapplicable: markup, themes, icons, schema, flags.

Final review: the diff touches the controller, `LocalWriteService.php`, one test, this record, the plans index, the ledger and the debug log. Existing helpers only.

Commit/merge/publication status: completion commit pending at the time of writing; branch `fix/private_access_confirm_timing`; PR to be opened; not merged. `private_access_one_evaluation` is stacked on this branch.

Debug: [debug log](./private_access_confirm_timing_debug_log.md).

Enhancement requests: none.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5

Cycle: `v3/private_access_confirm_timing`; Mode: one-shot; Record/index: [plans index](../README.md). Second of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25); siblings `private_access_waiting_keys`, `private_access_one_evaluation` (stacked on this one), `private_access_signing_helper`, `private_access_card_settle`.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_confirm_timing`; host ledger [private_access_confirm_timing.md](../effort_ledger/private_access_confirm_timing.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.08**

Status: planned; next permitted action: implement.

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

Planning commit: pending.

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.08**. Recorded after inspecting the controller and writer, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

*Claude · 2026-11-25 UTC*

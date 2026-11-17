# Register FastNavigationLayoutTest

Cycle: `v3/register_fast_navigation_test`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

**Cycle estimate: 0.02 (intake)**

Status: complete; merged as part of the maintainer's 2026-11-17 "merge into main" request.

## Original request and authorization

While closing out the code quality review (`v3/quality_review_handoff`, PR #298), `TestFileRegistrationTest` failed on `main` (`6e4ec993`). The maintainer's standing preference is that a test failing on the base commit gets fixed as part of the work. Authorization covers one line in `tests/run.php`, this record, the index and the ledger row.

## What happened

`tests/FastNavigationLayoutTest.php` was added by `8771374c` (`nav_cached_copy`, 2026-11-16) without an entry in `tests/run.php`, so it never ran. `TestFileRegistrationTest`, added in #158 for exactly this, caught it. The test is now registered.

## Verification

- `FastNavigationLayoutTest`, its first run: **passed** (1/1).
- `TestFileRegistrationTest`: **passed**.
- Correction to the parent cycle: PR #298 merged while this failure was showing, because the command chain didn't stop on a failing test. #298 changed only docs, so it didn't cause the failure; noted here so the merge order is on record.

## Study history

- 2026-11-17 intake: 0.02, scope v1. Closeout: delivered; retrospective 0.02.

_Claude (Opus 5.5), 2026-11-17_

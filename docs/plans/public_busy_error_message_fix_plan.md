# Public Busy Error Message Fix Plan

## Problem

The execution-lock timeout page currently exposes implementation details:

- it says the site is blocked by a write or read-model rebuild
- it prints the raw exception message
- the exception message includes an absolute server path such as `/home/dh_zsagad/v3/state/cache/forum-rewrite.lock`

For public traffic, this should read like a temporary site condition and stay in the zenmemes voice.

## Plan

1. Replace the public lock-timeout copy in `FrontController::renderBusyError()` with a short joke-style message that does not mention writes, read models, locks, rebuilds, exceptions, or file paths.
2. Stop passing the raw lock exception message into the public busy template. Keep the original exception text available only to server logs if logging is added or already present nearby.
3. Update `LocalAppSmokeTest::testFrontControllerShowsBusyPageForExecutionLockTimeout()` so it asserts the friendly busy page is shown and explicitly rejects leaked internals such as `Timed out waiting for execution lock`, `forum-rewrite.lock`, `/home/`, `read-model`, and `rebuild`.
4. Keep the HTTP status as `503` so clients and caches still treat this as temporary unavailability.
5. Run the local PHP smoke test suite, or at minimum `tests/LocalAppSmokeTest.php`, to confirm the busy response still routes correctly and no internal details remain in the rendered HTML.


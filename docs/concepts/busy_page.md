# Busy page

Purpose: Tell a visitor quickly that the site cannot answer right now, and retry for them, instead of leaving the page loading.
Scenario: A full rebuild holds the read model locked. A visitor opens a thread; after waiting at most 2 seconds for the read model, the page answers 503 with the site profile's busy copy, `Retry-After: 10` and a 10-second reload, and loads normally once the lock is gone. After a deploy that changes the read-model schema, the same visitor gets the "Site Update" page in the same form until the queued rebuild finishes.
Sources: [front controller](../../src/ForumRewrite/Host/FrontController.php) (`renderBusyError()`, `renderReadModelRecovery()`, `sendRetryablePage()`), [wait limit](../../src/ForumRewrite/ReadModel/ReadModelConnection.php) (`PAGE_REQUEST_WAIT_MILLISECONDS`, `isLocked()`), [unavailable read model](../../src/ForumRewrite/ReadModel/ReadModelUnavailable.php), [copy](../../src/ForumRewrite/ProfilePresentationContent.php) (`busy()`), [tests](../../tests/ReadModelBusyPageTest.php).

Boundaries: page requests only. API requests keep their JSON busy response with `Retry-After: 10`; CLI commands and writers keep SQLite's default 60-second wait.

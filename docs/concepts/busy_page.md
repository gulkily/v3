# Busy page

Purpose: Tell a visitor quickly that the site cannot answer right now, and retry for them, instead of leaving the page loading.
Scenario: A full rebuild holds the read model locked. A visitor opens a thread; after waiting at most 2 seconds for the read model, the page answers 503 with the site profile's busy copy and `Retry-After: 30`; it reloads itself after about 10 seconds, then backs off (20, 40, 80, then every 120 seconds, with jitter; hidden tabs wait until shown; 30 seconds without JavaScript), and loads normally once the lock is gone. After a deploy that changes the read-model schema, the same visitor gets the "Back in a moment" page in the same form until the queued rebuild finishes.
Sources: [front controller](../../src/ForumRewrite/Host/FrontController.php) (`renderBusyError()`, `renderReadModelRecovery()`, `sendRetryablePage()`), [wait limit](../../src/ForumRewrite/ReadModel/ReadModelConnection.php) (`PAGE_REQUEST_WAIT_MILLISECONDS`, `isLocked()`), [unavailable read model](../../src/ForumRewrite/ReadModel/ReadModelUnavailable.php), [copy](../../src/ForumRewrite/ProfilePresentationContent.php) (`busy()`), [tests](../../tests/ReadModelBusyPageTest.php).

Boundaries: page requests only. API requests keep their JSON busy response with `Retry-After: 10`, and so do the Deploy page's script requests (`X-Requested-With: ForumDeploy`), which keep checking status through a 503 instead of stopping; CLI commands and writers keep SQLite's default 60-second wait.

Preview: `/preview/site-update`, `/preview/maintenance` and `/preview/busy` render each page with status 200 on a working site, without touching the read model, so its look and reload behavior can be checked on production.

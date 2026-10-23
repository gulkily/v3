# Implementing pagination

Use the shared native-link pagination contract for new bounded lists. Standard
Activity is the first consumer. Keep list-specific queries and access rules in
the list's service; reuse position handling and navigation controls instead of
building another paginator.

Forte Activity and Commits intentionally retain their existing **Load more**
behavior. QDB, Docs, SQLite viewer, messages and account key history also retain
their current implementations until separately reviewed migrations. Track those
in the [consolidation checklist](../plans/pagination_consolidation_checklist.md).

## Shared interfaces

[PageNavigation](../../src/ForumRewrite/Pagination/PageNavigation.php) provides:

- `encode(context, direction, boundary)` creates a URL-safe position token.
- `decode(token, context)` returns `direction` and `boundary`, rejecting malformed,
  oversized, unsupported-version or mismatched-context tokens with
  `InvalidArgumentException`.
- `links(path, query, context, previous, next, restart)` returns `previous_href`,
  `next_href` and `restart_href`. Unavailable destinations are null. Filter/order
  query parameters are preserved and the position parameter is replaced.

The reusable [navigation partial](../../templates/partials/page_navigation.php)
accepts that result as `pagination`. It renders native Previous/Next links,
with `rel="prev"`/`rel="next"`, and First page when recovery is requested.
It omits unavailable links and needs no JavaScript. Use the existing theme
navigation classes; add no new navigation destination or routine help panel.

A list adapter returns `items`, `previous` and `next`. Boundaries are maps of
strings owned and validated by that adapter. Totals and numbered jumps are not
required by the shared component; add capabilities only for a reviewed consumer.

## Connect a list

1. Define a context identifying the list, normalized filters and ordering.
   Authorization remains a separate server check on **every request**, including
   direct links, reloads and history navigation.
2. Decode the optional position using that context. Validate boundary fields in
   the query adapter; never interpolate token contents into SQL. Tokens are
   readable and editable, not signed credentials: include no secrets or private
   payloads and never treat context matching as authorization.
3. Query a bounded page with stable ordering, and return boundaries for available
   directions. Previous moves toward the start of that ordering; Next moves away.
4. Build links with the normalized filter/order query and render the shared
   partial. Category/order changes must discard the old position.
5. Catch invalid/expired positions at the page boundary and offer First page.
   Empty pages must retain valid return navigation. Native failed navigation can
   be retried with browser Reload or left using Back. If enhanced navigation is
   needed later, preserve the current page until success, provide retry and
   discard responses from abandoned requests.

## Standard Activity example

The implemented consumer is
[ActivityPageController](../../src/ForumRewrite/Http/ActivityPageController.php).
After existing route authorization, its normal flow is equivalent to:

```php
use ForumRewrite\Pagination\PageNavigation;

$view = $activityService->normalizeActivityView($requestedView);
$context = 'activity:' . $view . ':date:desc';
$cursor = $position === null ? null : PageNavigation::decode($position, $context);
$page = $activityService->fetchActivityPage(
    $view, $cursor['boundary'] ?? null, $cursor['direction'] ?? 'next'
);
$pagination = PageNavigation::links(
    '/activity/', ['view' => $view], $context,
    $page['previous'], $page['next']
);
```

Use the controller's complete implementation for non-string input validation,
exception handling, empty-page recovery, item links and rendering. The example
shows the successful path; errors must not silently become an unrelated first
page. `activity.php` renders the shared partial once below the list.

## Query and consistency rules

[ActivityService::fetchActivityPage](../../src/ForumRewrite/Activity/ActivityService.php)
adds a standard-only query capped at 100 entries. Existing `fetchActivity`,
`fetchActivityRows` and Forte APIs retain their contracts; RSS remains a recent
100-item feed. Standard Activity keeps date-descending order, without new sort
controls.

- Filter accessible membership **before** limiting. Use the same eligibility and
  visibility rules for page queries, boundary availability and any totals.
- Activity orders by record time and canonical action key. Forward comparisons
  exclude the boundary; Previous queries reverse the comparison/order, then
  restore display order. Do not base canonical positions on disposable SQLite IDs.
- Boundaries need not refer to an existing anchor row. Deleting an anchor must
  not strand the reader. Probe available directions, including empty pages.
- An unchanged dataset traverses once without gaps or duplicates. New entries
  ahead of an existing boundary appear when returning to the first page.
  Results remain live: deletion, withdrawal and permission changes apply now.
  Refresh/Back does not promise an immutable historical snapshot.
- Source-less local events have no canonical keys. Activity includes them using
  a local fallback and fingerprints that unkeyed history in positions. Changes
  or reindexing of that history expire the cursor with a First page link.
  Canonical-only cursors survive rebuilt SQLite IDs.
- Keep the adapter independent of navigation rendering. Avoid migrating another
  list or introducing unused adapters merely to demonstrate an abstraction.

Changes to core read-model queries require the version, schema documentation,
prior-version startup regression and rebuild verification specified in
[CONTRIBUTING](../../CONTRIBUTING.md#read-model-schema-changes). Standard Activity
introduced this contract in read-model version 25 without changing table shape.

## Serving and access

Query-bearing standard Activity requests bypass first-page static artifacts;
`StaticArtifactBuilder` publishes only the first `/activity/` page. Preserve
normal publication invalidation. When deploying changed pagination markup, rebuild
or republish existing static HTML as well as updating the application/read model;
a previously published first page will not gain controls merely from a PHP
change. The normal operator command is:

```bash
./v3 build-static /path/to/content-repository /path/to/read-model.sqlite3 /path/to/static-artifacts
```

Use the artifact destination configured for that installation. Verify the actual FrontController path, not
only direct Application rendering. Never strip a position and serve page one
under an older-page URL.

Activity HTML is not cached or handled by the offline reader fallback. An
uncached offline request requires reconnecting and browser Reload/Back; it must
not receive an unrelated reader shell. Protected security review retains live
authorization/no-store behavior. Tokens cannot confer access. Do not preload or
cache protected rows in ordinary documents.

## Verification before adopting

Run shared contract checks plus the adapter's real UI flow. Cover first, middle,
last and empty pages; equal sort values; arrivals/deletions; rebuilds; malformed,
expired and cross-context positions; filter/order resets; direct links; history;
access changes; counts; evidence; and failed-request retry/return. Verify native
links without JavaScript, keyboard access, phone/desktop themes and long values.
Check existing shared-service consumers for regressions.

For the current implementation:

```bash
php tests/run.php StandardActivityPaginationTest LocalAppSmokeTest::testVersion24StartupRebuildsBeforeServingStandardActivityPages
php tests/run.php OfflineNavigationWorkerTest
node tests/browser/standard_activity_pagination.mjs
node tests/browser/forte_activity_parity.mjs
node tests/browser/signed_reactions.mjs
./v3 rebuild /path/to/content-repository /path/to/read-model.sqlite3
```

Browser tests require `playwright-core` and Chromium. Set `CHROMIUM_PATH` to the
installed executable if it is not `/opt/google/chrome/chrome`; the standalone
paging harness also accepts `PLAYWRIGHT_MODULE`. Node must be on `PATH` for the
worker-policy tests. Use isolated test repositories; do not rewrite user content
as a pagination fixture. Record local environment failures, deployment and
physical-device limits separately from focused passes.

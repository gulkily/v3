# Implementing pagination

Paged lists use plain page numbers: `/recent/?page=3`. Page 1 has no `page`
parameter. A list can also open the page holding a given post or thread with
`?with=<ID>`. Both follow the [URLs are interface](../../PRINCIPLES.md#urls-are-interface)
principle: no encoded, versioned or JSON state in page links.

Board, Search, Tags, Recent, Gallery, Activity and profile history use this
contract. Tags and Search share one thread adapter,
[ThreadKeysetPage](../../src/ForumRewrite/ReadModel/ThreadKeysetPage.php),
which adds a membership condition to board eligibility. Keep list-specific
queries and access rules in the list's service; reuse the page parsing and
navigation controls instead of building another paginator.

Forte Activity and Commits intentionally keep their **Load more** behavior. QDB,
Docs, the SQLite viewer, messages and account key history keep their own
implementations. QDB and Docs already use plain page numbers. Track the rest in
the [consolidation checklist](../plans/pagination_consolidation_checklist.md).

## Shared interfaces

[PageNavigation](../../src/ForumRewrite/Pagination/PageNavigation.php) provides:

- `pageNumber(value)`: the requested page. A missing, malformed, zero, array or
  out-of-range value reads as page 1; values are capped at 100000.
- `numbered(path, query, page, hasNext, restart, pageParameter, fragment)`:
  `previous_href`, `next_href` and `restart_href`. Page 1 links leave out the
  page parameter. `restart_href` (First page) appears only on a page past page 1.
- `withId(value)`: a usable `with` ID (letters, digits, `.`, `_`, `:`, `-`, at
  most 512 characters) or null.
- `withLocation(path, query, page, id, pageParameter, anchorPrefix)`: the
  address of the page holding an item, ending in `#post-<ID>` by default.

Pass only non-default state in `query`. For example, the board leaves out
`view=liked` and `sort=newest`, and Activity leaves out `view=all`.

The reusable [navigation partial](../../templates/partials/page_navigation.php)
renders Previous, Next and First page with `rel="prev"`/`rel="next"`, omits
unavailable links and needs no JavaScript. Add no new navigation destination or
routine help panel.

A list adapter returns `items` and `has_next`. Read one row more than the page
size to learn whether a next page exists. For `with`, add a `pageOf` that finds
the item under the same eligibility and counts the eligible rows ahead of it.

## Connect a list

1. Read the page with `PageNavigation::pageNumber`. Authorization stays a
   separate server check on **every request**, including direct links, reloads
   and history navigation.
2. Query a bounded page with a stable order: the list's sort key, then a unique
   ID as tie-breaker.
3. Build links with `numbered`, passing the list's non-default filter and order.
   Changing a filter or order starts from page 1.
4. A page past the end shows the empty state with Previous and First page. A
   malformed page shows page 1, never an error.
5. For `with`, look the item up before rendering. When found, redirect with
   303 to `withLocation`; the page number changes as items arrive, so the
   redirect must never be permanent. When the ID is unusable or not in the
   list, render page 1. Give each row `id="post-<ID>"` so the browser scrolls
   to it; `.thread-row:target` and `.gallery-tile:target` are outlined.

## Example: Recent

[RecentPageController](../../src/ForumRewrite/Http/RecentPageController.php) is
the smallest complete consumer:

```php
use ForumRewrite\Pagination\PageNavigation;

$number = PageNavigation::pageNumber($query['page'] ?? null);
$page = RecentPostRepository::page($pdo, $number, 50);
$pagination = PageNavigation::numbered('/recent/', [], $number, $page['has_next'], $page['items'] === []);
```

and, before rendering:

```php
$id = PageNavigation::withId($query['with'] ?? null);
$found = $id === null ? null : RecentPostRepository::pageOf($pdo, $id, 50);
if ($found !== null) redirect(PageNavigation::withLocation('/recent/', [], $found, $id)); // 303
```

Profile history keeps two lists in one URL with `threads_page` and
`posts_page`; each list's links carry the other list's page. A profile `with`
opens the Threads list for a thread the person started and the Posts list
otherwise, with `#threads-post-<ID>` or `#posts-post-<ID>` anchors, because a
thread's root appears in both lists.

## Query and consistency rules

- Filter accessible membership **before** limiting. Use the same eligibility for
  the page query, `pageOf` and any totals.
- Order by the list's key and a unique ID. Activity orders by record time and
  canonical action key; source-less local events use a local fallback key.
- Pages are live. A new item shifts later pages by one, so a reader may see one
  item again on the next page. A retraction shifts them the other way. This is
  accepted: a post's permalink, or a `with` link, is the stable way to point at
  it. Refresh/Back does not promise an immutable snapshot.
- Offsets scan past earlier rows. Measure deep pages before adding a list that
  grows large; see the timing in the
  [readable page URLs summary](../plans/readable_page_urls/readable_page_urls_step4_implementation_summary.md).
- Bound per-item detail as well as item count. Standard Activity uses only
  `relevant_files` and skips full commit enrichment; keep complete manifests on
  explicit commit pages.
- Keep the adapter independent of navigation rendering.

Changing a query to depend on new tables, columns or indexes still requires the
[read-model schema change steps](../../CONTRIBUTING.md#read-model-schema-changes).

## Serving and access

Any query string bypasses first-page static artifacts, so `?page=` and `?with=`
always render live. `StaticArtifactBuilder` publishes only first pages. When
deploying changed pagination markup, rebuild or republish existing static HTML
as well as updating the application:

```bash
./v3 build-static /path/to/content-repository /path/to/read-model.sqlite3 /path/to/static-artifacts
```

Verify through the actual FrontController path, not only direct Application
rendering. Never serve page 1 under a later page's URL.

The offline reader pages saved tag pages with the same `?page=N` numbers, so an
online link opens the matching saved page. Activity HTML is not cached for the
offline reader; an uncached offline request needs a reconnect. Protected
security review keeps live authorization and no-store behavior.

## Verification before adopting

Cover first, middle, last and past-the-end pages; malformed numbers; equal sort
values; arrivals and deletions; rebuilds; filter and order changes; `with` for
an item on page 1, a later page, a hidden item and an unknown ID; direct links;
history; and failed-request retry. Verify native links without JavaScript,
keyboard access, phone and desktop themes and long values.

```bash
./v3 test --smoke RecentPostTest GalleryPageTest ProfilePublicDetailsTest BoardListTest TagPaginationTest BoardSearchTest StandardActivityPaginationTest OfflineSnapshotPresentationTest OfflineNavigationWorkerTest
node tests/browser/recent.mjs
node tests/browser/profile_public_details.mjs
node tests/browser/tag_pagination.mjs
node tests/browser/board_search.mjs
node tests/browser/standard_activity_pagination.mjs
```

Browser tests require `playwright-core` and Chromium. Set `CHROMIUM_PATH` if it
is not `/opt/google/chrome/chrome`. Use isolated test repositories; do not
rewrite user content as a pagination fixture.

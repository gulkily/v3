# Performance Low-Hanging Fruit

A punch-list of small, well-scoped, high-confidence performance wins found by
tracing the request-handling hot paths. These favor localized changes over
architectural rewrites. Each item lists the file(s), the problem, why it costs
performance, a proposed fix, effort, and risk.

Scope note: the anonymous, cookie-less, query-string-free path is already well
optimized — `Host/FrontController.php` serves canonical routes from pre-rendered
static HTML via `HighTrafficCache` and never reaches dynamic rendering. The
items below target the paths that *do* reach PHP rendering: logged-in /
cookie-bearing viewers, query-string pages, single-thread permalinks, boards,
search, and RSS.

Findings were identified by reading the code, not from `TODO`/`perf` comments
(none exist for these). Line numbers are approximate and should be reconfirmed
at implementation time.

---

## 1. Viewer reaction/label lookups scan and parse the entire records directory, several times per request

**Files:** `src/ForumRewrite/ReadModel/ViewerTagLookup.php` (`postTags`, `threadTags`); `src/ForumRewrite/Http/ThreadAndPostPageController.php` (`viewerHasThreadTag`, `thread`); `src/ForumRewrite/Http/BoardPageController.php` (`viewerReactionStateForThreads`)

**Problem:** `ViewerTagLookup::postTags()` runs
`glob($repositoryRoot . '/records/post-reactions/*.txt')` and then
`file_get_contents` + parses **every** record file just to find the ones
belonging to the current viewer. `threadTags()` does the same against
`records/thread-labels/*.txt`. Cost scales with the *total* number of
reactions/labels on the instance, not with the posts on the page.

It gets worse because these are called multiple times in a single render:
- `ThreadAndPostPageController::thread()` calls the post-reactions scan twice
  (once for `flag`, once for `like`), and on qdb instances loops
  `foreach ($qdbVoteTags as $tag) { ViewerTagLookup::threadTags(...) }` — a full
  `thread-labels` scan **per tag** (an N+1 over tags), plus upvote/downvote probes.
- `BoardPageController::viewerReactionStateForThreads()` triggers three full
  directory scans per board render for a logged-in qdb viewer (`upvote`,
  `downvote`, `flag`).
- `viewerHasThreadTag()` independently globs and parses every `thread-labels`
  file just to answer one boolean.

**Why it costs:** O(total_records) filesystem `glob` + `file_get_contents` +
parse, repeated ~5+ times per thread render for a logged-in viewer. On an
instance with thousands of reactions this is the dominant request cost.

**Fix (localized):** Memoize the glob+parse once per request, keyed by
`repositoryRoot` + record family, indexed by `authorIdentityId`; then filter in
memory per tag. This collapses the 5+ scans into one. A follow-up (larger) option
is to push reactions/labels into the SQLite read model and query by identity.

**Effort:** Small–Medium. **Risk:** Low (pure read path; behavior unchanged).
**Impact:** High — the single biggest localized win.

---

## 2. Missing read-model indexes force full scans + filesorts on every board and thread render

**Files:** `src/ForumRewrite/ReadModel/ReadModelSchema.php` (`statements()`);
consumed by `src/ForumRewrite/ReadModel/ThreadRepository.php`

**Problem:** The schema defines indexes only on `activity` and `commits`. The
`posts`, `threads`, and `profiles` tables get only their primary keys. But the
hottest content queries rely on columns with no index:
- `ThreadRepository::replyPostsByThreadId()` —
  `WHERE posts.thread_id = :thread_id AND posts.thread_id != posts.post_id AND is_hidden = 0 ORDER BY posts.sequence_number` —
  no index on `posts(thread_id, sequence_number)` ⇒ full table scan + filesort on
  every single-thread permalink view.
- `ThreadRepository::boardRowsSql()` —
  `ORDER BY last_activity_at DESC, root_post_id DESC` with no index on
  `threads.last_activity_at` ⇒ scan + in-memory sort of the whole threads table
  on every board / tags / RSS render.
- `allReplyPostsByThreadId()` and the `LEFT JOIN profiles ... = posts.author_identity_id`
  join also benefit from an index on `posts.author_identity_id`.

Notably, `Offline/PublicOfflineSnapshotBuilder.php` already creates
`CREATE INDEX posts_thread_sequence_idx ON posts (thread_id, sequence_number, post_id)`
for the snapshot DB — the main read model is simply missing the same index.

**Why it costs:** SQLite performs full scans and in-memory sorts that grow
linearly with table size on queries run on nearly every dynamic content page.

**Fix (localized):** Add to `ReadModelSchema::statements()`:
```sql
CREATE INDEX posts_thread_sequence_idx ON posts (thread_id, sequence_number);
CREATE INDEX posts_author_identity_idx ON posts (author_identity_id);
CREATE INDEX threads_last_activity_idx  ON threads (last_activity_at DESC, root_post_id DESC);
```
Per `CONTRIBUTING.md` (read-model schema changes), this must also: bump
`ReadModelMetadata::SCHEMA_VERSION` (currently `25` → `26`), update
`docs/specs/read_model_schema_v1.md`, add/update a regression test that starts
from the prior schema version and verifies startup rebuilds before the changed
query runs, and verify `./v3 rebuild` plus the affected UI/API flows. Changing
`statements()` automatically changes `ReadModelSchema::fingerprint()`, which
triggers the rebuild.

**Effort:** Small (plus the mandatory schema-change checklist). **Risk:** Low —
indexes only; `ReadModelBuilder` rebuilds the disposable read model.
**Impact:** High on instances with non-trivial post/thread counts.

---

## 3. Board fetches ALL threads, then filters / sorts / searches in PHP

**Files:** `src/ForumRewrite/Http/BoardPageController.php` (`fetchBoardThreads`,
`welcome`, `random`, `search`, `rss`); `src/ForumRewrite/ReadModel/ThreadRepository.php`
(`fetchThreads`, `boardRowsSql`)

**Problem:** `fetchThreads()` runs the unbounded `boardRowsSql()` (no `LIMIT`),
hydrates every row (`json_decode` of `board_tags_json` + `thread_labels_json` per
thread), then the controller applies `array_filter` (`matchesView`) and `usort`
(`compareThreads`) in PHP. `search()` fetches **all** threads and does
`stripos($thread['root_post_body'], $term)` in PHP across the whole set.
`rss()` renders every thread.

**Why it costs:** Every board / search / RSS render reads, json-decodes, and
sorts the entire thread corpus in PHP regardless of how many rows display.
Compounds with #2 (the sort has no supporting index).

**Fix:** Move `ORDER BY` + `LIMIT` into SQL for the common views; for `search()`,
filter in SQL (`WHERE body LIKE ...`) rather than pulling everything into PHP.
Keep each list's access rules intact (see `docs/reference/pagination.md`).

**Effort:** Medium. **Risk:** Medium — must preserve existing filter/visibility
semantics (hidden bootstrap threads, retraction, per-view rules).
**Impact:** Medium–High, grows with corpus size.

---

## 4. `FastmodMetaRead::decorate()` row-by-row INSERT (no transaction) on every read-model connection

**File:** `src/ForumRewrite/Scoring/FastmodMetaRead.php` (`decorate`); reached via
`src/ForumRewrite/Http/RouteServices.php` (`pdo`)

**Problem:** Opening the read-model PDO wraps it with `FastmodMetaRead`. That
opens a *second* SQLite DB read-only to read active ids, then:
```php
$read->exec('CREATE TEMP TABLE fastmod_public_meta(post_id TEXT PRIMARY KEY)');
$q = $read->prepare('INSERT INTO temp.fastmod_public_meta VALUES(?)');
foreach ($ids as $id) $q->execute([$id]);   // one execute per id, no transaction
```
then creates a `TEMP VIEW threads` that shadows the real table with a
`json_each`/`json_insert` per row. So the `threads` the board query reads is a
json-processing view, built via a per-id insert loop on every connection.

**Why it costs:** A second DB open, an un-batched insert loop, and a json-view
build — per request — even on pages that never render meta tags. `RouteServices::pdo()`
memoizes the decorated connection (once per request), but the setup is still on
the hot read path.

**Fix (localized):** Wrap the insert loop in a single transaction
(`beginTransaction` / `commit`) — cheap and high-confidence. Optionally skip
decoration entirely on routes that never render thread labels, and/or batch the
insert as a multi-row statement.

**Effort:** Small. **Risk:** Low. **Impact:** Medium (every dynamic read path).

---

## 5. FrontController repeats `realpath()` / `clearstatcache()` and re-builds the feature-flag evaluator per request

**File:** `src/ForumRewrite/Host/FrontController.php` (`handle`,
`activeStaticReleaseRoot`, `firstExistingPath`)

**Problem:** On the universal request path, `activeStaticReleaseRoot()` calls
`clearstatcache(true, ...)` + `realpath()`, `firstExistingPath()` loops candidate
paths with `is_file()`, and
`FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)->isEnabled(...)`
is constructed multiple separate times in one `handle()` (e.g. once for
`APPROVED_MEMBERS_ONLY`, again for `IMAGE_CONTENT_ENABLED`) instead of reusing a
single evaluator.

**Why it costs:** Extra filesystem syscalls and repeated flag-file reads on
*every* request, before any content is produced. Individually small, but on the
universal path.

**Fix:** Build the `FeatureFlagEvaluator` once per request and reuse it; resolve
the static-release root once.

**Effort:** Small. **Risk:** Low. **Impact:** Low–Medium (every request, small
per-request saving).

---

## 6. (Medium-confidence) Asset fingerprinting may re-hash files per reference

**File:** `src/ForumRewrite/View/AssetFingerprint.php` (and `TemplateRenderer::assetPath`)

**Problem:** `TemplateRenderer::assetPath()` → `AssetFingerprint::fingerprintedPath()`
runs per asset reference during template rendering and computes a fingerprint
from file contents. If the hash is not memoized, the same files are re-hashed on
every reference and every request.

**Why it costs:** Repeated `hash_file`/`md5_file` on unchanged files.

**Fix:** Confirm whether `AssetFingerprint` already memoizes; if not, add a
per-request (or longer-lived) cache keyed by path+mtime.

**Effort:** Small (once confirmed). **Risk:** Low. **Impact:** Low–Medium.
**Status:** Needs confirmation before acting — do not treat as confirmed.

---

## Already well-optimized (do not "fix")

- **Anonymous canonical pages** are served from pre-rendered static HTML
  (`HighTrafficCache` / static artifacts / offline snapshots).
- **Activity commit manifests** (`Activity/ActivityService.php`,
  `activityCommitManifest`) are already cached persistently in
  `SqliteActivityCommitManifestCache` plus a request-local memo; only cheap href
  formatting is recomputed. This is the model to copy for item #1.

## Known-heavy but out of "low-hanging fruit" scope

- `ActivityPageController::rss()` calls `fetchActivity(... )` with no limit and
  fully transforms every item (signature checks, link building). `ActivityService`
  itself notes this is expensive over an unlimited row set. A bounded/limited RSS
  feed would help but is a larger behavioral change.

---

## Suggested order of attack

1. **#2 Indexes** — highest impact-to-effort; mechanical (mind the schema-change checklist).
2. **#1 Memoize viewer tag lookups** — biggest single win for logged-in viewers.
3. **#4 Wrap Fastmod insert in a transaction** — trivial, touches every read path.
4. **#5 FrontController flag/realpath reuse** — trivial, universal path.
5. **#6 Confirm asset fingerprint memoization** — investigate, then fix if needed.
6. **#3 SQL-level LIMIT/sort/search** — larger, do after the cheap wins.

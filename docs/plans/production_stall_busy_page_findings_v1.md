# Production Stall and Busy Page Findings V1

## Question

Production sometimes appears to stall for a long time instead of either returning the requested page or returning the busy page.

This investigation focused on the PHP front-controller path, static HTML artifact fallback, read-model rebuild locking, and write-path locking.

## Short Answer

The busy page only covers one narrow failure mode: a `Timed out waiting for execution lock:` exception that escapes to `FrontController::handle()`.

Several production paths can legitimately block without producing that exception at the outer front-controller level:

1. a request that wins the execution lock can spend a long time rebuilding the read model;
2. an anonymous cache-miss request renders the page and then synchronously builds the missing static artifact before PHP finishes the response;
3. lock timeouts raised during that best-effort artifact build are swallowed, so the request waits and then returns the original page rather than the busy page;
4. full static artifact builds rebuild the read model without the shared execution lock;
5. external analysis / agent-reply calls have their own long timeouts and are not part of the busy-page mechanism.

After checking the reported action more closely, a reply containing a hashtag was an even more direct explanation: hashtag-bearing replies bypassed the incremental read-model updater and forced a full read-model rebuild inside the write lock. That specific hot-path issue has now been fixed so labeled thread/reply writes use incremental post and thread-label updates, with full rebuild retained only as fallback. The synchronous cache-miss artifact build can still add a second wait after the reply returns or during the redirected page load.

## Relevant Request Flow

`public/index.php` creates `FrontController` with:

- repository root from `FORUM_REPOSITORY_ROOT`
- database path from `FORUM_DATABASE_PATH`
- static HTML root from `FORUM_STATIC_HTML_ROOT` or `state/static_html`

Source: `public/index.php:10-16`.

For each request, `FrontController::handle()` does:

1. configuration checks;
2. fingerprinted asset serving;
3. static artifact lookup for anonymous queryless GETs;
4. dynamic `Application::handle()`;
5. best-effort static artifact generation after eligible dynamic misses.

Source: `src/ForumRewrite/Host/FrontController.php:29-69`.

The busy page is only emitted here:

```php
if (str_starts_with($throwable->getMessage(), 'Timed out waiting for execution lock: ')) {
    $this->sendHtml($this->renderBusyError(), 503);
    return;
}
```

Source: `src/ForumRewrite/Host/FrontController.php:62-66`.

## Confirmed Findings

### 1. Most dynamic routes call `ensureReadModel()` before routing

`Application::handle()` skips `ensureReadModel()` only for `/api/version`. Everything else reaches `ensureReadModel()` before route dispatch.

Source: `src/ForumRewrite/Application.php:54-64`.

If the database is missing, stale, has unreadable metadata, a schema mismatch, a repository-root mismatch, or a repository-head mismatch, the request enters a shared execution lock and may rebuild SQLite before serving the page.

Source: `src/ForumRewrite/Application.php:391-445`.

Implication:

- If a request cannot obtain the lock within `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS`, it can hit the busy page.
- If a request obtains the lock, it will rebuild under that lock and the user waits for the rebuild instead of seeing the busy page.
- If production sets `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS` higher than the default `5`, lock contention can look like a long stall before the busy page.

### 2. The execution lock polls until timeout

`ExecutionLock::withExclusiveLockTimed()` tries `flock(... LOCK_EX | LOCK_NB)`, sleeps 100ms when locked, and repeats until timeout. The default timeout is 5 seconds unless `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS` is set.

Source: `src/ForumRewrite/Support/ExecutionLock.php:49-79`.

Implication:

- The busy page is intentionally delayed by the lock timeout.
- A high production timeout directly increases "stalls before busy."

### 3. Cache-miss artifact generation happens after dynamic rendering but before request completion

After `Application::handle()` returns, the front controller calls:

```php
$this->buildStaticArtifactOnEligibleMiss($method, $requestUri, $cookies, $staticArtifact);
```

Source: `src/ForumRewrite/Host/FrontController.php:54-61`.

For eligible anonymous queryless GETs, `buildStaticArtifactOnEligibleMiss()` constructs `StaticArtifactBuilder` and calls `buildSingleRoute()`.

Source: `src/ForumRewrite/Host/FrontController.php:225-255`.

The test suite confirms this is intentional: first request renders from PHP, writes the missing artifact, and the second request serves static HTML.

Source: `tests/LocalAppSmokeTest.php:1044-1068`.

Implication:

- The requested page may already have been echoed, but PHP has not finished the HTTP request.
- Under Apache/FastCGI buffering, the browser can still appear stalled until the artifact build finishes.
- This path does not send a busy page unless the exception escapes the post-render build method.

### 4. Lock timeouts during post-render artifact generation are swallowed

`buildStaticArtifactOnEligibleMiss()` catches all throwables and ignores them:

```php
try {
    $builder->buildSingleRoute($requestUri);
} catch (Throwable) {
    // Best-effort generation should not affect the current response.
}
```

Source: `src/ForumRewrite/Host/FrontController.php:251-255`.

`StaticArtifactBuilder::buildSingleRoute()` renders the route with a new `Application`, and that `Application` still calls `ensureReadModel()`.

Sources:

- `src/ForumRewrite/Host/StaticArtifactBuilder.php:88-98`
- `src/ForumRewrite/Host/StaticArtifactBuilder.php:244-247`

Implication:

- If post-render artifact generation waits on the execution lock and times out, that timeout is swallowed.
- The user sees neither the busy page nor an immediate page; they wait until the timeout or rebuild attempt finishes, then receive the original page.
- This is the clearest code-level match for the reported symptom.

### 5. Full static artifact builds rebuild the read model without the shared execution lock

`scripts/build_static_artifacts.php` calls `StaticArtifactBuilder::build()`.

Source: `scripts/build_static_artifacts.php:15-16`.

`StaticArtifactBuilder::build()` immediately runs `ReadModelBuilder::rebuild()` with no `ExecutionLock`.

Source: `src/ForumRewrite/Host/StaticArtifactBuilder.php:24-31`.

By contrast, `scripts/rebuild_read_model.php` does wrap rebuilds in `ExecutionLock`.

Implication:

- An operator or deploy process running `build_static_artifacts.php` can rebuild the SQLite read model outside the web app's shared lock protocol.
- Web requests may then contend at the SQLite/filesystem layer instead of the application lock layer.
- That kind of contention is not guaranteed to become the friendly busy page.

### 6. Write requests hold the execution lock across git, read-model refresh, and artifact invalidation

`LocalWriteService` wraps write actions in `withTimedWriteLock()`.

Source: `src/ForumRewrite/Write/LocalWriteService.php:43-45` and `src/ForumRewrite/Write/LocalWriteService.php:881-898`.

Inside that lock, writes can perform git commits and read-model updates. When incremental read-model update is unavailable or fails, the same request can do a full rebuild.

Sources:

- `src/ForumRewrite/Write/LocalWriteService.php:443-463`
- `src/ForumRewrite/Write/LocalWriteService.php:771-779`

Implication:

- A write that gets the lock can legitimately run for longer than the lock timeout because the timeout only limits waiting to acquire the lock.
- Other requests then wait for the lock and may eventually get the busy page.
- The writer itself will not return the busy page; it is doing the work.

### 7. Replying with a hashtag used to force a full read-model rebuild

`LocalWriteService::createReply()` extracts hashtags from the reply body. If any valid label is found, it writes both:

- the normal reply post record;
- a `records/thread-labels/*.txt` record targeting the thread.

Source: `src/ForumRewrite/Write/LocalWriteService.php:131-150`.

Hashtag extraction ignores quoted lines and accepts lowercase label tokens like `#answered` or `#needs-review`.

Source: `src/ForumRewrite/Write/LocalWriteService.php:935-958`.

The key branch is in `synchronizePostDerivedState()`:

```php
if ($hasThreadLabelWrite || !$this->canIncrementallyUpdateReadModel()) {
    $rebuildTimings = $this->refreshDerivedStateAfterCommit($commitSha);
    ...
}
```

Source: `src/ForumRewrite/Write/LocalWriteService.php:482-494`.

Before the fix, `createReply()` passed `$labelRecordPath !== null` as `$hasThreadLabelWrite`, and any reply with a valid hashtag used `read_model_rebuild` instead of `read_model_incremental_update`.

The test suite now locks in the fixed behavior:

- normal thread/reply writes assert `read_model_incremental_update`;
- labeled writes assert `read_model_incremental_update` and no `read_model_rebuild`;
- labeled writes assert post and thread-label incremental sub-timings;
- labeled writes still assert immediate `thread_label_add` activity.

Source: `tests/WriteApiSmokeTest.php:1024-1050` and `tests/WriteApiSmokeTest.php:1124-1144`.

Original local reproduction with a temporary writable fixture showed the pre-fix timing shape:

```text
normal reply:
  read_model_incremental_update=7.9
  total=22.6

hashtag reply:
  read_model_rebuild=13.2
  read_model_index_posts=2.7
  read_model_write_metadata=2.8
  total=27.8
```

Those fixture numbers are small because the fixture repository is tiny. In production, `read_model_rebuild` scales with all canonical records, all identities, all thread-label records, all post-reaction records, and activity derivation.

Post-fix local reproduction shows hashtag replies no longer rebuild:

```text
normal reply:
  read_model_incremental_update=5.2
  total=17.8

hashtag reply:
  read_model_incremental_update=5.5
  read_model_incremental_post_insert_post=0.1
  read_model_incremental_thread_label_update_thread_labels=0.1
  read_model_incremental_thread_label_refresh_thread_label_activity=0.1
  total=17.2
```

This was the highest-confidence cause for the specific slow action, and the targeted fix is in place.

## Likely Production Scenarios

### Scenario A: User posts a reply containing a hashtag

1. The body contains a valid hashtag such as `#answered`.
2. `createReply()` writes the reply record and a thread-label record.
3. The write path commits both records to git.
4. Before the fix, because a thread-label record was written, `synchronizePostDerivedState()` performed a full read-model rebuild.
5. The request held the shared execution lock for the whole write/rebuild.
6. The posting user saw a slow submit instead of a busy page, because their request owned the lock and was doing the rebuild.
7. Other users could see busy pages or stalls while waiting behind that lock.

This is the highest-confidence match for the observed slow action. The targeted code path has been changed to use incremental updates.

### Scenario B: Redirect or next anonymous page load rebuilds a missing artifact after the hashtag reply

1. The hashtag reply invalidates the thread/post artifacts.
2. The browser follows the create-reply redirect to the thread page.
3. If the redirected GET is anonymous/queryless enough to be artifact-eligible and the artifact is missing, the front controller can render PHP and then synchronously rebuild the missing artifact before finishing the response.
4. If that post-render artifact build waits on read-model/lock work, the browser can appear stalled and still not see the busy page because that best-effort build catches throwables.

This can stack on top of Scenario A.

### Scenario C: Read model is stale after deploy

1. Static artifact is absent, bypassed by cookie, or not eligible.
2. Dynamic PHP route enters `ensureReadModel()`.
3. If no other process holds the lock, this request rebuilds and stalls.
4. If another process holds the lock past timeout, it returns busy.

This explains a stall when one request "wins" the rebuild.

### Scenario D: `build_static_artifacts.php` runs during traffic

1. Operator/deploy script starts a static build.
2. The build rebuilds SQLite without `ExecutionLock`.
3. Web requests interact with the same database while the build owns SQLite-level locks.
4. The app-level busy page may not appear because the contention did not happen through `ExecutionLock`.

This should be fixed because it violates the app's own locking model.

### Scenario E: Long non-lock operations

Analysis and agent-reply endpoints can call external providers with long timeouts. For example, Dedalus timeouts default to at least 60 seconds in the application setup.

These are not normal page GETs, but they are another class of "not busy page" stalls because they do not use the execution-lock busy mechanism.

## Recommendations

1. Add an incremental path for hashtag-bearing thread/reply writes. Completed.

   The code already had `IncrementalReadModelUpdater::applyThreadLabelWrite()` for explicit thread-tag writes. The implemented fix makes a post write with labels perform both incremental steps after the combined git commit:

   - `applyPostWrite($record, $commitSha)`
   - `applyThreadLabelWrite($threadId, $commitSha)`

   This preserves immediate reply visibility, thread label updates, score updates, metadata updates, and `thread_label_add` activity without a full rebuild. Full rebuild remains the fallback if either incremental step fails.

2. Add a regression test for hashtag replies using incremental read-model update. Completed.

   The labeled-write expectation now checks an incremental timing shape while keeping assertions for rendered labels and `thread_label_add` activity. A fallback test covers failure during the thread-label incremental step.

3. Stop doing same-request artifact generation after page rendering.

   Either remove `buildStaticArtifactOnEligibleMiss()` from the request path, put it behind a very small budget, or finish the HTTP response first with `fastcgi_finish_request()` when available before attempting best-effort artifact generation.

4. Make `StaticArtifactBuilder::build()` use `ExecutionLock`.

   Full static builds should follow the same locking protocol as `scripts/rebuild_read_model.php`. This prevents deploy/static-build work from bypassing the busy-page mechanism.

5. Do not swallow lock timeouts after waiting a long time.

   If same-request artifact generation stays, it should first check whether the read model is ready and whether the execution lock is currently held. If the lock is held, skip artifact generation immediately rather than waiting and swallowing the timeout.

6. Add request-phase instrumentation for production.

   Log at least:

   - static artifact hit/miss
   - dynamic render duration
   - `ensureReadModel()` reason and duration
   - lock wait duration
   - post-render artifact build attempted/skipped/duration
   - whether `buildStaticArtifactOnEligibleMiss()` swallowed an exception

7. Check production configuration.

   Verify:

   - `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS`
   - whether deploys run `scripts/build_static_artifacts.php` while traffic is live
   - whether `/api/read_model_status` often shows `stale_marker=present` or `lock_status=locked`
   - whether hot artifacts such as `public/index.html`, `public/threads.html`, and thread/profile artifacts are being invalidated and rebuilt repeatedly

## Verification Performed

Ran:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/run.php
```

Result: all tests passed, including `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention`.

That confirms the explicit lock-timeout busy-page path works in the focused test. It does not cover the production symptom where work happens outside that exact escaped-exception path.

Additional pre-fix focused reproduction:

- created a temporary writable copy of `tests/fixtures/parity_minimal_v1`;
- rebuilt the read model;
- posted one normal reply and one hashtag reply through `LocalWriteService::createReply()`;
- confirmed the normal reply used `read_model_incremental_update`;
- confirmed the hashtag reply used `read_model_rebuild`.

Post-fix verification:

- `php -l src/ForumRewrite/Write/LocalWriteService.php`
- `php -l tests/WriteApiSmokeTest.php`
- `php -d zend.assertions=1 -d assert.exception=1 tests/run.php`
- repeated the normal reply versus hashtag reply timing reproduction and confirmed both use `read_model_incremental_update`.

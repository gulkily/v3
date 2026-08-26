# Production Instant Busy Page Simple Plan

## Goal

Make users see the existing `Meme Oven Is Busy` page immediately when production is already rebuilding or otherwise holding the shared execution lock.

## Simplest Remedy

Set the production lock wait timeout to zero:

```text
FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0
```

This uses the lock behavior that already exists. When another request or CLI process owns `forum-rewrite.lock`, a contending PHP request fails the lock acquisition immediately, the front controller catches the lock-timeout exception, and the existing friendly busy page is returned with HTTP `503`.

## Why This Is The Smallest Change

- It requires no PHP code changes.
- It keeps the current `Meme Oven Is Busy` template and status code.
- It preserves the current single shared lock contract for web writes, request-driven read-model rebuilds, and `scripts/rebuild_read_model.php`.
- It can be rolled back by restoring the previous timeout value or removing the environment variable.

## Implementation Steps

1. Add `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0` to the production Apache/PHP environment.
2. Reload Apache or PHP-FPM, depending on the host setup, so the new environment value is visible to PHP requests.
3. Confirm the runtime value from production with a temporary diagnostic or by checking the web server environment configuration.
4. Hold the production execution lock from a shell as the web/deploy user.
5. Request a dynamic PHP route that needs the lock.
6. Verify the response is immediate, uses HTTP `503`, and contains `Meme Oven Is Busy`.
7. Release the lock and verify normal pages still load.

## Validation Command Shape

Use the actual production paths:

```bash
flock /srv/forum-rewrite/state/cache/forum-rewrite.lock sleep 30
```

In another shell or browser session, request a dynamic route. If the route is served through PHP and needs the shared lock, it should return the busy page immediately.

## Important Limits

This remedy makes lock contention fail fast. It does not make every rebuild-related stall impossible.

- The request that wins the lock can still spend time rebuilding before it returns.
- Anonymous queryless routes served directly from `public/*.html` by Apache do not enter PHP, so PHP cannot render the busy page for those requests.
- `StaticArtifactBuilder::build()` currently rebuilds the read model directly; if production static builds bypass `scripts/rebuild_read_model.php`, they can still create contention outside the app-level busy-page path.
- Post-render best-effort artifact generation can still extend a PHP response if it reaches work that does not fail fast.

## Follow-Up If This Is Not Enough

If production still shows long stalls after setting the timeout to zero, implement the next-smallest code change:

1. Add a typed fail-fast lock exception to `ExecutionLock`.
2. Replace the front-controller string match with that exception.
3. Make `Application::ensureReadModel()` use the fail-fast lock path when the read model is stale or missing.
4. Skip same-request static artifact generation whenever the shared execution lock is already held.
5. Put full static artifact builds under the same execution lock, or require operators to run the read-model rebuild separately before static artifact generation.

Those follow-ups are intentionally outside the simplest remedy.

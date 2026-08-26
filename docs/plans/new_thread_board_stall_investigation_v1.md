# New Thread Board Stall Investigation

Date: 2026-06-26

## Symptom

After creating a new thread, the created thread page renders successfully, but subsequent board requests appear unresponsive for roughly 5-15 seconds.

## Current Findings

The first suspected cause was automatic post-create Dedalus analysis. That path can block the PHP built-in server because it starts `/api/analyze_post` from `public/assets/post_analysis.js` after a newly created post page renders. A local mitigation was added:

```php
'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => false,
```

The local private config currently loads that value. `PrivateConfig::load()` reports:

```text
DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED => false
```

The first implementation of the mitigation mishandled this exact config shape. The private config stores a PHP boolean `false`; the code cast config values to string before comparison. In PHP, `(string) false` is `""`, so the automatic-agent flag was accidentally treated as enabled unless it came from an environment variable string such as `"false"`.

That explains why restarting the server did not stop `/api/analyze_post`: the server was loading the intended config, but interpreting it incorrectly.

The flag parser now handles PHP booleans, numeric values, and common string values consistently. A reflection check against the real local config now returns:

```text
raw config value: bool(false)
agentRepliesAutomaticEnabled(): bool(false)
```

A regression test now covers a temporary PHP secrets file containing:

```php
return [
    'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => false,
];
```

The normal write path is also not slow in a clean probe. Using `./v3 start 127.0.0.1:8765`, `POST /api/create_thread` completed in about 23 ms wall time. Its `Server-Timing` showed:

```text
lock_wait=0.0ms
git_commit=7.4ms
read_model_incremental_update=5.9ms
write_total=19.4ms
total=19.6ms
```

Follow-up requests against the same server were also fast:

```text
GET /threads/<new-id>?created_post_id=<new-id>&__v=<sha>   ~12.6ms
GET / with identity_hint cookie                            ~8.5ms
POST /api/set_identity_hint                                ~3.9ms
GET /api/read_model_status                                 ~7.0ms
GET /threads/<new-id>                                      ~13.2ms
```

The current read-model metadata matches the writable repository:

```text
rebuild_reason=write_incremental
repository_head=7ddba1729eb29c4ed0b6bbc0c00b0e4814fbd4fb
repository_root=/home/wsl/v3/state/local_repository
schema_version=10
```

No stale read-model marker was present in `state/cache`.

## Ruled Out For Current State

- A normal successful `createThread()` taking 5-15 seconds.
- Immediate full read-model rebuild after every thread write.
- Automatic agent reply work being intentionally enabled by the current local config. It was still happening because of the boolean parsing bug above.
- Static artifact generation on the exact created-thread landing URL. That URL has query parameters, and `FrontController::buildStaticArtifactOnEligibleMiss()` skips query-bearing requests.

## Still Plausible

The PHP built-in server is single-process. Any one long request can make unrelated board requests look hung until it finishes. Since the clean curl path is fast, the remaining likely cause is a browser-only or stale-process follow-up request that the curl probe did not trigger.

Most likely candidates after the boolean parser fix:

1. A browser tab with old HTML containing `data-agent-reply-work`, causing `post_analysis.js` to call `/api/analyze_post`.
2. A request to `/api/analyze_post` or `/api/generate_agent_reply` from another tab. With live Dedalus config, that can occupy the local PHP server for the Dedalus timeout window.
3. The create-thread write itself sometimes taking the slow path on the user's actual interaction, unlike the clean curl probe. This should be visible in `Server-Timing`.
4. A rare incremental read-model failure falling back to full rebuild. This should show up in `Server-Timing` as `read_model_rebuild` or `read_model_*_fallback`.
5. Lock contention from another local command or request. This should show up in `Server-Timing` as `lock_wait`, or as a 503 busy page if the wait exceeds the lock timeout.
6. Queryless, cookie-free dynamic route misses that build static artifacts after rendering. This should not happen for the created URL, but could happen if a browser later requests `/threads/<id>` without query/cookies and no artifact exists.

The user observed that `/api/analyze_post` returns much faster than stalled `/api/set_identity_hint` and `/api/create_thread`. That means `analyze_post` is not necessarily the 20-second request itself. Its importance is that it proved the automatic-work disable flag was not being honored.

## Next Diagnostic Step

Use the browser Network tab immediately after creating a thread and identify the request that is pending for 5-15 seconds. The important columns are URL, Initiator, Status, and Timing.

If the pending URL is:

- `/api/analyze_post` or `/api/generate_agent_reply`: automatic work is still being triggered somewhere. After the boolean parser fix, this would likely be stale page HTML or another tab.
- `/api/set_identity_hint`: identity prewarm is blocked behind another slow request, not slow by itself.
- `/` or `/threads/<id>`: inspect `Server-Timing`; look for `lock_wait`, `read_model_rebuild`, or fallback timings.
- `/api/create_thread`: inspect `Server-Timing`; a 20-second server-side create should name the phase, such as `lock_wait`, `git_commit`, `read_model_rebuild`, or `read_model_incremental_*`.
- `/api/version`: unlikely to be the real blocker because it bypasses `ensureReadModel()`.

Useful curl comparison after a stall:

```bash
curl -sS -D /tmp/headers.txt -o /tmp/body.txt -w 'total=%{time_total} code=%{http_code}\n' \
  -b 'identity_hint=guest' \
  'http://127.0.0.1:8000/'

grep -i 'server-timing' /tmp/headers.txt
```

## Working Conclusion

There was a real bug in automatic-agent-work flag parsing. That bug explains why `/api/analyze_post` still appeared after restart despite the local private config setting.

The current repository state still does not reproduce a slow core write/read path in a clean curl probe. If the 20-second stall continues after this parser fix and a fresh page load, the next fix should be driven by `Server-Timing` from the slow `/api/create_thread` response or by adding per-request logging around `FrontController::handle()` to print method, URI, elapsed time, and phase names.

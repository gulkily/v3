# PHP Forum Rewrite Trimmed Parity Suite V1

This document defines the reduced parity and regression surface that `v3` should carry forward as a local implementation target.

## Scope

Keep parity coverage only for the retained rewrite surface:

- board index reads
- thread reads
- post permalink reads
- profile reads
- username profile route
- activity page and RSS behavior
- instance page
- compose thread page
- compose reply page
- account key page
- identity hint behavior
- `llms.txt`
- PHP static HTML / PHP fallback behavior
- core write submissions for thread, reply, and bootstrap flows

Do not port parity for merge, moderation, profile-update, task-planning/task-status, or thread-title-update features.

## Required Suite Buckets

### 1. Read routes

- `/`
- `/threads/<thread-id>`
- `/posts/<post-id>`
- `/profiles/<profile-slug>`
- `/user/<username>`
- `/instance/`
- `/activity/`
- `/?format=rss`
- `/threads/<thread-id>?format=rss`
- `/activity/?view=<mode>&format=rss`

### 2. Compose and account routes

- `/compose/thread`
- `/compose/reply?thread_id=<thread-id>&parent_id=<post-id>`
- `/account/key/`

### 3. Plain-text and cookie routes

- `/llms.txt`
- `/api/set_identity_hint`

### 4. Core write APIs

- `/api/create_thread`
- `/api/create_reply`
- `/api/link_identity`

### 5. PHP host/cache behavior

- direct anonymous static-HTML eligibility rules
- PHP-native fallback on cache miss
- cookie-aware bypass behavior
- missing-config failure page

## Historical Inspiration

Earlier `v2` tests may be consulted for design ideas only. They are not part of the `v3` authority set and must not be treated as a parity tie-breaker.

## Recreated Neutral Cases

Some historical cases are coupled to out-of-scope features. Recreate only the retained behaviors in `v3` with neutral fixtures:

- thread page and post permalink checks should use plain thread/reply fixtures rather than typed task roots
- profile read tests should cover bootstrap-backed profiles and username routing without profile-update writes
- write-path tests should keep immediate read-after-write visibility and canonical record side effects while omitting auto-reply and merge behavior
- activity tests should cover only `all`, `content`, and `code`

## Minimum Assertion Set

Each recreated parity case should verify at least one of these contracts:

- canonical route shape and status code
- expected content type
- shared page shell ownership
- required links or action routes
- RSS availability where applicable
- cookie/header behavior where applicable
- canonical record side effects for successful writes
- immediate read-after-write visibility for thread, reply, and bootstrap flows
- no dependency on request-time full-repo parsing for hot reads

## Fixture Dependency

The authoritative seed fixture tree for this reduced suite lives in:

- `tests/fixtures/parity_minimal_v1/`

That tree is intentionally limited to the retained record families:

- `records/posts/`
- `records/identity/`
- `records/public-keys/`
- `records/instance/`

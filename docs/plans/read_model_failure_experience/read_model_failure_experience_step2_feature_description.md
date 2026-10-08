# Step 2: Read-Model Failure Experience — Feature Description

## Problem

An obsolete read-model cache can expose raw SQL as a PHP configuration error. Visitors need a safe maintenance experience, while operators need recovery that distinguishes a recently healthy queue executor from an unavailable one, survives a disconnected visitor, and leaves evidence of progress.

## User stories

- As a visitor, I want a clear maintenance message without internal errors so that I know when to retry safely.
- As an operator, I want a recognized stale read-model failure to queue one rebuild when the executor is recently healthy so that recovery happens outside a visitor request.
- As a visitor, I want recovery still to start when the executor is not recently healthy so that the site can return without my browser remaining open.
- As an operator, I want terminal recovery failure to stop automatic requeues so that an unresolved fault does not consume resources repeatedly.
- As an operator, I want queue-executor lifecycle logging so that I can confirm execution and investigate failed work.

## Core requirements

- Recognize the supported missing-read-model-column condition; render a tailored, sanitized 503 response and sanitize all other unexpected failures.
- Persist executor lifecycle evidence, including a recent-success heartbeat, in the application-managed private queue database and queue status.
- When that heartbeat is fresh, enqueue—not directly run—one deduplicated rebuild and tell visitors the site will be back soon.
- When it is missing or stale, queue the rebuild once and invoke the configured detached CLI recovery launcher; show that rebuilding has begun and ensure the worker's lifetime does not depend on the request or browser connection.
- Record safe rebuild progress checkpoints and terminal outcomes; after bounded failure, block automatic starts/requeues until an operator explicitly resets recovery.
- Preserve the existing private operator status/recovery path; public responses and public status must not expose SQL, paths, or failure details.

## Shared component inventory

- **FrontController failure presenter:** extend the canonical public failure response; no parallel error page.
- **Internal task queue and rebuild task:** reuse its deduplication, bounded attempts, locking, and status; extend its private database with executor-run history, liveness, durable fallback launch, and progress reporting rather than a new queue.
- **Detached CLI recovery launcher:** add an opt-in deployment adapter for starting the existing queue worker when its normal executor is unhealthy; do not provide a generic shell-command endpoint.
- **`./v3 status` and `./v3 task-queue status`:** extend the canonical private status surfaces with executor freshness, recovery state, and logged-executor guidance.
- **Task-queue cron reference:** retain cron as the preferred executor and document its lifecycle records in the application-managed private queue database.
- **`/api/read_model_status`:** retain only safe aggregate recovery state; do not turn it into an operator-detail endpoint.

## User flow

1. A request encounters the recognized stale read-model condition.
2. If executor success is recent, the application queues/reuses one rebuild and says the site will be back soon.
3. Otherwise, it queues one rebuild, invokes the configured detached recovery launcher, and shows rebuilding progress while the work continues after the request ends.
4. The executor records progress and outcome; a visitor retries after recovery, while terminal failure remains a maintenance page until operator reset.

## Success criteria

- The missing-column response contains neither SQL/PDO text nor host paths.
- Repeated failures create at most one outstanding rebuild or fallback start.
- A recently healthy executor follows the queue path; an unhealthy/missing executor invokes at most one configured detached recovery launcher, whose worker remains active after disconnect.
- Operators can inspect each executor run's heartbeat, progress, and outcome through private queue status/history.
- A bounded failed recovery prevents further automatic attempts until reset.
- Existing normal, busy, and unrelated failure responses retain their expected behavior.

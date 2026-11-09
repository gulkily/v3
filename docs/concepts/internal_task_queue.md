# Internal task queue

Purpose: Run allowlisted maintenance work outside visitor requests, one task at a time, with deduplication and visible status.
Scenario: A retraction enqueues a cleanup sweep and a reader's agent-reply request enqueues a reply; an operator then queues a deploy. At the next cron minute the worker claims the deploy first (deploys go ahead of other queued work), runs it and marks it completed; the sweep and the reply follow on later runs in the order they were queued. Asking for the same work again while it is still queued returns the existing task instead of adding another. Each worker run records a heartbeat, which status pages use to warn when cron has stopped.
Sources: [store, task types and claim order](../../src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php) (`enqueue()`, `claimNext()`, `executorHeartbeatStatus()`), [worker](../../src/ForumRewrite/TaskQueue/TaskQueueWorker.php), [CLI and cron line](../../scripts/task_queue.php), [operator guide](../runbooks/production_deploy.md#internal-task-queue).

Automatic rebuild requests: page requests and deploys hand read-model rebuilds to the queue through [`AutomaticReadModelRebuild`](../../src/ForumRewrite/TaskQueue/AutomaticReadModelRebuild.php), with reasons `read_model_stale` and `read_model_schema`; repeat requests reuse one task, and a detached worker starts only when cron is not running and `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` is set.

Task types: read-model rebuild, retraction sweep, Fastmod sweep, offline snapshot, agent reply, Railspike assessment and [application update](application_update.md). The queue stores no per-task payload; work that needs one keeps it beside the queue (the deploy requester is in `state/app_update_queued.txt`).

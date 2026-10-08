# Step 1: Read-Model Failure Experience — Solution Assessment

## Problem statement

When an outdated read-model SQLite cache lacks `threads.vote_count`, the site labels the failure as PHP host misconfiguration and exposes its raw SQL exception instead of giving people a clear, safe recovery path.

## Option A — Generic public outage page

- Show a friendly temporary-unavailable message for every unexpected application failure, with retry guidance.
- Pros: removes internal SQL and path details consistently; smallest behavior change.
- Cons: does not identify a recoverable stale read model or help an operator fix it.

## Option B — Classify stale read-model schema failures

- Detect missing read-model columns and present a tailored maintenance page; retain a generic sanitized fallback for other failures.
- Pros: explains the actual condition in plain language; offers an appropriate rebuild action to an operator; protects unrelated exceptions.
- Cons: requires a maintained, deliberately narrow error classification boundary.

## Option C — Queue the rebuild for the cron worker

- Enqueue the existing deduplicated `read-model` rebuild task and return a maintenance page while the cron worker processes it.
- Use the queue's task state and bounded retries; after terminal failure, open a recovery circuit so requests cannot enqueue another identical rebuild until an operator resets it.
- Pros: no slow rebuild in a visitor request; existing queue serialization, status, and task history are reused; prevents rebuild loops.
- Cons: recovery depends on cron actually being installed and healthy; the current application can report task state but cannot prove that its cron entry exists or is running.

## Recommendation

Choose **Options B + C**: classify the missing-column failure, enqueue one deduplicated rebuild for the existing cron worker, and show a concise “site data is being updated” page with retry guidance. If the queued task exhausts its bounded attempts, open a terminal recovery circuit: later requests show “site data needs maintenance” and cannot enqueue another rebuild until an operator explicitly resets recovery. Expose no raw SQL; provide operator status and clear recovery guidance through non-public channels, and keep unrelated failures sanitized.

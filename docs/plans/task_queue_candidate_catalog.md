# Task Queue Candidate Catalog

## Purpose and audit scope

This catalog identifies every operational or background-style workflow found in the current application that could plausibly be run by the durable cron task queue. It deliberately includes workflows whose correct disposition is to remain outside the general queue.

The audit covers the commands exported by `./v3`, all PHP scripts in `scripts/`, existing scheduled workers, read-model and static-artifact maintenance, caches, and expiration/cleanup paths in `src/`. The existing `rebuild_read_model` task is the baseline and is not counted as an “other” task below.

## Queue admission rules

A task belongs in the general task queue only when all of these are true:

1. Its input is a small, validated, non-secret snapshot—not an arbitrary shell command or a filesystem path supplied later.
2. Repeating it is safe, or a stable deduplication key makes it idempotent.
3. A worker crash can be retried without leaving canonical content or public output in an unsafe state.
4. It needs durable retry, bounded work, or queue visibility. Cheap read-only checks should normally remain direct cron commands.
5. It does not bypass an authorization, approval, or explicit operator decision that must still be valid when work starts.

Every new type needs a fixed handler, per-type lock/concurrency policy, maximum attempts, and documented producer. The queue must never become an “execute this command later” mechanism.

## Recommended next task types

| Proposed type | Existing basis | Producer and deduplication | Why it fits | Required safeguards |
| --- | --- | --- | --- | --- |
| `build_static_artifacts` | `scripts/build_static_artifacts.php`; `StaticArtifactBuilder` | Operator/deployment or successful canonical-write follow-up; one outstanding task per site and source revision | Deterministic derived output, potentially slow, currently rebuilt synchronously by archive/import/delete flows; visitor misses can also trigger single-route best-effort builds. | Run only after a healthy read model for the captured revision. Serialize full builds with single-route generation. Publish assets before HTML and add whole-set staging/atomic publication before using this for production updates, so mixed versions are not exposed. |
| `backfill_unicode_risk_deterministic` | `scripts/backfill_unicode_risk.php --deterministic-only`; `SqliteUnicodeRiskStore` | Explicit operator/deployment enqueue when `UNICODE_RISK_SCHEMA_VERSION` changes; dedupe by schema version and site/database | Existing storage already keys results by post content hash and schema version, so reruns update only missing or obsolete deterministic analysis. It makes no network call or canonical write. | Require a usable read model; implement bounded batches/checkpoints rather than one uninterruptible all-post scan; record schema version and summary in task status. Do not enqueue it because a visitor loads a page. |

These are the only existing workflows that are strong, immediate additions to the general queue. Static artifacts should be next if operator time or slow write responses are the concern; deterministic Unicode backfill should wait for an actual schema/policy change that creates backfill demand.

## Viable only with additional product design

| Potential type | Existing basis | Why it is not ready unchanged | Design needed before admission |
| --- | --- | --- | --- |
| `audit_post_signatures` | `scripts/audit_post_signatures.php` | It is a read-only scan that prints findings and exits nonzero for invalid/unknown signatures. Cron can run it directly; queue state alone would lose record-level findings. | Define a private access-controlled report artifact or audit-results table, retention policy, alerting, and revision-based dedupe. Queue it only if scans become expensive or must be coordinated with maintenance. |
| `check_static_artifacts` | `scripts/check_static_artifacts.php` | Cheap read-only deployment health check with stdout/stderr reporting only; no retry-worthy mutation. | Keep it in deployment/cron monitoring unless it gains persisted findings and alert routing. If queued, record the artifact release/revision being checked. |
| `warm_activity_commit_manifests` | `SqliteActivityCommitManifestCache`; activity commit detail rendering | Manifest creation is expensive but demand-populated. No command enumerates target commits, and there is no progress or invalidation model. | Define a bounded commit range, revision/commit dedupe key, cache location, and progress counters. A separate late-public-key invalidation policy is required. Never delete the cache database from a general handler. |
| `refresh_activity_commit_manifests` | Same cache | Entries are immutable except for the documented late-key case, so periodic refresh wastes work and obscures the integrity trigger. | Define a precise trigger (such as a new public-key record), affected commit selection, and auditable invalidation. This may be a targeted warm task rather than a cache-wide purge. |
| `read_model_health_audit` | Forte capability inspection, read-model status endpoint, `ReadModelStaleMarker` | The queue already repairs known stale/incompatible state with `rebuild_read_model`; a periodic check is redundant unless it creates an operator-facing health record. | Define checks, retention, alert threshold, and relationship to the existing rebuild dedupe key. It must enqueue one rebuild rather than run a competing rebuild. |
| `artifact_release_garbage_collection` | Fingerprinted asset/static artifact layout; stale-artifact recovery planning | The code does not know which historical assets/releases remain served or are needed for rollback. Filename-age deletion can break cached HTML. | First implement versioned/staged artifact releases and retention. Then add a reachability-based, dry-run-first deletion task with rollback protection. It is unsafe today. |

## Workflows that must retain their existing queue

| Workflow | Current owner | Decision |
| --- | --- | --- |
| Agent reply generation | `post_generated_responses`, `SqliteAgentReplyGenerationStore`, `./v3 agent-reply cron run` | Keep its dedicated queue. It owns user-request lifecycle, post/content-hash deduplication, LLM analysis, generated canonical replies, and its own locking/status vocabulary. Folding it into maintenance would weaken those domain controls. |
| Approved Codex handoffs | `codex_handoffs`, `CodexHandoffStore`, `./v3 codex-handoff run` | Keep its dedicated queue. It requires explicit approval and starts a local Codex process with a narrow sandbox. A general cron queue must not gain the ability to launch development agents. |
| Unicode backfill with `--with-llm` | `scripts/backfill_unicode_risk.php --with-llm`, post-analysis and LLM-exchange stores | Do not add it to the general queue. It is billable external-provider work with credential, rate, retry, and per-post outcome requirements. If needed, design a dedicated analysis queue. |

## Commands that must remain explicit operator actions

| Command/workflow | Reason it is excluded from the general queue |
| --- | --- |
| `./v3 import-repository` | Imports an operator-supplied archive, writes canonical records, may create conflict files, stages/commits Git changes, then refreshes derived output. The archive path and conflict decision must be evaluated at invocation. Only derived follow-up work could later be decoupled. |
| `./v3 archive-thread` | Creates and verifies an archive, removes live canonical records, may commit deletion, and removes public artifacts. This is irreversible content administration requiring a contemporaneous operator decision. |
| `./v3 delete-record` | Deletes a selected canonical record and refreshes public derived state. It must never be executable later from a generic task payload. |
| `./v3 approval seed` / `approval approve` | Changes identity/approval governance and may write a canonical approval record. Authorization and target validity must be checked at action time, not merely when queued. |
| `./v3 private-config` | Handles private configuration and potentially secrets. Secret-bearing input and configuration mutation do not belong in a durable task database. |
| Local repository initialization/bootstrap | Creates or seeds repository state. It is deployment/bootstrap work, not recurring maintenance. |
| `./v3 agent-reply test` and provider diagnostics | Explicit, potentially billable external-provider diagnostic; remain operator initiated. |
| `./v3 thread-attributes` | Targeted read-only inspection output, not background work. |
| `./v3 agent-reply status`, cron-reference commands, and `./v3 task-queue status`/`cron` | These expose status or installation guidance; they do not describe domain work for a queue handler. |
| `./v3 test`, `agent-reply test-local`, and `codex-handoff test-local` | Test execution is a developer/CI action. It must not be triggered by a production maintenance worker. |
| `scripts/build_sqlite_query_catalog.php` | Rewrites checked-in/generated development assets from repository query sources. It belongs in build/CI, where generated diffs are reviewed, not on a production worker. |

## Other inspected paths with no task to create

There is no independent expired-state cleanup job today: prepared posts and identity-bootstrap records are checked when used, and authentication challenges report expiration when used. A deletion task would require a retention and recovery policy that does not exist.

Temporary directories/files used by archive import, OpenPGP inspection and verification, artifact writes, and request processing are cleaned by the creating operation. They are not a durable backlog. A future orphaned-temp cleanup task would need narrow owned directories, an age threshold, a lock, and dry-run/report mode before consideration.

Incremental read-model updates and static-artifact invalidation are canonical-write correctness work and must remain synchronous. The stale marker plus `rebuild_read_model` task is the recovery path when derived-state refresh cannot complete.

## Suggested implementation order

1. Add `build_static_artifacts` only after defining coherent release publication and its interaction with the read-model rebuild lock.
2. Add deterministic Unicode backfill when a schema change supplies a real producer; use resumable batching, not the current one-shot script unchanged.
3. If monitoring needs durable results, design persisted reports for signature and artifact audits before adding their task types.
4. Treat cache warming, garbage collection, and all LLM work as separate designs rather than extensions of the initial maintenance queue.

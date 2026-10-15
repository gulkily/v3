# Phone site-development intake: deployment and Codex configuration

This release lets an existing approved operator start a genuine Railspike Step 1 assessment from a post/reply on zenmemes.com, answer clarification questions, request revisions, and approve an exact assessment. The original thread holds the private controls and history. Approval records a planning handoff and stops. Steps 2–4, previews, application deployment buttons, and rollback buttons remain on the [full-goal checklist](../plans/phone_site_development_checklist.md).

## Runtime layout and prerequisites

Use the existing [production deployment](production_deploy.md) and [task-queue recovery](operator_recovery.md) procedures. The server needs PHP 8.1+, PDO SQLite, Git, GnuPG for browser-action verification, cron, `proc_open`, outbound model-service access, and a Codex binary with working Linux read-only sandbox support. No model runs in an HTTP request.

Example paths (adapt to the instance):

| Purpose | Example |
| --- | --- |
| Serving application | `/srv/zenmemes/app` |
| Canonical forum content | `/srv/zenmemes/content` |
| Public read model | `/srv/zenmemes/state/cache/post_index.sqlite3` |
| Private task/workflow database | `/srv/zenmemes/state/private/internal_tasks.sqlite3` |
| Separate assessment Git checkout | `/srv/zenmemes-assessment` |
| Private Codex credentials/output home | `/var/lib/zenmemes-worker/codex` |

Run the normal queue worker under the existing trusted queue service identity, separate from public request handling where hosting permits. Give it only the permissions required by the existing maintenance jobs. Its Codex subprocess uses an isolated read-only assessment checkout. Never grant the assessment worker production deployment keys or root privileges. A separate checkout is not itself a sandbox: verify the Codex sandbox actually works on this host. Do not bypass it to accommodate a restricted shared host.

The web process needs read/write access to the private SQLite database and its directory for journal/sidecar files; the queue worker must use the **same file**. Keep that directory outside the document root, canonical content repository, published artifacts, and public read model. Restrict ownership/group permissions to these service identities (for example directory `0770`, files `0660` with a shared private group and restrictive umask). Back up this private database separately; a content archive/read-model rebuild cannot reconstruct approvals.

## Install and authenticate Codex

Install a reviewed, pinned Codex CLI release for the worker's OS/architecture using the [official CLI installation instructions](https://learn.chatgpt.com/docs/codex/cli). The adapter was developed against CLI `0.162.1`; check the installed version and flags before enabling it:

```bash
/opt/codex/bin/codex --version
/opt/codex/bin/codex exec --help
```

The executable must support `exec`, `--ignore-user-config`, `--json`, `--sandbox read-only`, `--model`, `-C`, `--output-schema`, and `-o`. Pin upgrades deliberately and repeat acceptance after upgrading.

Choose one authentication method under the actual worker identity:

- **API key (recommended for unattended operation):** use a dedicated OpenAI project credential and its usage/billing controls. Inject `CODEX_API_KEY` into the trusted worker environment from a private secret manager or protected service configuration. The adapter passes it only to the Codex subprocess's restricted environment; never place it in the repository, web assets, prompts, process arguments, or logs. Keep it out of unrelated build/test environments. API usage is separate from ChatGPT subscription usage.
- **ChatGPT account:** set `CODEX_HOME` to the private path below and run `codex login --device-auth` as the worker identity. Open the printed login URL from your phone and enter the code. This requires device-code login to be available/enabled for the account/workspace. Persist the credential directory between worker invocations and treat its auth cache as a password. Account access/rate limits must be suitable for the intended use.

For example, after creating the private directory with the correct ownership:

```bash
CODEX_HOME=/var/lib/zenmemes-worker/codex /opt/codex/bin/codex login --device-auth
CODEX_HOME=/var/lib/zenmemes-worker/codex /opt/codex/bin/codex login status
```

See [official authentication](https://learn.chatgpt.com/docs/auth) and [automation authentication/structured output](https://learn.chatgpt.com/docs/non-interactive-mode). Do not copy credentials into this application's source checkout.

## Configure the instance

Provision `/srv/zenmemes-assessment` as a **separate Git checkout** at the reviewed application revision, including `docs/railspike/`. Install the source-inspection dependencies there. It cannot equal, contain, or be contained in the serving application checkout. Refresh it deliberately after deployments, while no assessment is running. Codex is not allowed to update this checkout itself.

Set the normal instance configuration and the following environment variables in the queue worker's private launch configuration. These are process environment variables; adding them to `secrets.php` alone does not configure this adapter.

```text
FORUM_REPOSITORY_ROOT=/srv/zenmemes/content
FORUM_DATABASE_PATH=/srv/zenmemes/state/cache/post_index.sqlite3
FORUM_TASK_QUEUE_DATABASE_PATH=/srv/zenmemes/state/private/internal_tasks.sqlite3
FORUM_CODEX_EXECUTABLE=/opt/codex/bin/codex
FORUM_RAILSPIKE_WORKSPACE=/srv/zenmemes-assessment
FORUM_RAILSPIKE_CODEX_HOME=/var/lib/zenmemes-worker/codex
FORUM_RAILSPIKE_MODEL=<explicit model available to the worker's account/project>
FORUM_RAILSPIKE_TIMEOUT_SECONDS=90
```

Set the same `FORUM_TASK_QUEUE_DATABASE_PATH` in Apache/PHP configuration. The web process does not need Codex credentials. Existing maintenance/provider variables still apply to the shared queue. The adapter starts Codex with a restricted environment, ignores user configuration, fixes read-only sandboxing and `approval_policy="never"`, and chooses the configured model explicitly. Saved CLI authentication still uses the configured `CODEX_HOME`.

The 90-second maximum is enforced even if a larger timeout is configured; the minimum is five seconds. Input is capped at 256 KiB, captured progress at 1 MiB, and final structured output at 128 KiB. The first 100 chronological thread posts form the initial snapshot. Oversized context or invalid output fails visibly instead of being silently truncated. Temporary schema/results are private and removed after normal completion; after a killed worker, an operator may remove orphan `assessment-*` directories when no job uses them.

## Deploy and start processing

1. Back up the private task database, deploy the reviewed application revision through the normal production procedure, and provision the separate assessment checkout at that revision. This feature adds private tables only; there is no public read-model migration.
2. Install/verify Codex and authentication as above. Confirm the worker identity can read the assessment checkout and create private output directories. Test outbound model access and sandbox support using the smoke procedure below.
3. Inspect the queue without executing work:

   ```bash
   cd /srv/zenmemes/app
   ./v3 task-queue status
   ./v3 task-queue run --dry-run
   ./v3 task-queue cron
   ```

4. Install the cron line printed by the last command under the worker identity, loading its private environment first. Keep a **single** normal queue-worker cron entry; do not add the legacy `codex-handoffs` runner for these workflows. The existing worker processes one task per invocation/minute by default. An accepted web action queues immediately; execution starts on the next available worker tick.
5. For a controlled initial run, use `./v3 task-queue run --limit=1` under that same identity/environment, or `--task-id=ID` to target the queued assessment. Never run an unlocked parallel custom executor.

The queue's existing exclusive execution lock serializes work. A bounded assessment can delay maintenance by up to its timeout. If that is unacceptable for the host, revisit scheduling in a separately approved change.

## Required acceptance before declaring the release ready

Use a real approved operator browser identity on a 390px-wide phone and a real Codex account/project:

1. Open a post tagged `railspike`, choose **… → Start development**, and confirm exactly one workflow/task even after repeated taps. Verify **queued → running → questions/review** and request-specific options, not the legacy generic handoff template.
2. In another thread, add `#railspike` through a reply and confirm the action becomes available on ordinary root/reply posts. Open a selected reply and repeat the journey.
3. Answer a question, request a revision, approve the displayed revision, and verify the recorded actor/digest and next-planning handoff. Approval must not launch Step 2, implementation, or deployment.
4. Refresh, rebuild the read model, and reopen the thread; private questions/history/approval must remain. Confirm unauthenticated and non-operator browsers cannot fetch controls, activity summaries, or artifacts. Confirm no output appears in public/static/offline HTML or downloadable read models.
5. Exercise a deliberate unavailable executor/configuration failure and **Retry assessment** after repair. Test an expired/interrupted claim and stale browser decision without duplicating a revision.
6. Record checkout revision/status before and after assessment; the serving application must not change. Record the CLI version, model, worker identity, acceptance outcomes, and any unresolved host restrictions without recording secrets.

Local regression commands:

```bash
php tests/run.php RailspikeWorkflowTest TaskQueueWorkerTest TaskQueueStoreTest TaskQueueCommandTest CodexHandoffDraftServiceTest CodexHandoffStoreTest CodexHandoffRunnerTest
node tests/browser/railspike_workflow_browser.mjs
```

The browser harness uses real OpenPGP actions and deterministic worker responses; it does not establish live Codex authentication, model access, or production readiness. Do not mark the first full-goal checklist item complete until the real-instance acceptance above passes.

## Recovery and deployment rollback

Check `./v3 task-queue status` for task IDs and workflow ID/state/version. The operator thread panel preserves questions, assessment revisions, and action history. No broad logs or model text are written to public activity records. A failed assessment exposes retry; repair worker authentication, executable/model access, sandbox, or private paths first.

The existing abandoned-claim threshold is **900 seconds**. After a killed worker, let the normal locked worker recover the expired claim and reconcile workflow state; then retry from the thread. Never manually approve by editing SQLite. A saved result is reconciled without publishing it again, and late results cannot replace newer versions. Queue-history pruning does not prune workflow evidence; plan private backup/retention separately.

To roll back deployment of this feature, stop/drain its queued work before reverting application code through the existing production procedure. Keep the private database and Codex home; do not restore an old public read model over them or delete the durable approval records. An older queue worker does not support `railspike_assessment` jobs, so pause its cron until outstanding new-type jobs are accounted for. Re-enabling the reviewed release can resume retained workflow state. This operational rollback procedure does **not** provide the future user-facing rollback button or automatic mutable-data restoration.

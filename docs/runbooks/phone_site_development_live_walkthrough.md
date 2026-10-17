# Phone development: live setup and resumption notes

Session: 2026-10-16–17. Live acceptance remains in progress.

## Confirmed setup

- Server account: `dh_zsagad` on `vps22365`.
- Production checkout: `/home/dh_zsagad/v3`.
- Separate assessment checkout: `/home/dh_zsagad/v3-assessment`; its origin is the production checkout.
- Both checkouts reached `61241c77` (explicit unsandboxed assessment support).
- PHP 8.2.30, PDO SQLite, SQLite3, `proc_open`, Git, and GnuPG are available.
- Codex CLI 0.163.0 installed using the official standalone installer; executable: `/home/dh_zsagad/.local/bin/codex`. Installation occupied approximately 411 MB; 85 GB was free at setup.
- ChatGPT device login succeeded. Credentials remain in `/home/dh_zsagad/.codex`; never copy their contents into documentation.
- User namespaces work, but mount namespace propagation fails with permission denied. Bubblewrap cannot start; the deprecated Landlock fallback also fails because this CLI requires bubblewrap for filesystem-restricted execution.
- The operator explicitly accepted unsandboxed execution under the production account. This mode has the account’s filesystem/network access; the separate checkout does not enforce isolation.

## Completed steps

1. Inspected production Git status and backed up `public/.htaccess` to a timestamped file in the account’s home directory.
2. Preserved the local `.htaccess` settings and existing untracked files. The local settings were `FORUM_UNICODE_AUTHORED_TEXT=true`, `FORUM_APP_VERSION_NOTIFICATION=false`, `DEDALUS_AGENT_REPLIES_ENABLED=false`, and `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0`.
3. Confirmed production received the phone workflow merge, then pulled the explicit sandbox-mode change.
4. Installed Codex, authenticated with ChatGPT, and created the separate assessment checkout using `git clone --no-hardlinks /home/dh_zsagad/v3 /home/dh_zsagad/v3-assessment`.
5. Verified a minimal unsandboxed Codex run returned `OK` using model `gpt-6.1-sol`.
6. Validated application adapter readiness and ran a real adapter assessment. It returned valid clarification questions; neither checkout changed afterward.
7. Saved the following six environment variables in `/home/dh_zsagad/.config/v3/railspike.env`, outside the repository. The directory was created with mode 0700 and the file with umask 077:

   ```sh
   FORUM_CODEX_EXECUTABLE=/home/dh_zsagad/.local/bin/codex
   FORUM_RAILSPIKE_WORKSPACE=/home/dh_zsagad/v3-assessment
   FORUM_RAILSPIKE_CODEX_HOME=/home/dh_zsagad/.codex
   FORUM_RAILSPIKE_MODEL=gpt-6.1-sol
   FORUM_RAILSPIKE_SANDBOX_MODE=unsandboxed
   FORUM_RAILSPIKE_TIMEOUT_SECONDS=90
   ```

8. Disabled the old queue cron entry and enabled this single queue-worker entry:

   ```cron
   * * * * * cd /home/dh_zsagad/v3 && (set -a; . /home/dh_zsagad/.config/v3/railspike.env; set +a; php scripts/task_queue.php run --quiet --limit=1) >> /home/dh_zsagad/.config/v3/task-queue.log 2>&1
   ```

   The separate existing `run_agent_reply_requests.php` cron job was preserved.
9. Submitted a live tagged post from the site. The private panel progressed from queued to revision 1 clarification questions. The operator later reported Markdown output returned and otherwise looked good; the exact subsequent revision/state was not captured.

The private task database is `/home/dh_zsagad/v3/state/private/internal_tasks.sqlite3`. Unlike the public read model, it stores workflow history and approvals. A backup was proposed but not confirmed performed.

## Resume here

- Live test thread: <https://zenmemes.com/threads/thread-20261017011535-2dd9cbf7>.
- Request: “please assess the readiness of the developer harness. #railspike”.
- Confirm the current private workflow state and whether clarification answers were submitted.
- **Reported issue:** assessment text is returned as Markdown, but the interface does not render Markdown. Inspect the renderer and choose an appropriate presentation fix. No rendering fix was made in this session.
- Possible display issue: the question text appeared both in revision history and above the answer fields. Verify in the browser before treating it as a defect.
- Continue the live journey through clarification, assessment, requested revision, and exact-revision approval. Approval must stop at the Step 2 handoff.
- Remaining acceptance includes persistence after refresh/read-model rebuild, anonymous/non-operator privacy, retry after failure, and interrupted/stale-action recovery. Do not mark the full first slice accepted yet.
- The complete phone journey through implementation, review, deployment, and rollback remains future scope.

## Useful checks

```sh
cd /home/dh_zsagad/v3
./v3 task-queue status
tail -n 30 /home/dh_zsagad/.config/v3/task-queue.log
```

For manual adapter commands in a new SSH session, first load the saved environment:

```sh
set -a
. /home/dh_zsagad/.config/v3/railspike.env
set +a
```

Cron processes at most one task per invocation. A queued assessment may wait behind other work, and the assessment itself is capped at 90 seconds. The initial adapter request took noticeable time; no precise elapsed duration was recorded.

See the [deployment runbook](phone_site_development.md) and [full-goal checklist](../plans/phone_site_development_checklist.md).

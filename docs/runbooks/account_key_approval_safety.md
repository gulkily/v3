# Account key approval safety

This release adds explicit approval review, durable acceptance receipts, account key history, and authenticated operator correlation. The cross-account flag defaults to **enabled** for compatibility. Setting `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false` restricts future grants by ordinary members; it does not remove existing access.

## Operate and investigate

Use Tools → Feature Flags to inspect the effective setting and its source. An environment override takes precedence over site settings. In restricted mode, members may sponsor a new username or add a key to their own username. A canonically root-seeded operator can authorize another existing account. A display label of “root” does not confer this authority.

Profile and `/user/<username>#account-keys` pages show paginated approved keys and available acceptance evidence in classic and Forte. New approvals show their authorizer, acceptance time, classification, count, authority, and policy snapshot. Unknown historical facts stay unknown, including root-seed acceptance dates. Current audit review candidates do not establish a policy violation.

Authenticated root-approved operators can open `/activity/?view=security` or `/forte/activity/?view=security`, also linked from Feature Flags. Authenticate with the operator key from Account Key first; an identity hint alone is insufficient. Review grants beside recorded signed policy changes and follow account, grant, receipt, and commit evidence. Missing environment/deployment change history remains unknown. Use source commits when second-resolution timestamps tie. These views and their APIs use no-store; no security RSS feed is published.

```bash
./v3 approval audit --repository-root=/path/repository --database-path=/path/read-model.sqlite3 --json
```

The audit includes acceptance facts separately from current-state categories. The account pages show at most 50 keys per page by default and provide pagination; history-service requests cap page size at 100. Candidate grant reads are bounded per key. A history page is a view of currently approved keys, not a key-revocation log.

## Deploy and verify

Production deployment requires separate authorization. Use an isolated checkout and content copy for rehearsal. Preserve repository history, `records/approval-acceptances/`, grant records, signatures, and the instance's existing private configuration/backups.

1. Put the instance into maintenance for canonical writes while replacing code. Stop or block old web/CLI/background writers; older protocol routes can bypass the new restriction. A mixed-version writer rollout is unsafe.
2. Deploy the complete release, keeping the configured repository, database, static roots, site profile, environment overrides, and private-data locations consistent across web and CLI.
3. Rebuild the derived model and regenerate static artifacts before reopening writes. No canonical database migration is required; the rebuild also refreshes acceptance-time activity and corrects historical invitation expiry replay.

```bash
./v3 rebuild /path/repository /path/read-model.sqlite3
./v3 build-static /path/repository /path/read-model.sqlite3 /path/static-html
./v3 status --repository-root=/path/repository --database-path=/path/read-model.sqlite3
./v3 approval audit --repository-root=/path/repository --database-path=/path/read-model.sqlite3 --summary
```

4. Verify the effective flag value. If restriction is intended, explicitly disable cross-account approvals in site settings or the deployment environment; the default remains enabled.
5. In a rehearsal instance, verify first-key sponsorship, own-account key addition, denied ordinary cross-account approval when restricted, permitted operator override, and signed invitations. Check all three confirmation labels, the exact target key, cancellation, expiry, changed-context review/retry, and member access after success. Disabling the flag afterward must preserve accepted keys.
6. Inspect classic/Forte history and receipt source links. Verify anonymous and member requests cannot open operator pages/APIs, forged identity hints do not authorize them, responses use no-store, and RSS cannot return security review. Confirm private-site profile/source boundaries and static/offline behavior.
7. Repeat a rebuild and a trusted repository archive restore in rehearsal. Compare approved keys and immutable receipt classifications/counts/policy. Remote content imports must continue excluding authority records. Reopen writes only after the rehearsal and instance checks pass.

For local regression checks:

```bash
php tests/run.php ApprovalPolicyTest ApprovalAcceptanceTest ApprovalHistoryTest ApprovalAuditCommandTest
node tests/browser/approval_review.mjs
node tests/browser/account_key_approval.mjs
node tests/browser/invite_zero_click.mjs
```

Browser tests use isolated temporary repositories and real HTTP/signing where indicated. They do not authorize production grants or deployment.

## Recover a failed request

A denied or changed-context request has not authorized a new grant; refresh and review the account/key again. An expired preparation needs a fresh preparation and signature. Never silently broaden the original approval after a key count, username, authority, or policy change.

An error stating that the canonical write committed means the grant and receipt already exist. Do not create a substitute grant. Repair the read model with the configured paths, then retry the original signed request or refresh the account. Exact signed direct retries recover committed success, including after a refresh failure, and retire stale approval/account artifacts without duplicating the grant. The same-recipient invitation recovery preserves its original grant and destination. Persistent cache or filesystem failures require operator repair; the committed record remains authoritative.

A failed commit removes incomplete grant/signature/receipt files in the handled failure path. After an interrupted process or unexpected dirty canonical state, inspect Git status before rebuilding or retrying. Do not blindly delete files or commit a partial grant bundle. Restore the complete committed bundle from backup or resolve the interrupted write under maintenance.

## Rollback limits

Preserve all committed grants, signatures, and receipts. Rolling back application code does not revoke keys. Older code may ignore the restriction, omit receipts for new grants, or remove expired invitation grants during rebuild. Keep all canonical approval/invitation and legacy generic writers stopped during an unsafe rollback. Do not let an older reader rebuild the live model or publish regenerated artifacts without first verifying receipt/expiry compatibility on a copy. Prefer restoring this compatible release and repairing projections.

Notifications, unfamiliar-key reporting, revocation/session invalidation, pairing, and recovery remain separate work. Neither a policy toggle nor hiding a history entry removes access already granted.

Receipt format: [approval acceptance receipt v1](../specs/approval_acceptance_record_v1.md). Related guidance: [authentication modes](../reference/authentication_modes.md) and [instance content import](instance_content_import.md).

## Converting approval receipt storage

Use `./v3 approval receipts inspect`, then `convert --dry-run` with the instance's
repository and database paths. Resolve reported evidence conflicts before applying
`convert`. Only old receipts in a clean repository are replaced; grants and
signatures stay byte-identical. Conversion retains private recovery evidence until
its commit and read-model refresh succeed. Retry the same command after failures;
never delete the journal or re-approve a grant to repair conversion.

An unexpected HEAD or edited receipt stops recovery without overwriting the edit.
Preserve the changes separately and reconcile them against the journal's recorded
base commit before retrying. Inspection reports only the current repository, not
all historical backups. See [CLI recovery](../reference/v3_cli.md#approval-receipt-conversion).

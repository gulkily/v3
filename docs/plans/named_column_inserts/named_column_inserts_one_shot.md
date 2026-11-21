# Named-column inserts

Cycle v3/named_column_inserts · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/named_column_inserts`; host ledger [named_column_inserts.md](../effort_ledger/named_column_inserts.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.15**

Status: plan written; next permitted action: implement.

## Original request

"Can you please write commit and push to change this and also audit our code for this and not do it again in the future?" (Ilya, 2026-11-21, about SQL inserts that don't name their columns, found while planning [deploy_rollback](../deploy_rollback_step2_feature_description.md).)

## Authorization

The request authorizes a bounded one-shot: audit, fix, guard, commit and push to a feature branch. It does not authorize a merge, a deploy, or any table change.

## Outcome and work type

Application change. Every runtime `INSERT` in `src/` and `scripts/` names its columns. A test fails when a new one doesn't. `CONTRIBUTING.md` states the rule. No table, column or stored data changes.

## Why

An insert without a column list breaks as soon as its table gains a column. Older code then fails after a rollback, and newer code fails against a table it hasn't migrated yet. With named columns, a new column that has a default is safe for both.

## Audit (baseline `09a15f6a`)

Runtime inserts without column names, to fix:

- `Messaging/PrivateMessageReadState.php`: 1 (`private_message_tracking`).
- `Messaging/PrivateMessageHistorySyncStore.php`: 10. Nine name their table; one in `advance()` builds the table name. One of the nine sits in the `history_donor_delete` trigger.
- `Messaging/PrivateMessageHistoryRevision.php` `ensureEpoch()`: 1.
- `Scoring/LocalMetaScores.php`: 1 (`local_rubric_scores`).
- `Scoring/FastmodMetaRead.php`: 1, into a temporary table made on the line above. No risk, fixed so the guard needs no exception for it.

Left as they are, and listed as exceptions in the guard:

- `ReadModel/ActivityCounterSchema.php`: 2 seed rows.
- `Messaging/PrivateMessageHistoryRevision.php` `statements()`: a seed row and the trigger body.

These four are part of the read-model schema list. `ReadModelSchema::fingerprint()` hashes that list, so editing them would force a full rebuild on every instance at its next deploy. They run only beside the `CREATE TABLE` in the same list, so they can't meet a table from another version. Fix them the next time `SCHEMA_VERSION` changes for another reason.

Also checked, no change needed: multi-line inserts in `PrivateMessageStore.php` and `SqliteFastScoreStore.php` name their columns. The three `FETCH_NUM` reads select named columns, not `*`. `tests/` has 49 such inserts in fixtures; they are outside the guard and noted as a follow-up.

## Risks

- **A wrong column order changes what is stored.** Mitigate by copying each list from the `CREATE TABLE` in the same file, and by running the existing tests for each store.
- **Stored triggers don't change.** `CREATE TRIGGER IF NOT EXISTS` leaves the old `history_donor_delete` text in existing databases. That is harmless: both texts do the same thing. A later column on `history_donor_revision` must recreate the trigger.
- **The guard misses or over-reports.** Mitigate by testing its pattern against known good and bad statements.

## Reuse decisions

- Test style: a source-scanning test like `NavIconSetsTest.php`. No new tooling or dependency.
- Rule text: `CONTRIBUTING.md`, beside "Read-model schema changes".

## Completion conditions

`./v3 test --smoke` plus the tests for each touched store and the new guard pass. The branch is pushed. Production needs no migration; rollback of this change is a plain revert.

Concepts touched: none.

## Verification plan

`./v3 test --smoke SqlInsertColumnsTest PrivateMessageReadStateTest PrivateMessageHistorySyncTest LocalMetaSweepTest` plus any Fastmod meta-read test found. Full suite not run.

Starting revision and branch: `09a15f6a`, `fix/named-column-inserts`. Planning commit: pending.

## Study history

- 2026-11-21 UTC, intake and entry: **0.15**, after the audit (no pristine intake). Claude, single exposed rater. R1 not reverified this session.

— Claude · 2026-11-21 UTC · Model: claude-fable-5-2

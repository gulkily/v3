# Read-model insert columns

Cycle v3/read_model_insert_columns · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/read_model_insert_columns`; host ledger [read_model_insert_columns.md](../effort_ledger/read_model_insert_columns.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.05**

Status: plan written; next permitted action: implement.

## Original request

"let's fix all these as three one-shots?" (Ilya, 2026-11-22), about the three follow-ups left by [named_column_inserts](../named_column_inserts/named_column_inserts_one_shot.md). This is the first: the four read-model schema inserts that still rely on column order.

## Authorization

A bounded one-shot through an open PR. Not authorized: a merge, a deploy, or any table change.

## Outcome and work type

Application change. The four inserts name their columns, and `SqlInsertColumnsTest` loses its exception list, so the rule has no exceptions in `src/` or `scripts/`.

- `ReadModel/ActivityCounterSchema.php`: the two seed inserts.
- `Messaging/PrivateMessageHistoryRevision.php` `statements()`: the seed insert and the trigger body.

## Cost the maintainer should know

`ReadModelSchema::fingerprint()` hashes these statements, and startup compares it. Each instance therefore runs one full read-model rebuild at the first deploy that includes this change. Nothing else changes: same tables, columns, indexes and trigger behavior. The PR says so, and the maintainer chooses when to merge.

This is not a schema change under `CONTRIBUTING.md` (no table, column, index, constraint or query changes), so `SCHEMA_VERSION` and `docs/specs/read_model_schema_v1.md` stay as they are. The fingerprint alone triggers the rebuild.

## Risks

- **A wrong column order corrupts the seed rows.** Mitigate by copying each list from the `CREATE TABLE` beside it, and by running the activity-count and history tests.
- **Side stores share the trigger text.** `statements()` also builds triggers for the direct-message history stores with `CREATE TRIGGER IF NOT EXISTS`. Existing databases keep their old trigger, which does the same thing.

## Reuse decisions

No new code paths. The guard and the rule already exist; this removes the guard's exceptions and one sentence in `CONTRIBUTING.md`.

## Completion conditions

Smoke set plus `SqlInsertColumnsTest`, `ReadModelSchemaTest`, and the activity and direct-message history tests pass; `./v3 rebuild` succeeds locally. PR open. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: [read-model rebuild](../../concepts/read_model_rebuild.md), used unchanged.

Starting revision and branch: `cc6680c9`, `fix/read-model-insert-columns`.

## Study history

- 2026-11-22 UTC, intake and entry: **0.05**. Claude, single exposed rater (already knew the code from the parent cycle). R1 not reverified this session.

— Claude · 2026-11-22 UTC · Model: claude-fable-5-2

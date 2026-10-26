# Read-model maintenance follow-ups (from viewer_tag_read_model_and_memoization)

Two cross-cutting items surfaced during the `viewer_tag_read_model_and_memoization`
cycle (completed and archived; see
`docs/plans/archive/viewer_tag_read_model_and_memoization/viewer_tag_read_model_and_memoization_step4_implementation_summary.md`).
Neither was in that cycle's scope; both are recorded here so they are not lost
when the cycle is archived.

## 1. Derive `dropSchema()` from `ReadModelSchema::statements()` — DONE (2026-10-25)

**Resolved.** `ReadModelBuilder::dropSchema()` now derives its table list from
`ReadModelSchema::tableNames()` (parsed from the `CREATE TABLE` statements), so a
new table is dropped-and-recreated on in-place rebuild automatically with no
hand-maintained list. Covered by `tests/ReadModelDropSchemaTest.php` (tableNames
matches the schema; in-place rebuild recreates every table without collision) and
documented in `docs/specs/read_model_schema_v1.md` (Schema-change contract).

Original note (for history):

`ReadModelBuilder::dropSchema()` hand-maintains a hardcoded `DROP TABLE IF EXISTS`
list. Any new read-model **table** must be added there too, or in-place rebuilds
fail with `table <name> already exists`. This bit the viewer_tags cycle (Stage 2)
and was caught only by the `read_model_hot_indexes` startup-rebuild regression
test. Indexes are safe (dropping a table drops its indexes), so the earlier
hot-index cycle never had to touch `dropSchema`, which is why the list looked
complete.

- **Proposal:** derive the drop set from the table names in
  `ReadModelSchema::statements()` (parse `CREATE TABLE <name>`), so adding a
  table to the schema automatically drops it on rebuild. Removes a silent
  footgun for every future read-model table.
- **Risk/size:** small, localized to `ReadModelBuilder`/`ReadModelSchema`; needs
  a test that a schema-only table addition rebuilds in place without collision.

## 2. Remove `inactive` / `excludedPublicationPosts` exclusions from thread-label derivation

During the cycle the user indicated "we're getting rid of" the
`inactive`/publication exclusions. The cycle applied that **only** to the
`viewer_tags` projection (so viewer like/flag/vote/label state is byte-identical
to the former file scan, including on retracted/removed-source threads). The
broader removal — dropping those exclusions from the thread-label **score / label
/ vote-count derivation** in `ReadModelBuilder::indexThreadLabels()` and
`IncrementalReadModelUpdater` — was explicitly **not** done, because it changes
observable board listings, scores, and vote totals on retracted/removed-source
threads.

- **Proposal:** a dedicated cycle to decide and implement whether (and how)
  retracted/removed-source thread labels should continue to affect scores,
  labels, and vote counts, with parity/behavior-change acceptance notes.
- **Risk/size:** medium–large, cross-cutting (board, Forte, qdb listings and
  scores; incremental + full rebuild paths; retraction semantics). Needs its own
  solution assessment.

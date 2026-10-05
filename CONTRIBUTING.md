# Contributing

## Read-model schema changes

`state/cache/post_index.sqlite3` is a disposable derived read model, rebuilt
from canonical records by `ReadModelBuilder`. A schema change is any change
to its core tables, columns, indexes, constraints, or a query that depends on
one of them.

When making a read-model schema change, the same change set must:

1. Increment `ReadModelMetadata::SCHEMA_VERSION`.
2. Update `docs/specs/read_model_schema_v1.md`.
3. Add or update a regression test that starts with the prior schema version
   and verifies that normal application startup rebuilds the read model before
   the changed query runs.
4. Verify `./v3 rebuild` and the affected normal UI or API flow.

Do not rely on a repository-head change to rebuild deployed read models: the
canonical-record repository and application code can be deployed separately.

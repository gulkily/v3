# SQLite viewer query catalog

Each `.sql` file is a read-only sample query for the SQLite viewer and local SQLite users. Keep the metadata header at the top of every file:

```text
-- id: stable-query-id
-- label: Selector label
-- description: Short explanation for users.
-- category: board
-- order: 10
```

The SQL must be one `SELECT` or `WITH` statement. Regenerate the browser catalog and local query pack after adding or editing a file:

```sh
php scripts/build_sqlite_query_catalog.php
```

The generated outputs are `public/assets/sqlite_viewer.js` and `public/assets/sqlite_query_catalog.sql`. The latter is downloadable from `/downloads/sqlite_query_catalog.sql` alongside the database download.

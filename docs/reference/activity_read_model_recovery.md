# Activity read model and recovery

Standard Activity and Forte read compact evidence and category totals from the
existing derived SQLite database. Canonical records remain authoritative. Normal
page, filter, sort, append and selected-event requests do not rebuild evidence,
walk Git history or verify signatures. RSS and full source/commit inspection keep
their existing contracts.

## Updates and freshness

Ordinary writes maintain affected evidence inside the existing transaction.
Activity/post mutations maintain category totals and source-less cursor revision
through SQLite triggers, including direct feature-flag and handoff writes.
Identity linking refreshes evidence affected by newly available identity/key data.
Imports, retractions, physical deletion and maintenance paths that already rebuild
also rebuild Activity. Existing static-artifact retirement rules still apply.

Out-of-band changes to source/signature/key files require the existing operator
refresh/rebuild workflow. Reading Activity does not scan the repository to discover
them. A historical event's facts are distinct from current display/access state;
restricted Access history continues to require authorization.

## Recovery

An interrupted update rolls back its projection with the read-model transaction.
Existing write fallback/stale-marker recovery remains in place. Schema version
36 forces older databases through normal startup rebuilding before changed queries
run; operators can prepare the derived model ahead of traffic with:

    ./v3 rebuild [repository_root] [database_path]

Use the site's actual configured paths. This command also performs the existing
publication/offline maintenance defined by its normal workflow; follow site
operations guidance and inspect its completion output. Do not delete canonical
records or hand-edit metadata to claim that a database is current.

If evidence is unavailable, visitors can use Reload after maintenance completes;
other Activity navigation and source links remain available. A source-less event
has explicit empty evidence and is not an error. Missing signatures/keys/history
retain their existing unavailable/unsigned status; they are not represented as
successful verification. Invalid page positions offer First page.

If rebuilding is blocked by SQLite state, inspect `./v3 rebuild diagnose` and use
the existing documented recovery command only after resolving active owners/locks.
A failed candidate is not promoted. Retry the same route after recovery; clearing
browser storage is unnecessary. Republish/retire existing static Activity HTML
through normal site maintenance so older artifacts are not mistaken for new reads.

## Capacity and release evidence

The accepted 0.25–1 KB/event growth range is an estimate, not a limit. Evidence,
ordering indexes, fixed counter pages and SQLite allocation all contribute.
Measured on 2,222 ordinary local events: 4,132,864 → 5,955,584 bytes (+820/event).
The deliberately long-label/path bulk fixture grows by about 1,404/event. Allow
additional transient space for candidates, journals/WAL and the existing manifest
cache used by full-commit inspection. No HTML or entire commit manifest is stored
per activity event.

Local performance and browser results are in the implementation summary. They do
not establish production resolution. After an independently authorized deployment,
verify the user's ordinary zenmemes.com standard/Forte links with normal cookies,
filter/sort/selection and phone/desktop behavior. Capture document/detail timing
and separate shared startup/lock delays if slowness remains. Do not repeatedly
invalidate production caches or load-test production to collect these samples.

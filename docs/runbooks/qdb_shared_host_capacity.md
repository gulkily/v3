# QDB Shared-Host Capacity Runbook

Use this runbook only after the QDB static-release path has passed staging
smoke tests. It measures a safe operating point; it is not permission to load
test a shared account without the host's written limits.

## Before Running

- Obtain the host's allowed source IPs, request rate, duration, and stop rule.
- Use a production-shaped staging copy and an external machine with
  [k6](https://grafana.com/docs/k6/latest/).
- Build a complete QDB static release and verify `/.static/...` is forbidden,
  anonymous `/latest` is a static artifact, and cookie/query requests reach
  PHP.
- Choose and record the p95 latency and error-rate SLOs for this run. Start at
  one anonymous virtual user for 30 seconds; increase only one small step per
  completed run.

## Read Probe

The probe sends no write request. It mixes anonymous static landing/listing/
quote/asset reads with optional cookie/query PHP fallbacks. Supply an imported
numeric quote and, when available, its canonical thread path:

```bash
QDB_BASE_URL=https://staging.example.invalid \
QDB_NUMERIC_QUOTE_PATH=/42 \
QDB_CANONICAL_QUOTE_PATH=/threads/thread-20030613104735-qdb-42 \
QDB_STATIC_VUS=1 \
QDB_DYNAMIC_VUS=0 \
QDB_DURATION=30s \
QDB_P95_MILLISECONDS=1000 \
QDB_MAX_ERROR_RATE=0.01 \
k6 run scripts/qdb_capacity_probe.js
```

Repeat the probe at each host-approved level. Then repeat with a small
`QDB_DYNAMIC_VUS` value to measure the PHP fallback separately. Keep a
separate, manually authorized single-write smoke test outside the probe.

## Record and Decide

For every run, retain the command, timestamp, release identifier, host limit,
request rate, p50/p95/p99 latency, failure rate, 429/5xx counts, and whether
the route was static or PHP fallback. Also record static-release publish time,
disk/inode use, and SQLite lock symptoms.

The safe operating point is the highest approved level that meets the chosen
SLOs with deliberate headroom. Stop immediately on a host warning, rising
5xx/429 rate, SQLite locks, or an SLO failure. If expected traffic exceeds the
safe point, disable direct-static routing only for diagnosis if necessary and
move to a larger host or CDN/edge solution; do not compensate with an
unbounded on-demand cache.

## Dynamic Optimization Follow-up

Profile only the fallback route that fails its budget. Capture its query count,
wall time, and `EXPLAIN QUERY PLAN`; then make the smallest measured change
(query shape, existing index use, or bounded result set) and repeat the same
probe. A flat-file query cache is a fallback for a host that cannot expose
static releases, and requires an approved key, lock, expiry, disk, and
invalidation contract before use.

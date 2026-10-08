> **Feature plan:** [Step 1](./qdb_shared_host_traffic_capacity_step1_solution_assessment.md) · [Step 2](./qdb_shared_host_traffic_capacity_step2_feature_description.md) · [Step 3](./qdb_shared_host_traffic_capacity_step3_development_plan.md) · [Step 4](./qdb_shared_host_traffic_capacity_step4_implementation_summary.md)

# QDB Shared-Host Traffic Capacity — Step 1: Solution Assessment

## Original Query

Once I host the QDB website, I anticipate a lot of traffic. How can we ensure we can meet demand, despite basic shared web hosting? Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`, including both performance testing and optimization strategies. The host allows us `.htaccess` but not Apache config. Primary optimization techniques I used in the past have been:

- Optimizing SQL queries, obviously.
- Minimizing SQL queries.
- Caching some things as static HTML and allowing Apache to serve it directly.
- Caching SQL queries as flat files.

You don't have to use all or any of these; I'm just sharing what's worked in the past.

## Understood Intent

Make the public, read-heavy QDB experience demonstrably resilient within shared-host limits, while preserving dynamic and personalized behavior where it is needed.

## Problem

The current static-artifact release system avoids application rendering work but `public/.htaccess` still routes HTML requests through PHP, so popular public QDB pages can still exhaust shared-host PHP/SQLite capacity.

## Option A — Tune dynamic PHP/SQLite first

- Profile production-shaped QDB routes; use `EXPLAIN QUERY PLAN`, add or revise read-model indexes only when measurements identify a query, and paginate/bound expensive list, search, and random routes.
- Pros: improves uncached and personalized requests; reduces database work at its source.
- Cons: every popular anonymous request still consumes PHP; query tuning alone cannot provide a reliable traffic ceiling on shared hosting.

## Option B — Serve an anonymous static QDB release directly from Apache (Recommended)

- Publish the existing atomic static release in a document-root-reachable, versioned location; use conservative `.htaccess` rewrites to serve only approved anonymous `GET`/`HEAD`, no-query public routes and fingerprinted assets as files. Cookie-bearing, query, write, account, reaction, search, and random requests continue to the front controller.
- Pros: removes PHP and SQLite from the dominant cache-hit path; works with `.htaccess`; aligns with existing static-release generation and safe atomic activation; isolates dynamic behavior.
- Cons: requires host validation for rewrite/symlink or release-path support, static-file headers, and cache invalidation; content has a defined publish delay rather than immediate freshness.

## Option C — Add PHP flat-file page/query caches

- Cache selected rendered pages or expensive query results behind the front controller, keyed by route/version and invalidated on canonical writes or release activation.
- Pros: feasible if the host cannot expose a static release under the document root; can cover a small set of dynamic read paths.
- Cons: PHP remains the bottleneck; invalidation, locking, disk use, and cookie/query cache-key safety add operational risk. It duplicates much of Option B for anonymous pages.

## Required Performance Evidence

- Before launch, obtain the host's written load-test limits; run tests from an external generator against a production-shaped staging copy, never uncontrolled traffic against the live shared account.
- Measure cold and warm runs, empty-cookie versus cookie-bearing requests, cache hits/misses, and an artifact publish/rebuild during traffic. Use a realistic mix of QDB list, numeric permalink/thread, latest/top, random/search, assets, and a separately rate-limited low-write scenario.
- Increase concurrency in small stages until the agreed error-rate, p95/p99 TTFB, or host-limit threshold is reached; record throughput, 429/5xx/SQLite-lock symptoms, artifact freshness time, disk/inode use, and the safe operating point with headroom.
- Set launch SLOs and rollback triggers from those results; shared hosting cannot honestly guarantee unlimited demand, so include an upgrade/redirect-to-CDN threshold if the safe point is below expected traffic.

## Recommendation

Choose **Option B**, preceded by the required measurement baseline and with Option A applied only to measured dynamic hot spots. This is a viable vertical slice: Apache serves the anonymous high-volume QDB pages and fingerprinted assets directly, while PHP retains all cookie-aware and mutable routes. Treat Option C as a fallback only if host constraints prevent safe direct static delivery; do not add generic flat-file query caches speculatively.

# Activity load performance investigation

Date: 2026-10-27 UTC
Scope: Read-only production HTTP samples and local source inspection for [Step 1](./activity_load_performance_step1_solution_assessment.md).
Local revision: 7ef735dd21b3329f18716bf3d5484baab2b2b71b
Production revision: Unverified; local source identifies mechanisms, not proof of deployed behavior.

## Production observations

Five sequential anonymous curl GETs from this workspace, without cookies, conditional headers or compression negotiation, each capped at 25 seconds. These are diagnostic samples, not a load test or percentile benchmark.

| Route, in order | Result | First byte | Total | HTML bytes |
| --- | --- | --- | --- | --- |
| https://zenmemes.com/activity/ | 200 | 8.520 s | 8.730 s | 254,943 |
| https://zenmemes.com/ | 200 | 0.663 s | 0.801 s | 105,323 |
| https://zenmemes.com/activity/ | timeout, no bytes | unavailable | 25.002 s | 0 |
| https://zenmemes.com/activity/?view=identity | 200 | 4.628 s | 4.908 s | 275,082 |
| https://zenmemes.com/activity/ | 200 | 3.991 s | 4.200 s | 254,943 |

First Activity connection: TCP 0.077 s; TLS complete 0.210 s. Most elapsed time preceded HTML; the body arrived in about 0.21 s afterward. Investigate origin/proxy queuing and server work first. This does not distinguish those causes or prove assets are fast in the user's browser.

First Activity headers included `Cache-Control: private, no-cache, must-revalidate, max-age=0`, another `Cache-Control: max-age=600`, `Vary: Cookie,Accept-Encoding,User-Agent`, an ETag and Apache as server. Check the conflicting cache directives at the application/Apache boundary; they do not establish the internal cache path. No production logs, database, host configuration, process metrics or authenticated browser waterfall were accessed. Web fetch failed with a cache miss; direct curl supplied the measurements.

## Possible causes, in investigation order

### 1. Eager per-row evidence work

Source: [ActivityService](../../../src/ForumRewrite/Activity/ActivityService.php), `fetchActivityPage`, `enrichActivityItem`, `relevantCommitFiles`, `sourceCommitFiles`, `sourceSignatureStatus`; [ReactionSignature](../../../src/ForumRewrite/Security/ReactionSignature.php); [OpenPgpSignatureVerifier](../../../src/ForumRewrite/Security/OpenPgpSignatureVerifier.php).

All displayed rows (up to 100) are enriched before rendering. Relevant-file resolution executes `git diff-tree -r -M` for each distinct commit and indexes its whole file list. Its memoization is request-local; this path bypasses the persistent full-manifest cache. Many distinct commits amplify process startup; a bulk commit amplifies path parsing.

Signed reaction/label/retraction rows can perform GPG verification during list construction: temporary home creation, packet inspection, key import, lookup and detached verification. The byte-keyed static cache is not durable across ordinary PHP requests. Production HTML includes reaction and label evidence paths, but path counts are not verification counts. Record/key reads add filesystem work. Measure subprocess count/time and enrichment time before choosing safe caching or detail-on-demand; preserve signature correctness.

### 2. Ordinary browsing can bypass static delivery

Source: [FrontController](../../../src/ForumRewrite/Host/FrontController.php), `handle`, `resolveStaticArtifactPath`; [HighTrafficCache](../../../src/ForumRewrite/Host/HighTrafficCache.php), `generate`.

Legacy static delivery rejects any cookies and any Activity query string. Filters/pagination use queries and browsers commonly send cookies. The current HighTrafficRoute allowlist excludes both Activity routes: high-traffic mode does not supply an Activity cache. Missing/retired legacy standard artifacts can expose dynamic render cost; Forte has no legacy static route. Production routing still needs verification. Measure actual cache path, freshness, generation and lock timing on bare/filter/paged routes with anonymous and normal signed-in browsing; check refresh health before changing policy. Restricted Access history must never enter a shared public cache.

### 3. Request-time read-model maintenance and contention

Source: [Application](../../../src/ForumRewrite/Application.php), `handle`, `ensureReadModel`.

Dynamic startup can rebuild a missing/stale/incompatible read model under an exclusive execution lock. Repository-head checks also apply when current-read-model mode requires them. Intermittent delay after writes/deployments/maintenance is plausible; do not assume every request rebuilds. High-traffic rendering disables rebuild and has separate recovery. Correlate latency with rebuild reasons, stale markers, lock waits, writes and worker saturation. Broader startup/infrastructure remediation is separate scope if it dominates.

### 4. Pagination bounds output, not all work

Source: [ActivityService](../../../src/ForumRewrite/Activity/ActivityService.php), `fetchActivityPage`; [ReadModelSchema](../../../src/ForumRewrite/ReadModel/ReadModelSchema.php).

Each request reads and hashes all events with NULL `action_key` to detect expired local-event cursors. Cost grows with that subset. The main query and previous/next probes sort by date plus `COALESCE(action_key, 'local:' || id)`; the recent index orders date/post/id. A disposable in-memory SQLite EXPLAIN with equivalent definitions reported `SCAN activity USING INDEX activity_recent_idx` and `USE TEMP B-TREE FOR RIGHT PART OF ORDER BY`. That proves a structural mismatch, not production cost. Measure real cardinalities, timestamp ties and filtered query plans. Preserve cursor stability/recovery; core query/index changes require schema version/spec updates, prior-version startup rebuild regression and normal-flow/rebuild verification.

### 5. Browser and host effects remain unmeasured

PHP/Apache queues, CPU/disk contention, proxy/network delays, session contention and browser scripts/assets remain possible contributors. Capture desktop/phone navigation waterfalls and compare document TTFB with paint/interactivity. Anonymous slowness demonstrates that authentication is not a necessary condition.

## Prior fix and next evidence

The [manifest-size fix](../standard_activity_manifest_size_fix_outline.md) reduced local HTML from 88 MB to about 257 KB. Current source retains relevant-files-only rendering; production's 255 KB is consistent with it but does not prove a deployed revision. Do not propose pagination or relevant-files-only rendering as new fixes. Forte has a separate list/detail contract; the user subsequently confirmed it is also slow, so it is now included (see revision below).

Before choosing the concrete fix, record deployed version, route/cache selection, startup/lock/rebuild time, SQL time, Git/GPG count/time, enrichment/render time and response size for initial/filter/Next/Back. Use bounded existing tooling; no broad new diagnostics subsystem or load test. Compare warm and naturally cold/stale cases without deliberately invalidating production caches. Agree latency targets, then verify missing-history/evidence recovery, signatures, source access, cursor behavior and first paint on the user's real path. Production profiling and deployment remain follow-up work.

Retraction boundary: [Handoff](../retraction_simplification_handoff.md) read; preserve deletion plus retained records. Do not revive sweep diagnostics or publication guards/generations.
Dependency delta: Zero; no application changes, deployment, commit or todo.txt edits.

*— Codex · 2026-10-27 UTC*

## Revision — 2026-10-27T22:57:57Z: Forte also affected

User evidence: “It takes a long time to load in Forte too.” This expands the assessment to both views, without implementation approval. The existing activity table supplies both lists; a new table is not the default recommendation.

Fresh anonymous samples: `/forte/activity/` returned 200, TTFB 0.465 s, total 0.465 s, 30,754 bytes; `?view=commits` returned 200, TTFB 0.361 s, total 0.497 s, 81,390 bytes. Same sequential curl method with 20-second caps. These two fast samples do not refute intermittent or signed-in browsing delays; Forte slowness has not been independently reproduced.

Source: [ForteActivityController](../../../src/ForumRewrite/Http/ForteActivityController.php), `board`, and ActivityService’s `countActivityViewTotal`, `fetchActivityDetail`, `activityCommitManifest`.

- Initial list fetch is already rows-only and limited to 20. However, the controller immediately calls `fetchActivityDetail` for the selected/first row before returning HTML. That uses full-commit enrichment, not the standard view’s relevant-only mode. A cache miss enumerates and enriches all files in that commit; even a cache hit reconstructs all manifest entries before selecting relevant files. Signature verification can also run. One selected item from a large commit can therefore dominate a seemingly lightweight list.
- Every initial page computes all five category totals (plus security when authorized), even when only one category is displayed. `countActivityViewTotal` reads every matching row’s tags and filters/counts in PHP; it is not a bounded list query or a simple SQL aggregate. Queries use tag LIKE predicates and a posts join. The controller also counts commits. Sorting performs another page request and repeats this work. Measure each count separately and preserve identical membership rules if consolidating/caching them.
- Forte’s rows-only append path avoids evidence, but the compatibility API path without `rows_only=1` still fully enriches rows. Distinguish the actual browser request from that heavier API variant. Selected-item and commit-detail requests have their own evidence costs and shared dynamic startup.
- Both routes reach shared application startup; current HighTrafficRoute only allows home, threads list, about and individual threads. Earlier wording suggesting Activity might use its generation path was too broad and has been corrected above.

Revised diagnostic priority: time shared startup/locks, Forte category counts and initially selected detail independently, then query/render/browser costs; compare slow navigation, filter/sort, append and selection traces. Do not assume standard’s per-row work explains Forte, or that lazy details alone solve it. Consider removing nonessential sidebar counts under the project’s UI-copy guidance versus a cheap correct aggregate/cache; decide during requirements review, not by silently changing the interface.

*— Codex · 2026-10-27 UTC*

## Direction revision — 2026-10-27T23:04:17Z

User requested updating Step 1 around largely append-only Activity. Recommended C now stores reusable evidence and counts during indexing and serves both lists and ordinary selected details from the existing read model. This is a proposed architecture, not evidence that all required data or update hooks already exist. Avoid a new table unless the approved design needs it; avoid a separate page-artifact system.

Historical event facts are usually stable, but displayed identity, visibility/access, source/key/signature availability and retraction/deletion effects can change. Update affected projections and totals through existing mutation/import/indexing paths, with rebuild recovery for missed or interrupted updates. Preserve current access checks, retraction record retention and freshness rules; do not retain deleted prose or add per-request historical verification/publication guards. Warm browsing must not fall back to Git/GPG reconstruction. Full source/commit inspection is separate from ordinary Activity selection. Measure indexing overhead as well as read latency; shared startup/host delays remain independently unconfirmed.

*— Codex · 2026-10-27 UTC*

## Step 3 writer audit — 2026-10-27

Method: Source-wide search for Activity INSERT/UPDATE/DELETE statements and read-model builder/updater/stale-marker references; inspected rebuild transaction ordering, updater methods, direct inserts and candidate validation. This is planning evidence, not executed mutation coverage.

| Producer/change | Existing path | Required integration |
| --- | --- | --- |
| Full rebuild, startup, CLI/task/static rebuild | ReadModelBuilder transaction, indexActivity/indexCommits/ReactionSignatureIndex then metadata | Populate evidence and totals before readiness; preserve rollback |
| Posts/replies, labels, subjects, reactions | IncrementalReadModelUpdater apply methods; LocalWriteService fallback rebuild/stale marker | Update affected rows, evidence, membership/counts transactionally |
| Identity linking, approval, approval seeds | Updater author/profile/approval refresh | Keep current display and visibility/count state consistent |
| Feature-flag events | LocalWriteService direct Activity insert plus metadata; some full rebuild paths | Maintain counts, evidence and flag-dependent categories |
| Source-less Codex events | CodexHandoffStore direct insertActivityEvent; builder imports handoff events | Explicit no-source evidence, counts and maintained cursor revision |
| Profile-name/key/import changes | LocalWriteService full refresh paths; ImportedContentPublisher candidate rebuild/promote; AgentIdentityService rebuild | Cover late/replaced/missing keys and affected authors, preserve candidate recovery |
| Retractions and source deletion | LocalWriteService refresh/retry; SourceLabelRemoval rebuild/stale-marker path | No deleted prose in evidence; retained retraction semantics and existing invalidation |
| Out-of-band source/signature/key changes | Existing operator refresh/rebuild contract | Document freshness/rebuild boundary; do not silently add per-request filesystem/history scans |

All direct Activity SQL writers found are ReadModelBuilder, IncrementalReadModelUpdater, LocalWriteService and CodexHandoffStore. Recheck at implementation baseline for concurrent changes. Source-less revision maintenance must cover changes/deletion/rebuild, not just insertion. Feature flags affecting profile-name visibility require matching count variants or invalidation; one unqualified total is insufficient.

ReactionSignatureIndex already persists thread-label/post-reaction status, but its refresh scans a family. Reuse valid results; do not duplicate GPG verification. Retraction/other evidence needs explicit handling. Existing full-manifest cache cannot be trusted as a universal freshness mechanism: its own documentation notes later key availability is not automatically reflected.

Read-model rebuild is transactional with secure deletion; candidates are validated before promotion. Attach projection readiness to those existing completion boundaries rather than adding retraction/publication guards. Missing projections must not look like valid empty evidence. Core schema/query changes always carry the schema-version/spec/startup recovery contract.

Baseline instrumentation must distinguish ordinary Activity evidence subprocesses from shared application startup/head checks; broad startup changes remain outside scope. Any inability to meet approved end-to-end latency must be reported and replanned rather than redefined as success.

*— Codex · 2026-10-27 UTC*

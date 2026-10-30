> **Feature plan:** [Step 1](./qdb_rebuild_scope_step1_solution_assessment.md) · Step 2 · [Step 3](./qdb_rebuild_scope_step3_development_plan.md) · [Step 4](./qdb_rebuild_scope_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_rebuild_scope; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_rebuild_scope)
Extended estimates: enabled

**Cycle estimate: 0.85**

## Problem and users

QDB's static build renders every matching thread into each tag page and can exhaust 128 MiB. Hidden navigation does not establish that a feature or its data is dispensable; the [catalog](./qdb_rebuild_scope_catalog.txt) separates optional publication from required derivation.

- As a QDB operator, I want bounded tag rendering so that a popular tag does not prevent publication.
- As a reader, I want every tagged thread reachable through ordinary links so that reduced build cost does not hide content or news.

## Core requirements

- Show at most 25 matching threads per tag page, selected before full thread hydration. Preserve board-tag/label union, case-insensitive deduplication, existing activity/ID ordering, hidden/retracted exclusions, authorship, quote actions and existing tag URLs, including `/tags/news`. Unknown tags remain not found.
- Reuse native Previous/Next navigation. On unchanged data, traversal has no gaps/duplicates; changing data or rebuilding must not strand readers. Invalid or unusable positions offer First page; retain back links and normal Reload/Back recovery without JavaScript.
- Prebuild only each tag's first page. Later-page requests must reach their intended results through the normal frontend and never receive a cached first page. Preserve full-build and shared-refresh behavior, invalidation and prior-release recovery on failure.
- Keep offline tag browsing explicitly limited to saved content, with bounded pages through available results. Never silently reinterpret an online position as an unrelated saved first page; unavailable positions/content provide a visible route to the saved start or reconnect/retry. Other profiles using shared tag pages receive the same correctness guarantees.
- Complete the feature/cost catalog with evidence and follow-up ownership. Preserve canonical records, trust/scoring derivation and existing capabilities. No optional-prebuild policy, offline snapshot-generation redesign, dependency addition, limit increase or unrelated audit execution in this slice.

## Delivery and shared components

Work type: Application change plus catalog, verification, study ledger and cycle documentation. Reuse the shared pagination contract/controls and thread/quote cards; extend tag controller/template, route dispatch and static-serving integration. Coordinate the existing offline tag renderer and service-worker routing. Tag-index previews, welcome news, Forte grouping, direct detail pages and APIs are compatibility consumers; their listing contracts are not migrated. Follow CONTRIBUTING for changed core queries and UI verification.

Concepts touched: [Read-model rebuild](../../concepts/read_model_rebuild.md) keeps complete derivation; [static publication](../../concepts/static_publication.md) documents bounded tag artifacts; add a tag-browsing concept during implementation.

## Flow and completion

1. Reader opens a tag or All news, sees the first bounded page, and follows native links through results.
2. Reader reloads, goes Back, or uses First page after invalid position; offline users see saved-content scope and recovery.
3. Operator runs the existing build-static command; tag artifacts are bounded, and normal publication can continue. A failed candidate leaves the prior active release available.

Known environment: QDB PHP host at /home/qdb/v3, 128 MiB limit. After normal authorized deployment, rerun the original build-static command/settings, confirm all four phases and inspect published tag/news navigation. Retain the exact phase/error on failure. Local PHP 8.1 checks do not satisfy PHP 8.3 host recovery; without host access, handoff is locally verified with production acceptance explicitly pending.

## Risks and validation

- Membership/order drift can lose results: validate mixed labels/case, ties, hidden/retracted threads and complete traversal before integration; preserve shared selection semantics.
- Static/offline routing can serve the wrong page: trace these paths at Step 3 entry and test actual frontend, history and disconnected recovery before release.
- Large individual records or later build phases can still exceed budgets: test ordinary scaling and maximum-valid-record pages separately; retain the 128 MiB ceiling and report failures instead of truncating content or claiming universal capacity.

Success criteria: For fixed 25-result pages and ordinary 2 KiB bodies/1 KiB previews at 1000/10000/40000 threads, public tag rendering passes at 32 MiB and tag artifact generation at 64 MiB, with at most 4 MiB PHP-peak growth across sizes. Test a populated 24-tag workload, complete traversal, malformed positions, schema startup/rebuild, static/runtime/offline recovery and phone/desktop themes. Maximum-valid-record acceptance is checked separately at 128 MiB. Full production-build recovery remains a distinct host gate; do not generalize isolated tag results to other known memory risks.

## Approval and study checkpoint

Step 1 Option B approved, 2026-10-30 UTC. Scope v1/B and original baseline 0392dd59c0646bebc37042455ecaccf825b13889 retained; R1/P2, debug/extended mode and single exposed Codex rater unchanged. Frozen hashes reverified. Whole-cycle 0.85 reaffirmed, including offline routing and compatibility verification; no measured labor or accepted outcome. Initial missing pristine intake remains missing. No implementation, deployment or later-step approval; await Approved Step 2.

*— Codex · 2026-10-30 UTC*

Approval update: 2026-10-30 UTC. User approved Step 2 and explicitly confirmed that the shared tag-page change may affect all sites; this matches the existing requirement, not an expansion to other listing types. Step 3 planning proceeds; implementation remains gated.

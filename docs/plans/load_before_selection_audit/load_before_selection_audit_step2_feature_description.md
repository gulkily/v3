> **Feature plan:** [Step 1](load_before_selection_audit_step1_solution_assessment.md) · Step 2 · [Step 3](load_before_selection_audit_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/load_before_selection_audit; [P2 ledger](../effort_estimation_trial.md#cycle-v3load_before_selection_audit)
Extended estimates: enabled

**Cycle estimate: 0.55**

## Problem and outcomes

Some callers load whole collections before choosing a small result. Maintainers need measured findings that distinguish unnecessary work from necessary traversal.

- As a maintainer, I want ranked root causes with all affected callers so one correction does not break another reader.
- As an operator, I want reproducible loaded-versus-used evidence so priorities reflect actual work and uncertainty.

## Core requirements

- Cover PHP/SQL, canonical-file/Git readers, CLI/background jobs, APIs and browser consumers. Record each family as traced, necessary/bounded, or unresolved/deferred with a reason; search matches are leads, not confirmed defects.
- Give root causes stable IDs, current source revision/call paths, selection semantics, affected entry points and disposition. Reconcile inherited M02/M04/M09 with delivered tag/profile/history changes; resolved or duplicate findings are not new discoveries.
- Probe representative confirmed cases using isolated local fixtures and existing tools. Separate source rows examined, records materialized/transferred and results used; record dimensions, commands, repeated timing/memory observations and failed samples. A LIMIT or low PHP peak alone proves neither efficient work nor bounded host memory.
- Preserve eligibility/access rules, effective tags, pinned content, random distribution, ordering/ties, totals, complete exports and existing links in every proposed correction. Identify the smallest independently useful fix and its parity/recovery checks.
- Deliver a ranked inventory, justified exceptions, coverage gaps and reproduction evidence. No fixes, schema/index changes, CI detection, dependencies, production load or deployment in this audit.

## Shared contracts and concepts

Reuse existing evidence and contracts; add no UI/API component. Consumer inventory starts with [Application](../../../src/ForumRewrite/Application.php)/[RouteServices](../../../src/ForumRewrite/Http/RouteServices.php): standard board/tag/profile/directory views, QDB welcome/listing/search/random, Forte/detail APIs and RSS; [ThreadRepository](../../../src/ForumRewrite/ReadModel/ThreadRepository.php), [ActivityService](../../../src/ForumRewrite/Activity/ActivityService.php), [ReadModelBuilder](../../../src/ForumRewrite/ReadModel/ReadModelBuilder.php), [offline publisher](../../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php)/[reader](../../../public/assets/offline_reader.js), plus import/export/scoring/queue entry points under scripts and their services. Trace additional consumers per finding rather than assume navigation defines use. Reuse the [pagination contract](../../reference/pagination.md).

Concepts touched: [Effective tags](../../concepts/effective_tags.md) and [reaction records](../../concepts/reaction_record.md), used unchanged; collection selection, proposed documentation entry. Correct only factual inventory descriptions exposed by this audit.

## Delivery and completion boundary

Work type: Documentation-only internal audit. Allowed files: this cycle's `docs/plans/load_before_selection_audit*` artifacts (later its dedicated folder), its plan-index/effort-ledger entries, proposed `docs/concepts/collection_selection.md` and scoped corrections to the two concepts above. Disposable local probes run outside the repository; preserve commands/results as plain text. No application/runtime, test-suite, CI or deployment-artifact changes.

Flow: inventory candidates → trace callers and classify → probe representative confirmed cases → rank follow-up slices. Completion requires reviewed coverage, source-backed findings, reproducible measurements and explicit gaps sufficient to choose the next fix. Retain failed probes and reclassify repaired seeds; do not force findings.

Known environment: QDB `/home/qdb/v3`, PHP 8.3, 128 MiB; ordinary build is `./v3 build-static` with the operator's existing arguments. The old 314.961s build predates the latest optimizations. Fresh host evidence remains unavailable; local probes do not establish production recovery/capacity.

Risks: Early caller tracing must expose selection/permission dependencies before suggesting pushdown; complete exports may legitimately require full traversal. Recheck seeds against current code. Document fixture/cache bias and host limits; the capacity study remains separate.

## Approval and study checkpoint

2026-10-31 UTC: User approved Step 1, recommended Option B. v1/B scope, original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08 and R1/P2 retained; Step 2 source context ade35a65 includes subsequent fixes and is not a replacement historical baseline. Whole-cycle 0.55 reaffirmed, single exposed Codex rater with prior audit/build-time knowledge; all three frozen R1 hashes reverified. Debug/extended modes inherited. No measured labor, independent rating or completed outcome. Await Approved Step 2 before development planning.

2026-10-31 UTC update: User approved Step 2 without scope changes. [Step 3](load_before_selection_audit_step3_development_plan.md) is prepared; audit execution awaits Approved Step 3. Earlier checkpoint retained above.

*— Codex · 2026-10-31 UTC*

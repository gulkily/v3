> **Feature plan:** [Step 1](./load_before_selection_audit_step1_solution_assessment.md) · Step 2 (not started) · Step 3 (not started) · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/load_before_selection_audit; [P2 ledger](./effort_estimation_trial.md#cycle-v3load_before_selection_audit)
Extended estimates: enabled

## Original Query

Also make another Step 1 for finding the anti-pattern where we do something like this: "QDB listings load all quote bodies before pagination, the offline snapshot loads all thread rows before choosing its recent set."

## Understood Intent

Find unnecessary whole-collection work before selecting the small result a caller needs, across the codebase. This is a separate proposed audit; it includes wasted reads, parsing, hydration, CPU and transfer costs even where memory is currently acceptable. It is not authorization to change listing behavior or implement fixes.

Problem: Some paths load or construct all candidates before applying eligibility, ordering, pagination, sampling, counting or existence checks.

Concepts touched: Collection selection, public listings and snapshot selection; inventory entries proposed if implementation proceeds. Existing [effective tags](../concepts/effective_tags.md) and [reaction records](../concepts/reaction_record.md) used unchanged; preserve their selection semantics and the [pagination contract](../reference/pagination.md).

## Option A — Search-pattern inventory

**Estimate: 0.20**

Find whole-collection reads followed by filters, sorts, slices or client-side hiding and record candidates.

- Quick coverage; many false positives and indirect helper chains remain unresolved.

## Option B — Trace consumers and measure overfetch

**Estimate: 0.55**

Use searches as leads, then trace sources through consumers across PHP/SQL, file and Git readers, CLI jobs, API payloads and browser code. Classify unnecessary loading separately from necessary complete traversal and already-bounded collections; probe representative cases.

- Recommended: actionable findings with loaded-versus-used counts, call paths, scale, impact, smallest safe correction and verification requirements.
- Main cost is checking shared consumers and selection semantics; actual workload distribution remains uncertain.

## Option C — Audit plus automated detection

**Estimate: 0.90**

Perform B and add repository-specific checks for recurring patterns without adding third-party tooling.

- Helps prevent recurrence; syntax cannot reliably prove whether full traversal is necessary, adding maintenance and false-positive review.

## Recommendation and boundary

Choose B. Seed it with QDB quote pagination and offline recent-thread selection from the [memory audit](./qdb_memory_audit/qdb_memory_audit_findings.txt), without counting those as newly discovered defects. Give each root cause a stable ID and list all affected consumers. Distinguish rows examined by the data source from records materialized or transferred: SQL LIMIT alone does not prove efficient work, and filtering after LIMIT can change results.

Deliver a ranked inventory, legitimate exceptions, representative probes and independently useful remediation slices. Preserve permissions, complete exports, pinned content, random distribution, ordering/ties, totals and existing links. No application fixes, indexes, schema changes, pagination redesign, dependencies or deployment in this audit scope. Step 2 requires Approved Step 1.

## Study checkpoint

Intake and Step 1: 2026-10-30 UTC, v1/B, baseline cfb8364f6db03cc09e27cd26afb93c109c723a08, R1/P2; 0.55 total, reaffirmed at handoff. Codex single exposed rater; prior memory-audit inspection and findings are known, not an independent discovery pass. All three frozen R1 hashes verified. Alternatives are whole-audit projections, not additive fix estimates. No measured labor or accepted outcome. Related to v3/qdb_memory_audit; overlapping findings must not be counted twice. Debug inherited from the project marker.

*— Codex · 2026-10-30 UTC*

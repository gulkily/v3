> **Feature plan:** [Step 2](./forte_activity_parity_step2_feature_description.md) · [Step 3](./forte_activity_parity_step3_development_plan.md) · [Step 4](./forte_activity_parity_step4_implementation_summary.md)

Debug mode: enabled

Status: Implementation in progress. Planning commit: `efa30a6c` on `feature/forte-activity-parity`.

## Stage 1 - Reliable categories and detail lifecycle

- Changes: Initial/paged activity rows expose security membership; non-row security pagination renders the shared acceptance evidence. Entering/leaving security review navigates to a separate document, preventing cross-scope detail DOM/cache reuse. Ordinary category changes remain in place. Layout generations discard stale detail/commit/page responses and reset cached details after sorting. Failed activity details have an explicit Retry action.
- Changes: Replaced unbounded background pagination with one first-page warmup per other ordinary category and explicit, retryable Load more controls. Page requests remain rows-only; details are fetched on selection. Reused rows preserve each category's server order, including overlapping page windows. Operator rows are not prefetched into ordinary documents.
- Verification: `node tests/browser/forte_activity_parity.mjs` passed desktop/mobile mocked-transport flows for membership/order, bounded prefetch, detail/page failure and retry, keyboard selection, duplicate merging, stale response after sort, and security document transitions. Five focused PHP tests passed: ForteActivityReadModelRecoveryTest (2), lightweight activity rows, filtered query limits, and authenticated operator review (including paged security evidence and ordinary-detail exclusion). PHP/Node syntax and `git diff --check` passed.
- Notes: Security transitions use full navigation intentionally; operator authorization and no-store guards remain server-owned. Bounded category warmup and retained pagination replace the old eager scan to meet the plan's responsive/pagination contract. No production or canonical data changes.

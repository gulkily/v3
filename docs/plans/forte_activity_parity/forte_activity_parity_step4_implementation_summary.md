> **Feature plan:** [Step 2](./forte_activity_parity_step2_feature_description.md) · [Step 3](./forte_activity_parity_step3_development_plan.md) · [Step 4](./forte_activity_parity_step4_implementation_summary.md)

Debug mode: enabled

Status: Implementation in progress. Planning commit: `efa30a6c` on `feature/forte-activity-parity`.

## Stage 1 - Reliable categories and detail lifecycle

- Changes: Initial/paged activity rows expose security membership; non-row security pagination renders the shared acceptance evidence. Entering/leaving security review navigates to a separate document, preventing cross-scope detail DOM/cache reuse. Ordinary category changes remain in place. Layout generations discard stale detail/commit/page responses and reset cached details after sorting. Failed activity details have an explicit Retry action.
- Changes: Replaced unbounded background pagination with one first-page warmup per other ordinary category and explicit, retryable Load more controls. Page requests remain rows-only; details are fetched on selection. Reused rows preserve each category's server order, including overlapping page windows. Operator rows are not prefetched into ordinary documents.
- Verification: `node tests/browser/forte_activity_parity.mjs` passed desktop/mobile mocked-transport flows for membership/order, bounded prefetch, detail/page failure and retry, keyboard selection, duplicate merging, stale response after sort, and security document transitions. Five focused PHP tests passed: ForteActivityReadModelRecoveryTest (2), lightweight activity rows, filtered query limits, and authenticated operator review (including paged security evidence and ordinary-detail exclusion). PHP/Node syntax and `git diff --check` passed.
- Notes: Security transitions use full navigation intentionally; operator authorization and no-store guards remain server-owned. Bounded category warmup and retained pagination replace the old eager scan to meet the plan's responsive/pagination contract. No production or canonical data changes.

## Stage 2 - Equivalent information and controls

- Changes: Added an Activity heading and active-category RSS link inside Forte's window; RSS hides for security and Commits. Classic and Forte share the exact policy explanation partial, with Forte showing it above its list. Initial/rows-only security lists use the same receipt-derived labels as default Activity. Event headers now use the existing Forte author/time helper; shared acceptance details sit within the normal detail body. Existing source/signature/relevant-file components and Forte-native content links remain in use.
- Verification: Four focused PHP tests passed for default/Forte categories and RSS, shared manifests, signature-key evidence, and operator review (policy placement, hidden security RSS, receipt-derived row labels). `node tests/browser/forte_activity_parity.mjs` passed desktop/mobile behavior including RSS updates/hiding across categories and security transitions. PHP syntax and `git diff --check` passed after removing a trailing template blank line.
- Notes: No second activity data source or new feed endpoint. Forte continues to show bounded relevant files per event; its separate Commits view retains complete manifests on demand. Final real-HTTP browser/release checks follow in Stage 3.

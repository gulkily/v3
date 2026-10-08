# Multi-Site Refactor P2 Browser and Offline Identity — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_p2_browser_offline_identity_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_browser_offline_identity_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_browser_offline_identity_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_browser_offline_identity_step4_implementation_summary.md)

## Original Query

Oh, let's do that first then. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Problem

Browser preferences, PWA identity, and offline caches still use Zenmemes-only names, so profiles can collide and static publication cannot prove it delivers the correct offline runtime identity.

## Options

### Option A — One profile-derived browser-runtime contract

Use the validated `browserNamespace` to derive preference keys, cache identity, manifest identity, worker registration, diagnostics, and static-runtime output from one bounded contract.

- Pros: one source of truth; isolates profile presentation/offline state while preserving the shared logged-in identity; supports both dynamic responses and physical static artifacts; permits a one-time Zenmemes legacy-preference fallback.
- Cons: requires careful cache migration and an explicit runtime delivery contract.

### Option B — Per-profile worker and manifest implementations

Create separate worker scripts, manifests, and registration logic for each profile.

- Pros: straightforward static-file delivery.
- Cons: duplicates cache policy and diagnostics; encourages drift; makes a fourth profile costly.

### Option C — Namespace only browser storage and cache names

Change local-storage and Cache Storage names while retaining the shared Zenmemes manifest and worker delivery.

- Pros: smallest initial change.
- Cons: leaves install identity and physical-artifact delivery ambiguous; does not meet the full P2 checklist.

## Recommendation

Adopt Option A. Deliver one vertical slice in which every browser/offline identifier is derived from the validated profile namespace, legacy Zenmemes preferences are read once and migrated safely, and the same profile-aware manifest/worker contract is emitted for dynamic and static delivery. On a shared origin, switching profiles preserves the shared logged-in identity and session while retaining each profile's matching cache and never deleting a foreign profile's cache.

## Continuation Handoff

- Current state: P2 bounded presentation is complete; this browser/offline identity slice is the remaining P2 work and blocks P3 PWA/cache regression assertions.
- Resume point: review this Step 1 in the next conversation. Do not create Step 2 until the user responds `Approved Step 1`.
- Constraint: keep shared repository, database, identity, approval, content state, browser-held identity, and session state instance-owned; this slice changes only profile presentation/offline identity and delivery.

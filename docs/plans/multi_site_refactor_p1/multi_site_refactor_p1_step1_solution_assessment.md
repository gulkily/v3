# Multi-Site Refactor P1 — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_p1_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p1_step2_feature_description.md) · [Step 3](./multi_site_refactor_p1_step3_development_plan.md) · [Step 4](./multi_site_refactor_p1_step4_implementation_summary.md)

## Original Query

Please update the refactor checklist, merge into main, and prepare for P1.

## Problem

QDB's routes, board policy, chrome, and quote-number behavior remain distributed through generic application, controller, template, write, and read-model code, making the QDB experience hard to isolate or extend safely.

## Options

### Option A — Named QDB experience plus quote-number capability

Create one QDB experience module that owns its classic route matching/dispatch and presentation policy, and one QDB quote-number component that owns minting, parsing, lookup, and permalink display.

- Pros: gives QDB one explicit boundary; removes generic QDB conditionals; preserves all existing classic URLs while making their rejection on other profiles testable.
- Cons: requires deliberate interfaces between the experience, existing generic controllers, and the quote-number component.

### Option B — Extract only local helpers

Move individual QDB branches into helper methods on the existing application, controllers, templates, write service, and repository.

- Pros: smaller local diffs; preserves current call structures.
- Cons: leaves QDB behavior scattered and makes a future specialized experience harder to recognize or test as a unit.

### Option C — Build a general experience-plugin framework

Introduce generic plugin registration, route tables, rendering hooks, and data-service extension points before moving QDB.

- Pros: anticipates multiple future specialized sites.
- Cons: invents abstractions without a second consumer; delays the QDB outcome and risks a broad framework refactor.

## Recommendation

Adopt Option A. Deliver a QDB-only vertical slice from classic URL entry through navigation, board/card/compose/footer presentation and quote-number minting/lookup/permalinks, with every classic QDB route rejected by other profiles. Keep shared forum data and generic behavior intact, and avoid a reusable plugin framework until a second specialized experience demonstrates the needed common contract.

# Multi-Site Refactor P0 — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_p0_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p0_step2_feature_description.md) · [Step 3](./multi_site_refactor_p0_step3_development_plan.md) · [Step 4](./multi_site_refactor_p0_step4_implementation_summary.md)

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for P0 of docs/plans/multi_site_refactor_checklist.md.

## Problem

`SiteProfileRegistry` currently provides only name, default theme, and composer copy while browser/offline identity and the Zenmemes static-output suffix rule are duplicated across entry points and scripts.

## Options

### Option A — Canonical descriptor with a shared path resolver

Extend each registry entry with display identity, permitted/default themes, a browser/offline namespace, editorial-content key, and enabled experience keys; validate the descriptors and expose one resolver for profile-derived presentation paths.

- Pros: establishes one reviewable profile contract; removes repeated Zenmemes suffix branches; makes fallback and identifier safety testable in one place.
- Cons: requires a deliberate boundary so repository, database, identity, approval, and content state do not migrate into profile metadata.

### Option B — Keep the registry small and add separate browser/path configuration

Add one browser configuration source and one static-path helper, leaving `SiteProfileRegistry` responsible only for its current fields.

- Pros: smaller immediate changes; each consumer can migrate independently.
- Cons: profile facts remain split across registries and consumers, so a fourth site still needs coordinated edits and validation is fragmented.

### Option C — Introduce per-site profile classes

Give each site its own class that supplies identity, browser/offline settings, enabled experiences, and presentation paths through a common interface.

- Pros: strong typing and clear room for genuinely specialized behavior.
- Cons: disproportionate indirection for declarative P0 data; risks placing shared instance state or future QDB behavior in profile classes prematurely.

## Recommendation

Adopt Option A: make the registry the canonical declarative profile descriptor and have a single shared resolver derive presentation/static-output roots from its browser-safe profile identifier. Keep the resolver limited to presentation paths and retain all shared repository, database, identity, approval, and content state as instance concerns. This is a viable vertical slice: select a known or fallback profile, render/use its profile-safe browser and output identity end-to-end, and preserve Zenmemes' existing unsuffixed output path.


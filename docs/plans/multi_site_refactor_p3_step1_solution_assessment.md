# Multi-Site Refactor P3 — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_p3_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p3_step2_feature_description.md) · [Step 3](./multi_site_refactor_p3_step3_development_plan.md) · [Step 4](./multi_site_refactor_p3_step4_implementation_summary.md)

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for P3, taking into account that I'll continue it from a new conversation, and it needs to update docs/plans/multi_site_refactor_checklist.md.

## Problem

P0–P2 presentation and browser/offline behavior are declarative, but regression coverage still mixes fixed site literals with partial per-profile checks and cannot yet prove that the descriptor supports a fourth site.

## Options

### Option A — Descriptor-derived regression matrix

Build P3 tests from the profile descriptor, selected experience, and registered presentation slots; cover the completed P2 browser/offline contract alongside routes, presentation, static output, and shared state, then add a fourth-site fixture.

- Pros: keeps expectations aligned with the canonical contract; covers real profile variation; proves the existing browser/offline contract is generalized rather than site-specific.
- Cons: requires defining one complete descriptor-derived expectation set before expanding coverage.

### Option B — Expand current hand-written per-site assertions

Add more Zenmemes, Chouse, and QDB-specific checks to existing smoke and worker tests.

- Pros: quickest short-term coverage increase.
- Cons: repeats fixed literals and cannot demonstrate that a new profile can be described without bespoke tests.

### Option C — Add the fourth-site fixture first

Create a new profile fixture before replacing the current fixed expectations.

- Pros: exposes descriptor gaps early.
- Cons: makes failures harder to diagnose while the test contract is still fragmented; risks encoding another set of site-specific assertions.

## Recommendation

Adopt Option A. Deliver P3 as one bounded descriptor-derived regression matrix for routes, presentation, browser namespace, PWA/cache identity, static output, and shared state across the three existing profiles; use the same matrix with a fourth-site fixture to prove extension without bespoke assertions. This is a releasable vertical slice because normal profile selection is verified through each published concern, including the existing P2 browser/offline contract, rather than through site-specific branches.

## Continuation Handoff

- Current state: P0, P1, P2 bounded presentation, and P2 browser/offline identity are complete; P3 can now generalize their regression coverage.
- Resume point: review this Step 1 in the next conversation. Do not create Step 2 until the user responds `Approved Step 1`.
- Checklist boundary: update only P3 checklist entries after their matching verification is complete; retain the completed P2 entries as evidence for P3's PWA/cache assertions.

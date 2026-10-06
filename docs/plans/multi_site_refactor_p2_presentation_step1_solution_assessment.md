# Multi-Site Refactor P2 Presentation — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./multi_site_refactor_p2_presentation_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_presentation_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_presentation_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_presentation_step4_implementation_summary.md)

## Original Query

Never mind, it's a separate issue. Please continue with planning P2. Please remember to keep the checklist documented updated.

## Problem

Zenmemes, Chouse, and QDB presentation differences are still selected through scattered templates and conditionals, so editorial copy, theme availability, and ordinary chrome cannot be reviewed as one bounded profile contract.

## Options

### Option A — Registered presentation slots with profile selections

Define a fixed catalog of named slots and shared fallbacks; let each profile select only registered navigation, card, compose, about, editorial, and stylesheet choices.

- Pros: makes supported variation explicit and testable; preserves shared rendering; keeps QDB’s specialized experience separate.
- Cons: requires a disciplined slot catalog and explicit decisions about theme availability.

### Option B — Profile-owned template paths and stylesheet lists

Add arbitrary template and CSS-path fields to each profile descriptor.

- Pros: fastest way to relocate current conditionals.
- Cons: permits unbounded paths, stacked styles, and hidden dependencies; weakens validation and shared fallbacks.

### Option C — Site presentation classes

Give every profile a class that renders its own chrome and content.

- Pros: clear ownership for extensive future divergence.
- Cons: duplicates ordinary presentation mechanics; treats Chouse’s current content variation as a specialized experience prematurely.

## Recommendation

Adopt Option A. Deliver one vertical slice in which each profile selects registered presentation slots and theme-menu availability, with shared fallbacks for every slot. Keep QDB’s route/board behavior in its existing specialized module, keep Chouse declarative, and reject arbitrary template paths, CSS stacks, and unregistered selections.

> **Feature plan:** [Step 1](./runtime_detail_pages_step1_solution_assessment.md) · [Step 2](./runtime_detail_pages_step2_feature_description.md) · [Step 3](./runtime_detail_pages_step3_development_plan.md) · [Step 4](./runtime_detail_pages_step4_implementation_summary.md)

## Problem

Routine builds render every thread and post despite available runtime rendering, making builds unnecessarily slow. The approved approach makes runtime detail rendering the default while preserving explicit static opt-in.

## User Stories

- As an operator, I want routine builds to skip bulk detail rendering so that releases finish sooner.
- As a reader, I want direct thread, post, and quote links to work so that the rendering change does not interrupt reading.
- As a static-only host operator, I want to opt into detail pre-rendering so that my deployment remains supported.

## Core Requirements

- With no explicit override, full builds skip both thread and post rendering batches; other generated page categories and offline snapshot behavior remain intact.
- Existing explicit instance and environment choices retain their precedence; enabling static detail pages still produces them.
- Thread and post routes, plus applicable numeric quote aliases, remain accessible through existing runtime rendering with current visibility and access rules.
- A full build after disabling static detail pages activates a release without old detail HTML; shared-only refresh retains its existing scope and is documented as insufficient for this transition.
- Operator documentation and existing feature-flag controls explain the new default and the full-build/static-opt-in choices.

## Delivery Scope and Completion Boundary

Work type: application change, with focused verification and operator documentation. Reuse the existing rendering flag and runtime routes; exclude incremental rendering, new caches, and changes to other page-generation categories.

Normal entry is the usual full-build command. Completion is an activated release that skips detail pre-rendering and serves direct detail links at runtime. Recovery preserves failed-build protection and lets operators explicitly enable static detail rendering and rebuild. Release requires both default runtime and explicit static modes to pass relevant checks.

## Risks

- **Static-only hosting:** the new default could leave detail URLs unavailable. Earliest validation: inspect hosting/routing expectations before Step 3; mitigation: retain explicit opt-in and document it before deployment.
- **Routing and old artifacts:** stale HTML or alias gaps could conceal or break runtime behavior. Earliest validation: inspect full-release replacement and direct-route fallback before Step 3; mitigation: plan transition checks from an existing static release, including quote aliases.
- **Existing overrides:** explicit enablement could keep builds slow. Earliest validation: inspect flag precedence before Step 3; mitigation: preserve operator choices and explain how to disable them.

## Shared Component Inventory

- Full-build CLI, static builder, and release publisher: extend existing default behavior and reuse release activation/recovery.
- Feature-flag registry/evaluator and instance feature-flags page: reuse the existing rendering choice and controls.
- Application thread/post routes, QDB quote aliases, and canonical page renderer: reuse runtime output and access rules.
- Shared listing pages and offline reader/snapshot: retain existing components and verify links remain usable.
- Shared-only refresh and CLI reference: reuse the workflow and clarify its transition limitation. No new UI or API is needed.

## Simple User Flow

1. Operator runs a normal full build without a static-detail override.
2. Build skips both detail batches and activates the release.
3. Reader opens a thread, post, or quote link and receives its runtime page.
4. Operator needing static detail hosting explicitly enables it and runs a full build.

## Success Criteria

- Default builds report skipping detail pages, perform neither bulk detail batch, and produce no thread/post detail HTML.
- Direct thread/post links and applicable quote aliases work after activation, including transition from an existing static release.
- Explicit static opt-in produces working detail artifacts; other page categories and existing failure recovery continue to work.
- Representative before/after build timings are recorded alongside page counts to quantify the time saved.

> **Feature plan:** [Step 1](./runtime_detail_pages_step1_solution_assessment.md) · [Step 2](./runtime_detail_pages_step2_feature_description.md) · [Step 3](./runtime_detail_pages_step3_development_plan.md) · [Step 4](./runtime_detail_pages_step4_implementation_summary.md)

## Original Query

```text
Rendering thread pages (550/618).
Rendering thread pages (575/618).
Rendering thread pages (600/618).
Rendering thread pages (618/618).
Rendering post pages (0/1149).
Rendering post pages (25/1149).
Rendering post pages (50/1149).
Rendering post pages (75/1149).
Rendering post pages (100/1149).
Rendering post pages (125/1149).
Rendering post pages (150/1149).
Rendering post pages (175/1149).
Rendering post pages (200/1149).
Rendering post pages (225/1149).
Rendering post pages (250/1149).
Rendering post pages (275/1149).
Rendering post pages (300/1149).
Rendering post pages (325/1149).
Rendering post pages (350/1149).
Rendering post pages (375/1149).
Rendering post pages (400/1149).
Rendering post pages (425/1149).
Rendering post pages (450/1149).
Rendering post pages (475/1149).
Rendering post pages (500/1149).
Rendering post pages (525/1149).
Rendering post pages (550/1149).
Rendering post pages (575/1149).
Rendering post pages (600/1149).
Rendering post pages (625/1149).
Rendering post pages (650/1149).
Rendering post pages (675/1149).
Rendering post pages (700/1149).
Rendering post pages (725/1149).
Rendering post pages (750/1149).
Rendering post pages (775/1149).
Rendering post pages (800/1149).
Rendering post pages (825/1149).
Rendering post pages (850/1149).
Rendering post pages (875/1149).
Rendering post pages (900/1149).
Rendering post pages (925/1149).
Rendering post pages (950/1149).
Rendering post pages (975/1149).
Rendering post pages (1000/1149).
Rendering post pages (1025/1149).
Rendering post pages (1050/1149).
Rendering post pages (1075/1149).
Rendering post pages (1100/1149).
```

Most of the time this activity is not necessary, since we can dynamically generate the pages during runtime. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for not having this happen anymore. It takes a very long time.

## Understood Intent

Make routine builds skip bulk thread and post HTML generation and serve those pages at runtime, while retaining an explicit choice for deployments that need static detail pages.

## Problem Statement

Routine static builds spend substantial time rendering every thread and post even when runtime rendering can serve those routes.

## Current Evidence

- The existing [builder](../../../src/ForumRewrite/Host/StaticArtifactBuilder.php) already supports skipping both detail-page batches.
- The [feature flag registry](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php) and [CLI reference](../../reference/v3_cli.md) currently default static detail pages to enabled.
- Shared-only refresh avoids these batches, but requires an existing complete release and retains its detail artifacts; it does not address the default full-build behavior.

## Option A: Make runtime detail rendering the default

Use the existing rendering choice to skip bulk thread and post generation in routine builds, retaining explicit opt-in for static detail pages.

- Pros: Reuses existing capability; removes both expensive batches by default; preserves static-only deployments.
- Cons: Existing explicit enablement can retain the slow behavior; runtime routing, existing artifacts, and quote aliases need validation during the transition.

## Option B: Remove static detail generation entirely

Always serve thread and post pages at runtime and eliminate the bulk detail-generation path.

- Pros: Prevents accidental bulk rendering; simplifies the available rendering modes.
- Cons: Removes support for static-only detail hosting; creates a broader compatibility change than the request needs.

## Option C: Render only changed detail pages

Keep static detail output, but limit subsequent builds to pages affected by content changes.

- Pros: Reduces repeat work while preserving static detail hosting.
- Cons: Initial builds remain expensive; change tracking and invalidation add complexity; still performs work runtime rendering makes unnecessary.

## Recommendation

Choose Option A. The vertical slice is a normal build followed by successful runtime access to thread and post URLs, including direct links and applicable quote aliases, with explicit static opt-in still usable. Verify that ordinary builds skip both batches and that old static artifacts do not conceal the intended runtime behavior. No database migration is expected.

The assessment is kept concise; the original progress log is preserved in full as required by Step 1.


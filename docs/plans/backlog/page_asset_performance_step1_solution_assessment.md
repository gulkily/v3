# Page Asset Performance — Step 1: Solution Assessment

## Problem Statement

After theme splitting, standard pages still ship shared CSS, scripts, inline critical CSS, and static-artifact assets that may not serve their route; reduce transfer and parse cost without minifying source assets.

## Option A: Extract About as a page-CSS pilot

Move the About-only `.about-section` rules into an About stylesheet and add per-page CSS support to the standard layout.

Pros:
- Small, low-risk proof of the shared page-asset mechanism.
- Immediately stops non-About routes loading About glyphs and layout rules.

Cons:
- Leaves the larger CSS, script, and artifact opportunities untouched.
- Repeated one-page changes can create inconsistent asset ownership.

## Option B: Route-family CSS and JavaScript ownership pass

Create shared route-family asset contracts and extract every selector/script whose template use is exclusive to that family.

Pros:
- Captures About plus the current page-specific CSS and layout-wide no-op scripts.
- Keeps assets readable and cacheable without a bundling/minification system.

Cons:
- Requires a complete ownership inventory and route regression coverage.
- Extra requests can outweigh savings for very small assets if grouped poorly.

## Option C: Measured non-minification performance program

Combine route-family assets with critical-CSS reduction, HTML/payload review, deferred heavy tooling assets, and static-artifact/deployment hygiene, guided by page-size and request measurements.

Pros:
- Addresses CSS bytes, HTML bytes, JavaScript execution, and artifact bloat together.
- Makes prioritization evidence-based and protects each improvement with budgets.

Cons:
- Too broad for one implementation pass; should be delivered as focused follow-up stages.
- Some gains depend on server/CDN configuration outside application code.

## Recommendation

**Option C, starting with Option B's route-asset contracts and the About pilot.** The companion inventory identifies the complete current source-level opportunity set; use measurements to select the first small implementation feature, beginning with shared per-page CSS support and `about.css`.

Inventory: [non-minification performance inventory](../page_asset_performance_inventory.md).

Reply **Approved Step 1** to proceed to the feature description.

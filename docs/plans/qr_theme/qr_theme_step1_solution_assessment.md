> **Feature plan:** [Step 1](./qr_theme_step1_solution_assessment.md) · [Step 2](./qr_theme_step2_feature_description.md) · [Step 3](./qr_theme_step3_development_plan.md) · [Step 4](./qr_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qr_theme; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Cycle estimate: 0.30
Status: Step 1 approved; Option A; no implementation approval

## Original Query

Let's work on this item from todo.txt, please:

QR codes should match theme within reason.

## Problem

The invitation QR uses fixed black and white, which can look disconnected from the selected theme.

## Option A — Restrained theme colors (recommended)

**Estimate: 0.30**

Use a theme-compatible dark foreground and light, opaque background for the QR, updating an existing code when the theme changes; retain black and white wherever a suitable pair cannot be established.

- Benefit: The code itself fits palettes such as LCD green or dark blue without requiring an inverted code in dark themes.
- Constraint: Preserve square modules, the clear margin and strong contrast; avoid textures, gradients and decorative reshaping inside the code.
- Driver and uncertainty: Theme switching and phone/desktop decoding checks across all supported themes dominate verification; physical-camera readability needs separate acceptance evidence.

## Option B — Theme the surrounding frame only

**Estimate: 0.15**

Keep the existing black-on-white QR and match only its surrounding border and spacing to the theme.

- Benefit: Small presentation change that leaves encoded pixels unchanged.
- Tradeoff: The white rectangle remains prominent, especially in dark themes, and only partially addresses the request.
- Driver and uncertainty: Cross-theme layout review is the main work; visual improvement may be insufficient.

## Recommendation and scope

Choose A: generate an invitation through the normal Invite page, display a theme-compatible QR, switch themes without changing its payload, and scan the same displayed link. Preserve the copyable link, generation-failure recovery and protection against stale QR/link pairs. Fall back to black and white when theme coloring is unsuitable. Verify rendered phone/desktop layouts, decoded payloads, live theme changes and recovery; record physical-device limits honestly. No new controls or explanatory copy are needed.

Dependency inventory: Zero proposed additions or updates; reuse the existing local encoder. No schema changes. Documentation scope includes this cycle's plans, debug log, plan index and study ledger. Leave todo.txt untouched. The earlier [invite QR cycle](../invite_qr_code/invite_qr_code_step4_implementation_summary.md) is related prior work, whose outstanding acceptance is not closed by this assessment.

## Study checkpoints and approval

Scope: v1/A; baseline 085c47701f57334164f7fe1c562454443cd65596; R1/P2; same stable ID across worktrees, no parent replacement.
Intake: Pristine pre-inspection checkpoint missing; not reconstructed. Existing renderer, theme switching, styles and prior QR acceptance notes were inspected before scoring.
Step 1 handoff: 2026-10-27 UTC; projected total 0.30 for A, including inspection, planning, implementation, verification, documentation and ordinary corrections. B/v1 total 0.15 is an alternative, not additive. All three frozen R1 hashes verified. Codex single exposed rater; no independent rating or measured work claimed. Maintainer fit and outcome acceptance pending.
Approval: User supplied Approved Step 1 on 2026-10-27 UTC; Option A advances to Step 2. Planning remains uncommitted.

*— Codex · 2026-10-27 UTC*

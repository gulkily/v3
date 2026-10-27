> **Feature plan:** [Step 1](./qr_theme_step1_solution_assessment.md) · [Step 2](./qr_theme_step2_feature_description.md) · [Step 3](./qr_theme_step3_development_plan.md) · [Step 4](./qr_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qr_theme; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Step 3 approved; implementation in progress on feature/qr-theme

**Cycle estimate: 0.30**

## Entry checkpoint

2026-10-27 UTC: Step 3 projection 0.30 reaffirmed before implementation; v1/A, original baseline 085c47701f57334164f7fe1c562454443cd65596, R1/P2, single exposed Codex rater. Earlier scores and missing pristine intake preserved. Planning commit 0b52b2251. Scope and three original stage-work estimates inherited unchanged: S1 safe generation 0.10 (color serialization uncertainty), S2 synchronization 0.10 (async timing), S3 acceptance/closeout 0.10 (tool/device availability). Stage work is not additive or measured time; S3 owns shared acceptance. Physical scans and maintainer fit remain pending.

*— Codex · 2026-10-27 UTC*

## Stage 1 - Safe colors on generated invitations

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Canvas presentation uses theme ink/input colors; resolver accepts opaque computed sRGB, orders by luminance and enforces 7:1. Unsupported/transparent/low-contrast/forced colors fall back to black and white; geometry and invitation payload unchanged.
- Verification: node tests/browser/invitation_issuance.cjs passes color/fallback/margin and existing lifecycle cases. JS syntax and diff checks pass. Real signed browser issuance via normal navigation in light/dark/LCD independently decoded to the displayed link with jsQR; screenshots captured under /tmp/qr-theme/ and LCD reviewed.
- Notes: Installed jsQR 1.4.0 only in /tmp/qr-theme-tools for disposable evaluation; no repository dependency changes. Existing OpenCV decoder intermittently failed even after converting a failed light-theme image to black/white; switched to jsQR and retained this tooling limitation rather than treating OpenCV as a release oracle. No phone camera claim.
- Estimate details: S1/v1 original scope unchanged, no revision; R1/P2/exposed rater retained. Stage commit follows this record.

*— Codex · 2026-10-27 UTC*

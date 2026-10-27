> **Feature plan:** [Step 1](./qr_theme_step1_solution_assessment.md) · [Step 2](./qr_theme_step2_feature_description.md) · [Step 3](./qr_theme_step3_development_plan.md) · [Step 4](./qr_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qr_theme; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Steps 1–2 approved; Option A; no implementation approval

**Cycle estimate: 0.30**

## Problem

Invitation QR codes use fixed black and white regardless of theme. Approved Option A gives the code restrained theme colors while preserving readability and the existing invitation flow.

## User stories

- As an inviter, I want the QR to fit my theme so that the generated invitation feels part of the page.
- As a recipient, I want a readable code so that I can open the same invitation offered by the copyable link.

## Core requirements

- Use theme-compatible dark modules on a light, opaque background, including in dark themes. Preserve square modules, the existing four-module clear margin, responsive sizing and local encoding; no gradients, textures, logos or inversion inside the QR.
- Require foreground/background contrast of at least 7:1 as a conservative product guard, alongside decoding checks; contrast alone is not proof of scan reliability. Use black and white when colors are unavailable, invalid or fail the guard, including theme-load failure and forced-color presentation where needed.
- Apply the effective theme on initial generation and update a visible QR after explicit or automatic theme changes, without generating another invitation or altering its payload. Keep a usable code during theme loading or failure.
- Preserve exact QR/link pairing through repeated generation and theme changes. Issuance failure clears stale results; QR-only failure retains the current copyable link and existing recovery feedback. A theme change must never resurrect a cleared code.
- Reuse the existing label, link and Copy control; add no controls or explanatory text. Keep keyboard, phone and desktop use intact.

## Delivery scope and completion boundary

Work type: Application change limited to invitation QR presentation, theme integration and focused verification. Reuse the approved local encoder; proposed dependency delta is zero. No invitation-policy, payload, navigation or schema changes, downloads or printing features. Documentation includes this cycle's artifacts, debug log, plan index and study ledger; todo.txt remains untouched.

Completion: Enter through the ordinary Invite navigation, generate an invitation, view or switch its theme, and open the same invitation by scanning or copying. Validate all supported themes at phone and desktop sizes, initial/visible/cleared/failure states and theme transitions. Automated pixel decoding and rendered review are required; physical-camera scanning remains a distinct acceptance check and must be reported pending if unavailable. This cycle does not close unrelated limitations in the earlier invitation cycle.

## Risks and mitigation

- Poor scan readability: Validate candidate color pairs and decoded pixels before expanding across themes; retain dark-on-light geometry and black/white fallback. Require physical scan evidence separately before claiming camera acceptance.
- Theme loading or stale results: Validate switching while generation is pending, after clearing and after failure early in implementation; preserve one current link/QR pair and existing recovery.
- Layout or accessibility regressions: Review real loaded results on phone/desktop and forced-color presentation; preserve clear margins, usable Copy and the existing accessible alternative.

## Shared component inventory

Reuse/extend: The canonical Invite result in templates/pages/invites.php; renderer and result lifecycle in public/assets/invite_issuance.js; QR sizing in public/assets/invitations.css; effective-theme lifecycle in public/assets/theme_toggle.js and existing theme palettes. Reuse unchanged: Local qrcode-generator.js, Invite asset loading in LobbyController, issuance/redemption APIs and copyable-link path. Source search found one QR-rendering surface; no parallel widget is needed.

## User flow and success criteria

1. Open Invite and generate a link; its matching themed QR appears automatically.
2. Change theme; the same invitation adopts an eligible palette or black/white fallback.
3. Scan to open the displayed link, or use Copy when scanning is unavailable; retry generation through existing controls after failure.

Acceptance: Every tested supported-theme/viewport combination preserves a square, unclipped QR with its full clear margin, qualifying contrast and exact decoded-link equality. Theme changes preserve that equality without issuance requests. Invalid colors and loading failures retain a usable fallback; generation and QR failures preserve existing recovery with no stale code. Review screenshots for palette fit and record camera checks separately.

## Study checkpoint and approval

Step 2 handoff: 2026-10-27 UTC; whole-cycle 0.30 reaffirmed for v1/A, original baseline 085c47701f57334164f7fe1c562454443cd65596, R1/P2. Explicit contrast and transition criteria clarify approved scope; no scope expansion. All three frozen hashes reverified. Codex single exposed rater; earlier Step 1 score and missing pristine intake preserved, no measured work or accepted outcome. User supplied Approved Step 2 on 2026-10-27 UTC; proceed to Step 3. No implementation or commits.

*— Codex · 2026-10-27 UTC*

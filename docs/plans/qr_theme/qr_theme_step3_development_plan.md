> **Feature plan:** [Step 1](./qr_theme_step1_solution_assessment.md) · [Step 2](./qr_theme_step2_feature_description.md) · [Step 3](./qr_theme_step3_development_plan.md) · [Step 4](./qr_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qr_theme; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Steps 1–3 approved; implementation authorized

**Cycle estimate: 0.30**

## Completion Contract

Application change: From normal Invite navigation, generate a theme-compatible QR, change theme/palette and scan or copy the same link. Preserve issuance failure, QR-only recovery and stale-result clearing. Release evidence covers all supported themes/LCD palettes at phone and desktop sizes, exact decoded payloads, forced colors and loading failures. Physical-camera acceptance is separate and remains pending if unavailable; local completion does not imply deployment or close prior invitation limitations. Rollback restores presentation code without migrating data.

## Key Risks

- **High risk:** Unreadable or stale codes. Validate decoded pixels and generation/theme races early; preserve opaque dark-on-light colors, 7:1 eligibility, four-module margins and black/white fallback. Failures block dependent work.
- Deferred stylesheet loads and LCD palette changes can leave old colors. Validate initial/automatic/manual changes and failed loads before final acceptance; retain a usable current code while loading.

## Stage 1 — Safe colors on generated invitations

**Estimate: 0.10**

- Goal: Generate a readable QR using the active palette through the existing Invite flow.
- Dependencies: Approved Step 3, feature branch and planning commit; available independent pixel decoder for early acceptance.
- Expected changes: Resolve opaque theme colors, order dark/light by luminance and enforce 7:1 eligibility with black/white fallback; preserve existing geometry and recovery. Keep renderInvitationQr(canvas, value); add a small resolveInvitationQrColors(canvas) helper if needed.
- Verification approach: Existing invitation_issuance.cjs plus focused invalid/transparent/low-contrast/forced-color cases; rendered light, dark and LCD generation, independent pixel decoding and exact link equality.
- Risks or open questions: Reject unsupported color forms conservatively; settle decoder availability before proceeding, using existing tools or evaluation outside the repository.
- Canonical components/API contracts touched: invite_issuance.js and invitations.css; existing local encoder and invitation payload unchanged.
- Estimate details: S1/v1 stage work; driver is color normalization and fallback verification; uncertainty is browser color serialization.

## Stage 2 — Keep visible codes synchronized

**Estimate: 0.10**

- Goal: Theme and LCD palette switching updates the same invitation safely.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Observe effective-theme/palette changes and stylesheet readiness through the existing theme lifecycle; redraw only the current successful result, preserve it during loading, and isolate recoloring failure. Reuse result clearing and copy recovery.
- Verification approach: Manual/automatic/rapid changes, delayed and failed CSS, initial loading, all LCD palettes and forced-color transitions; switch during issuance and after failure/clearing. Assert unchanged payload, no extra issuance and no stale resurrection. Run theme_cycle.cjs and lcd_palettes.mjs when affected.
- Risks or open questions: Attribute changes alone may precede CSS readiness; verify that timing before committing. Failed redraw must preserve a usable current code or expose existing copy recovery.
- Canonical components/API contracts touched: Invite result lifecycle; theme_toggle.js only as needed for existing lifecycle notification, without changing selection behavior.
- Estimate details: S2/v1 stage work; driver is asynchronous transitions; uncertainty is observer/listener timing.

## Stage 3 — Normal-flow acceptance and handoff

**Estimate: 0.10**

- Goal: Verify the complete user outcome and record release limits.
- Dependencies: Stage 2 verified and committed.
- Expected changes: Focused browser acceptance coverage and cycle summary, debug log, plan index and ledger updates; no added UI copy.
- Verification approach: Reuse invite browser fixtures for real issuance and recipient opening; decode displayed pixels in every supported theme/LCD palette at 320/1280 widths. Review screenshots, overflow, clear margins and Copy; test no-result, pending, success, failure and retry. Run invitation issuance regression, theme checks, relevant PHP invitation tests, JS syntax and diff checks. Record physical scans separately, pending if unavailable.
- Risks or open questions: Decoder success does not establish camera reliability; record failed/omitted checks explicitly and resolve regressions before local completion.
- Canonical components/API contracts touched: Existing browser/PHP invitation harnesses and this cycle's documentation; issuance/redemption behavior unchanged.
- Estimate details: S3/v1 stage work; owns shared acceptance/closeout; uncertainty is test environment and physical-device availability.

## Execution inventory and study checkpoint

Dependency inventory: Zero additions/updates; existing local encoder unchanged. External evaluation artifacts stay outside the repository; any dependency change requires explicit approval. No schema changes or todo.txt edits.
Execution: After Approved Step 3, create feature/qr-theme, organize this cycle under docs/plans/qr_theme/, and commit approved plans/debug plus required index/reference fixes. Create Step 4 summary afterward. Commit each verified stage with its summary before the next; ledger updates remain uncommitted until closeout.
Checkpoint: 2026-10-27 UTC; whole-cycle 0.30 reaffirmed independently of stage estimates. Same v1/A, baseline 085c47701f57334164f7fe1c562454443cd65596, R1/P2, single exposed Codex rater; exact frozen hashes reverified. LCD variants/loading timing clarify verification within approved scope. S1–S3 estimates are separate stage work, not additive totals or measured labor. Earlier scores and missing pristine intake preserved; three planned/zero completed, acceptance pending. Approved Step 3 received on 2026-10-27 UTC.

*— Codex · 2026-10-27 UTC*

> **Feature plan:** [Step 1](./qr_theme_step1_solution_assessment.md) · [Step 2](./qr_theme_step2_feature_description.md) · [Step 3](./qr_theme_step3_development_plan.md) · [Step 4](./qr_theme_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qr_theme; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Original implementation locally complete; maintainer visual review failed; appearance follow-up and physical-camera acceptance pending

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

## Stage 2 - Keep visible codes synchronized

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Invite-local observer follows effective theme and LCD palette attributes, stylesheet load completion and forced-color changes. Only a current visible successful QR redraws. Redraw failures hide the QR and preserve the current link with existing recovery feedback. No changes to shared theme selection or issuance APIs.
- Verification: invitation_issuance.cjs and theme_cycle.cjs pass; existing lcd_palettes.mjs passes all palette/load/retry checks (its two separately recorded baseline composer errors remain unrelated). Real signed browser invitation passed live theme, all LCD palettes, automatic system and forced-color transitions with exact independently decoded payload and one issuance. Delayed/failed stylesheets retained a usable current code; forced redraw failure retained the current copyable link. Temporary evidence: /tmp/qr-theme/stage2.mjs.
- Notes: Corrected test route matching for fingerprinted assets and used DOMContentLoaded for navigation with intentionally held stylesheet requests; initial harness timeouts were not product failures. Physical scans still pending. Stage 1 commit: d52c39997.
- Estimate details: S2/v1 scope and original estimate unchanged; retrospective 0.10, same R1/P2/exposed rater.

*— Codex · 2026-10-27 UTC*

## Stage 3 - Normal-flow acceptance and handoff

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.15**

- Changes: Completed acceptance matrix and documentation; retained focused color, margin, repaint and stale-result regression assertions in the existing invitation harness from Stages 1–2. No additional product changes were needed.
- Verification passed: 38 rendered combinations cover all 16 registered explicit themes plus three additional LCD variants, each at 320/1280 pixels. Real signed invitations entered through normal navigation; QDB uses its existing direct Invite URL because its navigation deliberately omits Invite. Raw canvas and actual displayed canvas screenshots independently decode to the exact link with jsQR 1.4.0. Every combination has two opaque colors, dark-on-light contrast at least 7:1, a four-module clear margin, square sizing, unchanged module matrix across theme changes and no page overflow. All phone theme screenshots and representative desktop screenshots reviewed. Normal Copy reads back the same URL; a fresh recipient opens the intended thread through real redemption.
- Verification passed: Existing invitation_issuance.cjs, theme_cycle.cjs and lcd_palettes.mjs; php tests/run.php InvitationIssuanceTest (9/9); invite_zero_click.mjs in public and members-only modes, including fresh/existing identities, recovery, invalid/expired/revoked invitations and username-change restrictions. Browser acceptance additionally covers initial empty result, long/expanded destination, issuance failure, switching while cleared and retry; Stage 2 covers pending, load failures, live auto/forced colors and QR-only failure. JS syntax and whitespace checks passed.
- Evidence: Disposable scripts, screenshots and machine matrix results under /tmp/qr-theme/ (stage1.mjs, stage2.mjs, stage3.mjs, profiles.mjs, matrix.json and matrix-{profile}.json). Disposable decoder lives only in /tmp/qr-theme-tools. Profile harness initially attempted QDB's deliberately absent Invite navigation and passed a Node-only variable into the browser; corrected harness and reran all three profiles successfully. No new dependency was added to the repository.
- Notes: Stage 2 commit 7ee61aadd. Existing LCD test reports two baseline composer errors separately; its scoped palette assertions passed. Full test suite and physical camera scans not run. Deployment, merge and push not performed. No schema, API or UI-copy changes; todo.txt unchanged.
- Estimate details: S3/v1 original 0.10 retained; retrospective 0.15 reflects independent decoder/tooling and profile-specific acceptance work. Same scope/R1/P2/exposed rater; no measured labor claim.

*— Codex · 2026-10-27 UTC*

## Study closeout and release limits

Status: Three planned stages completed locally; physical-device and maintainer acceptance pending.
Original projected cycle: 0.30 retained.
Retrospective cycle scope: 0.35, separately judged, not the sum of stage annotations.
Scope: v1/A; original baseline 085c47701f57334164f7fe1c562454443cd65596; R1/P2; stable v3/qr_theme, deduplicated across worktrees.
Evidence: Frozen R1 hashes reverified at entry. Planning 0b52b2251, S1 d52c39997, S2 7ee61aadd and this Stage 3 closeout commit; final revision recorded in the subsequent debug handoff.
Outcome: Restrained colors, automatic theme/palette recoloring, conservative fallback and preserved generation/copy recovery delivered. Existing invitation behavior remains covered. No new scope, splits or replacement outcomes; earlier intake omission and projections preserved.
Release limits: Camera acceptance requires scanning a displayed light, dark and LCD code from a physical phone and confirming it opens the matching invitation. Automated decoding does not claim that evidence. No full-suite, production deployment or merge-readiness claim. Maintainer comfortable-fit judgment unknown; accepted study outcome count unchanged.
Dependency delta: Zero repository components, manifests or lock files added/updated. Existing encoder bytes unchanged; external jsQR evaluation stayed outside the repository.

*— Codex · 2026-10-27 UTC*

## Maintainer visual review — 2026-10-27 UTC

Request: "I also looked at the qr codes in different themes, and for most themes except LCD they still look black on white with a big white edge and very out of place. (Please follow up on this after finishing the plan)"
Additional question: "I have a question, are light-on-dark qr codes workable?"
Disposition: Visual acceptance failed; the prior decoder/layout passes remain valid technical evidence but do not satisfy the intended visual fit. No new product changes made in this review. The separate signing-readiness Step 1 was completed first as requested.
Finding: The visible margin is the preserved four-module quiet zone, not extra CSS padding. Current rendering sorts text/input colors dark-on-light; bright paper or near-black ink can still read visually as black/white. Transparent or unsuitable input colors trigger the literal black/white fallback. These were conservative implementation choices, not evidence of successful theme integration.
Follow-up direction: Review explicit QR colors for visual fit, and evaluate light-on-dark for dark themes. Inversion is supported by some scanners but requires compatibility evidence; keep clear margins in the background color. A non-inverted themed fallback remains a candidate. Do not remove the quiet zone to hide the visual mismatch.
Evidence: ZXing's ALSO_INVERTED option is documented at https://zxing.github.io/zxing/apidocs/com/google/zxing/DecodeHintType.html ; DENSO's QK30 manual section 7.3 describes configurable inverted-code scanning and a dark quiet zone at https://www.denso-wave.eu/fileadmin/download/Manuals/Scanner/QK30/QK30-U_Users_Manual_E1.pdf . These establish feasibility, not compatibility with the user's phone cameras.
Approval boundary: Light-on-dark changes the approved Steps 1–3 dark-on-light requirement. The user's question is not approval to change polarity. Revise the QR requirements before implementing inversion; retain the original estimates and verification record. No new prospective estimate or maintainer comfortable-fit acceptance is inferred.
Status: QR visual goal remains unresolved; physical-camera acceptance remains pending. Signing reliability has its own cycle and takes priority; neither a visual correction nor an identity fix has been implemented by this follow-up review.

*— Revised by Codex · 2026-10-27 UTC*

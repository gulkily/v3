Source-Thread: thread-20261031051903-0e8e7df4
Source-Post: reply-20261031060809-113139f7
Source-Record: ../../../state/local_repository/records/posts/2026/10/31/reply-20261031060809-113139f7.txt
Collected-At: 2026-10-31
Collection-Status: Step 3 proposal awaiting approval in the collected thread; implementation and device acceptance are not established by this import.
Collection-Note: Working plan revised from the signed source comment; consult Source-Record for the unchanged original. Historical estimates and attribution are retained.
Revised-At: 2026-10-31
Revised-By: Codex
Revision: v1.1; documentation corrections authorized by the user, without implementation approval.

Railspike Step 3: Development Plan
File: docs/plans/mobile_compose_keyboard/mobile_compose_keyboard_step3_development_plan.md

> **Feature plan:** [Step 1](./mobile_compose_keyboard_step1_solution_assessment.md) · [Step 2](./mobile_compose_keyboard_step2_feature_description.md) · [Step 3](./mobile_compose_keyboard_step3_development_plan.md) · [Step 4](./mobile_compose_keyboard_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `v3/mobile_compose_keyboard` · [ledger](../effort_estimation_trial.md#cycle-v3mobile_compose_keyboard)

Extended estimates: enabled

**Cycle estimate: 0.40 (historical v1 projection; v1.1 reassessment pending)**

## Completion Contract

- Normal entry: New post in the bottom tab bar, `/compose/thread`, on a phone.
- End-to-end outcome: with the keyboard open, during ordinary body editing the title, text area and Post row fit above the keyboard. The caret line and Post stay visible while typing; tapping Post creates exactly one thread.
- Recovery: a validation, signing or network error keeps the draft and makes the message and Post reachable for retry, using scrolling fallback when they cannot fit in the docked panel. If the space is too small (zoom, long error, rotation), the form falls back to normal page flow and stays scrollable.
- Release: the automated checks in S3 pass; device acceptance on Ilya's iPhone SE in Firefox and Safari is recorded in S4. Deployment is outside this cycle.

## Key Risks

- **High risk:** keyboard detection on iOS Firefox. `reply_viewport.js` assumes the keyboard is up when the visible height drops below 75% of the tallest seen. If Firefox reports heights differently, nothing docks. Early validation: before dependent S2 docking changes, use a temporary developer probe on a maintainer-reachable development/test instance to record visual viewport height, width, offsets, scale, layout height, focus and editing-intent events on the SE. No shipped debug query mode or production deployment is required. Record the test URL/build and device/browser versions; if access is unavailable, retain the gate as pending. Mitigation: the S1 fallback layout already keeps Post closer, and docking only applies when detection succeeds.
- Shared docking code: the reply composer depends on the same script, and `v3/composer_anchor_exit` (Step 1, unapproved) also plans to touch it. Early validation: run all `reply_viewport*`, `reply_continuation` and `reply_compose_focus` browser tests after S2. Mitigation: add a second hook without changing reply behavior.
- Shared composer partial: reordering tools changes the paned new-thread dialog too. Validation: `compose_disclosure`, `compose_layout_stability`, `composers` tests and a desktop paned-dialog check in S3.

## Stage 1

**Estimate: 0.08 (historical S1 projection; reassessment pending)**

- Goal: the dedicated form has the new order and a phone-friendly flow, with or without JavaScript.
- Dependencies: implementation approval and a recorded baseline of the reported form, existing overflow, and registered themes.
- Expected changes: in `composer.php`, render the tools row (tags, event) after `.compose-form-actions` for the full composer only. In `compose.css`, under 640px on `.compose-page`, drop the reserved hidden image-picker footprint that sits between the body and Post (keep it on wider screens, where it prevents layout shift), and shorten the default body height toward the compact form's.
- Verification approach: render the partial in PHP for full and compact modes and check element order. Headless WebKit at 375 x 667 and 375 x 300 in all explicit themes from `ThemeRegistry::explicitNames()` (currently 16): Post's distance from the top of the body shrinks, with no new horizontal overflow relative to the recorded baseline. Existing theme defects stay separately recorded; if they block composer use, stop for scope review. `compose_layout_stability` and `compose_disclosure` pass.
- Estimate details: driver: shared partial and the hidden-footprint rule; uncertainty: low.
- Risks or open questions:
  - Impact: removing the footprint could reintroduce the image-eligibility shift the rule prevents.
  - Early warning / validation: `compose_layout_stability` on phone widths.
  - Mitigation: keep the footprint but place it after Post instead of removing it, if the test fails.
- Canonical components/API contracts touched: `templates/partials/composer.php`, `public/assets/compose.css`; form field names and submit contract unchanged.

## Stage 2

**Estimate: 0.15 (historical S2 projection; reassessment pending)**

- Goal: when the keyboard is up on `/compose/thread`, the form docks above it exactly as the phone reply panel does.
- Dependencies: S1 and the early physical-device geometry/intent check described above. Prepare independent tests while awaiting evidence; do not implement the dependent docking extension against an unverified device assumption.
- Expected changes: in `reply_viewport.js`, accept a neutral `data-phone-composer` form hook alongside `data-phone-reply`, with the panel found the same way (`[data-reply-panel]`). Reply-only parts (`forum:reply-sent` reading mode) stay reply-only. `compose_thread.php` marks its card as the panel and passes an explicit opt-in through `thread_compose_form.php` to the canonical form attributes; the paned dialog does not opt in. In `compose.css`, while docked, the body flexes, collapsed tools are hidden (an open tool stays and the existing fit checks fall back to page flow if it doesn't fit), and the title follows Q2. Keep diagnostics in the temporary developer probe, outside shipped UI. Title-only editing stays in ordinary flow until body editing establishes intent; verify title/body transitions without losing selection or hiding controls.
- Verification approach: reuse the fake `visualViewport` harness with a 375 x 667 layout viewport and visible height reduced to 300px, including nonzero offsets. Render the actual full composer inside its card, including heading and feedback. Ordinary typing with short and 80-line drafts must dock with an editor at least 80px high, no action overlap, and visible caret/Post. Separately test fitting menus/feedback and oversized cases that must restore reachable normal flow. Test title-first focus, body/title transitions, keyboard close/reopen, rotation, zoom, scrolling, focus/selection and scroll restoration. Existing reply viewport tests must pass with their behavioral assertions preserved.
- Estimate details: driver: generalizing shared script hooks without reply regressions; uncertainty: medium (iOS Firefox detection).
- Risks or open questions:
  - Impact: a reply regression would break the most-used composer.
  - Early warning / validation: the reply tests listed in Key Risks, run before the compose tests.
  - Mitigation: additive hook; no changes to reply thresholds or timing.
- Canonical components/API contracts touched: `public/assets/reply_viewport.js` (docking contract: `[data-reply-panel]`, `--reply-panel-*` variables, `.reply-panel-active`), `templates/pages/compose_thread.php`, `templates/partials/thread_compose_form.php`, `public/assets/compose.css`.

## Stage 3

**Estimate: 0.12 (historical S3 projection; reassessment pending)**

- Goal: lock the behavior in with automated checks across themes and composers.
- Dependencies: S2 for completed integration checks. Test preparation and baseline checks may proceed independently of the device gate.
- Expected changes: add `tests/browser/compose_thread_viewport.mjs`: all explicit themes from `ThemeRegistry::explicitNames()` at 375 x 667 and 375 x 300 (fake keyboard), WebKit and Chromium. It checks order (tags after Post), docking, no overlap, no new horizontal overflow against baseline, short and long drafts, and no-JavaScript flow. Include real validation failure (with automatically opened tags/event controls), signing failure and network failure followed by successful retry and exactly one resulting thread. Verify retained draft and scrollable recovery whenever docking cannot fit. Cover anonymous/signed viewers, upload eligibility states, delayed initialization, and the desktop paned form. Run the existing composer, disclosure, layout stability, reply viewport, tab bar and image tests.
- Verification approach: all new and listed existing tests pass, with results recorded in the Step 4 summary, including baseline defects and pending physical-device results.
- Estimate details: driver: 13-theme matrix and fake-viewport setup; uncertainty: low (the harness already exists for replies).
- Risks or open questions:
  - Impact: icon themes (Word 97, Chicago, Midnight) have fixed status bars that eat vertical space.
  - Early warning / validation: the theme matrix at 375 x 300.
  - Mitigation: the reply panel's reading mode already measures a fixed `.site-status-bar`; measure actual viewport intersection and control occlusion before changing docked geometry; a fixed footer’s layout height alone does not establish keyboard-area overlap. Any fix must remain composer-scoped; broader theme work requires separate approval.
- Canonical components/API contracts touched: browser test harness in `tests/browser/`; no product contracts.

## Stage 4

**Estimate: 0.07 (historical S4 projection; reassessment pending)**

- Goal: replay the reported failure on the real phone and close out.
- Dependencies: S3; Ilya with the iPhone SE.
- Expected changes: Ilya opens New post in Firefox and Safari, types a short and a long draft with the keyboard open, and posts once (a test thread he can retract). Record device, iOS and browser versions and the result in the Step 4 summary. Add a `docs/concepts/composer.md` entry for the shared composer and its phone docking, and record the compose page's phone verification in `docs/plans/ui_copy_audit_checklist.md`. Add the closeout checkpoint to the effort ledger.
- Verification approach: the device result itself; headless results don't count here. A failure goes back to S2 with the observed heights.
- Estimate details: driver: waiting on the device check; uncertainty: medium (unknown device result).
- Risks or open questions:
  - Impact: a device-only failure after automated tests pass.
  - Early warning / validation: the S2 height check catches detection problems early.
  - Mitigation: the S1 fallback still improves the page; record the result and the follow-up honestly.
- Canonical components/API contracts touched: `docs/concepts/` (new composer entry), effort ledger.

## Concepts touched

- Composer (new entry `docs/concepts/composer.md`, S4): changed (order, phone docking for the dedicated page).
- Phone reply panel docking (described within the composer entry): extended with a second hook, reply behavior unchanged.
- App shell / bottom tab bar: used unchanged.

## Open questions for Ilya (defaults in brackets)

- Q1: while the keyboard is up, can the collapsed tags/event row hide until the keyboard closes? [yes]
- Q2: should the title stay on screen while docked, or hide when it's empty and the body has focus, to give the SE about 50px more writing space? [stay, per the clarification]
- Q3: should the paned new-thread dialog get the new order only, without docking, since it's a wide-screen layout? [yes]

## Enhancement Requests

- **Request:** Word 97 / Midnight phone overflow and Sticker / Arena Post padding at 375px. **Disposition and reason:** separate work; different cause. **Approval status:** pending. **Follow-up:** separate cycle on request.

## Historical effort snapshot

2026-10-31T06:15Z · Step 3 handoff · cycle `v3/mobile_compose_keyboard` · scope v1 unchanged · baseline `63f0849691902e665506ad4f23f374353a32ae78` · R1 (three hashes verified) · P2 · Claude single exposed rater (also inspected `reply_viewport.js`, its tests and `compose.css` for this plan) · **0.40 projected total, reaffirmed**. Stage work S1 0.08, S2 0.15, S3 0.12, S4 0.07; independent stage judgments, not summed into the cycle value. Reason unchanged: reuse of the existing docking script plus a reorder; the theme matrix and the device check keep it above the smallest references.

## Approval

Approval-Status: The original source reports Step 2 approval, but separate maintainer approval evidence was not recovered. The user authorized these plan revisions only. Recover the earlier approval or obtain approval of revised Step 2, then obtain explicit Step 3 approval before implementation. Q1–Q3 remain proposed defaults, not independently recovered decisions.

*Original source author: Claude · 2026-10-31 UTC · Model: Claude Opus 5.5*

#meta #bug #railspike

## Revision record

Revision-Date: 2026-10-31
Revision-Author: Codex
Authorization: User requested editing the imported plans after review; documentation only, no implementation, branch, commit or deployment approval.
Scope-Version: v1.1; original v1 outcome retained, with explicit overflow baseline, scrolling recovery, device gate and focus behavior.
Reuse-Review: Inspected templates/partials/composer.php, templates/partials/thread_compose_form.php, templates/pages/compose_thread.php, public/assets/reply_viewport.js, public/assets/compose.css, tests/browser/reply_viewport.mjs, tests/browser/compose_layout_stability.mjs and src/ForumRewrite/View/ThemeRegistry.php. Extend the existing full composer and docking contract; no new component, API, schema or dependency.
Verification: Reviewed working-plan consistency, source references and relative links. No application tests or physical-device replay performed; historical reported measurements are not new verification.
Study-State: Existing v3/mobile_compose_keyboard observation under standing railspike-effort P2 enrollment, not a new cycle. Original Step 2/3 total 0.40 and S1-S4 work projections 0.08/0.15/0.12/0.07 remain historical Claude single-exposed-rater judgments. Missing intake and Step 1 scores remain missing. Original baseline 63f0849691902e665506ad4f23f374353a32ae78 retained. This editorial revision does not reaffirm or replace those ratings; current reassessment and frozen R1 verification are pending before a new planning handoff. No independent rating, measured labor or accepted outcome.
Next-Gate: Reconcile earlier maintainer approvals and study references before formal handoff; revised Step 3 remains proposed. Preserve the signed source records unchanged.

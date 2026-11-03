> **Feature plan:** [Step 1](./compose_keyboard_fit_step1_solution_assessment.md) · [Step 2](./compose_keyboard_fit_step2_feature_description.md) · [Step 3](./compose_keyboard_fit_step3_development_plan.md) · [Step 4](./compose_keyboard_fit_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/compose_keyboard_fit` ([ledger](../effort_estimation_trial.md#cycle-v3compose_keyboard_fit)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.20**

Step 2 approved 2026-11-02 ("approved step 2") at scope v1.1 (Title below the Post row); no scope added since. Pending requests from Step 1 (theme chrome overflow; a forum reply superseding the posted steps) stay separate. Branch `feat/compose-keyboard-fit` from `main` (`2a7d356f8`).

## Completion Contract

- Normal entry: New post in the phone tab bar or `/compose/thread` directly, member or guest; the paned board's New Thread dialog on desktop.
- Outcome: the full composer renders body, Post row with the ··· menu, Title, then tags and event. On a 375px phone with the keyboard open the body and Post are both visible while typing; long drafts scroll inside the body. Pressing Post creates exactly one thread.
- Recovery: validation, signing or network errors keep the draft and show the message with Post reachable; JavaScript off gives the same layout; compact and reply composers are unchanged.
- Deployment and external verification: none beyond the normal deploy. The maintainer's iPhone SE check in Firefox and Safari is the acceptance evidence and is recorded as pending until it arrives.
- Release: Stage 1 and 2 checks pass; 13 permitted themes measured at 375 by 667 and 375 by 300; one full suite run with pre-existing failures recorded, not chased; concept entry, copy audit note and ledger closeout written.

## Key Risks

- **High risk:** iOS scrolls the focused body so Post lands just below the visible area even though the form is short. Early validation: Stage 2 measures body top to Post bottom at 375 by 300 in Chromium, and WebKit where installed; the Stage 3 iPhone replay decides. Mitigation: keep body, status line and Post row within about 250px on phones; if the real phone still hides Post, record it and open Step 1 Option B as a follow-up instead of adding a script here.
- Reorder reaches the paned New Thread dialog and the tests that assert Title first or tags before Post. Validation: Stage 1 runs the composer, disclosure and smoke tests. Mitigation: gate the new order on full mode; update assertions deliberately.
- Dropping the reserved picker footprint on phones reintroduces the shift it prevents when uploads enable after paint. Validation: Stage 2 runs the layout stability test at a phone viewport. Mitigation: on phones the footprint, if still needed, sits after Post rather than between body and Post.
- Attached image preview still sits between body and Post. Accepted in Step 2; noted in the Step 4 summary.

## Stage 1 - Full composer order

**Estimate: 0.07**

- Goal: in full mode the composer renders body, Post row, Title, tags, event; compact and reply output is unchanged.
- Dependencies: none.
- Expected changes: `templates/partials/composer.php` moves the Title input and its normalization status after `.compose-form-actions` and the tools block after the Title, both inside the existing `$composerFull` gates; the header comment is updated. `tests/browser/composers.mjs` order assertion becomes body, actions, title, tools. `LocalAppSmokeTest` gains an order assertion for `/compose/thread` and one that the compact board form has no Title or tools.
- Verification approach: render `/compose/thread`, the board, QDB add and a thread page through the existing PHP smoke harness before and after; compact and reply HTML byte-identical. Run `composers.mjs`, `compose_disclosure.mjs`, `board_compose_focus.mjs`, `reply_compose_focus.mjs`. Open the paned board dialog in Chromium and confirm order.
- Estimate details (extended mode only): driver: one partial and two test files; uncertainty: low.
- Risks or open questions:
  - Impact: a test or script that locates the Title by position breaks.
  - Early warning / validation: the composer browser tests and a grep for `.compose-title` consumers.
  - Mitigation: selectors stay class and name based; only the order assertion changes.
- Canonical components/API contracts touched: `composer.php` (form field names, `data-compose-*` attributes and submit contract unchanged).

## Stage 2 - Phone body height and footprint

**Estimate: 0.08**

- Goal: at phone widths the full composer's body starts at the compact form's height and nothing reserved sits between the body and Post, so body and Post fit above the keyboard.
- Dependencies: Stage 1.
- Expected changes: `public/assets/compose.css` gains rules under `(max-width: 639px)` for `.compose-page` and the paned dialog's full form: body `min-height` and height equal to the compact composer's (about 5.5rem, `rows` attribute overridden by CSS only on phones), and the `.compose-page .image-compose-fields[hidden]` reservation disabled on phones. Wider screens unchanged.
- Verification approach: `compose_layout_stability.mjs` extended with a 375 by 667 and a 375 by 300 pass that asserts Post's bottom edge is within 300px of the body's top edge and `scrollWidth` is 375, looping over the 13 permitted themes; run in Chromium, and WebKit when installed. Re-run the Stage 1 tests. Desktop screenshot of `/compose/thread` compared with `main` for no change above 639px.
- Estimate details (extended mode only): driver: the theme loop in the test; uncertainty: low for layout, medium for the real-phone outcome (owned by Stage 3).
- Risks or open questions:
  - Impact: eligibility shift when uploads enable (see Key Risks).
  - Early warning / validation: the stability test's existing geometry comparison before and after eligibility at the phone viewport.
  - Mitigation: footprint after Post on phones if the test shows a shift.
- Canonical components/API contracts touched: `compose.css` phone rules; `--app-tabbar-offset` read only.

## Stage 3 - Acceptance, records and closeout

**Estimate: 0.05**

- Goal: replay the reported failure on the maintainer's phone, record the result, and close the cycle.
- Dependencies: Stages 1–2.
- Expected changes: new `docs/concepts/shared_composer.md` (purpose, scenario, sources: `composer.php`, `thread_compose_form.php`, `compose.css`, `composers.mjs`); `ui_copy_audit_checklist.md` entry (no copy change); Step 4 summary closeout with the iPhone SE Firefox and Safari result for a one-line and a 30-line draft, or "pending" with what was checked instead; ledger closeout; `docs/plans/README.md` entry.
- Verification approach: the maintainer's SE replay (keyboard open, Post visible, thread created, error path keeps the draft); one full suite run with failures compared against `main`; concept entry paths checked against the code.
- Estimate details (extended mode only): driver: suite run and records; uncertainty: low, apart from the device result which is outside the agent's control.
- Risks or open questions:
  - Impact: device result unavailable at closeout.
  - Early warning / validation: ask for it at the Stage 2 handoff.
  - Mitigation: close technically with acceptance pending; do not substitute emulator evidence.
- Canonical components/API contracts touched: none.

## Concepts touched

- Shared composer: created (`docs/concepts/shared_composer.md`, Stage 3); changed behavior in Stages 1–2.
- Compact board composer, reply keyboard docking, phone app shell: used unchanged; no entries created this cycle.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-11-02T01:30Z | 0.20 (reaffirmed) | v1.1 / R1 / P2, baseline `2a7d356f8` | Claude (claude-fable-5-1), single exposed rater; additionally inspected the composer order assertion in `composers.mjs`, the smoke test's compose assertions, the layout stability harness and the permitted theme list | Three small stages; the theme loop and device acceptance keep it at 0.20 |

Stage work S1 0.07, S2 0.08, S3 0.05 are separate annotations and are not summed. Earlier snapshots: [Step 2](./compose_keyboard_fit_step2_feature_description.md#approval-and-study-checkpoint). R1 hashes verified 2026-11-02.

Debug: [diagnostic log](./compose_keyboard_fit_debug_log.md#2026-11-02t0130z--step-3).

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*

Reply "Approved Step 3" (or send changes) and I will create the branch, make the planning commit and start Stage 1.
